<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\SpotCheckItem;
use App\Models\SpotCheckLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SpotCheckController extends Controller
{
    public function currentShift(Request $request)
    {
        $now = now();
        $today = $now->toDateString();

        // Xác định ca hiện tại
        $shifts = Shift::all();
        $currentShift = null;
        
        foreach ($shifts as $shift) {
            $start = Carbon::parse($today . ' ' . $shift->start_time)->subMinutes(60);
            $end = Carbon::parse($today . ' ' . $shift->end_time);
            if ($now->between($start, $end)) {
                $currentShift = $shift;
                break;
            }
        }

        if (!$currentShift) {
            return response()->json(['message' => 'Không có ca nào đang diễn ra'], 422);
        }

        // Lấy danh sách nhân viên đang check-in
        $attendances = Attendance::with('staff:id,name')
            ->where('date', $today)
            ->where('shift', $currentShift->key)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->get();

        $staff = $attendances->map(function ($att) {
            return [
                'id' => $att->staff_id,
                'name' => $att->staff?->name,
                'inTime' => Carbon::parse($att->check_in_at)->format('H:i'),
                'init' => mb_substr($att->staff?->name ?? '?', 0, 1),
            ];
        });

        return response()->json([
            'shift' => [
                'name' => $currentShift->name,
                'key' => $currentShift->key,
                'time_range' => Carbon::parse($currentShift->start_time)->format('H:i') . ' – ' . Carbon::parse($currentShift->end_time)->format('H:i'),
            ],
            'staff' => $staff,
        ]);
    }

    public function items()
    {
        $items = SpotCheckItem::where('active', true)->orderBy('order')->orderBy('group')->get();
        return response()->json(['items' => $items]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'shift_key' => 'required|string',
            'staff_ids' => 'required|array',
            'items_checked' => 'required|array',
            'items_checked.*.id' => 'required|exists:spot_check_items,id',
            'items_checked.*.result' => 'required|in:pass,fail',
            'items_checked.*.note' => 'nullable|string',
            'items_checked.*.photos' => 'nullable|array',
        ]);

        $now = now();
        $shiftInfo = Shift::where('key', $validated['shift_key'])->first();
        
        $totalChecked = count($validated['items_checked']);
        if ($totalChecked === 0) {
            return response()->json(['message' => 'Phải kiểm tra ít nhất 1 mục'], 422);
        }

        $totalPassed = collect($validated['items_checked'])->where('result', 'pass')->count();
        $score = (int) round(($totalPassed / $totalChecked) * 100);

        $shiftType = (str_contains(strtolower($validated['shift_key']), 'sang') || str_contains(strtolower($validated['shift_key']), 'morning')) ? 'sang' : 'chieu';

        $log = SpotCheckLog::create([
            'date' => $now->toDateString(),
            'shift_type' => $shiftType,
            'shift_start' => $shiftInfo ? Carbon::parse($now->toDateString() . ' ' . $shiftInfo->start_time) : null,
            'checked_at' => $now,
            'checker_id' => $request->user()->id,
            'checker_role' => $request->user()->role,
            'staff_ids' => $validated['staff_ids'],
            'items_checked' => $validated['items_checked'],
            'total_checked' => $totalChecked,
            'total_passed' => $totalPassed,
            'score' => $score,
            'is_locked' => true,
        ]);

        return response()->json(['message' => 'Lưu Spot Check thành công', 'log' => $log]);
    }

    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:5120', // max 5MB
        ]);

        $path = $request->file('photo')->store('spot_check_photos', 'public');
        return response()->json(['url' => asset('storage/' . $path)]);
    }

    public function history(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $query = SpotCheckLog::with('checker:id,name')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderByDesc('checked_at');

        if ($request->filled('employee')) {
            $query->whereJsonContains('staff_ids', (int) $request->employee);
        }

        $logs = $query->paginate(20);

        // Lấy tên staff
        $allStaffIds = collect($logs->items())->pluck('staff_ids')->flatten()->unique();
        $users = User::whereIn('id', $allStaffIds)->pluck('name', 'id');

        $logs->getCollection()->transform(function ($log) use ($users) {
            $staffNames = collect($log->staff_ids)->map(fn($id) => $users->get($id, 'Unknown'))->toArray();
            return [
                'id' => $log->id,
                'date' => Carbon::parse($log->date)->format('d/m/Y'),
                'time' => Carbon::parse($log->checked_at)->format('H:i'),
                'checker' => $log->checker?->name,
                'shift' => $log->shift_type === 'sang' ? 'Sáng' : 'Chiều',
                'staff' => $staffNames,
                'passed' => $log->total_passed,
                'total' => $log->total_checked,
                'score' => $log->score,
                'items_checked' => $log->items_checked,
            ];
        });

        return response()->json(['history' => $logs]);
    }

    public function summary(Request $request, $month)
    {
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $logs = SpotCheckLog::whereBetween('date', [$startDate, $endDate])->get();
        
        $staffStats = [];
        $totalGroupScore = 0;
        $totalGroupChecks = 0;

        foreach ($logs as $log) {
            // Tính trung bình nhóm (loại trừ ca chỉ có nhân viên thử việc - assume có function filter, tạm thời tính hết)
            $totalGroupScore += $log->score;
            $totalGroupChecks++;

            foreach ($log->staff_ids as $staffId) {
                if (!isset($staffStats[$staffId])) {
                    $staffStats[$staffId] = [
                        'employee_id' => $staffId,
                        'check_count' => 0,
                        'total_score' => 0,
                        'all_perfect' => true,
                    ];
                }
                
                $staffStats[$staffId]['check_count']++;
                $staffStats[$staffId]['total_score'] += $log->score;
                if ($log->score < 100) {
                    $staffStats[$staffId]['all_perfect'] = false;
                }
            }
        }

        $users = User::whereIn('id', array_keys($staffStats))->get(['id', 'name']);
        
        $summary = collect($staffStats)->map(function ($stat) use ($users) {
            $user = $users->firstWhere('id', $stat['employee_id']);
            $avg = $stat['check_count'] > 0 ? round($stat['total_score'] / $stat['check_count']) : 0;
            return [
                'employee_id' => $stat['employee_id'],
                'name' => $user?->name,
                'init' => mb_substr($user?->name ?? '?', 0, 1),
                'check_count' => $stat['check_count'],
                'avg_score' => $avg,
                'bonus_eligible' => $stat['all_perfect'] && $stat['check_count'] >= 4,
            ];
        })->values()->sortByDesc('avg_score')->all();

        return response()->json([
            'group_avg' => $totalGroupChecks > 0 ? round($totalGroupScore / $totalGroupChecks) : 0,
            'total_checks' => $totalGroupChecks,
            'staff_summary' => $summary,
        ]);
    }
}
