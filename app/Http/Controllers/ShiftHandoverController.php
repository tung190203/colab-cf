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
use Illuminate\Database\Query\Builder;
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
        $shiftReport = $this->buildShiftReport($start, $now);

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
            'report' => $shiftReport,
            'materials' => Material::where('active', true)->orderBy('id')->get(),
            'products' => $this->handoverProducts(),
            'staff' => User::whereIn('role', ['staff', 'shift_leader'])->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'shift_type' => 'required|string|max:30',
            'opening_cash' => 'nullable|integer|min:0',
            'cash_theoretical' => 'required|integer|min:0',
            'cash_actual' => 'required|integer|min:0',
            'cash_note' => 'nullable|string|max:2000',
            'total_orders' => 'required|integer|min:0',
            'revenue_cash' => 'required|integer|min:0',
            'revenue_transfer' => 'required|integer|min:0',
            'inventory_items' => 'nullable|array',
            'inventory_items.*.material_id' => 'required|exists:materials,id',
            'inventory_items.*.material_name' => 'required|string',
            'inventory_items.*.unit' => 'nullable|string',
            'inventory_items.*.opening_stock' => 'required|numeric|min:0',
            'inventory_items.*.imported_stock' => 'required|numeric|min:0',
            'inventory_items.*.used_stock' => 'required|numeric|min:0',
            'inventory_items.*.closing_stock' => 'required|numeric',
            'equipment_checklist' => 'nullable|array',
            'handover_note' => 'nullable|string|max:3000',
        ]);

        if (!$this->userHasScheduleForShift($request->user(), $validated['date'], $validated['shift_type'])) {
            return response()->json([
                'message' => 'Bạn không có lịch làm ca này nên không thể tạo biên bản giao ca',
            ], 422);
        }

        $handover = DB::transaction(function () use ($request, $validated) {
            $inventoryItems = $validated['inventory_items'] ?? [];
            $now = Carbon::now('Asia/Ho_Chi_Minh');
            $lastHandover = ShiftHandover::where('status', 'confirmed')->latest('received_at')->first();
            $start = $lastHandover?->received_at ?: $now->copy()->startOfDay();

            $cashDiff = (int) $validated['cash_actual'] - (int) $validated['cash_theoretical'];
            $hasAlert = abs($cashDiff) > self::CASH_DIFF_THRESHOLD;

            return ShiftHandover::create([
                'date' => $validated['date'],
                'shift_type' => $validated['shift_type'],
                'outgoing_employee_id' => $request->user()->id,
                'handover_at' => now(),
                'opening_cash' => (int) ($validated['opening_cash'] ?? 0),
                'cash_theoretical' => $validated['cash_theoretical'],
                'cash_actual' => $validated['cash_actual'],
                'cash_diff' => $cashDiff,
                'cash_note' => $validated['cash_note'] ?? null,
                'total_orders' => $validated['total_orders'],
                'revenue_cash' => $validated['revenue_cash'],
                'revenue_transfer' => $validated['revenue_transfer'],
                'total_revenue' => $validated['revenue_cash'] + $validated['revenue_transfer'],
                'report_snapshot' => $this->buildShiftReport($start, $now),
                'sold_products' => [],
                'nvl_snapshot' => $inventoryItems,
                'damaged_materials' => [],
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

        DB::transaction(function () use ($request, $shiftHandover) {
            $shiftHandover->update([
                'incoming_employee_id' => $request->user()->id,
                'received_at' => now(),
                'status' => 'confirmed',
            ]);

            if (is_array($shiftHandover->nvl_snapshot)) {
                foreach ($shiftHandover->nvl_snapshot as $item) {
                    if (isset($item['material_id']) && isset($item['closing_stock'])) {
                        Material::where('id', $item['material_id'])->update([
                            'current_stock' => $item['closing_stock']
                        ]);
                    }
                }
            }
        });

        return response()->json(['message' => 'Đã xác nhận nhận ca', 'handover' => $shiftHandover->fresh(['outgoingEmployee:id,name', 'incomingEmployee:id,name'])]);
    }

    public function dispute(Request $request, ShiftHandover $shiftHandover)
    {
        $validated = $request->validate([
            'dispute_note' => 'nullable|string|max:2000',
            'receive_cash_actual' => 'nullable|integer|min:0',
            'receive_cash_reason' => 'nullable|string|max:1000',
            'receive_material_discrepancies' => 'nullable|array',
            'receive_material_discrepancies.*.material_id' => 'required',
            'receive_material_discrepancies.*.material_name' => 'required|string|max:255',
            'receive_material_discrepancies.*.unit' => 'nullable|string|max:50',
            'receive_material_discrepancies.*.expected' => 'required|numeric',
            'receive_material_discrepancies.*.actual_received' => 'required|numeric|min:0',
            'receive_material_discrepancies.*.reason' => 'required|string|max:1000',
        ]);

        $cashIsDifferent = array_key_exists('receive_cash_actual', $validated)
            && (int) $validated['receive_cash_actual'] !== (int) $shiftHandover->cash_actual;
        if ($cashIsDifferent && empty($validated['receive_cash_reason'])) {
            return response()->json(['message' => 'Vui lòng nhập lý do lệch tiền thực nhận'], 422);
        }

        $materialDiscrepancies = $validated['receive_material_discrepancies'] ?? [];
        if (!$cashIsDifferent && empty($materialDiscrepancies) && empty($validated['dispute_note'])) {
            return response()->json(['message' => 'Vui lòng nhập nội dung sai lệch'], 422);
        }

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
            'dispute_note' => $validated['dispute_note'] ?? 'Có sai lệch khi nhận ca',
            'receive_cash_actual' => $validated['receive_cash_actual'] ?? null,
            'receive_cash_reason' => $validated['receive_cash_reason'] ?? null,
            'receive_material_discrepancies' => $materialDiscrepancies,
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
            fputcsv($out, ['Ngày', 'Ca', 'Người giao', 'Người nhận', 'Tiền đầu ca', 'Tiền dự kiến cuối ca', 'Tiền cuối ca', 'Chênh lệch', 'Order', 'DT tiền mặt', 'DT CK', 'Tổng DT', 'Cảnh báo', 'Trạng thái', 'Ghi chú']);

            foreach ($handovers as $handover) {
                fputcsv($out, [
                    $handover->date,
                    $handover->shift_type,
                    $handover->outgoingEmployee?->name,
                    $handover->incomingEmployee?->name,
                    $handover->opening_cash,
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

    private function buildShiftReport(Carbon $start, Carbon $end): array
    {
        $bookingOrders = Booking::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'confirmed');
        $posOrders = PosOrder::query()
            ->whereBetween('created_at', [$start, $end]);

        $cashTotal = (int) (clone $bookingOrders)->where('payment_method', 'cash')->sum('total_price')
            + (int) (clone $posOrders)->where('payment_method', 'cash')->sum('total_amount');
        $transferTotal = (int) (clone $bookingOrders)->whereIn('payment_method', ['transfer', 'momo', 'card'])->sum('total_price')
            + (int) (clone $posOrders)->whereIn('payment_method', ['transfer', 'momo', 'card'])->sum('total_amount');
        $totalOrders = (clone $bookingOrders)->count() + (clone $posOrders)->count();
        $totalRevenue = $cashTotal + $transferTotal;

        return [
            'period_start' => $start->toDateTimeString(),
            'period_end' => $end->toDateTimeString(),
            'summary' => [
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'average_order_value' => $totalOrders > 0 ? (int) round($totalRevenue / $totalOrders) : 0,
                'cash_total' => $cashTotal,
                'transfer_total' => $transferTotal,
            ],
            'payment_methods' => $this->paymentMethodSummary($start, $end),
            'sources' => $this->sourceSummary($start, $end),
            'product_groups' => $this->productGroupSummary($start, $end),
            'top_products' => $this->topProductSummary($start, $end),
        ];
    }

    private function paymentMethodSummary(Carbon $start, Carbon $end): array
    {
        $bookingRows = Booking::query()
            ->select('payment_method', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total_price) as revenue'))
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'confirmed')
            ->groupBy('payment_method')
            ->get();

        $posRows = PosOrder::query()
            ->select('payment_method', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total_amount) as revenue'))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('payment_method')
            ->get();

        return $bookingRows
            ->concat($posRows)
            ->groupBy('payment_method')
            ->map(fn ($rows, $method) => [
                'name' => $this->paymentMethodLabel($method),
                'orders_count' => (int) $rows->sum('orders_count'),
                'revenue' => (int) $rows->sum('revenue'),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    private function sourceSummary(Carbon $start, Carbon $end): array
    {
        $bookingRows = Booking::query()
            ->select('mode_booking', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total_price) as revenue'))
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'confirmed')
            ->groupBy('mode_booking')
            ->get()
            ->map(fn ($row) => [
                'name' => $this->bookingModeLabel($row->mode_booking),
                'orders_count' => (int) $row->orders_count,
                'revenue' => (int) $row->revenue,
            ]);

        $posRevenue = (int) PosOrder::query()
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');
        $posOrders = PosOrder::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();

        return $bookingRows
            ->push([
                'name' => 'POS bán tại quầy',
                'orders_count' => $posOrders,
                'revenue' => $posRevenue,
            ])
            ->filter(fn ($row) => $row['orders_count'] > 0 || $row['revenue'] > 0)
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    private function productGroupSummary(Carbon $start, Carbon $end): array
    {
        return collect()
            ->merge($this->posItemBaseQuery($start, $end)
                ->select('extras.category', DB::raw('SUM(pos_order_items.quantity) as quantity'), DB::raw('SUM(pos_order_items.line_total) as revenue'))
                ->groupBy('extras.category')
                ->get())
            ->merge($this->bookingExtraBaseQuery($start, $end)
                ->select('extras.category', DB::raw('SUM(booking_extras.quantity) as quantity'), DB::raw('SUM(CASE WHEN booking_extras.quantity > booking_extras.free_applied THEN (booking_extras.quantity - booking_extras.free_applied) * extras.price ELSE 0 END) as revenue'))
                ->groupBy('extras.category')
                ->get())
            ->groupBy('category')
            ->map(fn ($rows, $category) => [
                'name' => $category ?: 'Chưa phân nhóm',
                'quantity' => (int) $rows->sum('quantity'),
                'revenue' => (int) $rows->sum('revenue'),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    private function topProductSummary(Carbon $start, Carbon $end): array
    {
        return collect()
            ->merge($this->posItemBaseQuery($start, $end)
                ->select('pos_order_items.product_id', 'pos_order_items.product_name as name', 'extras.category', DB::raw('SUM(pos_order_items.quantity) as quantity'), DB::raw('SUM(pos_order_items.line_total) as revenue'))
                ->groupBy('pos_order_items.product_id', 'pos_order_items.product_name', 'extras.category')
                ->get())
            ->merge($this->bookingExtraBaseQuery($start, $end)
                ->select('booking_extras.extra_id as product_id', 'extras.name', 'extras.category', DB::raw('SUM(booking_extras.quantity) as quantity'), DB::raw('SUM(CASE WHEN booking_extras.quantity > booking_extras.free_applied THEN (booking_extras.quantity - booking_extras.free_applied) * extras.price ELSE 0 END) as revenue'))
                ->groupBy('booking_extras.extra_id', 'extras.name', 'extras.category')
                ->get())
            ->groupBy('product_id')
            ->map(fn ($rows) => [
                'name' => $rows->first()->name,
                'category' => $rows->first()->category,
                'quantity' => (int) $rows->sum('quantity'),
                'revenue' => (int) $rows->sum('revenue'),
            ])
            ->sortByDesc('revenue')
            ->take(8)
            ->values()
            ->all();
    }

    private function posItemBaseQuery(Carbon $start, Carbon $end): Builder
    {
        return DB::table('pos_order_items')
            ->join('pos_orders', 'pos_order_items.pos_order_id', '=', 'pos_orders.id')
            ->leftJoin('extras', 'pos_order_items.product_id', '=', 'extras.id')
            ->whereBetween('pos_orders.created_at', [$start, $end]);
    }

    private function bookingExtraBaseQuery(Carbon $start, Carbon $end): Builder
    {
        return DB::table('booking_extras')
            ->join('bookings', 'booking_extras.booking_id', '=', 'bookings.id')
            ->join('extras', 'booking_extras.extra_id', '=', 'extras.id')
            ->whereBetween('bookings.created_at', [$start, $end])
            ->where('bookings.status', 'confirmed');
    }

    private function paymentMethodLabel(?string $method): string
    {
        return [
            'cash' => 'Tiền mặt',
            'transfer' => 'Chuyển khoản',
            'momo' => 'MoMo',
            'card' => 'Thẻ',
            'none' => 'Chưa thanh toán',
        ][$method] ?? ($method ?: 'Không rõ');
    }

    private function bookingModeLabel(?string $mode): string
    {
        return [
            'seat' => 'Đặt chỗ',
            'room' => 'Phòng họp',
            'order' => 'Đơn online',
        ][$mode] ?? ($mode ?: 'Không rõ');
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

    private function normalizeDamagedMaterials(array $damagedMaterials): array
    {
        $items = collect($damagedMaterials)
            ->filter(fn ($item) => !empty($item['material_id']) && (float) ($item['quantity'] ?? 0) > 0)
            ->groupBy(fn ($item) => (int) $item['material_id']);

        if ($items->isEmpty()) {
            return [];
        }

        $materials = Material::whereIn('id', $items->keys())->get(['id', 'name', 'unit'])->keyBy('id');

        return $items
            ->map(function ($rows, $materialId) use ($materials) {
                $material = $materials->get((int) $materialId);

                if (!$material) {
                    return null;
                }

                return [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'unit' => $material->unit,
                    'quantity' => round((float) $rows->sum(fn ($item) => (float) ($item['quantity'] ?? 0)), 3),
                    'note' => $rows->pluck('note')->filter()->implode('; '),
                ];
            })
            ->filter()
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
            ->sortBy('material_id')
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
