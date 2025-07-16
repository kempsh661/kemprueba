<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $categories = Category::where('user_id', $userId)->get();
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->only(['name', 'code', 'description']);
        $data['user_id'] = $userId;
        
        // Generar código si no se proporciona
        if (empty($data['code'])) {
            $data['code'] = $this->generateCategoryCode($data['name']);
        }
        
        $category = Category::create($data);
        return response()->json([
            'success' => true,
            'data' => $category
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $userId = $request->user()->id;
        $category = Category::where('user_id', $userId)->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    public function update(Request $request, $id)
    {
        $userId = $request->user()->id;
        $category = Category::where('user_id', $userId)->findOrFail($id);
        $category->update($request->only(['name', 'code', 'description']));
        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;
        $category = Category::where('user_id', $userId)->findOrFail($id);
        $category->delete();
        return response()->json(['success' => true]);
    }

    private function generateCategoryCode($name)
    {
        // Generar código basado en las primeras letras de cada palabra
        $words = explode(' ', strtoupper($name));
        $code = '';
        
        foreach ($words as $word) {
            if (!empty($word)) {
                $code .= substr($word, 0, 1);
            }
        }
        
        // Si no hay código, usar CAT
        if (empty($code)) {
            $code = 'CAT';
        }
        
        // Agregar número si ya existe
        $count = Category::where('code', 'LIKE', $code . '%')->count();
        if ($count > 0) {
            $code .= str_pad($count + 1, 2, '0', STR_PAD_LEFT);
        }
        
        return $code;
    }
}
