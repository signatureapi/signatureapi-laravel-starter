<?php

namespace Tests\Feature;

use App\Models\Agreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use StandardWebhooks\Webhook;
use Tests\TestCase;

class SignatureApiWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const ENVELOPE_ID = '223c4d7d-10c3-4f69-8b82-f537158fe50a';

    public function test_a_verified_event_updates_the_status(): void
    {
        $agreement = Agreement::factory()->create(['envelope_id' => self::ENVELOPE_ID]);

        $this->sendEvent('envelope.completed')->assertSuccessful();

        $this->assertSame('completed', $agreement->fresh()->status);
    }

    public function test_each_tracked_event_maps_to_its_status(): void
    {
        foreach ([
            'recipient.rejected' => 'rejected',
            'envelope.failed' => 'failed',
            'envelope.canceled' => 'canceled',
        ] as $type => $status) {
            $agreement = Agreement::factory()->create();

            $this->sendEvent($type, $agreement->envelope_id)->assertSuccessful();

            $this->assertSame($status, $agreement->fresh()->status);
        }
    }

    public function test_a_bad_signature_is_rejected(): void
    {
        $agreement = Agreement::factory()->create(['envelope_id' => self::ENVELOPE_ID]);

        $this->sendEvent('envelope.completed', secret: 'whsec_'.base64_encode('not the secret'))
            ->assertUnauthorized();

        $this->assertSame('sent', $agreement->fresh()->status);
    }

    public function test_missing_signature_headers_are_rejected(): void
    {
        $this->postJson('/webhooks/signatureapi', ['type' => 'envelope.completed'])->assertUnauthorized();
    }

    public function test_other_event_types_are_acknowledged_and_ignored(): void
    {
        $agreement = Agreement::factory()->create(['envelope_id' => self::ENVELOPE_ID]);

        $this->sendEvent('recipient.viewed')->assertSuccessful();

        $this->assertSame('sent', $agreement->fresh()->status);
    }

    public function test_unknown_envelopes_are_acknowledged(): void
    {
        $this->sendEvent('envelope.completed')->assertSuccessful();
    }

    public function test_a_terminal_status_is_not_overwritten(): void
    {
        $agreement = Agreement::factory()->create(['envelope_id' => self::ENVELOPE_ID, 'status' => 'completed']);

        $this->sendEvent('envelope.failed')->assertSuccessful();

        $this->assertSame('completed', $agreement->fresh()->status);
    }

    private function sendEvent(string $type, string $envelopeId = self::ENVELOPE_ID, ?string $secret = null): TestResponse
    {
        $payload = json_encode([
            'id' => 'evt_test',
            'type' => $type,
            'timestamp' => now()->toIso8601ZuluString(),
            'data' => ['envelope_id' => $envelopeId, 'object_id' => $envelopeId, 'object_type' => 'envelope'],
        ]);
        $timestamp = time();
        $signature = (new Webhook($secret ?? config('services.signatureapi.webhook_secret')))
            ->sign('msg_test', $timestamp, $payload);

        return $this->call('POST', '/webhooks/signatureapi', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => 'msg_test',
            'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => $signature,
        ], content: $payload);
    }
}
