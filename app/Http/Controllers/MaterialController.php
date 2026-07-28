<?php

namespace App\Http\Controllers;

use App\Models\Material;
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
            ->orderBy('name')
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
                    $material->update([
                        'name' => $item['name'],
                        'unit' => $item['unit'],
                        'low_stock_threshold' => (float)$item['low_stock_threshold'],
                        'price_per_unit' => isset($item['price_per_unit']) ? (float)$item['price_per_unit'] : null,
                        'note' => $item['note'] ?? null,
                        'active' => $item['active'] ?? true,
                    ]);
                } else {
                    $exists = Material::where('name', $item['name'])->exists();
                    if ($exists) {
                        throw new \Exception('Tên nguyên vật liệu "' . $item['name'] . '" đã tồn tại.');
                    }
                    Material::create([
                        'name' => $item['name'],
                        'unit' => $item['unit'],
                        'current_stock' => 0, // Default to 0, only updated via inventory
                        'low_stock_threshold' => (float)$item['low_stock_threshold'],
                        'price_per_unit' => isset($item['price_per_unit']) ? (float)$item['price_per_unit'] : null,
                        'note' => $item['note'] ?? null,
                        'active' => $item['active'] ?? true,
                    ]);
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
