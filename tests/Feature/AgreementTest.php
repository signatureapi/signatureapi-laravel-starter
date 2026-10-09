<?php

namespace Tests\Feature;

use App\Models\Agreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgreementTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.signatureapi.com/v1';

    private const ENVELOPE_ID = '223c4d7d-10c3-4f69-8b82-f537158fe50a';

    public function test_index_shows_the_form_and_an_empty_list(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Send for signature')
            ->assertSee('No agreements yet.');
    }

    public function test_store_uploads_the_pdf_creates_an_envelope_and_saves_the_agreement(): void
    {
        Http::fake([
            self::API.'/uploads' => Http::response(['id' => 'upl_1', 'url' => self::API.'/uploads/upl_1'], 201),
            self::API.'/envelopes' => Http::response(['id' => self::ENVELOPE_ID], 201),
        ]);

        $this->post('/agreements', ['signer_name' => 'Ada Lovelace', 'signer_email' => 'ada@example.com'])
            ->assertRedirect('/');

        $agreement = Agreement::sole();
        $this->assertSame(self::ENVELOPE_ID, $agreement->envelope_id);
        $this->assertSame('sent', $agreement->status);

        Http::assertSent(fn (Request $request) => $request->url() === self::API.'/uploads'
            && $request->method() === 'POST'
            && $request->hasHeader('X-API-Key', 'key_test_phpunit')
            && $request->hasHeader('Content-Type', 'application/pdf')
            && str_starts_with($request->body(), '%PDF-'));

        Http::assertSent(fn (Request $request) => $request->url() === self::API.'/envelopes'
            && $request->method() === 'POST'
            && $request->hasHeader('X-API-Key', 'key_test_phpunit')
            && $request->data() === [
                'title' => 'Sample agreement',
                'documents' => [[
                    'format' => 'pdf',
                    'url' => self::API.'/uploads/upl_1',
                    'places' => [['key' => 'signer_signature', 'type' => 'signature', 'recipient_key' => 'signer']],
                ]],
                'recipients' => [['type' => 'signer', 'key' => 'signer', 'name' => 'Ada Lovelace', 'email' => 'ada@example.com']],
                'metadata' => ['agreement_id' => (string) $agreement->id],
            ]);

        $this->get('/')
            ->assertSee('data-envelope-id="'.self::ENVELOPE_ID.'"', false)
            ->assertSee('data-status="sent"', false);
    }

    public function test_store_shows_the_api_error_and_saves_nothing(): void
    {
        Http::fake([
            self::API.'/uploads' => Http::response(['id' => 'upl_1', 'url' => self::API.'/uploads/upl_1'], 201),
            self::API.'/envelopes' => Http::response([
                'type' => 'https://signatureapi.com/docs/v1/errors/invalid-api-key',
                'title' => 'Invalid API Key',
                'status' => 401,
                'detail' => 'Please provide a valid API key in the X-API-Key header.',
            ], 401),
        ]);

        $this->post('/agreements', ['signer_name' => 'Ada Lovelace', 'signer_email' => 'ada@example.com'])
            ->assertOk()
            ->assertSee('Could not create the envelope: Please provide a valid API key in the X-API-Key header.');

        $this->assertSame(0, Agreement::count());
    }

    public function test_signed_pdf_redirects_to_a_fresh_deliverable_url(): void
    {
        $agreement = Agreement::factory()->create(['envelope_id' => self::ENVELOPE_ID, 'status' => 'completed']);
        Http::fake([
            self::API.'/envelopes/'.self::ENVELOPE_ID.'/deliverables' => Http::response([
                'data' => [['id' => 'del_1', 'status' => 'generated', 'url' => 'https://files.example.com/signed.pdf']],
            ]),
        ]);

        $this->get("/agreements/{$agreement->id}/signed.pdf")
            ->assertRedirect('https://files.example.com/signed.pdf');
    }

    public function test_signed_pdf_is_404_when_no_deliverable_is_ready(): void
    {
        $agreement = Agreement::factory()->create(['envelope_id' => self::ENVELOPE_ID, 'status' => 'completed']);
        Http::fake([
            self::API.'/envelopes/'.self::ENVELOPE_ID.'/deliverables' => Http::response([
                'data' => [['id' => 'del_1', 'status' => 'processing', 'url' => null]],
            ]),
        ]);

        $this->get("/agreements/{$agreement->id}/signed.pdf")->assertNotFound();
    }

    public function test_signed_pdf_is_404_until_the_agreement_is_completed(): void
    {
        Http::preventStrayRequests();
        $agreement = Agreement::factory()->create();

        $this->get("/agreements/{$agreement->id}/signed.pdf")->assertNotFound();
    }
}
