<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>PaymentMethod Generator</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://js.stripe.com/v3/"></script>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:640px;margin:40px auto;padding:0 16px}
    .card{border:1px solid #e5e7eb;border-radius:12px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
    .row{margin:12px 0}
    #pmId{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-family:ui-monospace,Menlo,monospace}
    button{padding:10px 14px;border-radius:8px;border:0;background:#111827;color:#fff;cursor:pointer}
    button[disabled]{opacity:.6;cursor:not-allowed}
    .muted{color:#6b7280;font-size:14px}
    .error{color:#dc2626;font-size:14px;margin-top:8px}
    .success{color:#16a34a;font-size:14px;margin-top:8px}
    .inline{display:inline-flex;gap:8px;align-items:center}
  </style>
</head>
<body>
  <h1>Generate a Stripe PaymentMethod (pm_…)</h1>
  <p class="muted">Use test card <strong>4242 4242 4242 4242</strong>, any future expiry, any CVC.</p>

  <div class="card">
    <div class="row">
      <label for="card-element">Card details</label>
      <div id="card-element" style="padding:10px;border:1px solid #d1d5db;border-radius:8px;"></div>
      <div id="card-errors" class="error"></div>
    </div>

    <div class="row inline">
      <button id="createPm">Create PaymentMethod</button>
      <span id="spinner" class="muted" style="display:none;">Processing…</span>
    </div>

    <div class="row" id="pmRow" style="display:none;">
      <label for="pmId">PaymentMethod ID</label>
      <div class="inline" style="width:100%;">
        <input id="pmId" readonly>
        <button id="copyBtn" type="button">Copy</button>
      </div>
      <div id="success" class="success"></div>
    </div>
  </div>

  <script>
    const stripe = Stripe("{{ $stripeKey }}"); // publishable key from config/services.php
    const elements = stripe.elements();
    const card = elements.create('card');
    card.mount('#card-element');

    const createBtn = document.getElementById('createPm');
    const spinner = document.getElementById('spinner');
    const pmRow = document.getElementById('pmRow');
    const pmIdInput = document.getElementById('pmId');
    const copyBtn = document.getElementById('copyBtn');
    const errEl = document.getElementById('card-errors');
    const okEl = document.getElementById('success');

    createBtn.addEventListener('click', async () => {
      errEl.textContent = '';
      okEl.textContent = '';
      createBtn.disabled = true;
      spinner.style.display = 'inline';

      const { error, paymentMethod } = await stripe.createPaymentMethod({
        type: 'card',
        card: card
      });

      spinner.style.display = 'none';
      createBtn.disabled = false;

      if (error) {
        errEl.textContent = error.message || 'Something went wrong.';
        return;
      }

      pmIdInput.value = paymentMethod.id; // e.g., pm_1ABC...
      pmRow.style.display = 'block';
      okEl.textContent = 'PaymentMethod created! You can copy the pm_… id and use it in your API.';
    });

    copyBtn.addEventListener('click', async () => {
      if (!pmIdInput.value) return;
      await navigator.clipboard.writeText(pmIdInput.value);
      okEl.textContent = 'Copied to clipboard.';
      setTimeout(() => okEl.textContent = '', 1200);
    });
  </script>
</body>
</html>
