<?php

namespace App\Http\Controllers;

use App\Models\Extra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $query = Extra::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->has('category') && $request->category != '') {
            $query->where('category', $request->category);
        }

        $perPage = $request->input('per_page', 10);
        $items = $query->orderBy('category')->orderBy('name')->paginate($perPage);
        $categories = Extra::distinct()->whereNotNull('category')->pluck('category');

        return response()->json([
            'items' => $items,
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'nullable|string|unique:extras,sku',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'status' => 'boolean',
            'stock_tracking' => 'boolean',
            'tags' => 'nullable|array',
            'toppings' => 'nullable|array'
        ]);

        $item = Extra::create($validated);
        
        if (empty($item->sku)) {
            $item->sku = 'SP-' . str_pad($item->id, 3, '0', STR_PAD_LEFT);
            $item->save();
        }

        return response()->json([
            'message' => 'Thêm sản phẩm thành công',
            'item' => $item
        ]);
    }

    public function update(Request $request, $id)
    {
        $item = Extra::findOrFail($id);

        $validated = $request->validate([
            'sku' => 'nullable|string|unique:extras,sku,' . $id,
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'status' => 'boolean',
            'stock_tracking' => 'boolean',
            'tags' => 'nullable|array',
            'toppings' => 'nullable|array'
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Cập nhật sản phẩm thành công',
            'item' => $item
        ]);
    }

    public function destroy($id)
    {
        $item = Extra::findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Xoá sản phẩm thành công'
        ]);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('menu', 'public');
            return response()->json([
                'url' => Storage::url($path)
            ]);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }
}
