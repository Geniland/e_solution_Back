<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Vote;
use App\Models\Candidate;
use App\Models\Category;
use App\Models\VotePayment;
use Illuminate\Http\JsonResponse;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use FedaPay\Customer;

class VotePaymentController extends Controller
{
    /**
     * Initier un paiement FedaPay
     */
   public function initiateVotePayment(Request $request): JsonResponse
{
    Log::info('[InitVotePayment] Requête reçue', $request->all());

    $validated = $request->validate([
        'candidate_id'  => 'required|exists:candidates,id',
        'phone_number'  => 'required|string',
        'visitor_token' => 'nullable|string',
        'votes'         => 'nullable|integer|min:1'
    ]);

    Log::info('[InitVotePayment] Données validées', $validated);

    // Charger le candidat
    $candidate = Candidate::findOrFail($validated['candidate_id']);
    $category  = $candidate->category;

    if ($category->is_closed) {
        return response()->json(['error' => 'Voting is closed for this category'], 403);
    }

    // Montant total en fonction du nombre de votes
    $votes = $validated['votes'] ?? 1;
    $baseAmount = $category->amount; // prix d’un seul vote
    $amount = $votes * $baseAmount;

    $identifier = 'VOTE-' . strtoupper(Str::random(12));

    try {
        // Initialisation de FedaPay
        FedaPay::setApiKey(config('services.fedapay.secret_key'));
        FedaPay::setEnvironment(config('services.fedapay.mode')); // sandbox ou live

        $defaultEmail = 'no-reply@tonsite.com';
        $email = $request->input('email', $defaultEmail);

        $transaction = Transaction::create([
            'amount' => (float) $amount,
            'description' => "Vote x{$votes} pour {$candidate->first_name} {$candidate->last_name}",
            'callback_url' => route('api.votes.callback'),
            'currency' => ['iso' => 'XOF'],
            'customer' => [
                'firstname' => $candidate->first_name,
                'lastname'  => $candidate->last_name,
                'email'     => $email,
                'phone_number' => [
                    'number'  => $validated['phone_number'],
                    'country' => 'TG',
                ],
            ],
        ]);

        $paymentUrl = $transaction->generateToken()->url;
        $transactionId = $transaction->id;

        // Enregistrement en DB
        $payment = VotePayment::create([
            'amount'                => $amount,
            'candidate_id'          => $candidate->id,
            'payment_status'        => 'pending',
            'transaction_reference' => $transactionId,
            'network'               => 'FedaPay',
            'visitor_token'         => $validated['visitor_token'] ?? null,
            'ip_address'            => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement initié avec succès.',
            'payment_url' => $paymentUrl,
            'transaction_id' => $transactionId,
            'votes' => $votes,
            'amount_per_vote' => $baseAmount,
            'total_amount' => $amount,
        ]);

    } catch (\Exception $e) {
        Log::error('[InitVotePayment] Exception', ['message' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Erreur interne lors de l’initiation du paiement.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    // Les méthodes handleVotePaymentCallback et checkTransactionStatus restent inchangées
    // ...

    public function handleVotePaymentCallback(Request $request): JsonResponse
    {
        Log::info('[FedaPay Vote Callback] Données reçues', $request->all());

        $txReference = $request->input('tx_reference');
        $identifier  = $request->input('identifier');

        if (!$txReference || !$identifier) {
            return response()->json(['error' => 'Transaction invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Vérifier le statut réel auprès de FedaPay
            $statusResponse = $this->verifyTransactionStatus($txReference);

            Log::info('[FedaPay Vote Callback] Status API response:', $statusResponse);

            if (!isset($statusResponse['status'])) {
                return response()->json(['error' => 'Impossible de vérifier le statut auprès de FedaPay.'], 500);
            }

            $payment = VotePayment::where('transaction_reference', $txReference)->first();

            if (!$payment) {
                return response()->json(['error' => 'Transaction inconnue.'], 404);
            }

            if ($statusResponse['status'] != 'success') { // FedaPay retourne "success" pour une transaction validée
                $payment->payment_status = 'failed';
                $payment->save();

                Log::warning("[FedaPay Vote Callback] Transaction $txReference non validée.");

                return response()->json([
                    'error' => 'Transaction non confirmée.',
                    'status' => $statusResponse['status']
                ], Response::HTTP_BAD_REQUEST);
            }

            // Paiement confirmé → création du vote
            $candidate = Candidate::find($payment->candidate_id);
            $category  = $candidate->category;

            $vote = Vote::create([
                'candidate_id'  => $candidate->id,
                'category_id'   => $category->id,
                'user_id'       => auth()->check() ? auth()->id() : null,
                'visitor_token' => $payment->visitor_token,
                'ip_address'    => $payment->ip_address,
            ]);

            $payment->payment_status = 'approved';
            $payment->save();

            Log::info("[FedaPay Vote Callback] Vote enregistré pour TX $txReference");

            return response()->json([
                'success' => true,
                'message' => 'Vote enregistré avec succès.',
                'vote_id' => $vote->id,
            ]);

        } catch (\Exception $e) {
            Log::error('[FedaPay Vote Callback Exception]', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Erreur serveur'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Vérifie le statut d'une transaction FedaPay.
     */
    private function verifyTransactionStatus(string $txReference): array
    {
        $client = new Client();

        $response = $client->get("https://api.fedapay.com/v1/transactions/$txReference", [
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.fedapay.secret_key'),
                'Accept'        => 'application/json',
            ],
            'timeout' => 10,
        ]);

        $body = $response->getBody()->getContents();

        Log::info('[FedaPay Vote CheckStatus Raw Response]', [$body]);

        return json_decode($body, true);
    }

    /**
     * Vérification manuelle du statut d'une transaction
     */
    public function checkTransactionStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tx_reference' => 'required|string',
        ]);

        try {
            $payment = VotePayment::where('transaction_reference', $validated['tx_reference'])->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction introuvable.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => $payment->payment_status,
                'transaction_reference' => $payment->transaction_reference,
                'amount' => $payment->amount,
                'network' => $payment->network,
                'candidate_id' => $payment->candidate_id,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
            ]);

        } catch (\Exception $e) {
            Log::error('[FedaPay Status Check Exception]', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}