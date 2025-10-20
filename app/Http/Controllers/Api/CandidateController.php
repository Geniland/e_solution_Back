<?php

namespace App\Http\Controllers\Api;

use App\Models\Candidate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CandidateController extends Controller
{
    public function index()
    {
        return Candidate::with('category')->get();
    }

public function store(Request $request)
{
    try {
        Log::info('CandidateController@store called');

        // ✅ Vérification que l'utilisateur est connecté
        $user = Auth::user();
        if (!$user) {
            Log::warning('No authenticated user detected');
            return response()->json(['message' => 'Unauthorized. No authenticated user.'], 401);
        }

        // ✅ Vérification du rôle admin
        if ($user->role !== 'admin') {
            Log::warning('Access denied. User is not admin.', ['user_id' => $user->id, 'role' => $user->role]);
            return response()->json(['message' => 'Access denied. Only admin can create candidates.'], 403);
        }

        // ✅ Validation des données
        $validated = $request->validate([
            'category_id'      => 'required|exists:categories,id',
            'first_name'       => 'required|string|max:255',
            'last_name'        => 'required|string|max:255',
            'phone'            => 'required|string|max:20',
            'position_number'  => 'nullable|integer',
            'photo'            => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'bio'              => 'nullable|string',
            'social_links'     => 'nullable|array',
            'video_url'        => 'nullable|url',
        ]);

        Log::info('Validation passed', ['validated' => $validated]);

        // ✅ Gestion du fichier photo
        if ($request->hasFile('photo')) {
            Log::info('Photo file detected');
            $path = $request->file('photo')->store('candidates', 'public');
            $validated['photo'] = $path;
            Log::info('Photo stored at: '.$path);
        }

        // ✅ Encodage des liens sociaux
        if (isset($validated['social_links'])) {
            $validated['social_links'] = json_encode($validated['social_links'], JSON_UNESCAPED_UNICODE);
            Log::info('social_links encoded', ['social_links' => $validated['social_links']]);
        }

        // ✅ Ajout du créateur
        $validated['created_by'] = $user->id;

        Log::info('Creating candidate...', ['validated' => $validated]);

        // ✅ Création du candidat
        $candidate = Candidate::create($validated);

        Log::info('Candidate created', $candidate->toArray());

        return response()->json([
            'message'   => 'Candidate created successfully',
            'candidate' => $candidate
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation failed', [
            'errors' => $e->errors()
        ]);

        return response()->json([
            'message' => 'Validation error',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        Log::error('Unexpected error in CandidateController@store', [
            'message' => $e->getMessage(),
            'trace'   => $e->getTraceAsString()
        ]);

        return response()->json([
            'message' => 'An unexpected error occurred.',
        ], 500);
    }
}


public function show($id)
{
    try {
        // Recherche du candidat avec sa catégorie
        $candidate = Candidate::with('category')->findOrFail($id);

        return response()->json([
            'message' => 'Candidat trouvé',
            'candidate' => $candidate
        ]);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'message' => 'Candidat non trouvé'
        ], 404);
    } catch (\Exception $e) {
        \Log::error('Erreur dans CandidateController@show', [
            'message' => $e->getMessage()
        ]);

        return response()->json([
            'message' => 'Une erreur est survenue.'
        ], 500);
    }
}


}
