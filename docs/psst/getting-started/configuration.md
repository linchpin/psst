---
title: Configuration
---

Everything the plugin reads at runtime lives in one option, one write-only option for the Turnstile secret, and a handful of constants. The admin screen edits the option; constants are set in `wp-config.php`. None of it affects the encryption, which is fixed by the protocol and runs in the browser.

## The admin screen

Open **Settings → Psst**. When the Mantle plugin is active the screen moves to **Mantle → Psst**. Either way it requires the `manage_options` capability and has five sections. The sections are links rather than tab state, so `?tab=pages` is a real address that can be bookmarked, shared and survives a save.

| Tab | What it does |
| --- | --- |
| Settings | Every key in the table below, plus Save and Reset to defaults. Reset preserves the two page ids. |
| Pages | Every page the plugin needs, with the state of each: whether it exists, is published, and still contains the block that makes it work. Described below. |
| Secrets | A metadata-only list of live secrets: short id, created, expires, status, pass phrase flag, size, and TTL. Each row has a **Shred** action. There is no way to see content because there is no key on the server. |
| Health | Action Scheduler status, next sweep, active count, WP-Cron status, Turnstile status, remaining 1.x data, both page URLs, and the version. |
| About | The standard About Linchpin page, rendered from [`@linchpinagency/ui`](https://github.com/linchpin/ui). The copy is the agency's and is the same in every Linchpin plugin. |

The screen talks to the administrator REST routes described in the [REST API reference](../reference/rest-api.md) using a standard REST nonce.

### Pages

**Settings → Psst → Pages** lists every page the plugin needs — the create and viewer pages, and the three account pages once accounts are enabled — and reports the state of each: whether it exists, whether it is published, and whether it still contains the block that makes it work.

That last check is the point of the screen. A page can be selected in the settings and do nothing, because it was trashed, left as a draft, or edited until the block was removed, and all three look the same from a dropdown. Each page can be swapped for another, or replaced with a freshly created one that already has the right blocks in it.

## Settings

<!-- wp:docspress/fields {"title":"psst_settings","description":"Stored as one autoloaded option. Unknown keys are dropped on save and absent keys keep their stored value.","fields":[{"name":"ttl_options","type":"array","required":false,"defaultValue":"all eleven","description":"Which expiration choices senders see, in minutes. Values outside the catalog are discarded; an empty set falls back to the full catalog.","values":"10080, 4320, 1440, 720, 360, 240, 120, 60, 30, 15, 5","deprecated":false},{"name":"ttl_default","type":"number","required":false,"defaultValue":"10080","description":"The preselected choice. Forced to be a member of <code>ttl_options</code>, otherwise the first enabled option is used.","values":"","deprecated":false},{"name":"max_plaintext_bytes","type":"number","required":false,"defaultValue":"32768","description":"Cap on the UTF-8 byte length of a secret. The browser enforces it before encrypting; the server enforces the matching ciphertext length. Clamped to the range on save.","values":"1024 to 262144","deprecated":false},{"name":"rate_limit_create_per_hour","type":"number","required":false,"defaultValue":"10","description":"Create requests allowed per client IP per hour. The shred limit is three times this value. Zero disables.","values":"0 or more","deprecated":false},{"name":"rate_limit_create_global_per_hour","type":"number","required":false,"defaultValue":"300","description":"Create requests allowed site-wide per hour, counted only after the per-IP check passes. Zero disables.","values":"0 or more","deprecated":false},{"name":"rate_limit_reveal_per_hour","type":"number","required":false,"defaultValue":"60","description":"Reveal requests allowed per client IP per hour. Zero disables.","values":"0 or more","deprecated":false},{"name":"turnstile_site_key","type":"string","required":false,"defaultValue":"","description":"Cloudflare Turnstile site key. The challenge is enabled only when both this and the secret are set.","values":"","deprecated":false},{"name":"trusted_proxy_header","type":"enum","required":false,"defaultValue":"","description":"When set to <code>cloudflare</code>, the client IP used for rate limiting and Turnstile is read from <code>CF-Connecting-IP</code> before <code>REMOTE_ADDR</code>. Leave empty unless every request reaches PHP through Cloudflare.","values":"'' or cloudflare","deprecated":false},{"name":"delete_on_uninstall","type":"boolean","required":false,"defaultValue":"true","description":"Whether uninstall removes secrets, options, capabilities, and scheduled actions.","values":"","deprecated":false},{"name":"create_page_id","type":"number","required":false,"defaultValue":"0","description":"The page holding the create form. Feeds the <code>createUrl</code> handed to the blocks and the cross-sell pattern.","values":"","deprecated":false},{"name":"reveal_page_id","type":"number","required":false,"defaultValue":"0","description":"The page holding the viewer block. The <code>/s/{id}/</code> rewrite rule exists only while this points at a published page. Changing it flags a rewrite flush.","values":"","deprecated":false,{"name":"accounts_enabled","type":"boolean","required":false,"defaultValue":"false","description":"Master switch for the front end account layer. Off, none of the keys below do anything and the plugin behaves exactly as it did before they existed. Switching it on for the first time creates the sign-in, registration and account pages. See <a href=\"../guides/accounts.md\">Accounts</a>.","values":"","deprecated":false},{"name":"allow_registration","type":"boolean","required":false,"defaultValue":"false","description":"Whether the front end offers account creation. WordPress still decides: closed registration in Settings &rarr; General, or network-wide on multisite, stays closed.","values":"","deprecated":false},{"name":"require_login_to_create","type":"boolean","required":false,"defaultValue":"false","description":"Whether creating a secret requires a signed-in user. Off keeps anonymous sending, which is the default behaviour of the plugin.","values":"","deprecated":false},{"name":"block_admin_access","type":"boolean","required":false,"defaultValue":"false","description":"Redirect users without the <code>edit_posts</code> capability away from wp-admin, their profile screen included, and hide the admin bar for them. Super admins are never affected. The capability is filterable through <code>psst_admin_access_capability</code>. <code>wp-login.php?psst=bypass</code> is the escape hatch.","values":"","deprecated":false},{"name":"history_enabled","type":"boolean","required":false,"defaultValue":"false","description":"Record metadata about the secrets a signed-in sender creates, so they can see what became of them. Never the secret, which the server cannot read either.","values":"","deprecated":false},{"name":"history_retention_days","type":"number","required":false,"defaultValue":"30","description":"How long a history row is kept, independent of the secret it describes. Pruned by the daily sweep.","values":"1 to 3650","deprecated":false},{"name":"email_delivery_enabled","type":"boolean","required":false,"defaultValue":"false","description":"Whether a sender may have Psst email the share link. <strong>This is the one feature that gives the server a decryption key</strong>, and the key then sits in the recipient\u2019s mailbox. Read <a href=\"../guides/accounts.md#emailing-a-link\">Emailing a link</a> before enabling it.","values":"","deprecated":false},{"name":"login_page_id","type":"number","required":false,"defaultValue":"0","description":"The page holding the Sign In Form block. <code>wp_login_url()</code> and a plain GET of wp-login.php both resolve here.","values":"","deprecated":false},{"name":"register_page_id","type":"number","required":false,"defaultValue":"0","description":"The page holding the Create Account Form block.","values":"","deprecated":false},{"name":"account_page_id","type":"number","required":false,"defaultValue":"0","description":"The page holding the Account block, and where a user blocked from wp-admin is sent.","values":"","deprecated":false}}],"searchable":true,"compact":false} /-->

The expiration catalog is fixed at eleven values and can be relabeled or reordered with the `psst_schedule_options` filter. Filters can also override the plaintext cap and rate limits per request; see the [hook reference](../reference/hooks.md).

Every account key is `false` or `0` by default, so an existing install that updates Psst gains no login page, no registration, no new data and no change to wp-admin until somebody turns them on.

## Cloudflare Turnstile

Turnstile adds a bot challenge to the create form. It is optional and off until both keys are present.

<!-- wp:docspress/flow {"start":1,"steps":[{"title":"Create a widget","content":"<p>In the Cloudflare dashboard, add a Turnstile widget for the site's hostname and copy the site key and secret key.</p>"},{"title":"Enter the site key","content":"<p>Paste it into <strong>Turnstile site key</strong> on the settings tab. It is public and is rendered into the form as a <code>data-sitekey</code> attribute.</p>"},{"title":"Enter the secret","content":"<p>Paste it into the secret field on the same tab, or define <code>PSST_TURNSTILE_SECRET_KEY</code> in <code>wp-config.php</code>. The constant takes precedence and the field becomes read-only.</p>"},{"title":"Verify","content":"<p>The Health tab reports Turnstile as on. The create form now loads <code>challenges.cloudflare.com/turnstile/v0/api.js</code> and posts the token as <code>challenge</code>.</p>"}]} /-->

The secret is stored in its own non-autoloaded option, `psst_turnstile_secret`, and is never returned by any REST route. The settings payload reports only whether a secret exists and whether it came from the constant. Saving an empty string deletes the stored secret; omitting the field leaves it untouched. Verification fails closed: a network error or a non-success response from Cloudflare refuses the create with `403 psst_challenge_failed`.

## Constants

<!-- wp:docspress/fields {"title":"Constants read from wp-config.php","description":"Set these before plugins load. None is required.","fields":[{"name":"PSST_TURNSTILE_SECRET_KEY","type":"string","required":false,"defaultValue":"","description":"The Turnstile secret. Overrides the stored option when defined as a string.","values":"","deprecated":false},{"name":"PSST_CRYPTO_KEY","type":"string","required":false,"defaultValue":"","description":"The 1.x server-side key. Version 2 never reads it for encryption. When it is still defined after the upgrade, administrators see a dismissible notice asking them to remove it.","values":"","deprecated":true},{"name":"DISABLE_WP_CRON","type":"boolean","required":false,"defaultValue":"false","description":"Not a Psst constant, but the Health tab reports it. Expiry works through Action Scheduler either way; the fallback path uses WP-Cron only when Action Scheduler is unavailable.","values":"","deprecated":false}],"searchable":false,"compact":true} /-->

The plugin also defines `PSST_FILE`, `PSST_PATH`, `PSST_URL`, `PSST_BASENAME`, `PSST_BLOCK_PATH`, and `PSST_VERSION` for its own use. They are informational and not meant to be overridden.

## Client configuration

The blocks receive a small configuration object through the Interactivity API, and the same object is served publicly at `GET /psst/v1/config`. It is filterable through `psst_client_config`.

| Key | Source |
| --- | --- |
| `restUrl` | `rest_url('psst/v1/')` |
| `createUrl` | Permalink of the create page, or the home URL |
| `ttlOptions` | Enabled expiration choices as minutes to label |
| `ttlDefault` | The preselected choice |
| `maxPlaintext` | The plaintext cap in bytes |
| `kdfIterations` | The PBKDF2 iteration count senders use, 600000 by default |
| `turnstileSiteKey` | The site key, or an empty string when Turnstile is off |
| `locale` | The site locale |

Nothing in this object is sensitive. The Turnstile secret, the rate limits, and the page ids are not included.
