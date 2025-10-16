# Backlog

## TPW Control – Secure Uploads (optional enhancements)

- Configurable signed URL TTL
  - Add a setting to control the default lifetime of signed links generated for Upload Pages (files, thumbnails, and editor images).
  - Scope: global plugin setting (with sensible minimum, e.g., 60s; default ~10–15 minutes). Potential per-page override in a later phase.
  - Acceptance: public and admin links respect the configured TTL without code changes; documentation updated.

- Default download vs inline behavior
  - Add a setting to choose whether file responses should default to inline display or forced download when served via the secure handler.
  - Scope: global plugin setting; may later support per-file or per-type behavior (e.g., images inline, docs download).
  - Acceptance: handler honors the configured default while still allowing explicit overrides via the `dl` query flag; documentation updated.
