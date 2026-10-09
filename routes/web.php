<?php

use App\Http\Controllers\AgreementController;
use App\Http\Controllers\SignatureApiWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AgreementController::class, 'index']);
Route::post('/agreements', [AgreementController::class, 'store']);
Route::get('/agreements/{agreement}/signed.pdf', [AgreementController::class, 'signedPdf']);

// Excluded from CSRF protection in bootstrap/app.php.
Route::post('/webhooks/signatureapi', SignatureApiWebhookController::class);
