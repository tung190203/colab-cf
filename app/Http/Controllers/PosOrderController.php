<?php

namespace App\Http\Controllers;

use App\Models\Extra;
use App\Models\PosOrder;
use App\Services\StockDeductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosOrderController extends Controller
{
    public function options()
    {
        return response()->json([
            'products' => $this->orderableProducts()->get(['id', 'name', 'category', 'price', 'sku']),
        ]);
    }

    public function index(Request $request)
    {
        $query = PosOrder::with(['items', 'creator:id,name'])->latest();

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        return response()->json([
            'orders' => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    public function store(Request $request, StockDeductionService $stockDeductionService)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,transfer,card,momo',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:extras,id',
            'items.*.quantity' => 'required|integer|min:1',
            'note' => 'nullable|string|max:2000',
        ]);

        $products = $this->orderableProducts()
            ->whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        if ($products->count() !== collect($validated['items'])->pluck('product_id')->unique()->count()) {
            return response()->json(['message' => 'Một số sản phẩm không hợp lệ để bán thủ công'], 422);
        }

        $order = DB::transaction(function () use ($request, $validated, $products, $stockDeductionService) {
            $orderCode = 'MANUAL-' . now()->format('YmdHis') . '-' . random_int(100, 999);
            $totalQuantity = 0;
            $totalAmount = 0;

            $order = PosOrder::create([
                'order_code' => $orderCode,
                'payment_method' => $validated['payment_method'],
                'total_quantity' => 0,
                'total_amount' => 0,
                'note' => $validated['note'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($validated['items'] as $item) {
                $product = $products[(int) $item['product_id']];
                $quantity = (int) $item['quantity'];
                $lineTotal = $quantity * (int) $product->price;
                $totalQuantity += $quantity;
                $totalAmount += $lineTotal;

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => (int) $product->price,
                    'line_total' => $lineTotal,
                ]);
            }

            $stockDeductionService->deductByOrder(
                'pos_' . $order->id,
                collect($validated['items'])->map(fn ($item) => [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                ])->all(),
                $request->user()?->id,
                'Trừ NVL theo đơn bán thủ công ' . $order->order_code
            );

            $order->update([
                'total_quantity' => $totalQuantity,
                'total_amount' => $totalAmount,
            ]);

            return $order->fresh(['items', 'creator:id,name']);
        });

        return response()->json([
            'message' => 'Đã ghi nhận đơn bán thủ công',
            'order' => $order,
        ], 201);
    }

    private function orderableProducts()
    {
        return Extra::query()
            ->where('status', true)
            ->whereNotIn(DB::raw('LOWER(category)'), [
                'services',
                'office_services',
                'other_services',
                'others_services',
                'office services',
                'other services',
                'others services',
            ])
            ->orderBy('category')
            ->orderBy('name');
    }
}
