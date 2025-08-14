


## Accessing Payment Settings

The payment-related settings are also stored within the same `flexievent_settings` option. You can access them using:

```php
$settings = get_option( 'flexievent_settings', [] );
$currency_symbol = $settings['currency_symbol'] ?? '£';
$currency_code = $settings['currency_code'] ?? 'GBP';
```

### Payment Settings and Defaults

| Key               | Default Value | Description |
|------------------|----------------|-------------|
| `currency_symbol` | `£`            | Currency symbol used in price displays (e.g., £10.00) |
| `currency_code`   | `GBP`          | ISO 4217 currency code used for integrations or display |