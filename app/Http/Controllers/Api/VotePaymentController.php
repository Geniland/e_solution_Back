<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Vote;
use App\Models\Candidate;
use App\Models\Category;
use App\Models\VotePayment;
use Illuminate\Http\JsonResponse;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use GuzzleHttp\Client;

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

        $candidate = Candidate::findOrFail($validated['candidate_id']);
        $category  = $candidate->category;

        if ($category->is_closed) {
            return response()->json(['error' => 'Voting is closed for this category'], 403);
        }

        $votes = $validated['votes'] ?? 1;
        $baseAmount = $category->amount;
        $amount = $votes * $baseAmount;

        try {
            FedaPay::setApiKey(config('services.fedapay.secret_key'));
            FedaPay::setEnvironment(config('services.fedapay.mode')); // sandbox ou live

            $defaultEmail = 'noreply@gmail.com';
            $email = $request->input('email', $defaultEmail);

            $transaction = Transaction::create([
                'amount' => (float) $amount,
                'description' => "Vote x{$votes} pour {$candidate->first_name} {$candidate->last_name}",
                'callback_url' => route('vote.callback'), // retour utilisateur
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

            VotePayment::create([
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

    /**
     * Callback utilisateur (retour navigateur après paiement)
     */
    public function callbackPage(Request $request)
    {
        $txId = $request->input('id'); // FedaPay envoie l'id dans l'URL

        if (!$txId) {
            return view('vote.callback', [
                'error' => true,
                'message' => "ID de transaction manquant.",
                'transactionId' => null
            ]);
        }

        return view('vote.callback', [
            'error' => false,
            'message' => "Merci pour votre paiement, cliquez pour confirmer votre vote.",
            'transactionId' => $txId
        ]);
    }

    /**
     * Confirmation manuelle du vote (POST via bouton sur la page callback)
     */
    public function confirmVote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string',
        ]);

        $txId = $validated['transaction_id'];

        // Vérification du statut auprès de FedaPay
        $statusResponse = $this->verifyTransactionStatus($txId);
        $transaction = $statusResponse['transaction'] ?? null;

        if (!$transaction || strtolower($transaction['status']) !== 'approved') {
            return response()->json(['error' => 'Transaction non confirmée.'], 400);
        }

        $payment = VotePayment::where('transaction_reference', $txId)->first();
        if (!$payment) {
            return response()->json(['error' => 'Transaction inconnue.'], 404);
        }

        $candidate = Candidate::find($payment->candidate_id);
        if (!$candidate) {
            return response()->json(['error' => 'Candidat introuvable'], 500);
        }

        $vote = Vote::create([
            'candidate_id'  => $candidate->id,
            'category_id'   => $candidate->category_id,
            'user_id'       => auth()->check() ? auth()->id() : null,
            'visitor_token' => $payment->visitor_token,
            'ip_address'    => $payment->ip_address,
        ]);

        $payment->update(['payment_status' => 'approved']);

        return response()->json([
            'success'   => true,
            'message'   => 'Vote enregistré avec succès.',
            'candidate' => $candidate->first_name . ' ' . $candidate->last_name,
            'vote_id'   => $vote->id,
        ]);
    }

    /**
     * Vérifie le statut d'une transaction FedaPay.
     */
   private function verifyTransactionStatus(string $txId): array
{
    $client = new Client();
    $response = $client->get("https://api.fedapay.com/v1/transactions/$txId", [
        'headers' => [
            'Authorization' => 'Bearer ' . config('services.fedapay.secret_key'),
            'Accept'        => 'application/json',
        ],
        'timeout' => 10,
    ]);

    $body = $response->getBody()->getContents();
    Log::info('[FedaPay CheckStatus]', [$body]);

    $decoded = json_decode($body, true);

    // ✅ On renvoie directement la transaction dans un format standardisé
    return [
        'transaction' => $decoded['v1/transaction'] ?? null
    ];
}

}
