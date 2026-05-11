<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\Payroll;
use App\Models\Shift;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

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
            ->whereIn('role', ['admin', 'staff'])
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
        if ($user && $user->role === 'staff') {
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
        }

        $totalStaff = User::where('role', 'staff')->count();
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
        $staff = User::whereIn('role', ['admin', 'staff'])
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
            'role'        => 'required|in:admin,staff',
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
            'role'        => 'required|in:admin,staff',
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
            'schedules.*.shift'    => 'required|in:morning,afternoon',
            'schedules.*.note'     => 'nullable|string',
        ]);

        $saved = [];
        foreach ($request->schedules as $item) {
            $schedule = StaffSchedule::updateOrCreate(
                ['staff_id' => $item['staff_id'], 'date' => $item['date'], 'shift' => $item['shift']],
                ['status' => 'scheduled', 'note' => $item['note'] ?? null]
            );
            $saved[] = $schedule;
        }

        return response()->json(['message' => 'Lưu lịch thành công', 'schedules' => $saved]);
    }

    public function deleteSchedule($id)
    {
        $schedule = StaffSchedule::findOrFail($id);
        $schedule->delete();
        return response()->json(['message' => 'Đã xóa lịch']);
    }

    // ─── Payroll ──────────────────────────────────────────────────────────────

    public function getPayroll(Request $request)
    {
        $month = $request->query('month', now()->month);
        $year  = $request->query('year', now()->year);

        $staff = User::whereIn('role', ['admin', 'staff'])->get();

        $result = $staff->map(function ($u) use ($month, $year) {
            $payroll = Payroll::where('staff_id', $u->id)
                ->where('month', $month)->where('year', $year)->first();

            // Tính số giờ làm từ bảng attendance
            $attendances = Attendance::where('staff_id', $u->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->whereNotNull('check_in_at')
                ->whereNotNull('check_out_at')
                ->get();

            $workedHours = 0;
            foreach ($attendances as $att) {
                $checkIn = Carbon::parse($att->check_in_at);
                $checkOut = Carbon::parse($att->check_out_at);
                // Làm tròn đến 2 chữ số thập phân
                $workedHours += round(abs($checkOut->diffInMinutes($checkIn, false)) / 60, 2);
            }

            return [
                'staff_id'     => $u->id,
                'name'         => $u->name,
                'role'         => $u->role,
                'image_url'    => $u->image_url,
                'hourly_rate'  => $u->hourly_rate,
                'worked_hours' => $workedHours,
                'payroll'      => $payroll,
            ];
        });

        return response()->json($result);
    }

    public function savePayroll(Request $request)
    {
        $request->validate([
            'staff_id'     => 'required|exists:users,id',
            'month'        => 'required|integer|between:1,12',
            'year'         => 'required|integer',
            'hourly_rate'  => 'required|integer|min:0',
            'worked_hours' => 'required|numeric|min:0',
            'bonus'        => 'nullable|integer|min:0',
            'deduction'    => 'nullable|integer|min:0',
            'note'         => 'nullable|string',
            'bonus_details' => 'nullable|array',
            'deduction_details' => 'nullable|array',
            'is_settled' => 'boolean',
        ]);

        $hourly    = (int) $request->hourly_rate;
        $hours     = (float) $request->worked_hours;
        $calcBase  = (int) ($hourly * $hours);
        $bonus     = (int) ($request->bonus ?? 0);
        $deduction = (int) ($request->deduction ?? 0);
        $total     = max(0, $calcBase + $bonus - $deduction);

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
                'bonus_details'     => $request->bonus_details,
                'deduction_details' => $request->deduction_details,
                'is_settled'        => $request->is_settled ?? false,
            ]
        );

        return response()->json(['message' => 'Lưu bảng lương thành công', 'payroll' => $payroll]);
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

        foreach ($request->shifts as $item) {
            Shift::where('key', $item['key'])->update([
                'name'       => $item['name'],
                'start_time' => $item['start_time'],
                'end_time'   => $item['end_time'],
                'color'      => $item['color'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Cập nhật ca làm thành công', 'shifts' => Shift::all()]);
    }
}
