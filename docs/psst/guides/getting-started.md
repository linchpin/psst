# Getting started

Install with Composer from Linchpin's package repository and activate.

```bash
composer require linchpin/psst
```

Activation creates two pages: **Share a Secret** at `/share/` with the create pattern, and
**Secret** at `/s/` with the viewer block. Secret links look like `/s/{id}#{key}`.

1. Set the create page as the front page under Settings → Reading, or link to it.
2. Open Settings → Psst (Mantle → Psst when Mantle is installed) to choose which
   expiration options senders see, the size cap, and rate limits.
3. Optionally add Cloudflare Turnstile keys to challenge the create form.

The plugin needs HTTPS: browsers only expose the Web Crypto API on secure origins.
