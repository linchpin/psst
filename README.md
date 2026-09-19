# Psst

**Pretty Secure Secret Transmissions.** A WordPress plugin for sharing one-time, expiring
secrets. The secret is encrypted in the sender's browser before it is sent; the server
stores ciphertext it cannot read, the link carries the only key, and the secret is
destroyed the moment it is viewed.

Version: <!-- x-release-please-start-version -->1.0.5<!-- x-release-please-end -->

## How it works

1. The sender types a secret into the **Secret Form** block, optionally adds a pass phrase,
   and picks how long the link should live.
2. The browser generates a random 256-bit key, encrypts the text with AES-256-GCM, and
   posts only the ciphertext. The key never leaves the browser except inside the link the
   sender copies: `https://example.com/s/{id}#{key}`.
3. The recipient opens the link and sees an interstitial. Nothing is consumed until they
   click **View Secret**, so link previews and mail scanners cannot burn it.
4. Clicking View fetches the ciphertext exactly once, deletes it server-side in the same
   request, and decrypts it in the recipient's browser. A pass phrase, if set, is checked
   locally against a key-derived check value, so a typo does not cost the secret.
5. Anything unread expires on schedule through Action Scheduler, and a read-time check
   enforces expiry even if the scheduler is behind.

Administrators can see that a secret exists, when it expires, and shred it. They cannot
read it: there is no key on the server to read it with.

## Requirements

- WordPress 6.9 or later, with a block theme.
- PHP 8.3 or later.
- HTTPS. The Web Crypto API only runs in secure contexts.

## Installation

Psst is published to Linchpin's private Composer repository.

```bash
composer require linchpin/psst
```

Activating the plugin creates two pages, **Share a Secret** (`/share/`) holding the create
pattern and **Secret** (`/s/`) holding the viewer block, and registers the `/s/{id}` route.
Make the create page your front page, or link to it from wherever you like, then check
**Settings → Psst** (or **Mantle → Psst** when Mantle is installed).

## Blocks and patterns

| Name | What it does |
| --- | --- |
| `psst/secret-form` | The create form. Swaps in place to the share-link confirmation, with a shred button and editable FAQ inner blocks. |
| `psst/secret-viewer` | The interstitial, pass phrase prompt, reveal and "no longer available" states. Lives on the viewer page. |
| Pattern `psst/create-page` | Heading, intro and the form. What the front page holds. |
| Pattern `psst/cross-sell` | "Have something to share?" with a button bound to the create page URL. |

Block styles use theme preset variables only (`--wp--preset--color--primary`, spacing and
font-size presets) and render buttons with core's button classes, so the theme's palette
and button styles apply without any plugin CSS overrides.

## REST API

Namespace `psst/v1`. Every response is `Cache-Control: no-store` and `X-Robots-Tag: noindex`.

| Route | Purpose |
| --- | --- |
| `POST /secrets` | Create. Public, rate limited, honeypot, optional Turnstile. Returns the id, URL and a one-time management token. |
| `POST /secrets/{id}/reveal` | The only consuming call. Must be `application/json`. Returns the envelope once and destroys the row atomically. |
| `DELETE /secrets/{id}` | Shred. Needs the `X-Psst-Manage-Token` header, or an administrator's cookie session. |
| `GET /config` | Public client configuration. |
| `GET/POST /settings`, `POST /settings/reset`, `GET /admin/secrets`, `GET /admin/health` | Administrators only. |

## Settings

Pages, enabled expiration choices and the default, the plaintext size cap, rate limits,
an optional Cloudflare Turnstile challenge, the trusted proxy header, and whether to
delete everything on uninstall. The Turnstile secret is stored write-only. Either Turnstile
key can instead be defined in `wp-config.php` with `PSST_TURNSTILE_SITE_KEY` and
`PSST_TURNSTILE_SECRET_KEY`; a defined constant wins and locks its field on the screen.

## Hooks

Actions: `before_psst_init`, `after_psst_init`, `psst_ready`, `psst_activated`,
`psst_secret_created`, `psst_secret_revealed`, `psst_secret_shredded`,
`psst_secret_expired`, `psst_secret_destroyed`, `psst_secrets_swept`. None receives
content.

Filters: `psst_secret_args`, `psst_secret_labels`, `psst_schedule_options`,
`psst_slug_length` (bytes of entropy, floor 16), `psst_date_time_format`,
`psst_url_base`, `psst_create_page_url`, `psst_client_config`, `psst_create_response`,
`psst_create_challenge`, `psst_rate_limit`, `psst_client_ip`, `psst_max_plaintext_bytes`,
`psst_max_request_bytes`, `psst_min_kdf_iterations`, `psst_sweep_batch_size`,
`psst_frame_options`, `psst_disable_page_cache`, `psst_content_security_policy`.

A reasonable Content-Security-Policy for the secret pages, opt-in through the filter:

```
default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'
```

## Upgrading from 1.x

Version 2 is a rewrite. On first load it removes the 1.x secrets (encrypted with a
server-side key this version does not use), the old options and cron events, and answers
old `/secret/...` links with 410 and the "no longer available" state. If `PSST_CRYPTO_KEY`
is still defined an admin notice asks you to remove it. The Foundation-based `psst` theme
is no longer needed; any block theme works.

## Development

```bash
composer install
npm run install:all
npm run build:all          # admin app + blocks
composer test              # lint, phpstan, phpunit
npm run test:unit          # crypto vectors
npm run test:e2e           # Playwright against WordPress Playground
npm run playground:start   # a local site on :9400
bash scripts/build.sh      # the distributable zip
```

## License

GPL-2.0-or-later. © Linchpin.
