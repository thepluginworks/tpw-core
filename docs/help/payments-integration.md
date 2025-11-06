# Payments Integration (for external plugins)

This page shows how an external plugin (e.g., FlexiTicket or RSVP) can plug into TPW Core’s Payments system — with a focus on Square — using Core’s styling, data contracts, and helper methods.

Applies to: front‑end forms and custom wp‑admin pages you own.

---

## 1) Enqueue Core UI + Payments bootstrap

Use Core CSS for consistent UI and the tiny JS bootstrap that mounts Square:

- CSS
  - `tpw-admin-ui` → layout/typography/widgets (scoped to `.tpw-admin-ui`)
  - `tpw-buttons`  → button styles
- JS
  - `tpw-core-payments` → exposes `window.TPW_Core_Payments.boot(config)`
  - Load the Square Web Payments SDK (client-side) yourself.

Example (PHP):

```php
if ( defined('TPW_CORE_URL') ) {
    wp_enqueue_style('tpw-admin-ui', TPW_CORE_URL . 'assets/css/tpw-admin-ui.css', [], null);
    wp_enqueue_style('tpw-buttons',  TPW_CORE_URL . 'assets/css/tpw-buttons.css',  [], null);
}
// Square Web Payments SDK (client-side UI)
wp_enqueue_script('square-web-payments', 'https://sandbox.web.squarecdn.com/v1/square.js', [], null, true);

// TPW Core Payments bootstrap (registered by Core; you enqueue it)
wp_enqueue_script('tpw-core-payments');

// Option A: Localize config for your page manually (see section 6)
wp_localize_script('tpw-core-payments', 'tpwPaymentsConfig', $cfg);

// Option B: Use Core helper to enqueue SDK + bootstrap and localize default config
if ( function_exists('tpw_core_enqueue_payments_assets') ) {
  tpw_core_enqueue_payments_assets(); // or pass your own $cfg array
}
```

Wrap your form with the Core scope:

```html
<div class="tpw-admin-ui">
  <!-- your form -->
</div>
```

---

## 2) Payment method picker contract

- Radio inputs must use name="tpw_payment_method" and a slug value that matches Core’s methods (e.g., `square`, `bacs`, `cheque`, `cash`, `card-on-the-day`, `sumup`, `woocommerce`).
- Show/hide method-specific UI based on the selected radio.

Minimal structure:

```html
<fieldset class="tpw-payment-methods">
  <legend>Payment method</legend>
  <label><input type="radio" name="tpw_payment_method" value="square"> Pay by Card (via Square)</label>
  <label><input type="radio" name="tpw_payment_method" value="bacs"> Bank Transfer (BACS)</label>
  <!-- …others as enabled on the site -->
</fieldset>

<!-- Square UI mount point (convention) -->
<div id="tpw-square-container" hidden></div>
<div id="tpw-square-errors" role="alert" aria-live="polite"></div>
```

Container IDs (conventions used by Core-compatible UIs):
- `#tpw-square-container` — mount the Square “Card”/“Payment” element here.
- `#tpw-square-errors` — surface validation or SDK errors.

Tip: Read active methods via `TPW_Payments_Manager::get_active_methods()` to show only available options.

---

## 3) JS events Core-compatible code listens for

Fire this event whenever the selected method changes so any Core-compatible UI can react:

- Event name: `tpw_payment_method_changed`
- Target: `document`
- Detail payload: `{ method: '<slug>' }`

Example:

```js
document.addEventListener('change', (e) => {
  const el = e.target;
  if (el && el.name === 'tpw_payment_method') {
    const method = el.value;
    document.dispatchEvent(new CustomEvent('tpw_payment_method_changed', { detail: { method } }));
  }
});
```

With the bootstrap, you don’t need to manually mount/unmount Square; the bootstrap listens for the event and does it for you.

---

## 4) Create a payment record (server side)

Use Core’s helper to persist a payment row that your plugin controls. This does not call gateway APIs; it stores the payment intent/metadata for your own flow.

Method: `TPW_Core_Payments::create_payment( array $args )`

Args (subset):
- `submission_id` (int) — your owning object ID (order/entry/etc.)
- `guest_id` (int|null) — optional sub‑entity
- `amount` (float) — base amount (Core will apply offline surcharges automatically for BACS/Cheque/Cash/Card-on-the-day)
- `payment_method` (string) — slug (e.g., `square`)
- `paid_by` (string) — email/name for traceability
- Optional: `payment_reference`, `checkout_url`, `notes`

Returns:
- `success` (bool), `payment_id` (int), `payment_reference` (string), `checkout_url` (string)

Example (PHP):

```php
$result = TPW_Core_Payments::create_payment([
    'submission_id'  => (int) $order_id,
    'amount'         => (float) $amount,
    'payment_method' => 'square',
    'paid_by'        => (string) $email,
    'notes'          => 'FlexiTicket checkout',
]);

if (!$result['success']) {
    // Handle error (e.g., log and show message)
}

// For redirect-style gateways you’d then redirect to $result['checkout_url'] if present.
```

Notes:
- For online “on-page” gateways like Square, you’ll typically capture on the same page (via the SDK) and then store the gateway reference back into the Core record (set `payment_reference`).

---

## 5) Square capture flow (with Core bootstrap)

