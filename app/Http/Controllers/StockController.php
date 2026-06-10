<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\StockLog;
use App\Services\StockDeductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function import(Request $request)
    {
        $validated = $request->validate([
            'material_id' => 'required|exists:materials,id',
            'quantity' => 'required|numeric|min:0.001',
            'unit_price' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $result = DB::transaction(function () use ($request, $validated) {
            $material = Material::whereKey($validated['material_id'])->lockForUpdate()->firstOrFail();
            $stockBefore = $this->stockNumber($material->current_stock);
            $quantity = $this->stockNumber($validated['quantity']);
            $stockAfter = $this->stockNumber($stockBefore + $quantity);

            $material->update([
                'current_stock' => $stockAfter,
                'price_per_unit' => $validated['unit_price'] ?? $material->price_per_unit,
            ]);

            $log = StockLog::create([
                'material_id' => $material->id,
                'type' => 'import',
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'unit_price' => $validated['unit_price'] ?? null,
                'note' => $validated['note'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            return [
                'material' => $material->fresh(),
                'log' => $log->load('creator:id,name'),
            ];
        });

        return response()->json([
            'message' => 'Nhập kho thành công',
            ...$result,
        ], 201);
    }

    public function manualDeduct(Request $request)
    {
        $validated = $request->validate([
            'material_id' => 'required|exists:materials,id',
            'quantity' => 'required|numeric|min:0.001',
            'note' => 'required|string|max:2000',
        ]);

        $result = DB::transaction(function () use ($request, $validated) {
            $material = Material::whereKey($validated['material_id'])->lockForUpdate()->firstOrFail();
            $stockBefore = $this->stockNumber($material->current_stock);
            $quantity = $this->stockNumber($validated['quantity']);

            if ($quantity > $stockBefore) {
                abort(422, "Số lượng xuất không được lớn hơn tồn kho hiện tại ({$stockBefore} {$material->unit})");
            }

            $stockAfter = $this->stockNumber($stockBefore - $quantity);

            $material->update([
                'current_stock' => $stockAfter,
            ]);

            $log = StockLog::create([
                'material_id' => $material->id,
                'type' => 'manual_deduct',
                'quantity' => -$quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'note' => $validated['note'],
                'created_by' => $request->user()?->id,
            ]);

            return [
                'material' => $material->fresh(),
                'log' => $log->load('creator:id,name'),
            ];
        });

        return response()->json([
            'message' => 'Xuất kho thành công',
            ...$result,
        ], 201);
    }

    public function deductByOrder(Request $request, StockDeductionService $stockDeductionService)
    {
        $validated = $request->validate([
            'order_id' => 'required|string|max:120',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:extras,id',
            'items.*.quantity' => 'required|integer|min:1',
            'note' => 'nullable|string|max:2000',
        ]);

        $result = $stockDeductionService->deductByOrder(
            $validated['order_id'],
            $validated['items'],
            $request->user()?->id,
            $validated['note'] ?? null
        );

        return response()->json([
            'message' => $result['already_deducted']
                ? 'Đơn hàng này đã được trừ kho trước đó'
                : 'Trừ kho theo đơn hàng thành công',
            ...$result,
        ], 201);
    }

    public function logs(Request $request)
    {
        $validated = $request->validate([
            'material' => 'nullable|exists:materials,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = StockLog::with(['material:id,name,unit', 'creator:id,name'])->latest();

        if (!empty($validated['material'])) {
            $query->where('material_id', $validated['material']);
        }

        return response()->json([
            'logs' => $query->paginate($validated['per_page'] ?? 20),
        ]);
    }

    public function alerts()
    {
        $materials = Material::query()
            ->where('active', true)
            ->whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->orderBy('current_stock')
            ->orderBy('name')
            ->get()
            ->map(function (Material $material) {
                return [
                    'id' => $material->id,
                    'name' => $material->name,
                    'unit' => $material->unit,
                    'current_stock' => (float) $material->current_stock,
                    'low_stock_threshold' => (float) $material->low_stock_threshold,
                    'note' => $material->note,
                ];
            });

        return response()->json([
            'alerts' => $materials,
            'count' => $materials->count(),
        ]);
    }

    private function stockNumber(float|string|null $value): float
    {
        return round((float) $value, 3);
    }
}
