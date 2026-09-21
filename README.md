# Psst

**Pretty Secure Secret Transmissions.** A WordPress plugin by Linchpin for sharing one-time,
expiring secrets. The secret is encrypted in the sender's browser before it is sent, the
server stores ciphertext it has no key for, the link carries the only key, and the secret is
destroyed the moment it is viewed.

Please see [CHANGELOG.md](CHANGELOG.md) for the latest information on the plugin.

<!-- x-release-please-start-version -->
## Latest Release: 2.2.0
<!-- x-release-please-end -->

| Workflow | Status |
|----------|--------|
| Release | ![Build Status](https://github.com/linchpin/psst/actions/workflows/release-please.yml/badge.svg) |
| PHP | ![PHP Status](https://github.com/linchpin/psst/actions/workflows/php.yml/badge.svg) |
| JavaScript | ![JavaScript Status](https://github.com/linchpin/psst/actions/workflows/js.yml/badge.svg) |
| Plugin Check | ![Plugin Check Status](https://github.com/linchpin/psst/actions/workflows/plugin-check.yml/badge.svg) |
| WP Version Check | ![WP Version Checker Status](https://github.com/linchpin/psst/actions/workflows/wp-version-checker.yml/badge.svg) |
| WordPress.org Deploy | ![WordPress.org Deploy Status](https://github.com/linchpin/psst/actions/workflows/wordpress-plugin-deploy.yml/badge.svg) |

## The server cannot read your secrets

Psst is a zero-knowledge design. Encryption and decryption happen only in the browser.

1. The sender's browser generates a random 256-bit key and encrypts the text with
   AES-256-GCM using the Web Crypto API. Only the ciphertext is posted to WordPress.
2. The key travels in the share link's `#fragment`, which browsers never send in an HTTP
   request: `https://example.com/s/{id}/#{key}`. It is not logged, not stored, and never
   reaches the server. A create request that carries a field named `key`, `passphrase`,
   `secret`, `plaintext`, or `message` is rejected outright.
3. The recipient opens the link and sees an interstitial. Nothing is consumed by a `GET`, so
   link previewers, mail scanners, and reloads cannot burn the secret.
4. Clicking **View Secret** sends a JSON `POST` that claims the row with a single conditional
   `UPDATE`, returns the envelope exactly once, and hard-deletes it in the same request. The
   recipient's browser decrypts locally with the key from the fragment, then scrubs the
   fragment from the URL.
5. An optional pass phrase is folded into the key derivation in the browser and checked
   locally against a stored check value, so a typo does not cost the secret and the pass
   phrase is never transmitted.

Administrators can see that a secret exists, when it expires, and shred it. They cannot read
it, and neither can anyone with the database, because there is no key on the server to read
it with. Every claim above is asserted by the checked-in unit and end-to-end suites. The full
walkthrough is in [How the encryption works](docs/psst/guides/encryption.md).

One opt-in feature trades this away, and only for the secrets it is used on. Emailing a share
link means giving the server the key, because the server is what sends the mail — and the key
then sits in the recipient's mailbox. It is off by default and the sender is shown the
tradeoff before choosing it. See [Accounts](docs/psst/guides/accounts.md#emailing-a-link).

## Key Features

| Feature | Details |
|---------|---------|
| Browser-side encryption | AES-256-GCM with HKDF-derived keys, PBKDF2 for pass phrases, plain Web Crypto, no dependencies |
| One-shot reveal | Atomic conditional claim; concurrent callers race for one row and the loser gets a 404 |
| Preview-safe links | `GET` never consumes; the reveal requires `Content-Type: application/json` and answers `415` to anything else |
| Local pass-phrase retry | Wrong pass phrases are detected in the browser against a check value with no second request |
| Expiry | Eleven choices from five minutes to one week, enforced by Action Scheduler and again at read time |
| Shred | Sender's one-time management token or an administrator can destroy a secret before it is read |
| Gutenberg blocks | `psst/secret-form` and `psst/secret-viewer`, plus `psst/login-form`, `psst/register-form` and `psst/account` for the optional account layer |
| Hardening | `no-store`, `noindex`, `no-referrer`, `nosniff`, `X-Frame-Options`, `DONOTCACHEPAGE`, opt-in CSP on secret pages |
| Abuse controls | Per-IP and global rate limits, honeypot, optional Cloudflare Turnstile that fails closed |
| Optional accounts | Front end sign in, registration and an account area; a signed-in sender's record of what they sent (metadata only), an optional wp-admin lockout, and optional email delivery. All off by default |
| Admin screen | Settings, a metadata-only list of live secrets with shred, and a health check; under Settings or Mantle |
| Extensibility | Sixteen actions and thirty-six filters. Only `psst_share_email_body`, part of the opt-in email feature, ever sees a key; nothing else receives a key, pass phrase, or plaintext |

## Requirements

* WordPress 6.9 or later, with a block theme
* PHP 8.3 or later
* HTTPS. The Web Crypto API runs only in secure contexts.
* Composer (for development)

## Installation

Psst is on the [WordPress.org plugin directory](https://wordpress.org/plugins/psst/). Install it
from **Plugins → Add New** and activate it.

It is also published to Linchpin's private Composer repository for Composer-managed sites.

```bash
composer require linchpin/psst
```

Activating the plugin creates two pages, **Share a Secret** (`/share/`) holding the create
pattern and **Secret** (`/s/`) holding the viewer block, and registers the `/s/{id}/` route.
Make the create page your front page or link to it, then review **Settings → Psst**
(**Mantle → Psst** when Mantle is installed).

Release zips are attached to each GitHub release and can be uploaded from the Plugins screen.
See [Installation](docs/psst/getting-started/installation.md) for the details, including what
to check after a Composer deploy that never fires activation.

## Development

### Available Scripts

In the plugin root directory, you can run:

#### `npm run install:all`

Installs JavaScript dependencies for the root admin app and the `blocks/` project.

#### `npm run build:all`

Builds the admin app into `build/` and the two blocks into `blocks/build/`. Neither directory
is committed, so a fresh checkout needs this before the editor will load.

#### `npm run test:unit`

Runs the browser crypto vectors under Node.

#### `npm run test:e2e`

Runs the Playwright suite against WordPress Playground.

#### `composer test`

Runs every static gate (syntax lint, PHPCS, php-cs-fixer, PHPStan) and then the PHPUnit suite.

#### `composer build`

Builds the distributable `build/psst.zip`.

### Local Development Setup

1. Run `composer install` to install PHP dependencies, including Action Scheduler
2. Run `npm run install:all` to install JavaScript dependencies
3. Run `npm run build:all`, or `npm run start` and `npm run start:blocks` for watch builds
4. Run `npm run playground:start` for a throwaway site on `http://localhost:9400`

## Architecture

* **Controllers, Models, Helpers** under `includes/`, namespace `Linchpin\Psst`, discovered and
  booted by `Core\Bootstrap` on `plugins_loaded`
* **Blocks** under `blocks/src/`, `apiVersion` 3, server-rendered with Interactivity API stores
* **Protocol** in one file, `blocks/src/shared/crypto.js`, validated server-side by
  `Model\Envelope`
* **REST API** in the `psst/v1` namespace, hidden from the index
* **Admin app** under `src/admin/`, React with WordPress components and DataViews

## Documentation

Full documentation lives in [`docs/`](docs/psst/index.md) and is published to the Linchpin
docs site by the `sync-docs` workflow.

| Section | Contents |
|---------|----------|
| [Getting started](docs/psst/getting-started/index.md) | Requirements, installation, configuration, your first secret |
| [Guides](docs/psst/guides/index.md) | How the encryption works, accounts, hardening, upgrading from 1.x |
| [Reference](docs/psst/reference/index.md) | REST API, hooks, blocks and patterns, development |
| [Troubleshooting](docs/psst/troubleshooting.md) | Symptom-to-check reference |

## Upgrading from 1.x

Version 2 is a rewrite. On first load it removes the 1.x secrets, which were encrypted with a
server-side key this version does not use, along with the old options and cron events, and
answers old `/secret/...` links with `410`. If `PSST_CRYPTO_KEY` is still defined an admin
notice asks you to remove it. The Foundation-based `psst` theme is no longer needed. See
[Upgrading from 1.x](docs/psst/guides/upgrading-from-1x.md).

## Support

* **GitHub Issues**: [Report bugs or request features](https://github.com/linchpin/psst/issues)
* **Documentation**: See [`docs/`](docs/psst/index.md)

## License

GPL-2.0-or-later
