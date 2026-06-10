<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Extra;
use App\Models\Material;
use App\Models\PosOrder;
use App\Models\Recipe;
use App\Models\Shift;
use App\Models\ShiftHandover;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftHandoverController extends Controller
{
    private const CASH_DIFF_THRESHOLD = 50000;

    public function prepare()
    {
        $now = Carbon::now('Asia/Ho_Chi_Minh');
        $lastHandover = ShiftHandover::where('status', 'confirmed')->latest('received_at')->first();
        $start = $lastHandover?->received_at ?: $now->copy()->startOfDay();

        $bookingOrders = Booking::whereBetween('created_at', [$start, $now])
            ->where('status', 'confirmed');

        $posOrders = PosOrder::whereBetween('created_at', [$start, $now]);

        $bookingRevenueCash = (clone $bookingOrders)->where('payment_method', 'cash')->sum('total_price');
        $bookingRevenueTransfer = (clone $bookingOrders)->whereIn('payment_method', ['transfer', 'momo', 'card'])->sum('total_price');
        $posRevenueCash = (clone $posOrders)->where('payment_method', 'cash')->sum('total_amount');
        $posRevenueTransfer = (clone $posOrders)->whereIn('payment_method', ['transfer', 'momo', 'card'])->sum('total_amount');
        $revenueCash = (int) $bookingRevenueCash + (int) $posRevenueCash;
        $revenueTransfer = (int) $bookingRevenueTransfer + (int) $posRevenueTransfer;
        $totalOrders = (clone $bookingOrders)->count() + (clone $posOrders)->count();
        $cashTheoretical = (int) ($lastHandover?->cash_actual ?? 0) + (int) $revenueCash;

        return response()->json([
            'date' => $now->toDateString(),
            'shift_type' => $now->hour < 15 ? 'sang' : 'chieu',
            'can_create' => $this->userHasScheduleForShift(auth()->user(), $now->toDateString(), $now->hour < 15 ? 'sang' : 'chieu'),
            'cash_theoretical' => $cashTheoretical,
            'cash_received_previous' => (int) ($lastHandover?->cash_actual ?? 0),
            'total_orders' => $totalOrders,
            'revenue_cash' => (int) $revenueCash,
            'revenue_transfer' => (int) $revenueTransfer,
            'materials' => Material::where('active', true)->orderBy('name')->get(),
            'products' => $this->handoverProducts(),
            'staff' => User::whereIn('role', ['staff', 'shift_leader'])->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'shift_type' => 'required|string|max:30',
            'cash_theoretical' => 'required|integer|min:0',
            'cash_actual' => 'required|integer|min:0',
            'cash_note' => 'nullable|string|max:2000',
            'total_orders' => 'required|integer|min:0',
            'revenue_cash' => 'required|integer|min:0',
            'revenue_transfer' => 'required|integer|min:0',
            'sold_products' => 'nullable|array',
            'sold_products.*.product_id' => 'required|exists:extras,id',
            'sold_products.*.quantity' => 'required|integer|min:1',
            'equipment_checklist' => 'nullable|array',
            'handover_note' => 'nullable|string|max:3000',
        ]);

        if (!$this->userHasScheduleForShift($request->user(), $validated['date'], $validated['shift_type'])) {
            return response()->json([
                'message' => 'Bạn không có lịch làm ca này nên không thể tạo biên bản giao ca',
            ], 422);
        }

        $handover = DB::transaction(function () use ($request, $validated) {
            $soldProducts = $this->normalizeSoldProducts($validated['sold_products'] ?? []);
            $snapshotData = $this->buildNvlSnapshotFromSoldProducts($soldProducts);
            $snapshot = $snapshotData['snapshot'];
            $hasAlert = $snapshotData['has_alert'];

            $cashDiff = (int) $validated['cash_actual'] - (int) $validated['cash_theoretical'];
            $hasAlert = $hasAlert || abs($cashDiff) > self::CASH_DIFF_THRESHOLD;

            return ShiftHandover::create([
                'date' => $validated['date'],
                'shift_type' => $validated['shift_type'],
                'outgoing_employee_id' => $request->user()->id,
                'handover_at' => now(),
                'cash_theoretical' => $validated['cash_theoretical'],
                'cash_actual' => $validated['cash_actual'],
                'cash_diff' => $cashDiff,
                'cash_note' => $validated['cash_note'] ?? null,
                'total_orders' => $validated['total_orders'],
                'revenue_cash' => $validated['revenue_cash'],
                'revenue_transfer' => $validated['revenue_transfer'],
                'total_revenue' => $validated['revenue_cash'] + $validated['revenue_transfer'],
                'sold_products' => $snapshotData['sold_products'],
                'nvl_snapshot' => $snapshot,
                'equipment_checklist' => $validated['equipment_checklist'] ?? [],
                'handover_note' => $validated['handover_note'] ?? null,
                'has_alert' => $hasAlert,
                'status' => 'pending',
            ])->load(['outgoingEmployee:id,name', 'incomingEmployee:id,name']);
        });

        return response()->json(['message' => 'Đã lưu biên bản giao ca', 'handover' => $handover], 201);
    }

    public function index(Request $request)
    {
        $query = ShiftHandover::with(['outgoingEmployee:id,name', 'incomingEmployee:id,name'])->latest('handover_at');

        if ($request->filled('date')) $query->whereDate('date', $request->date);
        if ($request->filled('shift_type')) $query->where('shift_type', $request->shift_type);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('employee_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('outgoing_employee_id', $request->employee_id)
                    ->orWhere('incoming_employee_id', $request->employee_id);
            });
        }

        $handovers = $query->paginate($request->input('per_page', 20));
        $handovers->getCollection()->transform(fn (ShiftHandover $handover) => $this->withPermissions($handover, $request->user()));

        return response()->json(['handovers' => $handovers]);
    }

    public function show(Request $request, ShiftHandover $shiftHandover)
    {
        return response()->json(['handover' => $this->withPermissions($shiftHandover, $request->user())]);
    }

    public function confirm(Request $request, ShiftHandover $shiftHandover)
    {
        if ($shiftHandover->status !== 'pending') {
            return response()->json(['message' => 'Biên bản này không còn chờ xác nhận'], 422);
        }

        $receiveSchedule = $this->receiveScheduleForHandover($shiftHandover);
        if (!$this->userHasScheduleForShift($request->user(), $receiveSchedule['date'], $receiveSchedule['shift_type'])) {
            return response()->json([
                'message' => 'Bạn không có lịch làm ca tiếp theo nên không thể nhận ca',
            ], 422);
        }

        $shiftHandover->update([
            'incoming_employee_id' => $request->user()->id,
            'received_at' => now(),
            'status' => 'confirmed',
        ]);

        return response()->json(['message' => 'Đã xác nhận nhận ca', 'handover' => $shiftHandover->fresh(['outgoingEmployee:id,name', 'incomingEmployee:id,name'])]);
    }

    public function dispute(Request $request, ShiftHandover $shiftHandover)
    {
        $validated = $request->validate(['dispute_note' => 'required|string|max:2000']);

        $receiveSchedule = $this->receiveScheduleForHandover($shiftHandover);
        if (!$this->userHasScheduleForShift($request->user(), $receiveSchedule['date'], $receiveSchedule['shift_type'])) {
            return response()->json([
                'message' => 'Bạn không có lịch làm ca tiếp theo nên không thể báo cáo sai lệch',
            ], 422);
        }

        $shiftHandover->update([
            'incoming_employee_id' => $request->user()->id,
            'received_at' => now(),
            'status' => 'disputed',
            'dispute_note' => $validated['dispute_note'],
            'has_alert' => true,
        ]);

        return response()->json(['message' => 'Đã báo cáo sai lệch', 'handover' => $shiftHandover->fresh(['outgoingEmployee:id,name', 'incomingEmployee:id,name'])]);
    }

    public function export(string $month)
    {
        $date = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $handovers = ShiftHandover::with(['outgoingEmployee:id,name', 'incomingEmployee:id,name'])
            ->whereBetween('date', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])
            ->orderBy('date')
            ->orderBy('shift_type')
            ->get();

        $filename = "shift-handovers-{$month}.csv";

        return response()->streamDownload(function () use ($handovers) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Ngày', 'Ca', 'Người giao', 'Người nhận', 'Tiền mặt LT', 'Tiền mặt TT', 'Chênh lệch', 'Order', 'DT tiền mặt', 'DT CK', 'Tổng DT', 'Cảnh báo', 'Trạng thái', 'Ghi chú']);

            foreach ($handovers as $handover) {
                fputcsv($out, [
                    $handover->date,
                    $handover->shift_type,
                    $handover->outgoingEmployee?->name,
                    $handover->incomingEmployee?->name,
                    $handover->cash_theoretical,
                    $handover->cash_actual,
                    $handover->cash_diff,
                    $handover->total_orders,
                    $handover->revenue_cash,
                    $handover->revenue_transfer,
                    $handover->total_revenue,
                    $handover->has_alert ? 'Có' : 'Không',
                    $handover->status,
                    $handover->handover_note,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function userHasScheduleForShift(User $user, string $date, string $shiftType): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $scheduledShiftKeys = StaffSchedule::where('staff_id', $user->id)
            ->whereDate('date', $date)
            ->pluck('shift');

        if ($scheduledShiftKeys->isEmpty()) {
            return false;
        }

        $matchingShiftKeys = $this->shiftKeysForHandoverType($shiftType);

        return $scheduledShiftKeys->intersect($matchingShiftKeys)->isNotEmpty();
    }

    private function receiveScheduleForHandover(ShiftHandover $handover): array
    {
        if ($handover->shift_type === 'sang') {
            return [
                'date' => $handover->date,
                'shift_type' => 'chieu',
            ];
        }

        return [
            'date' => Carbon::parse($handover->date)->addDay()->toDateString(),
            'shift_type' => 'sang',
        ];
    }

    private function withPermissions(ShiftHandover $handover, User $user): ShiftHandover
    {
        $handover->load(['outgoingEmployee:id,name', 'incomingEmployee:id,name']);
        $receiveSchedule = $this->receiveScheduleForHandover($handover);
        $handover->setAttribute('can_confirm', $handover->status === 'pending' && $this->userHasScheduleForShift($user, $receiveSchedule['date'], $receiveSchedule['shift_type']));
        $handover->setAttribute('receive_shift_type', $receiveSchedule['shift_type']);
        $handover->setAttribute('receive_date', $receiveSchedule['date']);

        return $handover;
    }

    private function handoverProducts()
    {
        $recipeProductIds = Recipe::where('active', true)->pluck('product_id')->all();

        return Extra::query()
            ->where('status', true)
            ->whereNotIn(DB::raw('LOWER(category)'), $this->excludedProductCategories())
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'sku'])
            ->map(fn (Extra $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category,
                'sku' => $product->sku,
                'has_recipe' => in_array($product->id, $recipeProductIds, true),
            ])
            ->values();
    }

    private function normalizeSoldProducts(array $soldProducts): array
    {
        return collect($soldProducts)
            ->filter(fn ($item) => !empty($item['product_id']) && (int) ($item['quantity'] ?? 0) > 0)
            ->groupBy(fn ($item) => (int) $item['product_id'])
            ->map(fn ($items, $productId) => [
                'product_id' => (int) $productId,
                'quantity' => (int) $items->sum(fn ($item) => (int) ($item['quantity'] ?? 0)),
            ])
            ->values()
            ->all();
    }

    private function buildNvlSnapshotFromSoldProducts(array $soldProducts): array
    {
        if (empty($soldProducts)) {
            return [
                'snapshot' => [],
                'sold_products' => [],
                'has_alert' => false,
            ];
        }

        $items = collect($soldProducts);
        $products = Extra::whereIn('id', $items->pluck('product_id'))->get(['id', 'name', 'category', 'sku'])->keyBy('id');
        $recipes = Recipe::whereIn('product_id', $items->pluck('product_id'))
            ->where('active', true)
            ->get()
            ->keyBy('product_id');

        $requirements = [];
        $hasAlert = false;
        $missingRecipeRows = [];

        $normalizedProducts = $items->map(function ($item) use ($products, $recipes, &$requirements, &$hasAlert, &$missingRecipeRows) {
            $product = $products[(int) $item['product_id']];
            $quantity = (int) $item['quantity'];
            $recipe = $recipes->get($product->id);

            if (!$recipe) {
                $hasAlert = true;
                $missingRecipeRows[] = [
                    'material_id' => 'missing-product-' . $product->id,
                    'material_name' => 'Chưa có công thức: ' . $product->name,
                    'unit' => '',
                    'theoretical' => 0,
                    'actual' => 0,
                    'diff' => 0,
                    'diff_percent' => 0,
                    'required' => 0,
                    'reason' => 'Món này chưa có công thức NVL',
                    'has_alert' => true,
                ];
            } else {
                foreach ($recipe->ingredients as $ingredient) {
                    $materialId = (int) $ingredient['material_id'];
                    $requirements[$materialId] = ($requirements[$materialId] ?? 0)
                        + round((float) $ingredient['quantity'] * $quantity, 3);
                }
            }

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'category' => $product->category,
                'sku' => $product->sku,
                'quantity' => $quantity,
                'has_recipe' => (bool) $recipe,
            ];
        })->values()->all();

        $materials = Material::whereIn('id', array_keys($requirements))->get()->keyBy('id');
        $snapshot = collect($requirements)
            ->map(function ($requiredQuantity, $materialId) use ($materials, &$hasAlert) {
                $material = $materials[(int) $materialId];
                $currentStock = round((float) $material->current_stock, 3);
                $requiredQuantity = round((float) $requiredQuantity, 3);
                $afterExpected = round($currentStock - $requiredQuantity, 3);
                $isAlert = $afterExpected < 0;
                $hasAlert = $hasAlert || $isAlert;

                return [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'unit' => $material->unit,
                    'theoretical' => $currentStock,
                    'actual' => max($afterExpected, 0),
                    'diff' => $requiredQuantity,
                    'diff_percent' => $currentStock > 0 ? round($requiredQuantity / $currentStock * 100, 2) : ($requiredQuantity > 0 ? 100 : 0),
                    'required' => $requiredQuantity,
                    'reason' => $isAlert ? 'Không đủ tồn theo món đã bán' : null,
                    'has_alert' => $isAlert,
                ];
            })
            ->sortBy('material_name')
            ->values()
            ->merge($missingRecipeRows)
            ->all();

        return [
            'snapshot' => $snapshot,
            'sold_products' => $normalizedProducts,
            'has_alert' => $hasAlert,
        ];
    }

    private function excludedProductCategories(): array
    {
        return [
            'services',
            'office_services',
            'other_services',
            'others_services',
            'office services',
            'other services',
            'others services',
        ];
    }

    private function shiftKeysForHandoverType(string $shiftType): array
    {
        return Shift::all()
            ->filter(function (Shift $shift) use ($shiftType) {
                $key = strtolower($shift->key);
                $name = strtolower($shift->name);
                $hour = (int) Carbon::parse($shift->start_time)->format('H');

                if ($shiftType === 'sang') {
                    return str_contains($key, 'morning')
                        || str_contains($name, 'sáng')
                        || $hour < 12;
                }

                return str_contains($key, 'afternoon')
                    || str_contains($name, 'chiều')
                    || $hour >= 12;
            })
            ->pluck('key')
            ->values()
            ->all();
    }
}
