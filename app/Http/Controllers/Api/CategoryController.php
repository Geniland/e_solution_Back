<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
  
    // public function index()
    // {
    //     // return Category::with('candidates')->get();
    //     $categories = Category::with('candidates')->get();
    // return response()->json($categories);
    // }

    public function index()
{
    $categories = Category::with(['candidates' => function($query) {
        $query->withCount('votes'); // ✅ Ajoute votes_count
    }])->get();

    return response()->json($categories);
}


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'description' => 'nullable',
            'banner' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'amount' => 'nullable|numeric|min:0'
            
        ]);

        if ($request->hasFile('banner')) {
            $path = $request->file('banner')->store('banners', 'public');
            $validated['banner'] = $path;
        }

        $validated['created_by'] = Auth::id();

        return Category::create($validated);
    }

    public function close(Category $category)
    {
        $category->is_closed = true;
        $category->save();
        return $category;
    }

    public function reopen(Category $category)
    {
        $category->is_closed = false;
        $category->save();
        return $category;
    }

    public function show($id)
{
    try {
        // Vérifie que la catégorie existe
        $category = Category::findOrFail($id);

        // Charge les candidats associés
        $candidates = $category->candidates; // Relation définie dans le modèle

        return response()->json([
            'category' => $category,
            'candidates' => $candidates
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'message' => 'Catégorie non trouvée'
        ], 404);
    } catch (\Exception $e) {
        \Log::error('Erreur dans candidatesByCategory', [
            'message' => $e->getMessage()
        ]);

        return response()->json([
            'message' => 'Erreur serveur.'
        ], 500);
    }
}

}

