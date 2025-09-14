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

        if ($request->hasFile('photo')) {
            Log::info('Photo file detected');
            $path = $request->file('photo')->store('candidates', 'public');
            $validated['photo'] = $path;
            Log::info('Photo stored at: '.$path);
        }

        if (isset($validated['social_links'])) {
            $validated['social_links'] = json_encode($validated['social_links'], JSON_UNESCAPED_UNICODE);
            Log::info('social_links encoded', ['social_links' => $validated['social_links']]);
        }

        $userId = Auth::id();
        Log::info('Auth user id', ['id' => $userId]);

        if (!$userId) {
            Log::warning('No authenticated user detected');
            return response()->json(['message' => 'Unauthorized. No authenticated user.'], 401);
        }

        $validated['created_by'] = $userId;

        Log::info('Creating candidate...', ['validated' => $validated]);

        $candidate = Candidate::create($validated);

        Log::info('Candidate created', $candidate->toArray());

        return response()->json([
            'message'   => 'Candidate created successfully',
            'candidate' => $candidate
        ]);

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
