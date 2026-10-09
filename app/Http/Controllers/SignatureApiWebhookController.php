<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use StandardWebhooks\Exception\WebhookVerificationException;
use StandardWebhooks\Webhook;

class SignatureApiWebhookController extends Controller
{
    /**
     * Event types that change an agreement's status. Every other type is acknowledged and ignored.
     */
    private const STATUSES = [
        'envelope.completed' => 'completed',
        'recipient.rejected' => 'rejected',
        'envelope.failed' => 'failed',
        'envelope.canceled' => 'canceled',
    ];

    public function __invoke(Request $request): Response
    {
        try {
            // Verify the raw body bytes exactly as received, not the parsed JSON.
            $event = (new Webhook((string) config('services.signatureapi.webhook_secret')))->verify(
                $request->getContent(),
                [
                    'webhook-id' => $request->header('webhook-id'),
                    'webhook-timestamp' => $request->header('webhook-timestamp'),
                    'webhook-signature' => $request->header('webhook-signature'),
                ],
            );
        } catch (WebhookVerificationException) {
            return response()->noContent(401);
        }

        $status = self::STATUSES[$event['type'] ?? ''] ?? null;

        // Events can repeat or arrive out of order: setting the same status twice is harmless,
        // and a terminal status is never moved. Unknown envelopes match no row.
        if ($status) {
            Agreement::where('envelope_id', $event['data']['envelope_id'] ?? null)
                ->whereNotIn('status', Agreement::TERMINAL_STATUSES)
                ->update(['status' => $status]);
        }

        return response()->noContent();
    }
}
