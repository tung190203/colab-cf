<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Material;
use App\Models\PosOrder;
use App\Models\Shift;
use App\Models\ShiftHandover;
use App\Models\StaffSchedule;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftHandoverController extends Controller
{
    private const CASH_DIFF_THRESHOLD = 50000;
    private const MATERIAL_DIFF_PERCENT_THRESHOLD = 10;

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
            'materials' => 'required|array|min:1',
            'materials.*.material_id' => 'required|exists:materials,id',
            'materials.*.actual' => 'required|numeric|min:0',
            'materials.*.reason' => 'nullable|string|max:1000',
            'equipment_checklist' => 'nullable|array',
            'handover_note' => 'nullable|string|max:3000',
        ]);

        if (!$this->userHasScheduleForShift($request->user(), $validated['date'], $validated['shift_type'])) {
            return response()->json([
                'message' => 'Bạn không có lịch làm ca này nên không thể tạo biên bản giao ca',
            ], 422);
        }

        $handover = DB::transaction(function () use ($request, $validated) {
            $materials = Material::whereIn('id', collect($validated['materials'])->pluck('material_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $hasAlert = false;
            $snapshot = collect($validated['materials'])->map(function ($item) use ($materials, &$hasAlert, $request) {
                $material = $materials[(int) $item['material_id']];
                $theoretical = round((float) $material->current_stock, 3);
                $actual = round((float) $item['actual'], 3);
                $diff = round($theoretical - $actual, 3);
                $percent = $theoretical > 0 ? round(abs($diff) / $theoretical * 100, 2) : ($diff == 0 ? 0 : 100);
                $isAlert = $percent > self::MATERIAL_DIFF_PERCENT_THRESHOLD;
                $hasAlert = $hasAlert || $isAlert;

                if ($actual !== $theoretical) {
                    StockLog::create([
                        'material_id' => $material->id,
                        'type' => 'adjustment',
                        'quantity' => round($actual - $theoretical, 3),
                        'stock_before' => $theoretical,
                        'stock_after' => $actual,
                        'note' => $item['reason'] ?? 'Điều chỉnh sau giao ca',
                        'created_by' => $request->user()?->id,
                    ]);
                    $material->update(['current_stock' => $actual]);
                }

                return [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'unit' => $material->unit,
                    'theoretical' => $theoretical,
                    'actual' => $actual,
                    'diff' => $diff,
                    'diff_percent' => $percent,
                    'reason' => $item['reason'] ?? null,
                    'has_alert' => $isAlert,
                ];
            })->values()->all();

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
