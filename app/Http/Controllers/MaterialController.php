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
