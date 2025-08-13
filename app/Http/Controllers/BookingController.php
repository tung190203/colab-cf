<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Table;
use App\Models\Package;
use App\Models\Extra;
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
        ]);

        $startTime = isset($validated['start_time']) 
        ? Carbon::parse($validated['start_time']) 
        : now();

        if($validated['table']) {
            $selectedTable = Table::where('code', $validated['table'])->first();
            $tableId = $selectedTable->id;
            // Kiểm tra trùng lịch
            $conflict = Booking::where('table_id', $tableId)
                ->where('status', ['confirmed','pending'])
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
            ]);

            if (!empty($serviceQuantities)) {
                $booking->extras()->attach($serviceQuantities);
            }

            if ($validated['payment_method'] !== 'momo' && $validated['payment_method'] !== 'transfer') {
                $selectedTable->status = 'occupied';
                $selectedTable->save();
                if($selectedTable->code == 'C1') {
                    $listTableOccupied = Table::whereIn('code', ['C2', 'C3'])->get();
                    foreach ($listTableOccupied as $table) {
                        $table->status = 'occupied';
                        $table->save();
                    }
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
            $booking->table->status = 'occupied';
            $booking->table->save();
            if($booking->table->code == 'C1') {
                $listTableOccupied = Table::whereIn('code', ['C2', 'C3'])->get();
                foreach ($listTableOccupied as $table) {
                    $table->status = 'occupied';
                    $table->save();
                }
            }
        } else {
            $booking->status = 'cancelled';
        }

        $booking->save();

        return response('OK', 200);
    }

    public function extras()
    {
        $extras = Extra::select('id', 'category', 'name', 'price')
            ->orderByRaw("CASE
                WHEN category IN ('prints', 'rooms') THEN 1
                ELSE 0
            END, category")
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
                        'status' => $item->status
                    ];
                })->values();
            });
        return response()->json($tables);
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
        if($booking->table->code == 'C1') {
            $listTableOccupied = Table::whereIn('code', ['C2', 'C3'])->get();
            foreach ($listTableOccupied as $table) {
                $table->status = 'occupied';
                $table->save();
            }
        }

        return response()->json([
            'message' => 'Ảnh xác nhận đã được lưu',
            'path' => $path
        ]);
    }
}
