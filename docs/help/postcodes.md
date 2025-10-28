# Postcodes

## Overview
The Postcodes helper resolves town/county/coordinates for a postcode using pluggable providers (Postcodes.io, GetAddress.io, Google). It can also return full address options when supported.

## Key Screens / Shortcodes
- Front‑end: used by TPW screens and shortcodes that offer postcode lookup.
- AJAX action: tpw_lookup_postcode (nonce: tpw_lookup_postcode) returns JSON.

## Hooks
- tpw_postcode_lookup_provider (filter) — Choose provider.
- tpw_postcode_lookup_api_key (filter) — Supply provider API keys.

## Extending
- Call TPW_Postcode_Helper::lookup_postcode( $postcode, $country = 'GB', $mode = 'basic|full' ).
- In full mode, you may pass a street number prefix to filter results.

## References
- Developer Guide → ../developer-guide.md
- Helper: modules/postcodes/class-tpw-postcode-helper.php
- AJAX: modules/postcodes/postcode-ajax.php

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
