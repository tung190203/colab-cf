<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Recipe;
use App\Models\StockLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockDeductionService
{
    public function checkAvailability(array $items): array
    {
        $items = $this->normalizeItems($items);

        if ($items->isEmpty()) {
            return [
                'ok' => true,
                'message' => 'Đơn không có món cần trừ NVL',
                'missing_product_ids' => [],
                'insufficient' => [],
            ];
        }

        $recipes = Recipe::whereIn('product_id', $items->pluck('product_id'))
            ->where('active', true)
            ->get()
            ->keyBy('product_id');

        $missingProductIds = $this->missingProductIds($items, $recipes);
        if ($missingProductIds->isNotEmpty()) {
            return [
                'ok' => false,
                'message' => 'Một số sản phẩm chưa có công thức đang sử dụng: ' . $missingProductIds->join(', '),
                'missing_product_ids' => $missingProductIds->all(),
                'insufficient' => [],
            ];
        }

        $requirements = $this->buildRequirements($items, $recipes);
        $materials = Material::whereIn('id', array_keys($requirements))->get()->keyBy('id');
        $insufficient = $this->insufficientMaterials($requirements, $materials);

        return [
            'ok' => empty($insufficient),
            'message' => empty($insufficient)
                ? 'Đủ nguyên vật liệu để phục vụ đơn'
                : 'Không đủ tồn kho: ' . implode('; ', array_column($insufficient, 'message')),
            'missing_product_ids' => [],
            'insufficient' => $insufficient,
        ];
    }

    public function deductByOrder(string $orderId, array $items, ?int $userId = null, ?string $note = null): array
    {
        if (StockLog::where('type', 'auto_deduct')->where('order_id', $orderId)->exists()) {
            return ['logs' => [], 'already_deducted' => true];
        }

        $items = $this->normalizeItems($items);

        if ($items->isEmpty()) {
            return ['logs' => [], 'already_deducted' => false];
        }

        $recipes = Recipe::whereIn('product_id', $items->pluck('product_id'))
            ->where('active', true)
            ->get()
            ->keyBy('product_id');

        $missingProductIds = $this->missingProductIds($items, $recipes);

        if ($missingProductIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Một số sản phẩm chưa có công thức đang sử dụng: ' . $missingProductIds->join(', '),
            ]);
        }

        $requirements = $this->buildRequirements($items, $recipes);

        return DB::transaction(function () use ($orderId, $requirements, $userId, $note) {
            $materials = Material::whereIn('id', array_keys($requirements))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $insufficient = $this->insufficientMaterials($requirements, $materials);

            if (!empty($insufficient)) {
                throw ValidationException::withMessages([
                    'stock' => 'Không đủ tồn kho: ' . implode('; ', array_column($insufficient, 'message')),
                ]);
            }

            $logs = [];
            foreach ($requirements as $materialId => $requiredQuantity) {
                $material = $materials[$materialId];
                $stockBefore = $this->stockNumber($material->current_stock);
                $requiredQuantity = $this->stockNumber($requiredQuantity);
                $stockAfter = $this->stockNumber($stockBefore - $requiredQuantity);

                $material->update([
                    'current_stock' => $stockAfter,
                ]);

                $logs[] = StockLog::create([
                    'material_id' => $material->id,
                    'type' => 'auto_deduct',
                    'quantity' => -$requiredQuantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'order_id' => $orderId,
                    'note' => $note ?: 'Trừ NVL khi xác nhận đơn',
                    'created_by' => $userId,
                ])->load('material:id,name,unit');
            }

            return ['logs' => $logs, 'already_deducted' => false];
        });
    }

    private function buildRequirements(Collection $items, Collection $recipes): array
    {
        $requirements = [];

        foreach ($items as $item) {
            $recipe = $recipes[(int) $item['product_id']];
            foreach ($recipe->ingredients as $ingredient) {
                $materialId = (int) $ingredient['material_id'];
                $requirements[$materialId] = ($requirements[$materialId] ?? 0)
                    + $this->stockNumber((float) $ingredient['quantity'] * (int) $item['quantity']);
            }
        }

        return $requirements;
    }

    private function normalizeItems(array $items): Collection
    {
        return collect($items)
            ->filter(fn ($item) => !empty($item['product_id']) && (int) ($item['quantity'] ?? 0) > 0)
            ->map(fn ($item) => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ])
            ->values();
    }

    private function missingProductIds(Collection $items, Collection $recipes): Collection
    {
        return $items
            ->pluck('product_id')
            ->unique()
            ->reject(fn ($productId) => $recipes->has($productId))
            ->values();
    }

    private function insufficientMaterials(array $requirements, Collection $materials): array
    {
        $insufficient = [];

        foreach ($requirements as $materialId => $requiredQuantity) {
            $material = $materials[$materialId];
            $currentStock = $this->stockNumber($material->current_stock);
            $requiredQuantity = $this->stockNumber($requiredQuantity);

            if ($requiredQuantity > $currentStock) {
                $message = "{$material->name} cần {$this->formatStockNumber($requiredQuantity)} {$material->unit}, còn {$this->formatStockNumber($currentStock)} {$material->unit}";
                $insufficient[] = [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'required' => $requiredQuantity,
                    'current_stock' => $currentStock,
                    'unit' => $material->unit,
                    'message' => $message,
                ];
            }
        }

        return $insufficient;
    }

    private function stockNumber(float|string|null $value): float
    {
        return round((float) $value, 3);
    }

    private function formatStockNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
