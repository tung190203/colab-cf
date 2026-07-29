<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $query = Material::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('unit', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('active', $request->status === 'active');
        }

        $perPage = (int) $request->input('per_page', 10);
        $materials = $query
            ->orderByDesc('active')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'materials' => $materials,
            'alerts_count' => Material::whereColumn('current_stock', '<=', 'low_stock_threshold')
                ->where('active', true)
                ->count(),
        ]);
    }

    public function store(Request $request)
    {
        $material = Material::create($this->validateMaterial($request));

        return response()->json([
            'message' => 'Thêm nguyên vật liệu thành công',
            'material' => $material,
        ], 201);
    }

    public function update(Request $request, Material $material)
    {
        $material->update($this->validateMaterial($request, $material));

        return response()->json([
            'message' => 'Cập nhật nguyên vật liệu thành công',
            'material' => $material,
        ]);
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'materials' => 'required|array',
            'materials.*.id' => 'nullable|exists:materials,id',
            'materials.*.name' => 'required|string|max:255',
            'materials.*.unit' => 'required|string|max:30',
            'materials.*.current_stock' => 'nullable|numeric|min:0',
            'materials.*.low_stock_threshold' => 'required|numeric|min:0',
            'materials.*.price_per_unit' => 'nullable|numeric|min:0',
            'materials.*.note' => 'nullable|string',
            'materials.*.active' => 'boolean',
        ]);

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            foreach ($request->materials as $item) {
                // To avoid duplicate name errors, check if updating or creating
                if (!empty($item['id'])) {
                    $material = Material::findOrFail($item['id']);
                    // Check name uniqueness if changed
                    if ($material->name !== $item['name']) {
                        $exists = Material::where('name', $item['name'])->where('id', '!=', $item['id'])->exists();
                        if ($exists) {
                            throw new \Exception('Tên nguyên vật liệu "' . $item['name'] . '" đã tồn tại.');
                        }
                    }
                    
                    $oldStock = (float)$material->current_stock;
                    $newStock = isset($item['current_stock']) ? (float)$item['current_stock'] : $oldStock;

                    $material->update([
                        'name' => $item['name'],
                        'unit' => $item['unit'],
                        'current_stock' => $newStock,
                        'low_stock_threshold' => (float)$item['low_stock_threshold'],
                        'price_per_unit' => isset($item['price_per_unit']) ? (float)$item['price_per_unit'] : null,
                        'note' => $item['note'] ?? null,
                        'active' => $item['active'] ?? true,
                    ]);

                    if (abs($oldStock - $newStock) > 0.0001) {
                        StockLog::create([
                            'material_id' => $material->id,
                            'type' => 'adjustment',
                            'quantity' => $newStock - $oldStock,
                            'stock_before' => $oldStock,
                            'stock_after' => $newStock,
                            'note' => 'Cập nhật từ quản lý kho',
                            'created_by' => $request->user()?->id,
                        ]);
                    }
                } else {
                    $exists = Material::where('name', $item['name'])->exists();
                    if ($exists) {
                        throw new \Exception('Tên nguyên vật liệu "' . $item['name'] . '" đã tồn tại.');
                    }
                    
                    $initialStock = isset($item['current_stock']) ? (float)$item['current_stock'] : 0;
                    
                    $material = Material::create([
                        'name' => $item['name'],
                        'unit' => $item['unit'],
                        'current_stock' => $initialStock,
                        'low_stock_threshold' => (float)$item['low_stock_threshold'],
                        'price_per_unit' => isset($item['price_per_unit']) ? (float)$item['price_per_unit'] : null,
                        'note' => $item['note'] ?? null,
                        'active' => $item['active'] ?? true,
                    ]);

                    if ($initialStock > 0) {
                        StockLog::create([
                            'material_id' => $material->id,
                            'type' => 'adjustment',
                            'quantity' => $initialStock,
                            'stock_before' => 0,
                            'stock_after' => $initialStock,
                            'note' => 'Khởi tạo tồn kho ban đầu',
                            'created_by' => $request->user()?->id,
                        ]);
                    }
                }
            }
            \Illuminate\Support\Facades\DB::commit();
            return response()->json(['message' => 'Lưu nguyên vật liệu thành công']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Material $material)
    {
        $material->update(['active' => false]);

        return response()->json([
            'message' => 'Đã ẩn nguyên vật liệu',
            'material' => $material,
        ]);
    }

    private function validateMaterial(Request $request, ?Material $material = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('materials', 'name')->ignore($material?->id),
            ],
            'unit' => 'required|string|max:30',
            'current_stock' => 'required|numeric|min:0',
            'low_stock_threshold' => 'required|numeric|min:0',
            'price_per_unit' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
            'active' => 'boolean',
        ]);
    }
}
