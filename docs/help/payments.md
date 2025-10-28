# Payments

## Overview
Payments provides lightweight helpers and webhooks to log completed transactions (e.g., SumUp, Square) and expose settings like currency for dependent plugins.

## Key Screens / Shortcodes
- Settings → TPW Core → Payments (gateway settings, currency)
- Webhook endpoint(s): modules/payments/webhook.php (for gateways to call)

## Hooks
- tpw_payment_completed (action) — Fires when a gateway webhook marks a payment completed. Args: gateway, reference, email, amount, payload.

## Extending
- Subscribe to tpw_payment_completed to update your domain models (orders, entries). Validate payloads and idempotency yourself.
- Use get_option('flexievent_settings') for currency_symbol and currency_code where needed.

## References
- Developer Guide → ../developer-guide.md
- Logger: modules/payments/class-tpw-payment-logger.php
- Settings UI: modules/payments/views/payment-settings-page.php

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
