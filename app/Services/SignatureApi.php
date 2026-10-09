<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The SignatureAPI client. Every call to the API lives here.
 * Request and response shapes: https://spec.signatureapi.com/openapi.yaml
 */
class SignatureApi
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
    ) {}

    /**
     * Upload a PDF and return its temporary URL, usable as a document URL.
     */
    public function uploadDocument(string $bytes): string
    {
        return $this->http()
            ->withBody($bytes, 'application/pdf')
            ->post('/uploads')
            ->json('url');
    }

    /**
     * Create an envelope with one signer and return its id.
     * The signer is emailed a signing link (the default `email_link` ceremony).
     */
    public function createEnvelope(string $documentUrl, string $signerName, string $signerEmail, string $agreementId): string
    {
        return $this->http()->post('/envelopes', [
            'title' => 'Sample agreement',
            'documents' => [[
                'format' => 'pdf',
                'url' => $documentUrl,
                // Placed on the [[signer_signature]] placeholder in the PDF.
                'places' => [
                    ['key' => 'signer_signature', 'type' => 'signature', 'recipient_key' => 'signer'],
                ],
            ]],
            'recipients' => [
                ['type' => 'signer', 'key' => 'signer', 'name' => $signerName, 'email' => $signerEmail],
            ],
            'metadata' => ['agreement_id' => $agreementId],
        ])->json('id');
    }

    /**
     * List an envelope's deliverables (the signed PDF files).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDeliverables(string $envelopeId): array
    {
        return $this->http()
            ->get("/envelopes/{$envelopeId}/deliverables")
            ->json('data', []);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->acceptJson()
            ->throw();
    }
}
