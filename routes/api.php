

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Controllers\Api\VotePaymentController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');




Route::get('/test', [\App\Http\Controllers\Api\TestController::class, 'index']);


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');


// Routes publiques (accessibles sans auth)
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

Route::get('/candidates', [CandidateController::class, 'index']);
Route::get('/candidates/{id}', [CandidateController::class, 'show']);

Route::post('/votes', [VoteController::class, 'store']);
Route::get('/votes/{candidate_id}', [VoteController::class, 'showVotesForCandidate']);

// Les routes de paiement (selon besoin, tu peux aussi les protéger)
Route::post('/vote-payments/initiate', [VotePaymentController::class, 'initiateVotePayment']);
Route::post('/vote-payments/callback', [VotePaymentController::class, 'handleVotePaymentCallback'])
    ->name('api.votes.callback');
    

// Autoriser GET pour éviter l'erreur quand l’iframe est fermé
Route::get('vote-payments/callback', function () {
    return response()->json([
        'message' => 'Callback GET reçu, mais seule la méthode POST est utilisée pour valider les paiements.'
    ]);
});


Route::post('/status', [VotePaymentController::class, 'checkTransactionStatus']);

// Routes protégées par authentification Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Gestion catégories (création, modif, suppression)
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Gestion candidats (création, modif, suppression)
    Route::post('/candidates', [CandidateController::class, 'store']);
    Route::put('/candidates/{id}', [CandidateController::class, 'update']);
    Route::delete('/candidates/{id}', [CandidateController::class, 'destroy']);
});
