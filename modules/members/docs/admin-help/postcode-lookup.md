# Address Lookup

Members forms can use Core address lookup to help fill address fields from a GB postcode when a live provider is enabled.

Providers:
- None
- Ideal Postcodes
- Fetchify

Configure under Member Settings → Address Lookup and use Test Lookup to verify the active live provider.

Current Core status:
- None: member forms show manual address fields only, with no lookup UI.
- Ideal Postcodes: live GB address lookup is available when an API key is configured.
- Fetchify: settings are scaffolded only in this Core release; manual address entry remains active.

Troubleshooting:
- If Ideal Postcodes lookups fail, check your API key and account limits.
- If the provider is set to None, or to a scaffolded provider, lookup controls stay hidden by design.
