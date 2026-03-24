# Address Lookup

## Overview
The Core address lookup helper normalizes provider selection and lookup behavior for manual-entry-aware forms. Core now supports only None, Ideal Postcodes, and Fetchify, with manual address entry remaining the fallback for every form.

## Key Screens / Shortcodes
- Front‑end: used by TPW screens and shortcodes that offer address lookup.
- AJAX action: tpw_lookup_postcode (nonce: tpw_lookup_postcode) returns JSON.

## Hooks
- tpw_postcode_lookup_provider (filter) — Choose provider.
- tpw_postcode_lookup_api_key (filter) — Supply provider API keys.

## Extending
- Call TPW_Postcode_Helper::lookup_postcode( $postcode, $country = 'GB', $mode = 'basic|full' ).
- Use TPW_Postcode_Helper::should_render_lookup_ui() to decide whether lookup controls should render at all.
- In full mode, you may pass a street number prefix to filter results when the active provider supports full address lists.

## References
- Developer Guide → ../developer-guide.md
- Helper: modules/postcodes/class-tpw-postcode-helper.php
- AJAX: modules/postcodes/postcode-ajax.php

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
