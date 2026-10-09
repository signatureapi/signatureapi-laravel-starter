<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Services\SignatureApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AgreementController extends Controller
{
    public function index(?string $error = null): View
    {
        return view('agreements.index', [
            'agreements' => Agreement::latest('id')->get(),
            'error' => $error,
        ]);
    }

    public function store(Request $request, SignatureApi $signatureApi): View|RedirectResponse
    {
        $validated = $request->validate([
            'signer_name' => ['required', 'string', 'max:255'],
            'signer_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            // The row is only kept if the envelope was created.
            DB::transaction(function () use ($validated, $signatureApi) {
                $agreement = Agreement::create([...$validated, 'status' => 'sent']);

                $documentUrl = $signatureApi->uploadDocument(file_get_contents(resource_path('sample-agreement.pdf')));

                $agreement->update(['envelope_id' => $signatureApi->createEnvelope(
                    $documentUrl,
                    $agreement->signer_name,
                    $agreement->signer_email,
                    (string) $agreement->id,
                )]);
            });
        } catch (RequestException $e) {
            return $this->index($e->response->json('detail') ?? $e->getMessage());
        } catch (ConnectionException $e) {
            return $this->index($e->getMessage());
        }

        return redirect('/');
    }

    public function signedPdf(Agreement $agreement, SignatureApi $signatureApi): Response|RedirectResponse
    {
        if ($agreement->status !== 'completed') {
            return response('This agreement is not completed.', 404);
        }

        // Deliverable URLs expire after an hour, so fetch a fresh one on every download.
        $url = collect($signatureApi->listDeliverables($agreement->envelope_id))->pluck('url')->filter()->first();

        if (! $url) {
            return response('The signed PDF is not ready yet. Try again in a moment.', 404);
        }

        return redirect()->away($url);
    }
}
