<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Send an agreement · SignatureAPI example</title>
  <link rel="stylesheet" href="/app.css">
</head>
<body>
  <main>
    <header>
      <h1>Send an agreement</h1>
      <p class="hint">A SignatureAPI e-signature API example for Laravel. Test mode: no emails are sent.</p>
    </header>

    <section class="card">
      <h2>New agreement</h2>
      <form method="post" action="/agreements">
        @csrf
        <label>Signer name <input name="signer_name" required autocomplete="off" value="{{ old('signer_name') }}"></label>
        <label>Signer email <input name="signer_email" type="email" required autocomplete="off" value="{{ old('signer_email') }}"></label>
        <button type="submit">Send for signature</button>
      </form>
      @if ($error)
        <p class="error" role="alert">Could not create the envelope: {{ $error }}</p>
      @elseif ($errors->any())
        <p class="error" role="alert">{{ $errors->first() }}</p>
      @endif
    </section>

    <section class="card">
      <h2>Agreements</h2>
      <table>
        <thead><tr><th>Signer</th><th>Status</th><th>Sent</th><th></th></tr></thead>
        <tbody>
          @forelse ($agreements as $agreement)
            <tr data-agreement-id="{{ $agreement->id }}" data-envelope-id="{{ $agreement->envelope_id }}">
              <td class="signer"><strong>{{ $agreement->signer_name }}</strong>{{ $agreement->signer_email }}</td>
              <td><span class="status status-{{ $agreement->status }}" data-status="{{ $agreement->status }}">{{ ucfirst($agreement->status) }}</span></td>
              <td>{{ $agreement->created_at->format('Y-m-d H:i') }}</td>
              <td>
                @if ($agreement->status === 'completed')
                  <a href="/agreements/{{ $agreement->id }}/signed.pdf">Signed PDF</a>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="empty">No agreements yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </section>
  </main>
</body>
</html>
