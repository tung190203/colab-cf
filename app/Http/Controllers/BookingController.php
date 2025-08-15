<?php

namespace App\Http\Controllers;

use App\Events\NewBookingCreated;
use App\Models\Booking;
use App\Models\Table;
use App\Models\Package;
use App\Models\Extra;
use App\Models\User;
use App\Models\VipCard;
use App\Services\MomoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function store(Request $request, MomoService $momo)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'table' => 'nullable|exists:tables,code',
            'start_time' => 'nullable|date',
            'end_time' => 'required|date|after:now',
            'extras' => 'array',
            'extras.*.id' => 'exists:extras,id',
            'extras.*.quantity' => 'integer|min:1',
            'payment_method' => 'required|in:momo,card,none,cash,transfer',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:15',
        ]);

        $startTime = isset($validated['start_time'])
            ? Carbon::parse($validated['start_time'])
            : now();

        if ($validated['table']) {
            $selectedTable = Table::where('code', $validated['table'])->first();
            $tableId = $selectedTable->id;
            // Kiểm tra trùng lịch
            $conflict = Booking::where('table_id', $tableId)
                ->where('status', ['confirmed', 'pending'])
                ->where(function ($query) use ($startTime, $validated) {
                    $query->whereBetween('start_time', [$startTime, $validated['end_time']])
                        ->orWhereBetween('end_time', [$startTime, $validated['end_time']])
                        ->orWhere(function ($q) use ($startTime, $validated) {
                            $q->where('start_time', '<=', $startTime)
                                ->where('end_time', '>=', $validated['end_time']);
                        });
                })->exists();

            if ($conflict) {
                return response()->json(['message' => 'Bàn đã được đặt trong khoảng thời gian này'], 422);
            }
        }

        // Tính tổng tiền
        $package = Package::find($validated['package_id']);
        $total = $package->price;
        $serviceQuantities = [];

        if (!empty($validated['extras'])) {
            foreach ($validated['extras'] as $srv) {
                $extra = Extra::find($srv['id']);
                if ($extra) {
                    $total += $extra->price * $srv['quantity'];
                    $serviceQuantities[$srv['id']] = ['quantity' => $srv['quantity']];
                }
            }
        }

        // Xác định status ban đầu
        $status = '';
        switch ($validated['payment_method']) {
            case 'momo':
                $status = 'pending';
                break;
            case 'cash':
                $status = 'confirmed';
                break;
            case 'transfer':
                $status = 'pending';
                break;
            default:
                $status = 'pending';
        }
        // Tạo booking
        DB::beginTransaction();
        try {
            $booking = Booking::create([
                'package_id'     => $validated['package_id'],
                'table_id'       => $tableId,
                'start_time'     => $startTime,
                'end_time'       => Carbon::parse($validated['end_time'])->format('Y-m-d H:i:s'),
                'total_price'    => $total,
                'payment_method' => $validated['payment_method'],
                'status'         => $status,
                'full_name'     => $validated['customer_name'],
                'phone'         => $validated['customer_phone'],
            ]);

            if (!empty($serviceQuantities)) {
                $booking->extras()->attach($serviceQuantities);
            }

            if ($validated['payment_method'] !== 'momo' && $validated['payment_method'] !== 'transfer') {
                $booking->load('package', 'table', 'extras');
                $today = Carbon::today();
                if (Carbon::parse($booking->start_time)->isSameDay($today)) {
                    broadcast(new NewBookingCreated($booking))->toOthers();
                }
            }

            DB::commit();

            if ($validated['payment_method'] === 'momo') {
                return $this->handleMomoPayment($booking, $package, $total, $momo);
            }

            return response()->json([
                'message' => 'Đặt bàn thành công',
                'booking' => $booking->load('package', 'table', 'extras'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi hệ thống, vui lòng thử lại sau'], 500);
        }
    }

    private function handleMomoPayment($booking, $package, $total, MomoService $momo)
    {
        $orderId = 'booking_' . $booking->id;
        $orderInfo = "Đặt bàn - " . $package->name;

        $momoResponse = $momo->createPayment($orderId, $total, $orderInfo);

        if (!$momoResponse || !isset($momoResponse['payUrl'])) {
            return response()->json(['error' => 'Lỗi khi tạo thanh toán Momo'], 500);
        }

        return response()->json([
            'payUrl' => $momoResponse['payUrl'],
            'booking_id' => $booking->id
        ]);
    }

    public function handleMomoCallback(Request $request)
    {
        $orderId = $request->input('orderId');
        $bookingId = str_replace('booking_', '', $orderId);

        $booking = Booking::find($bookingId);
        if (!$booking) {
            return response('Booking not found', 404);
        }

        if ($request->input('resultCode') == 0) {
            $booking->status = 'confirmed';
            $booking->load('package', 'table', 'extras');
            $today = Carbon::today();

            if (Carbon::parse($booking->start_time)->isSameDay($today)) {
                broadcast(new NewBookingCreated($booking))->toOthers();
            }
        } else {
            $booking->status = 'cancelled';
        }

        $booking->save();

        return response('OK', 200);
    }

    public function extras()
    {
        $categories = ['services'];
        $extras = Extra::select('id', 'category', 'name', 'price')
            ->whereNotIn('category', $categories)
            ->get()
            ->groupBy('category')
            ->map(function ($items) {
                return $items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'price' => $item->price
                    ];
                })->values();
            });

        return response()->json($extras);
    }

    public function packages()
    {
        $packages = Package::get()
            ->groupBy('category')
            ->map(function ($items) {
                return $items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'price' => $item->price,
                        'category' => $item->category,
                        'duration' => $item->duration,
                        'duration_label' => $item->duration_label,
                    ];
                })->values();
            });
        return response()->json($packages);
    }

    public function tables()
    {
        $tables = Table::select('id', 'code', 'category', 'status')
            ->get()
            ->groupBy('category')
            ->map(function ($items) {
                return $items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'code' => $item->code,
                    ];
                })->values();
            });
        return response()->json($tables);
    }

    public function checkTableAvailability(Request $request)
    {
        $tableId   = $request->table_id;
        $startTime = $request->start_time;
        $endTime   = $request->end_time;

        $table = Table::findOrFail($tableId);
        $conflictMap = [
            'C1' => ['C1', 'C2', 'C3'],
            'C2' => ['C1', 'C2'],
            'C3' => ['C1', 'C3'],
        ];

        if (array_key_exists($table->code, $conflictMap)) {
            $tableIdsToCheck = Table::whereIn('code', $conflictMap[$table->code])->pluck('id');
        } else {
            $tableIdsToCheck = [$tableId];
        }

        $hasConflict = Booking::whereIn('table_id', $tableIdsToCheck)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            })
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'success' => false,
                'message' => 'Bàn này hoặc bàn liên quan đã được đặt trong thời gian bạn chọn. Vui lòng chọn lại.'
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đặt bàn thành công.',
        ]);
    }

    public function getVietQR(Booking $booking)
    {
        $bankCode = config('bank.bank_code');
        $accountNumber = config('bank.bank_account_number');
        $amount = $booking->total_price;
        $paymentNote = "Đặt bàn - {$booking->package->name}";

        $qrCodeUrl = "https://img.vietqr.io/image/{$bankCode}-{$accountNumber}-compact2.png?amount={$amount}&addInfo=" . urlencode($paymentNote);

        return response()->json([
            'qrCodeUrl' => $qrCodeUrl,
        ]);
    }

    public function uploadProof(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'proof' => 'required|image|max:5120', // 5MB
        ]);

        $path = $request->file('proof')->store('proofs', 'public');

        $booking = Booking::find($request->booking_id);
        $booking->proof_image = $path;
        $booking->status = 'confirmed';
        $booking->table->status = 'occupied';
        $booking->save();
        if ($booking->table->code == 'C1') {
            $listTableOccupied = Table::whereIn('code', ['C2', 'C3'])->get();
            foreach ($listTableOccupied as $table) {
                $table->status = 'occupied';
                $table->save();
            }
        }
        $booking->load('package', 'table', 'extras');
        broadcast(new NewBookingCreated($booking))->toOthers();

        return response()->json([
            'message' => 'Ảnh xác nhận đã được lưu',
            'path' => $path
        ]);
    }

    public function findUserByCard(Request $request)
    {
        $request->validate([
            'cardParam' => 'required|string|max:15',
        ]);

        // $user = User::where('phone', $request->phoneParam)->first();
        $card = VipCard::where('card_number', $request->cardParam)->first();
        if (!$card) {
            return response()->json(['message' => 'Không tìm thấy thẻ VIP'], 404);
        }
        $user = User::find($card->user_id);

        return response()->json(['user' => $user, 'card' => $card]);
    }

    public function findUserByPhone(Request $request)
    {
        $request->validate([
            'phoneParam' => 'required|string|max:15',
        ]);

        $user = User::where('phone', $request->phoneParam)->first();
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng'], 404);
        }

        return response()->json(['user' => $user]);
    }

    public function getListBookings(Request $request)
    {
        $now = Carbon::now();

        $todayBookings = Booking::with('package', 'table', 'extras')
            ->where('status', 'confirmed')
            ->where('is_served', false)
            ->whereDate('start_time', $now->toDateString())
            ->get();
        $future = $todayBookings->filter(fn($b) => Carbon::parse($b->start_time)->gte($now))
            ->sortBy('start_time')
            ->values();
        $past = $todayBookings->filter(fn($b) => Carbon::parse($b->start_time)->lt($now))
            ->sortBy('start_time')
            ->values();
        $sorted = $future->merge($past);

        return response()->json(['bookings' => $sorted]);
    }

    public function addMember(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'image' => 'nullable|image|max:2048',
            'role' => 'required|string',
            'note' => 'nullable|string',
        ]);
        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'role' => $request->role,
            'note' => $request->note,
        ]);
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('users', 'public');
            $user->image = $path;
            $user->save();
        }

        return response()->json([
            'message' => 'Thêm thành viên thành công',
            'user' => $user,
        ]);
    }

    public function markAsServed(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $booking = Booking::find($request->booking_id);
        if (!$booking) {
            return response()->json(['message' => 'Không tìm thấy đặt bàn'], 404);
        }

        $booking->is_served = true;
        $booking->save();

        return response()->json(['message' => 'Đánh dấu là đã phục vụ', 'booking' => $booking]);
    }

    public function getListMembers()
    {
        $members = User::all()->append('image_url');

        return response()->json($members);
    }

    public function deleteMember(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy thành viên'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'Xóa thành viên thành công']);
    }

    public function editMember(Request $request, $id)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'image' => 'nullable|image|max:2048',
            'role'  => 'required|string',
            'note'  => 'nullable|string',
        ]);
    
        $user = User::find($id);
    
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy thành viên'], 404);
        }
        $user->name  = $request->name;
        $user->phone = $request->phone;
        $user->role  = $request->role;
        $user->note  = $request->note;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('users', 'public');
            $user->image = $path;
        }

        $user->save();
    
        return response()->json([
            'message' => 'Cập nhật thành viên thành công',
            'user'    => $user->append('image_url'),
        ]);
    }
}
