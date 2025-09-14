<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Vote;
use App\Models\Candidate;
use App\Models\VotePayment;

class VoteController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'candidate_id'    => 'required|exists:candidates,id',
            'visitor_token'   => 'nullable|string',
            'vote_amount'     => 'required|numeric|min:1', // montant du vote
        ]);

        $candidate = Candidate::findOrFail($validated['candidate_id']);
        $category = $candidate->category;

        if ($category->is_closed) {
            return response()->json(['error' => 'Voting is closed for this category'], 403);
        }

        // Vérifier si l'utilisateur a déjà voté pour ce candidat
        $already = Vote::where('candidate_id', $candidate->id)
            ->where('category_id', $category->id)
            ->where(function ($q) use ($validated) {
                if (Auth::check()) {
                    $q->where('user_id', Auth::id());
                } elseif (isset($validated['visitor_token'])) {
                    $q->where('visitor_token', $validated['visitor_token']);
                }
            })->exists();

        if ($already) {
            return response()->json(['error' => 'You have already voted for this candidate'], 409);
        }

        // Enregistrer le vote
        $vote = Vote::create([
            'candidate_id'   => $candidate->id,
            'category_id'    => $category->id,
            'user_id'        => Auth::check() ? Auth::id() : null,
            'visitor_token'  => $validated['visitor_token'] ?? null,
            'ip_address'     => $request->ip(),
        ]);

        // Créer un enregistrement de paiement associé au vote
        $payment = VotePayment::create([
            'vote_id'              => $vote->id,
            'amount'               => $validated['vote_amount'],
            'currency'             => 'XOF',
            'payment_status'       => 'pending',
            'transaction_reference'=> null, // sera mis à jour après retour de paygate
            'payment_gateway'      => 'paygate',
        ]);

        // TODO: Intégrer ici l'appel à paygate API pour initier le paiement

        return response()->json([
            'success' => true,
            'message' => 'Vote recorded. Proceed to payment.',
            'vote' => $vote,
            'payment' => $payment
        ]);
    }
}
