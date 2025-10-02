# Postcode Lookup

Members forms can auto‑fill Town/County based on a postcode.

Providers:
- Postcodes.io (default, GB only)
- GetAddress.io (requires API key)
- Google Maps (requires API key; supports full address list)

Configure under Member Settings → Postcodes and use Test Lookup to verify.

Full address mode (Google only):
- When enabled by the provider, users can select a street‑level address returned by Google.
- Fields like Address1, Town, County, Postcode, Country are auto‑filled.

Troubleshooting:
- If lookups fail, check your API key and quotas.
- Errors are logged to debug.log when WP_DEBUG is enabled.
