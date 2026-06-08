<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\Payroll;
use App\Models\PenaltyRule;
use App\Models\Shift;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    // ─── Authentication ──────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $request->validate([
            'phone'    => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('phone', $request->phone)
            ->whereIn('role', ['admin', 'staff', 'shift_leader'])
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Số điện thoại hoặc mật khẩu không đúng'], 401);
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'        => $user->id,
                'name'      => $user->name,
                'phone'     => $user->phone,
                'role'      => $user->role,
                'image_url' => $user->image_url,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Đăng xuất thành công']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->append('image_url');
        return response()->json(['user' => $user]);
    }

    public function getStats()
    {
        $today = Carbon::now('Asia/Ho_Chi_Minh');
        $start = $today->copy()->startOfDay();
        $end = $today->copy()->endOfDay();
        $user = auth()->user();

        $workedHoursThisMonth = 0;
        if ($user && in_array($user->role, ['staff', 'shift_leader'])) {
            $attendances = Attendance::where('staff_id', $user->id)
                ->whereMonth('date', $today->month)
                ->whereYear('date', $today->year)
                ->whereNotNull('check_in_at')
                ->whereNotNull('check_out_at')
                ->get();
            foreach ($attendances as $att) {
                $checkIn = Carbon::parse($att->check_in_at);
                $checkOut = Carbon::parse($att->check_out_at);
                $workedHoursThisMonth += round(abs($checkOut->diffInMinutes($checkIn, false)) / 60, 2);
            }
            $workedHoursThisMonth = round($workedHoursThisMonth, 2);
        }

        $totalStaff = User::whereIn('role', ['staff', 'shift_leader'])->count();
        $activeSchedules = StaffSchedule::where('date', $today->toDateString())->count();
        
        // Đơn hàng hôm nay (confirmed)
        $totalBookings = Booking::whereBetween('created_at', [$start, $end])
            ->where('status', 'confirmed')
            ->count();
        
        // Doanh thu hôm nay
        $todayRevenue = Booking::whereBetween('created_at', [$start, $end])
            ->where('status', 'confirmed')
            ->sum('total_price');

        // Thêm: Đơn hàng gần đây (5 đơn)
        $recentBookings = Booking::with('package')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Thêm: Nhân viên đang làm việc (Đã check-in nhưng chưa check-out hôm nay)
        $activeStaff = Attendance::with('staff:id,name,image,role')
            ->where('date', $today->toDateString())
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->get()
            ->map(fn($a) => [
                'name' => $a->staff->name,
                'image_url' => $a->staff->image_url,
                'check_in' => Carbon::parse($a->check_in_at)->format('H:i')
            ]);

        // Thêm: Doanh thu 7 ngày gần nhất
        $revenue7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $dayStart = $d->copy()->startOfDay();
            $dayEnd = $d->copy()->endOfDay();
            
            $rev = Booking::whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('status', 'confirmed')
                ->sum('total_price');
                
            $revenue7Days[] = [
                'day' => $d->format('d/m'),
                'total' => (int)$rev
            ];
        }

        return response()->json([
            'total_staff'      => $totalStaff,
            'active_schedules' => $activeSchedules,
            'total_bookings'   => $totalBookings,
            'today_revenue'    => (int) ($todayRevenue ?? 0),
            'worked_hours'     => $workedHoursThisMonth,
            'recent_bookings'  => $recentBookings,
            'active_staff'     => $activeStaff,
            'revenue_chart'    => $revenue7Days,
            'debug' => [
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString()
            ]
        ]);
    }

    // ─── Staff Management ─────────────────────────────────────────────────────

    public function getStaffList()
    {
        $staff = User::whereIn('role', ['staff', 'shift_leader'])
            ->get()
            ->map(fn($u) => array_merge($u->toArray(), ['image_url' => $u->image_url]));

        return response()->json($staff);
    }

    public function addStaff(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'phone'       => 'required|string|max:15|unique:users,phone',
            'password'    => 'required|string|min:6',
            'role'        => 'required|in:admin,staff,shift_leader',
            'hourly_rate' => 'nullable|integer|min:0',
            'note'        => 'nullable|string',
            'image'       => 'nullable|image|max:2048',
        ]);

        $user = User::create([
            'name'        => $request->name,
            'phone'       => $request->phone,
            'password'    => Hash::make($request->password),
            'role'        => $request->role,
            'hourly_rate' => $request->hourly_rate ?? 0,
            'note'        => $request->note,
        ]);

        if ($request->hasFile('image')) {
            $path        = $request->file('image')->store('users', 'public');
            $user->image = $path;
            $user->save();
        }

        return response()->json([
            'message' => 'Thêm nhân viên thành công',
            'user'    => array_merge($user->toArray(), ['image_url' => $user->image_url]),
        ], 201);
    }

    public function updateStaff(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'        => 'required|string|max:255',
            'phone'       => 'required|string|max:15|unique:users,phone,' . $id,
            'password'    => 'nullable|string|min:6',
            'role'        => 'required|in:admin,staff,shift_leader',
            'hourly_rate' => 'nullable|integer|min:0',
            'note'        => 'nullable|string',
            'image'       => 'nullable|image|max:2048',
        ]);

        $user->name        = $request->name;
        $user->phone       = $request->phone;
        $user->role        = $request->role;
        $user->hourly_rate = $request->hourly_rate ?? $user->hourly_rate;
        $user->note        = $request->note;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($request->hasFile('image')) {
            $path        = $request->file('image')->store('users', 'public');
            $user->image = $path;
        }

        $user->save();

        return response()->json([
            'message' => 'Cập nhật nhân viên thành công',
            'user'    => array_merge($user->toArray(), ['image_url' => $user->image_url]),
        ]);
    }

    public function deleteStaff($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'Xóa nhân viên thành công']);
    }

    // ─── Penalty Rules ───────────────────────────────────────────────────────

    public function getPenaltyRules(Request $request)
    {
        $query = PenaltyRule::query()->orderByDesc('is_active')->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return response()->json(['items' => $query->get()]);
    }

    public function storePenaltyRule(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:bonus,penalty',
            'name' => 'required|string|max:255',
            'amount' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $rule = PenaltyRule::create([
            ...$data,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json(['message' => 'Đã thêm quy định phạt', 'item' => $rule], 201);
    }

    public function updatePenaltyRule(Request $request, PenaltyRule $penaltyRule)
    {
        $data = $request->validate([
            'type' => 'required|in:bonus,penalty',
            'name' => 'required|string|max:255',
            'amount' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $penaltyRule->update([
            ...$data,
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json(['message' => 'Đã cập nhật quy định phạt', 'item' => $penaltyRule]);
    }

    public function destroyPenaltyRule(PenaltyRule $penaltyRule)
    {
        $penaltyRule->delete();

        return response()->json(['message' => 'Đã xóa quy định phạt']);
    }

    // ─── Schedule ─────────────────────────────────────────────────────────────

    public function getSchedule(Request $request)
    {
        $from = $request->query('from', Carbon::now()->startOfWeek()->toDateString());
        $to   = $request->query('to', Carbon::now()->endOfWeek()->toDateString());

        $schedules = StaffSchedule::with('staff:id,name,image,role')
            ->whereBetween('date', [$from, $to])
            ->get()
            ->map(fn($s) => array_merge($s->toArray(), [
                'staff' => array_merge($s->staff->toArray(), ['image_url' => $s->staff->image_url]),
            ]));

        return response()->json($schedules);
    }

    public function saveSchedule(Request $request)
    {
        $request->validate([
            'schedules'            => 'required|array',
            'schedules.*.staff_id' => 'required|exists:users,id',
            'schedules.*.date'     => 'required|date',
            'schedules.*.shift'    => 'required|exists:shifts,key',
            'schedules.*.is_ot'    => 'nullable|boolean',
            'schedules.*.is_holiday' => 'nullable|boolean',
            'schedules.*.ot_multiplier' => 'nullable|numeric|min:1|max:10',
            'schedules.*.note'     => 'nullable|string',
        ]);

        $saved = [];
        foreach ($request->schedules as $item) {
            $isOt = (bool) ($item['is_ot'] ?? false);
            $isHoliday = (bool) ($item['is_holiday'] ?? false);
            $otMultiplier = ($isOt || $isHoliday) ? (float) ($item['ot_multiplier'] ?? 2) : null;

            $schedule = StaffSchedule::updateOrCreate(
                ['staff_id' => $item['staff_id'], 'date' => $item['date'], 'shift' => $item['shift']],
                [
                    'status' => 'scheduled',
                    'is_ot' => $isOt,
                    'is_holiday' => $isHoliday,
                    'ot_multiplier' => $otMultiplier,
                    'note' => $item['note'] ?? null,
                ]
            );
            $saved[] = $schedule;
        }

        return response()->json(['message' => 'Lưu lịch thành công', 'schedules' => $saved]);
    }

    public function deleteSchedule($id)
    {
        $schedule = StaffSchedule::findOrFail($id);

        DB::transaction(function () use ($schedule) {
            $date = $schedule->date->format('Y-m-d');

            Attendance::where('staff_id', $schedule->staff_id)
                ->whereDate('date', $date)
                ->where('shift', $schedule->shift)
                ->delete();

            $schedule->delete();

            $this->recalculateDraftPayrollForDate($schedule->staff_id, $date);
        });

        return response()->json(['message' => 'Đã xóa lịch']);
    }

    // ─── Payroll ──────────────────────────────────────────────────────────────

    public function getPayroll(Request $request)
    {
        $month = $request->query('month', now()->month);
        $year  = $request->query('year', now()->year);

        $staff = User::whereIn('role', ['staff', 'shift_leader'])->get();

        $result = $staff->map(function ($u) use ($month, $year) {
            $payroll = Payroll::where('staff_id', $u->id)
                ->where('month', $month)->where('year', $year)->first();

            $hourlyRate = $payroll ? (int) $payroll->hourly_rate : (int) $u->hourly_rate;
            $summary = $this->calculatePayrollSummary($u->id, (int) $month, (int) $year, $hourlyRate);

            if ($payroll && !$payroll->is_settled) {
                $payroll = $this->syncDraftPayrollWithSummary($payroll, $summary);
            }

            return [
                'staff_id'     => $u->id,
                'name'         => $u->name,
                'role'         => $u->role,
                'image_url'    => $u->image_url,
                'hourly_rate'  => $u->hourly_rate,
                'worked_hours' => $summary['worked_hours'],
                'calculated_salary' => $summary['calculated_salary'],
                'bonus' => $summary['bonus'],
                'bonus_details' => $summary['bonus_details'],
                'shift_breakdown' => $summary['shift_breakdown'],
                'payroll'      => $payroll,
            ];
        });

        return response()->json($result);
    }

    public function savePayroll(Request $request)
    {
        foreach (['bonus_details', 'deduction_details'] as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $decoded = json_decode($request->input($field), true);
                $request->merge([$field => is_array($decoded) ? $decoded : []]);
            }
        }

        $request->validate([
            'staff_id'     => 'required|exists:users,id',
            'month'        => 'required|integer|between:1,12',
            'year'         => 'required|integer',
            'hourly_rate'  => 'required|integer|min:0',
            'worked_hours' => 'nullable|numeric|min:0',
            'bonus'        => 'nullable|integer|min:0',
            'deduction'    => 'nullable|integer|min:0',
            'note'         => 'nullable|string',
            'bonus_details' => 'nullable|array',
            'deduction_details' => 'nullable|array',
            'deduction_details.*.penalty_rule_id' => 'required_with:deduction_details|integer|exists:penalty_rules,id',
            'deduction_details.*.reason' => 'nullable|string|max:1000',
            'deduction_details.*.evidence_path' => 'nullable|string|max:2048',
            'deduction_evidences' => 'nullable|array',
            'deduction_evidences.*' => 'nullable|file|max:5120',
            'is_settled' => 'boolean',
            'status' => 'nullable|in:draft,pending_approval,approved',
        ]);

        $targetStatus = $request->input('status');
        if (!$targetStatus) {
            $targetStatus = $request->boolean('is_settled') ? 'approved' : 'draft';
        }

        if ($targetStatus === 'approved' && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Chỉ admin được xác nhận quyết toán bảng lương'], 403);
        }

        $existingPayroll = Payroll::where('staff_id', $request->staff_id)
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->first();

        $existingStatus = $existingPayroll
            ? ($existingPayroll->status ?: ($existingPayroll->is_settled ? 'approved' : 'draft'))
            : null;

        if ($existingStatus === 'approved') {
            return response()->json(['message' => 'Bảng lương đã quyết toán, không thể sửa'], 422);
        }

        $hourly    = (int) $request->hourly_rate;
        $summary = $this->calculatePayrollSummary((int) $request->staff_id, (int) $request->month, (int) $request->year, $hourly);
        $hours = $summary['worked_hours'];
        $calcBase = $summary['calculated_salary'];
        $manualBonusDetails = $this->normalizePayrollRuleDetails($request->bonus_details ?? [], 'bonus');
        $bonusDetails = $this->mergeAutoBonusDetails($summary['bonus_details'], $manualBonusDetails);
        $bonus = collect($bonusDetails)->sum(fn($item) => (int) ($item['amount'] ?? 0));
        $deductionDetails = $this->normalizePayrollDeductions($request, $request->deduction_details ?? []);
        $deduction = collect($deductionDetails)->sum(fn($item) => (int) ($item['amount'] ?? 0));
        $total     = max(0, $calcBase + $bonus - $deduction);
        $isSettled = $targetStatus === 'approved';

        $payroll = Payroll::updateOrCreate(
            ['staff_id' => $request->staff_id, 'month' => $request->month, 'year' => $request->year],
            [
                'hourly_rate'       => $hourly,
                'worked_hours'      => $hours,
                'calculated_salary' => $calcBase,
                'bonus'             => $bonus,
                'deduction'         => $deduction,
                'total'             => $total,
                'note'              => $request->note,
                'bonus_details'     => $bonusDetails,
                'deduction_details' => $deductionDetails,
                'is_settled'        => $isSettled,
                'status'            => $targetStatus,
                'submitted_at'      => $targetStatus === 'pending_approval' ? now() : null,
                'approved_at'       => $isSettled ? now() : null,
                'approved_by'       => $isSettled ? $request->user()->id : null,
            ]
        );

        return response()->json(['message' => 'Lưu bảng lương thành công', 'payroll' => $payroll]);
    }

    public function getAttendance(Request $request)
    {
        $request->validate([
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'staff_id' => 'nullable|exists:users,id',
        ]);

        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $staffId = $request->query('staff_id');
        $today = now()->format('Y-m-d');

        $schedules = StaffSchedule::with('staff:id,name,image,role')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->when($staffId, fn($query) => $query->where('staff_id', $staffId))
            ->whereHas('staff', fn($query) => $query->whereIn('role', ['staff', 'shift_leader']))
            ->orderByDesc('date')
            ->orderBy('staff_id')
            ->get();

        $attendances = Attendance::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->when($staffId, fn($query) => $query->where('staff_id', $staffId))
            ->get();

        $latestAdjustmentLogs = DB::table('attendance_adjustment_logs as logs')
            ->leftJoin('users as editor', 'logs.adjusted_by', '=', 'editor.id')
            ->whereIn('logs.attendance_id', $attendances->pluck('id')->filter()->values())
            ->orderByDesc('logs.created_at')
            ->select([
                'logs.*',
                'editor.name as editor_name',
            ])
            ->get()
            ->groupBy('attendance_id')
            ->map(fn($logs) => $logs->first());

        $attendances = $attendances
            ->keyBy(fn($item) => $item->staff_id . '_' . $item->date->format('Y-m-d') . '_' . $item->shift);
        $shifts = Shift::all()->keyBy('key');

        $records = $schedules
            ->filter(fn($schedule) => $schedule->date->format('Y-m-d') <= $today)
            ->map(function ($schedule) use ($attendances, $shifts) {
                $date = $schedule->date->format('Y-m-d');
                $attendance = $attendances->get($schedule->staff_id . '_' . $date . '_' . $schedule->shift);
                $latestLog = $attendance ? $latestAdjustmentLogs->get($attendance->id) : null;
                $shift = $shifts->get($schedule->shift);
                $payableHours = ($attendance && $shift)
                    ? $this->calculatePayableHoursForSchedule($schedule, $attendance, $shift)
                    : 0;

                $adjustmentChanges = [];
                if ($latestLog) {
                    if ((string) $latestLog->old_check_in_at !== (string) $latestLog->new_check_in_at) {
                        $adjustmentChanges[] = 'Giờ vào';
                    }
                    if ((string) $latestLog->old_check_out_at !== (string) $latestLog->new_check_out_at) {
                        $adjustmentChanges[] = 'Giờ ra';
                    }
                    if ((string) $latestLog->old_note !== (string) $latestLog->new_note) {
                        $adjustmentChanges[] = 'Ghi chú';
                    }
                }

                return [
                    'id' => $schedule->id . '_admin_attendance',
                    'staff_id' => $schedule->staff_id,
                    'staff_name' => $schedule->staff?->name,
                    'staff_role' => $schedule->staff?->role,
                    'staff_image_url' => $schedule->staff?->image_url,
                    'date' => $date,
                    'shift' => $schedule->shift,
                    'shift_start_time' => $shift?->start_time,
                    'shift_end_time' => $shift?->end_time,
                    'is_ot' => (bool) $schedule->is_ot,
                    'is_holiday' => (bool) $schedule->is_holiday,
                    'attendance_id' => $attendance?->id,
                    'check_in_at' => $attendance?->check_in_at,
                    'check_out_at' => $attendance?->check_out_at,
                    'payable_hours' => $payableHours,
                    'note' => $attendance?->note,
                    'is_manual_adjusted' => (bool) ($attendance?->is_manual_adjusted ?? false),
                    'adjusted_at' => $attendance?->adjusted_at,
                    'adjusted_by_name' => $latestLog?->editor_name,
                    'latest_adjustment_at' => $latestLog?->created_at,
                    'latest_adjustment_changes' => $adjustmentChanges,
                ];
            })
            ->values();

        return response()->json($records);
    }

    public function saveAttendance(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'shift' => 'required|exists:shifts,key',
            'check_in_time' => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i',
            'note' => 'nullable|string',
        ]);

        $date = Carbon::parse($request->date)->toDateString();

        $schedule = StaffSchedule::where('staff_id', $request->staff_id)
            ->whereDate('date', $date)
            ->where('shift', $request->shift)
            ->first();

        if (!$schedule) {
            return response()->json(['message' => 'Nhân viên chưa được phân ca này'], 422);
        }

        $shift = Shift::where('key', $request->shift)->firstOrFail();
        $shiftStart = Carbon::parse($date . ' ' . $shift->start_time, 'Asia/Ho_Chi_Minh');
        $shiftEnd = Carbon::parse($date . ' ' . $shift->end_time, 'Asia/Ho_Chi_Minh');
        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd->addDay();
        }

        if ($request->filled('check_out_time') && !$request->filled('check_in_time')) {
            return response()->json(['message' => 'Cần có giờ vào trước khi nhập giờ ra'], 422);
        }

        $checkInAt = $request->filled('check_in_time')
            ? Carbon::parse($date . ' ' . $request->check_in_time, 'Asia/Ho_Chi_Minh')
            : null;
        $checkOutAt = $request->filled('check_out_time')
            ? Carbon::parse($date . ' ' . $request->check_out_time, 'Asia/Ho_Chi_Minh')
            : null;

        if ($checkInAt && $checkOutAt && $checkOutAt->lessThanOrEqualTo($checkInAt)) {
            return response()->json(['message' => 'Giờ ra phải sau giờ vào'], 422);
        }

        if (!$schedule->is_ot && !$schedule->is_holiday) {
            if ($checkInAt && ($checkInAt->lt($shiftStart) || $checkInAt->gt($shiftEnd))) {
                return response()->json(['message' => 'Giờ vào phải nằm trong khung giờ ca ' . $shift->start_time . ' - ' . $shift->end_time], 422);
            }
            if ($checkOutAt && ($checkOutAt->lt($shiftStart) || $checkOutAt->gt($shiftEnd))) {
                return response()->json(['message' => 'Giờ ra phải nằm trong khung giờ ca ' . $shift->start_time . ' - ' . $shift->end_time], 422);
            }
        }

        $oldAttendance = Attendance::where('staff_id', $request->staff_id)
            ->where('date', $date)
            ->where('shift', $request->shift)
            ->first();

        $attendance = Attendance::updateOrCreate(
            ['staff_id' => $request->staff_id, 'date' => $date, 'shift' => $request->shift],
            [
                'check_in_at' => $checkInAt,
                'check_out_at' => $checkOutAt,
                'note' => $request->note,
                'is_manual_adjusted' => true,
                'adjusted_by' => $request->user()->id,
                'adjusted_at' => now(),
            ]
        );

        DB::table('attendance_adjustment_logs')->insert([
            'attendance_id' => $attendance->id,
            'staff_id' => $request->staff_id,
            'adjusted_by' => $request->user()->id,
            'date' => $date,
            'shift' => $request->shift,
            'old_check_in_at' => $oldAttendance?->check_in_at,
            'old_check_out_at' => $oldAttendance?->check_out_at,
            'new_check_in_at' => $attendance->check_in_at,
            'new_check_out_at' => $attendance->check_out_at,
            'old_note' => $oldAttendance?->note,
            'new_note' => $attendance->note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recalculateDraftPayrollForDate((int) $request->staff_id, $date);

        return response()->json(['message' => 'Đã cập nhật chấm công', 'attendance' => $attendance]);
    }

    public function getAuditLogs(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Chỉ admin được xem audit log'], 403);
        }

        $request->validate([
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'staff_id' => 'nullable|exists:users,id',
        ]);

        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $staffId = $request->query('staff_id');

        $logs = DB::table('attendance_adjustment_logs as logs')
            ->leftJoin('users as staff', 'logs.staff_id', '=', 'staff.id')
            ->leftJoin('users as editor', 'logs.adjusted_by', '=', 'editor.id')
            ->leftJoin('shifts', 'logs.shift', '=', 'shifts.key')
            ->whereMonth('logs.date', $month)
            ->whereYear('logs.date', $year)
            ->when($staffId, fn($query) => $query->where('logs.staff_id', $staffId))
            ->orderByDesc('logs.created_at')
            ->select([
                'logs.id',
                'logs.attendance_id',
                'logs.staff_id',
                'logs.adjusted_by',
                'logs.date',
                'logs.shift',
                'logs.old_check_in_at',
                'logs.old_check_out_at',
                'logs.new_check_in_at',
                'logs.new_check_out_at',
                'logs.old_note',
                'logs.new_note',
                'logs.created_at',
                'staff.name as staff_name',
                'editor.name as editor_name',
                'shifts.name as shift_name',
            ])
            ->limit(300)
            ->get();

        return response()->json($logs);
    }

    public function getCustomerStats(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Chỉ admin được xem thống kê khách hàng'], 403);
        }

        $request->validate([
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $period = Carbon::create($year, $month, 1);
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        $memberRoles = ['member', 'vip'];
        $baseBookingQuery = Booking::query()
            ->whereBetween('start_time', [$start, $end])
            ->where('status', '!=', 'cancelled');

        $totalMembers = User::whereIn('role', $memberRoles)->count();
        $vipMembers = User::where('role', 'vip')->count();
        $newMembers = User::whereIn('role', $memberRoles)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $totalBookings = (clone $baseBookingQuery)->count();
        $revenue = (int) (clone $baseBookingQuery)->sum('total_price');
        $activeCustomers = (clone $baseBookingQuery)
            ->whereNotNull('phone')
            ->distinct('phone')
            ->count('phone');
        $avgOrderValue = $totalBookings > 0 ? (int) round($revenue / $totalBookings) : 0;

        $returningCustomers = Booking::query()
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('phone')
            ->select('phone', DB::raw('COUNT(*) as orders_count'))
            ->groupBy('phone')
            ->having('orders_count', '>', 1)
            ->get()
            ->count();

        $memberBreakdown = User::whereIn('role', $memberRoles)
            ->select('role', DB::raw('COUNT(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        $topCustomers = (clone $baseBookingQuery)
            ->whereNotNull('phone')
            ->select(
                'phone',
                DB::raw('MAX(full_name) as name'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total_price) as total_spent'),
                DB::raw('MAX(start_time) as last_booking_at')
            )
            ->groupBy('phone')
            ->orderByDesc('total_spent')
            ->limit(8)
            ->get()
            ->map(fn($customer) => [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'orders_count' => (int) $customer->orders_count,
                'total_spent' => (int) $customer->total_spent,
                'last_booking_at' => $customer->last_booking_at,
                'is_member' => User::where('phone', $customer->phone)
                    ->whereIn('role', $memberRoles)
                    ->exists(),
            ]);

        $topPackages = (clone $baseBookingQuery)
            ->join('packages', 'bookings.package_id', '=', 'packages.id')
            ->select(
                'packages.name',
                DB::raw('COUNT(*) as bookings_count'),
                DB::raw('SUM(bookings.total_price) as revenue')
            )
            ->groupBy('packages.id', 'packages.name')
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get()
            ->map(fn($package) => [
                'name' => $package->name,
                'bookings_count' => (int) $package->bookings_count,
                'revenue' => (int) $package->revenue,
            ]);

        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = $period->copy()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            $query = Booking::whereBetween('start_time', [$monthStart, $monthEnd])
                ->where('status', '!=', 'cancelled');

            $monthlyTrend[] = [
                'label' => $date->format('m/Y'),
                'customers' => (clone $query)->whereNotNull('phone')->distinct('phone')->count('phone'),
                'bookings' => (clone $query)->count(),
                'revenue' => (int) (clone $query)->sum('total_price'),
            ];
        }

        return response()->json([
            'summary' => [
                'total_members' => $totalMembers,
                'vip_members' => $vipMembers,
                'new_members' => $newMembers,
                'active_customers' => $activeCustomers,
                'returning_customers' => $returningCustomers,
                'total_bookings' => $totalBookings,
                'revenue' => $revenue,
                'avg_order_value' => $avgOrderValue,
            ],
            'member_breakdown' => [
                'member' => (int) ($memberBreakdown['member'] ?? 0),
                'vip' => (int) ($memberBreakdown['vip'] ?? 0),
            ],
            'top_customers' => $topCustomers,
            'top_packages' => $topPackages,
            'monthly_trend' => $monthlyTrend,
        ]);
    }

    public function getShifts()
    {
        return response()->json(Shift::all());
    }

    public function saveShifts(Request $request)
    {
        $request->validate([
            'shifts'              => 'required|array',
            'shifts.*.key'        => 'required|string',
            'shifts.*.name'       => 'required|string',
            'shifts.*.start_time' => 'required|string',
            'shifts.*.end_time'   => 'required|string',
            'shifts.*.color'      => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $keys = collect($request->shifts)->pluck('key')->toArray();
            $removedKeys = Shift::whereNotIn('key', $keys)->pluck('key');

            if ($removedKeys->isNotEmpty()) {
                $affectedPayrolls = Attendance::query()
                    ->select('staff_id', 'date')
                    ->whereIn('shift', $removedKeys)
                    ->get()
                    ->map(fn($attendance) => [
                        'staff_id' => $attendance->staff_id,
                        'month' => $attendance->date->month,
                        'year' => $attendance->date->year,
                    ])
                    ->unique(fn($item) => $item['staff_id'] . '-' . $item['month'] . '-' . $item['year'])
                    ->values();

                Attendance::whereIn('shift', $removedKeys)->delete();
                StaffSchedule::whereIn('shift', $removedKeys)->delete();
                Shift::whereIn('key', $removedKeys)->delete();

                foreach ($affectedPayrolls as $item) {
                    $this->recalculateDraftPayroll($item['staff_id'], $item['month'], $item['year']);
                }
            }

            foreach ($request->shifts as $item) {
                Shift::updateOrCreate(
                    ['key' => $item['key']],
                    [
                        'name'       => $item['name'],
                        'start_time' => $item['start_time'],
                        'end_time'   => $item['end_time'],
                        'color'      => $item['color'] ?? '#2D4F1E',
                    ]
                );
            }
        });

        return response()->json(['message' => 'Cập nhật ca làm thành công', 'shifts' => Shift::all()]);
    }

    private function recalculateDraftPayrollForDate(int $staffId, string $date): void
    {
        $payrollDate = Carbon::parse($date);
        $this->recalculateDraftPayroll($staffId, $payrollDate->month, $payrollDate->year);
    }

    private function recalculateDraftPayroll(int $staffId, int $month, int $year): void
    {
        $payroll = Payroll::where('staff_id', $staffId)
            ->where('month', $month)
            ->where('year', $year)
            ->where('is_settled', false)
            ->first();

        if (!$payroll) {
            return;
        }

        $hourlyRate = (int) $payroll->hourly_rate;
        $summary = $this->calculatePayrollSummary($staffId, $month, $year, $hourlyRate);
        $this->syncDraftPayrollWithSummary($payroll, $summary);
    }

    private function syncDraftPayrollWithSummary(Payroll $payroll, array $summary): Payroll
    {
        $workedHours = $summary['worked_hours'];
        $calculatedSalary = $summary['calculated_salary'];
        $bonusDetails = $this->mergeAutoBonusDetails(
            $summary['bonus_details'],
            $this->normalizePayrollRuleDetails($payroll->bonus_details ?? [], 'bonus')
        );
        $bonus = collect($bonusDetails)->sum(fn($item) => (int) ($item['amount'] ?? 0));
        $deductionDetails = $this->normalizeStoredPayrollDeductions($payroll->deduction_details ?? []);
        $deduction = collect($deductionDetails)->sum(fn($item) => (int) ($item['amount'] ?? 0));

        $payroll->update([
            'worked_hours' => $workedHours,
            'calculated_salary' => $calculatedSalary,
            'bonus' => $bonus,
            'bonus_details' => $bonusDetails,
            'deduction' => $deduction,
            'deduction_details' => $deductionDetails,
            'total' => max(0, $calculatedSalary + $bonus - $deduction),
        ]);

        return $payroll->refresh();
    }

    private function calculateWorkedHours(int $staffId, int $month, int $year): float
    {
        return $this->calculatePayrollSummary($staffId, $month, $year, 0)['worked_hours'];
    }

    private function calculatePayrollSummary(int $staffId, int $month, int $year, int $hourlyRate): array
    {
        $schedules = StaffSchedule::query()
            ->where('staff_id', $staffId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get();

        $attendances = Attendance::where('staff_id', $staffId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get();

        $attendanceMap = $attendances->keyBy(fn($item) => $item->date->format('Y-m-d') . '_' . $item->shift);
        $shifts = Shift::all()->keyBy('key');

        $workedHours = 0;
        $calculatedSalary = 0;
        $eligibleMealShifts = 0;
        $breakdown = [
            'regular_hours' => 0,
            'ot_hours' => 0,
            'regular_shifts' => 0,
            'ot_shifts' => 0,
            'morning_shifts' => 0,
            'afternoon_shifts' => 0,
        ];

        foreach ($schedules as $schedule) {
            $date = $schedule->date->format('Y-m-d');
            $attendance = $attendanceMap->get($date . '_' . $schedule->shift);
            $shift = $shifts->get($schedule->shift);

            if (!$attendance || !$shift || !$attendance->check_in_at || !$attendance->check_out_at) {
                continue;
            }

            $hours = $this->calculatePayableHoursForSchedule($schedule, $attendance, $shift);
            if ($hours <= 0) {
                continue;
            }

            $isSpecialPayShift = $schedule->is_ot || $schedule->is_holiday;
            $multiplier = $isSpecialPayShift ? (float) ($schedule->ot_multiplier ?: 2) : 1;
            $workedHours += $hours;
            $calculatedSalary += (int) round($hours * $hourlyRate * $multiplier);
            if ($hours >= 7) {
                $eligibleMealShifts++;
            }

            if ($isSpecialPayShift) {
                $breakdown['ot_hours'] += $hours;
                $breakdown['ot_shifts']++;
            } else {
                $breakdown['regular_hours'] += $hours;
                $breakdown['regular_shifts']++;
            }

            if (str_contains(strtolower($shift->name), 'sáng') || str_contains($schedule->shift, 'morning')) {
                $breakdown['morning_shifts']++;
            }
            if (str_contains(strtolower($shift->name), 'chiều') || str_contains($schedule->shift, 'afternoon')) {
                $breakdown['afternoon_shifts']++;
            }
        }

        $mealAllowance = $eligibleMealShifts * 30000;
        $bonusDetails = $mealAllowance > 0
            ? [['label' => '[AUTO] Phụ cấp ăn ca (' . $eligibleMealShifts . ' ca)', 'amount' => $mealAllowance]]
            : [];

        foreach ($breakdown as $key => $value) {
            $breakdown[$key] = is_float($value) ? round($value, 2) : $value;
        }

        return [
            'worked_hours' => round($workedHours, 2),
            'calculated_salary' => $calculatedSalary,
            'bonus' => $mealAllowance,
            'bonus_details' => $bonusDetails,
            'shift_breakdown' => $breakdown,
        ];
    }

    private function calculatePayableHoursForSchedule(StaffSchedule $schedule, Attendance $attendance, Shift $shift): float
    {
        if (!$attendance->check_in_at || !$attendance->check_out_at) {
            return 0;
        }

        $date = $schedule->date->format('Y-m-d');
        $shiftStart = Carbon::parse($date . ' ' . $shift->start_time, 'Asia/Ho_Chi_Minh');
        $shiftEnd = Carbon::parse($date . ' ' . $shift->end_time, 'Asia/Ho_Chi_Minh');

        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd->addDay();
        }

        $checkIn = Carbon::parse($attendance->check_in_at);
        $checkOut = Carbon::parse($attendance->check_out_at);

        $payStart = $checkIn->greaterThan($shiftStart) ? $checkIn : $shiftStart;
        $payEnd = $checkOut->lessThan($shiftEnd) ? $checkOut : $shiftEnd;

        if ($payEnd->lessThanOrEqualTo($payStart)) {
            return 0;
        }

        return round($payStart->diffInMinutes($payEnd) / 60, 2);
    }

    private function mergeAutoBonusDetails(array $autoDetails, array $details): array
    {
        $manualDetails = collect($details)
            ->filter(fn($item) => !str_starts_with((string) ($item['label'] ?? ''), '[AUTO]'))
            ->values()
            ->all();

        return array_values(array_merge($autoDetails, $manualDetails));
    }

    private function normalizePayrollDeductions(Request $request, array $details): array
    {
        if (empty($details)) {
            return [];
        }

        $ruleIds = collect($details)
            ->pluck('penalty_rule_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();
        $rules = PenaltyRule::whereIn('id', $ruleIds)->where('type', 'penalty')->get()->keyBy('id');

        return collect($details)
            ->map(function ($item, $index) use ($request, $rules) {
                $ruleId = (int) ($item['penalty_rule_id'] ?? 0);
                $rule = $rules->get($ruleId);

                if (!$rule) {
                    return null;
                }

                $evidencePath = $item['evidence_path'] ?? null;
                $file = $request->file("deduction_evidences.{$index}");
                if ($file) {
                    if ($evidencePath) {
                        Storage::disk('public')->delete($evidencePath);
                    }
                    $evidencePath = $file->store('payroll-penalties', 'public');
                }

                return [
                    'penalty_rule_id' => $rule->id,
                    'label' => $rule->name,
                    'unit_amount' => (int) $rule->amount,
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'amount' => (int) $rule->amount * max(1, (int) ($item['quantity'] ?? 1)),
                    'reason' => trim((string) ($item['reason'] ?? '')),
                    'evidence_path' => $evidencePath,
                    'evidence_name' => $file ? $file->getClientOriginalName() : ($item['evidence_name'] ?? null),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizePayrollRuleDetails(array $details, string $type): array
    {
        if (empty($details)) {
            return [];
        }

        $ruleIds = collect($details)
            ->pluck('rule_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();
        $rules = PenaltyRule::whereIn('id', $ruleIds)->where('type', $type)->get()->keyBy('id');

        return collect($details)
            ->map(function ($item) use ($rules) {
                $ruleId = (int) ($item['rule_id'] ?? 0);
                $rule = $rules->get($ruleId);

                if (!$rule) {
                    return null;
                }

                $quantity = max(1, (int) ($item['quantity'] ?? 1));

                return [
                    'rule_id' => $rule->id,
                    'label' => $rule->name,
                    'unit_amount' => (int) $rule->amount,
                    'quantity' => $quantity,
                    'amount' => (int) $rule->amount * $quantity,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeStoredPayrollDeductions(array $details): array
    {
        if (empty($details)) {
            return [];
        }

        $ruleIds = collect($details)
            ->pluck('penalty_rule_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();
        $rules = PenaltyRule::whereIn('id', $ruleIds)->where('type', 'penalty')->get()->keyBy('id');

        return collect($details)
            ->map(function ($item) use ($rules) {
                $ruleId = (int) ($item['penalty_rule_id'] ?? 0);
                $rule = $rules->get($ruleId);

                if (!$rule) {
                    return null;
                }

                $quantity = max(1, (int) ($item['quantity'] ?? 1));

                return [
                    'penalty_rule_id' => $rule->id,
                    'label' => $rule->name,
                    'unit_amount' => (int) $rule->amount,
                    'quantity' => $quantity,
                    'amount' => (int) $rule->amount * $quantity,
                    'reason' => trim((string) ($item['reason'] ?? '')),
                    'evidence_path' => $item['evidence_path'] ?? null,
                    'evidence_name' => $item['evidence_name'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
