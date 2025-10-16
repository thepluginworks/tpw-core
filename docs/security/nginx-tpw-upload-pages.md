# NGINX configuration for TPW Upload Pages security

This snippet blocks direct web access to files stored under `wp-content/uploads/tpw-upload-pages/` while still allowing access via the secure PHP handler `modules/tpw-control/serve.php`.

Add this to your server/site `nginx.conf` (inside the appropriate `server {}` block):

```
# Deny direct access to Upload Pages storage; files are served via serve.php with auth checks
location ^~ /wp-content/uploads/tpw-upload-pages/ {
    # Block everything by default
    deny all;
    return 403;
}

# Optional: if you also store editor media under uploads/tpw-upload-pages/editor/
# they will be denied above and should be accessed through the signed handler URLs.

# (Normal WordPress PHP handling should already be configured. Example:)
# location ~ \.php$ {
#     include fastcgi_params;
#     fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
#     fastcgi_pass php-handler; # adjust to your PHP-FPM upstream
# }
```

Notes:
- The application will generate signed, time-limited URLs pointing to `/wp-content/plugins/tpw-core/modules/tpw-control/serve.php`, which performs permission checks and streams the file.
- Ensure your general PHP handling location passes requests for `serve.php` to PHP-FPM.
- Token TTL defaults to about 10–15 minutes; links expire automatically.
- If you use a CDN or caching layer, bypass/ignore cache for `serve.php` responses to prevent leaking content.
