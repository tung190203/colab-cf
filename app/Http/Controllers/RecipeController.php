<?php

namespace App\Http\Controllers;

use App\Models\Extra;
use App\Models\Material;
use App\Models\Recipe;
use App\Models\RecipeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $query = Recipe::query()->with('product:id,name,category,sku,status');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('category', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json([
            'recipes' => $query->orderBy('product_name')->get(),
        ]);
    }

    public function options()
    {
        $excludedCategories = [
            'services',
            'office_services',
            'other_services',
            'others_services',
            'office services',
            'other services',
            'others services',
        ];

        return response()->json([
            'products' => Extra::query()
                ->where('status', true)
                ->whereNotIn(DB::raw('LOWER(category)'), $excludedCategories)
                ->orderBy('category')
                ->orderBy('name')
                ->get(['id', 'name', 'category', 'sku']),
            'materials' => Material::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'unit']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:extras,id',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.material_id' => 'required|exists:materials,id',
            'ingredients.*.quantity' => 'required|numeric|min:0.001',
            'active' => 'boolean',
        ]);

        $product = Extra::findOrFail($validated['product_id']);
        $materials = Material::whereIn('id', collect($validated['ingredients'])->pluck('material_id'))->get()->keyBy('id');
        $ingredients = collect($validated['ingredients'])
            ->map(function ($ingredient) use ($materials) {
                $material = $materials[(int) $ingredient['material_id']];

                return [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'quantity' => (float) $ingredient['quantity'],
                    'unit' => $material->unit,
                ];
            })
            ->values()
            ->all();

        $recipe = DB::transaction(function () use ($request, $validated, $product, $ingredients) {
            $recipe = Recipe::where('product_id', $product->id)->first();
            $ingredientsBefore = $recipe?->ingredients;

            if ($recipe) {
                $recipe->update([
                    'product_name' => $product->name,
                    'ingredients' => $ingredients,
                    'active' => $validated['active'] ?? true,
                ]);
            } else {
                $recipe = Recipe::create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'ingredients' => $ingredients,
                    'active' => $validated['active'] ?? true,
                ]);
            }

            RecipeLog::create([
                'recipe_id' => $recipe->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'ingredients_before' => $ingredientsBefore,
                'ingredients_after' => $ingredients,
                'changed_by' => $request->user()?->id,
            ]);

            return $recipe->fresh('product:id,name,category,sku,status');
        });

        return response()->json([
            'message' => 'Lưu công thức thành công',
            'recipe' => $recipe,
        ]);
    }

    public function logs(Recipe $recipe)
    {
        return response()->json([
            'logs' => RecipeLog::with('changer:id,name')
                ->where('recipe_id', $recipe->id)
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }
}
