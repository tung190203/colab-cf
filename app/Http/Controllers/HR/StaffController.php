<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\StaffSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StaffController extends Controller
{
    // ─── Attendance ───────────────────────────────────────────────────────────

    public function checkIn(Request $request)
    {
        $request->validate([
            'shift' => 'required|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $user = $request->user();
        $today = Carbon::today()->toDateString();
        $shiftKey = $request->shift;

        // GPS Check
        $locationError = $this->verifyLocation($request->lat, $request->lng);
        if ($locationError) {
            return response()->json(['message' => $locationError], 422);
        }

        // 1. Kiểm tra có được phân ca không
        $schedule = StaffSchedule::where('staff_id', $user->id)
            ->where('date', $today)
            ->where('shift', $shiftKey)
            ->first();

        if (!$schedule) {
            return response()->json(['message' => 'Bạn không được phân ca này hôm nay!'], 403);
        }

        // 2. Kiểm tra giờ check-in
        $shiftInfo = \App\Models\Shift::where('key', $shiftKey)->first();
        if ($shiftInfo) {
            $nowTime = now();
            // Cho phép check-in sớm 60 phút
            $allowStart = Carbon::parse($today . ' ' . $shiftInfo->start_time)->subMinutes(60);
            $allowEnd = Carbon::parse($today . ' ' . $shiftInfo->end_time);

            if ($nowTime->lt($allowStart)) {
                return response()->json(['message' => 'Chưa đến giờ check-in ca này (chỉ được check-in trước 60 phút)!'], 422);
            }
            if ($nowTime->gt($allowEnd)) {
                return response()->json(['message' => 'Đã qua giờ ca này, không thể check-in nữa!'], 422);
            }
        }

        $attendance = Attendance::firstOrCreate(
            ['staff_id' => $user->id, 'date' => $today, 'shift' => $shiftKey],
            ['check_in_at' => null, 'check_out_at' => null]
        );

        if ($attendance->check_in_at) {
            return response()->json(['message' => 'Bạn đã check-in ca này rồi', 'attendance' => $attendance], 422);
        }

        $attendance->check_in_at = now();
        $attendance->save();

        return response()->json(['message' => 'Check-in thành công', 'attendance' => $attendance]);
    }

    public function checkOut(Request $request)
    {
        $request->validate([
            'shift' => 'required|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $user = $request->user();
        $today = Carbon::today()->toDateString();
        $shiftKey = $request->shift;

        // GPS Check
        $locationError = $this->verifyLocation($request->lat, $request->lng);
        if ($locationError) {
            return response()->json(['message' => $locationError], 422);
        }

        $attendance = Attendance::where('staff_id', $user->id)
            ->where('date', $today)
            ->where('shift', $shiftKey)
            ->first();

        if (!$attendance || !$attendance->check_in_at) {
            return response()->json(['message' => 'Bạn chưa check-in ca này'], 422);
        }

        if ($attendance->check_out_at) {
            return response()->json(['message' => 'Bạn đã check-out ca này rồi', 'attendance' => $attendance], 422);
        }

        $attendance->check_out_at = now();
        $attendance->save();

        return response()->json(['message' => 'Check-out thành công', 'attendance' => $attendance]);
    }

    public function getMyAttendance(Request $request)
    {
        $user  = $request->user();
        $month = $request->query('month', now()->month);
        $year  = $request->query('year', now()->year);

        // Lấy tất cả lịch làm việc trong tháng
        $schedules = StaffSchedule::where('staff_id', $user->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderByDesc('date')
            ->get();

        // Lấy dữ liệu điểm danh
        $attendances = Attendance::where('staff_id', $user->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->keyBy(function($item) {
                return $item->date->format('Y-m-d') . '_' . $item->shift;
            });

        $result = [];
        $today = now()->format('Y-m-d');
        
        foreach ($schedules as $sched) {
            $dateStr = $sched->date->format('Y-m-d');
            
            // Nếu là ngày trong tương lai (chưa đến giờ làm), không hiển thị trong lịch sử
            if ($dateStr > $today) continue;

            $key = $dateStr . '_' . $sched->shift;
            $att = $attendances->get($key);

            $result[] = [
                'id' => $sched->id . '_hist',
                'date' => $dateStr,
                'shift' => $sched->shift,
                'check_in_at' => $att ? $att->check_in_at : null,
                'check_out_at' => $att ? $att->check_out_at : null,
            ];
        }

        return response()->json($result);
    }

    // ─── Schedule ─────────────────────────────────────────────────────────────

    public function getMySchedule(Request $request)
    {
        $user = $request->user();
        $from = $request->query('from', Carbon::now()->startOfWeek()->toDateString());
        $to   = $request->query('to', Carbon::now()->endOfWeek()->toDateString());

        $schedules = StaffSchedule::where('staff_id', $user->id)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        return response()->json($schedules);
    }

    // ─── Payroll ──────────────────────────────────────────────────────────────

    public function getMyPayroll(Request $request)
    {
        $user  = $request->user();
        $month = $request->query('month', now()->month);
        $year  = $request->query('year', now()->year);

        $payroll = Payroll::where('staff_id', $user->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($payroll && !$payroll->is_settled) {
            $payroll = null; // Ẩn hoàn toàn với nhân viên nếu chưa duyệt
        }

        // Tính số giờ làm từ bảng attendance
        $attendances = Attendance::where('staff_id', $user->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->whereNotNull('check_in_at')
            ->whereNotNull('check_out_at')
            ->get();

        $workedHours = 0;
        foreach ($attendances as $att) {
            $checkIn = Carbon::parse($att->check_in_at);
            $checkOut = Carbon::parse($att->check_out_at);
            $workedHours += round(abs($checkOut->diffInMinutes($checkIn, false)) / 60, 2);
        }
        $workedHours = round($workedHours, 2);

        return response()->json([
            'payroll'      => $payroll,
            'worked_hours' => $workedHours,
        ]);
    }

    public function getMyPayrollHistory(Request $request)
    {
        $user = $request->user();

        $payrolls = Payroll::where('staff_id', $user->id)
            ->where('is_settled', true)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return response()->json($payrolls);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // mét
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }

    private function verifyLocation($lat, $lng)
    {
        // Tọa độ quán (Ví dụ: Tòa nhà Landmark 81)
        // Bạn hãy thay thế bằng tọa độ thực tế của quán bạn
        $shopLat = 21.03558768528232;
        $shopLng = 105.81652468074789;

        if (!$lat || !$lng) {
            return 'Bạn cần cho phép truy cập vị trí để chấm công.';
        }

        $distance = $this->calculateDistance($lat, $lng, $shopLat, $shopLng);
        if ($distance > 150) { // Giới hạn 150 mét cho sai số GPS
            return 'Bạn đang ở quá xa quán để có thể chấm công (Cách ' . round($distance) . 'm).';
        }

        return null;
    }

    public function smartCheckIn(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();
        $now = now();

        // GPS Check
        $locationError = $this->verifyLocation($request->lat, $request->lng);
        if ($locationError) {
            return response()->json(['message' => $locationError], 422);
        }

        // 1. Tìm tất cả các ca được phân hôm nay
        $schedules = StaffSchedule::where('staff_id', $user->id)
            ->where('date', $today)
            ->get();

        if ($schedules->isEmpty()) {
            return response()->json(['message' => 'Bạn không có ca làm việc nào được phân trong hôm nay!'], 403);
        }

        $potentialSchedules = [];

        foreach ($schedules as $schedule) {
            $shiftInfo = \App\Models\Shift::where('key', $schedule->shift)->first();
            if (!$shiftInfo) continue;

            $allowStart = Carbon::parse($today . ' ' . $shiftInfo->start_time)->subMinutes(60);
            $allowEnd = Carbon::parse($today . ' ' . $shiftInfo->end_time);

            if ($now->between($allowStart, $allowEnd)) {
                $attendance = Attendance::where('staff_id', $user->id)
                    ->where('date', $today)
                    ->where('shift', $schedule->shift)
                    ->first();

                $potentialSchedules[] = [
                    'schedule' => $schedule,
                    'shiftInfo' => $shiftInfo,
                    'attendance' => $attendance
                ];
            }
        }

        if (empty($potentialSchedules)) {
            return response()->json(['message' => 'Hiện tại không có ca làm việc nào của bạn đang diễn ra hoặc sắp bắt đầu.'], 422);
        }

        // ƯU TIÊN 1: Tìm ca nào đã check-in nhưng CHƯA check-out (để thực hiện check-out)
        foreach ($potentialSchedules as $item) {
            if ($item['attendance'] && $item['attendance']->check_in_at && !$item['attendance']->check_out_at) {
                $attendance = $item['attendance'];
                $attendance->check_out_at = $now;
                $attendance->save();

                return response()->json([
                    'message' => 'Check-out thành công ca ' . $item['shiftInfo']->name,
                    'type' => 'check-out',
                    'shift' => $item['shiftInfo']->name,
                    'time' => $now->format('H:i')
                ]);
            }
        }

        // ƯU TIÊN 2: Tìm ca nào CHƯA check-in (để thực hiện check-in)
        foreach ($potentialSchedules as $item) {
            if (!$item['attendance'] || !$item['attendance']->check_in_at) {
                $attendance = Attendance::updateOrCreate(
                    ['staff_id' => $user->id, 'date' => $today, 'shift' => $item['schedule']->shift],
                    ['check_in_at' => $now]
                );

                return response()->json([
                    'message' => 'Check-in thành công ca ' . $item['shiftInfo']->name,
                    'type' => 'check-in',
                    'shift' => $item['shiftInfo']->name,
                    'time' => $now->format('H:i')
                ]);
            }
        }

        // Nếu tất cả các ca trong khung giờ đều đã hoàn thành
        return response()->json(['message' => 'Bạn đã hoàn thành việc chấm công cho các ca trong khung giờ này.'], 422);
    }
}