Client side (browser):
1) Enqueue Square Web Payments SDK and `tpw-core-payments`.
2) Call `TPW_Core_Payments.boot(window.tpwPaymentsConfig)` once the page is ready; the bootstrap will mount Square into `#tpw-square-container` when the `square` method is selected and surface errors to `#tpw-square-errors`.
3) On submit, call `api.tokenize()` to get a nonce, then POST to your WP endpoint (AJAX/admin‑post).

Server side (your endpoint, PHP):
1) Call `TPW_Square_Gateway::process_payment([ 'nonce' => $nonce, 'amount' => $amount, 'submission_id' => $id, 'reference_id' => $yourRef, 'member_name' => $name, 'payment_id' => $tpw_payment_id ])` to charge via Square.
2) On success, call `TPW_Core_Payments::create_payment()` (or update your own domain records) with the `payment_reference` returned by Square and redirect to a thank‑you page.

Important:
- Core does not enqueue the Square SDK for you.
- Use `TPW_Core_Payments::tpw_core_calculate_payable_total($amount, 'square')` if you want to preview surcharges client‑side (server remains source of truth).

Bootstrap API (summary):
- `TPW_Core_Payments.boot(config) => Promise<api>`
  - Mounts/unmounts Square automatically when `tpw_payment_method_changed` fires.
  - Returns `api` with:
    - `method` — currently selected method (e.g., `square` or `none`)
    - `tokenize(): Promise<{ ok:boolean, nonce?:string, errors?:Array }>` — tokenizes current Square card
    - `onNonce(fn: (nonce:string) => void)` — optional callback after successful tokenization
    - `unmount()` — manually unmount Square
    - `getSquareCard()` — returns the underlying Square Card instance (or null)

---

## 6) Localize payment config to your JS

Expose currency and (optionally) Square config to your front‑end script:

```php
$cfg = [
  'currency' => [
    'code'   => function_exists('tpw_core_get_currency_code') ? tpw_core_get_currency_code() : 'GBP',
    'symbol' => function_exists('tpw_core_get_currency_symbol') ? tpw_core_get_currency_symbol() : '£',
  ],
  'square' => [
    'appId'      => get_option('tpw_square_app_id'),
    'locationId' => get_option('tpw_square_location_id'),
    'sandbox'    => (get_option('tpw_square_sandbox_mode') === '1'),
  ],
  'activeMethods' => (class_exists('TPW_Payments_Manager') ? TPW_Payments_Manager::get_active_methods() : []),
];
wp_register_script('my-checkout', plugins_url('assets/js/checkout.js', __FILE__), ['jquery'], '1.0', true);
wp_localize_script('my-checkout', 'tpwPaymentsConfig', $cfg);
wp_enqueue_script('my-checkout');
```

---

## 7) End‑to‑end example (front‑end)

HTML:

```html
<form id="ticket-checkout" class="tpw-admin-ui">
  <label>Amount <input type="number" step="0.01" min="0" id="amount" value="10.00"></label>

  <fieldset>
    <legend>Payment method</legend>
    <label><input type="radio" name="tpw_payment_method" value="square" checked> Pay by Card (via Square)</label>
    <label><input type="radio" name="tpw_payment_method" value="bacs"> Bank Transfer (BACS)</label>
  </fieldset>

  <div id="tpw-square-container"></div>
  <div id="tpw-square-errors" role="alert" aria-live="polite"></div>

  <button type="submit" class="tpw-btn tpw-btn-primary">Pay now</button>
</form>
```

JS (outline using the bootstrap):

```js
(async function(){
  // Emit change event for method toggles (bootstrap listens for this)
  document.addEventListener('change', (e) => {
    if (e.target && e.target.name === 'tpw_payment_method') {
      document.dispatchEvent(new CustomEvent('tpw_payment_method_changed', { detail: { method: e.target.value } }));
    }
  });

  const api = await window.TPW_Core_Payments.boot(window.tpwPaymentsConfig || {});

  document.getElementById('ticket-checkout').addEventListener('submit', async (e) => {
    e.preventDefault();
    const method = (new FormData(e.currentTarget)).get('tpw_payment_method');
    const amount = parseFloat(document.getElementById('amount').value || '0');

    if (method === 'square') {
      // Get a one-time token via bootstrap
      const resTok = await api.tokenize();
      if (!resTok.ok) return; // bootstrap already surfaced errors
      // Send to your WP endpoint; server calls TPW_Square_Gateway::process_payment()
      const res = await fetch('/?action=my_square_charge', { // your route
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nonce: resTok.nonce, amount })
      }).then(r => r.json());

      if (res?.success) {
        // Optional: also persist via TPW_Core_Payments::create_payment() on the server
        window.location.assign(res.redirect || '/thank-you/');
      } else {
        document.getElementById('tpw-square-errors').textContent = res?.error || 'Payment failed';
      }
    } else {
      // Non-card methods (e.g., BACS): create a TPW payment record server-side and show instructions
      // … your own flow here
    }
  });
})();
```

---

## 8) Webhooks and completion

- Core exposes a lightweight webhook example under `modules/payments/webhook.php` and a central action: `tpw_payment_completed`.
- Listen to `tpw_payment_completed` in your plugin to update domain models (orders/entries). Validate payloads and idempotency in your handler.

See also:
- `TPW_Core_Payments` (helpers, surcharges, row creation)
- `TPW_Square_Gateway` (server-side Square charge using SDK)
- `TPW_Payments_Manager::get_active_methods()` (discover site-enabled methods)
- Branding/UI: `docs/help/tpw-branding.md`, Payments UI: `docs/tpw-payments-ui.md`
