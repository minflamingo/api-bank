# APIBank Deploy Notes

Production domain: `apibank.com.vn` / `www.apibank.com.vn`.

Runtime files intentionally not committed:

- `.env` and `.env.*`
- `vendor/`, `node_modules/`
- storage logs/cache/sessions/views
- public storage symlink
- TLS private keys/certificates
- database dumps

The legacy v1 runtime lives on the VPS at `/www/wwwroot/apibank-v1.0` and is exposed by the nginx config through `/v1.0`, `/v1`, and old `/api/*` compatibility rewrites.
