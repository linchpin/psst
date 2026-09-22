=== Psst ===
Contributors: linchpin_agency, aware
Tags: secrets, password, encryption, one-time, privacy
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.3
x-release-please-start-version
Stable tag: 2.3.0
x-release-please-end
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Share one-time, expiring secrets that are encrypted in the browser. The server only ever stores ciphertext it cannot read.

== Description ==

Psst, short for Pretty Secure Secret Transmissions, lets you share a password, a key, or a note through a link that works exactly once and then destroys itself.

The secret is encrypted in the sender's browser before it is sent. WordPress stores ciphertext it has no key for. The key travels only in the link's `#fragment`, which browsers never send to a server. When the recipient clicks **View Secret**, the row is claimed, returned once, and hard-deleted in the same request.

Administrators can see that a secret exists, when it expires, and shred it. They cannot read it, and neither can anyone with a copy of the database, because there is no key on the server to read it with.

= How it works =

1. The sender's browser generates a random 256-bit key and encrypts the text with AES-256-GCM using the Web Crypto API. Only the ciphertext is posted to WordPress.
2. The key travels in the share link's fragment: `https://example.com/s/{id}/#{key}`. It is not logged, not stored, and never reaches the server.
3. The recipient opens the link and sees an interstitial. Nothing is consumed by opening the page, so link previewers, mail scanners, and reloads cannot burn the secret.
4. Clicking **View Secret** sends one request that returns the envelope exactly once and deletes it. The browser decrypts locally and scrubs the key from the address bar.
5. An optional pass phrase is folded into the key in the browser and checked locally, so a typo does not cost the secret and the pass phrase is never transmitted.

= Features =

* **Browser-side encryption.** AES-256-GCM with HKDF-derived keys and PBKDF2 for pass phrases. Plain Web Crypto, no JavaScript dependencies.
* **One-shot reveal.** An atomic conditional claim. Concurrent readers race for one row and the loser gets nothing.
* **Preview-safe links.** Opening the page never consumes the secret. The reveal requires a JSON request.
* **Expiry.** Eleven choices from five minutes to one week, enforced by a scheduler and again at read time.
* **Shred.** The sender or an administrator can destroy a secret before it is read.
* **Two blocks.** A create form and a viewer, both built on the Interactivity API and styled from your theme's presets.
* **Optional accounts.** A front end sign-in, registration and account area, so a signed-in sender keeps a record of the secrets they have sent: who each was for, when it expires, and whether it has been read. Metadata only, never the secret. Off by default; anonymous sending is unchanged.
* **Hardening.** Secret pages send no-store, noindex, no-referrer, nosniff and frame-denial headers, are excluded from sitemaps and page caches, and can carry an opt-in Content-Security-Policy.
* **Abuse controls.** Per-IP and site-wide rate limits, a honeypot field, and optional Cloudflare Turnstile that fails closed.
* **Admin screen.** Settings, a metadata-only list of live secrets with a shred action, and a health check.
* **Extensible.** Actions and filters for developers, none of which ever receives a key, a pass phrase, or plaintext.

= Emailing a secret link =

Psst can optionally email a share link for the sender. **This is off by default, and it is the one feature that weakens the guarantee the rest of the plugin makes.**

Everywhere else, the decryption key is generated in the sender's browser and travels in the link's `#fragment`, which browsers never send to a server. For the site to email a link the recipient can open, the server has to be given that key, because the server is what sends the mail. The key is then in an email, in the recipient's mailbox, for as long as that message exists — anyone who can read the email can read the secret.

Psst never stores, logs or hooks that key: it composes one message and is discarded. But the tradeoff is real, the sender is shown it in the form next to the checkbox, and a site that does not want it should leave the setting off. Copying the link and sending it yourself involves no key ever reaching the server.

= Requirements =

* WordPress 6.9 or later. A block theme is recommended, because the two pages the plugin creates are built from blocks and patterns.
* PHP 8.3 or later.
* HTTPS. Browsers expose the Web Crypto API only in secure contexts, so the blocks refuse to run on a plain HTTP site.
* Pretty permalinks. The `/s/{id}/` route needs any structure other than Plain.

= Third-party services =

Psst works without any external service. One optional integration contacts a third party when you turn it on:

**Cloudflare Turnstile.** When you enter a Turnstile site key and secret key in the plugin settings, the create form loads the Turnstile widget script from `https://challenges.cloudflare.com/turnstile/v0/api.js`, and the server verifies each submitted challenge token against `https://challenges.cloudflare.com/turnstile/v0/siteverify`. The request to Cloudflare carries the challenge token, your secret key, and the sender's IP address. It never carries the secret being shared. Turnstile is off by default and nothing is sent to Cloudflare until both keys are set. See [Cloudflare's Turnstile documentation](https://developers.cloudflare.com/turnstile/), [terms of service](https://www.cloudflare.com/website-terms/) and [privacy policy](https://www.cloudflare.com/privacypolicy/).

= Bundled libraries =

Psst ships [Action Scheduler](https://actionscheduler.org/) (GPL-3.0-or-later), which expires unread secrets on time. WP-Cron is used as a fallback when it is unavailable.

= Source code =

The plugin is developed in the open at [github.com/linchpin/psst](https://github.com/linchpin/psst). The uncompressed source for the compiled JavaScript lives under `src/` and `blocks/src/` in that repository, alongside the full documentation and the test suites that assert every security claim above.

== Installation ==

1. Install the plugin from the WordPress.org directory, or upload the zip under **Plugins → Add New → Upload Plugin**.
2. Activate **Psst** on the Plugins screen.
3. Activation creates two pages: **Share a Secret** at `/share/`, holding the create form, and **Secret** at `/s/`, holding the viewer. Link to the first one or make it your front page.
4. Review **Settings → Psst** to choose expiration options, rate limits, and whether uninstall removes everything.

Open **Settings → Psst → Health** afterwards. It should report the scheduler as available and both page URLs as set. A viewer page reported as *Not set* means secret links will not resolve.

== Frequently Asked Questions ==

= Can the site owner or a WordPress administrator read a secret? =

No. The encryption key is generated in the sender's browser and travels only in the link's fragment, which browsers never send to the server. Administrators can see that a secret exists, when it expires, and shred it. Neither they nor anyone with database access can decrypt it.

= Does opening the link destroy the secret? =

No. Opening the link shows an interstitial and consumes nothing, so link previewers, mail scanners, and accidental reloads are safe. Only clicking **View Secret** reveals it, and that works exactly once.

= What happens if the recipient mistypes the pass phrase? =

Nothing is lost. The pass phrase is checked in the recipient's browser against a stored check value, so they can try again without a second request to the server.

= Why do the blocks say my browser is unsupported? =

The Web Crypto API is available only in secure contexts. Serve the site over HTTPS. A LAN IP address over plain HTTP is not a secure context, while `localhost` is.

= Secret links return a 404. What do I check? =

Open **Settings → Psst → Health**. If the viewer page reads *Not set*, choose a published page containing the viewer block on the Settings tab. Then visit **Settings → Permalinks** once to flush the rewrite rules, and confirm your permalink structure is not Plain.

= Does Psst send data anywhere? =

Not unless you enable Cloudflare Turnstile. See the *Third-party services* section above. There is no telemetry, no phone-home, and no external font or script on the secret pages.

If you enable the optional email delivery feature, your own site sends mail through `wp_mail()` to the address a sender types. That goes wherever your site's mail already goes; nothing is sent to us.

= Can a signed-in user see their old secrets? =

They can see that a secret existed, who they said it was for, when it expires, and whether it has been read or shredded. They cannot see the secret. Nobody can: the server has no key, and after a reveal it does not even have the ciphertext. The account area is a record of what happened, not an archive.

= Does the account feature lock me out of wp-admin? =

Only if you switch that setting on, and only for users without a content role — administrators, editors and network administrators are unaffected. If you do lock yourself out, `wp-login.php?psst=bypass` always shows the normal WordPress login screen.

= Can I use it with a classic theme? =

The two blocks render on any theme, but the pages activation creates are built from block patterns, so a block theme gives the best result. On a classic theme, add the `psst/secret-form` and `psst/secret-viewer` blocks to pages of your own and select them under **Settings → Psst**.

= What is removed on uninstall? =

By default every secret, the plugin's options, transients, capabilities, and its scheduled actions. The two pages stay, because they are your content. Turn off **Delete everything on uninstall** in the settings to keep the data.

= I am upgrading from 1.x. What changes? =

Version 2 is a rewrite. On first load it removes the 1.x secrets, which were encrypted with a server-side key this version does not use, along with the old options and cron events, and answers old `/secret/...` links with `410 Gone`. If `PSST_CRYPTO_KEY` is still defined in `wp-config.php` an admin notice asks you to remove it. The Foundation-based `psst` theme is no longer needed.

== Screenshots ==

1. The Share a Secret page with the create form: message, optional pass phrase, and expiration.
2. Typing a secret. The character count is enforced in the browser before anything is encrypted.
3. The confirmation with the one-time share link, its expiry, and the shred button.

== Changelog ==

= 2.2.0 =

* Added an optional front end account layer: a sign-in page, registration, and an account area where a signed-in sender sees the secrets they have sent. Off by default.
* Added a sent-secret history recording metadata only — identifier, recipient label, expiry, and whether the secret was read, shredded or expired. It never contains a secret, and it is pruned on a configurable schedule.
* Added an optional setting to keep users without a content role out of wp-admin, their profile screen included, redirecting them to the account area instead. Super administrators are never locked out, and `wp-login.php?psst=bypass` is always available.
* Added optional email delivery of a share link. This is the one feature that gives the server a decryption key; it is off by default, the tradeoff is shown to the sender at the point of use, and the key is never stored, logged or passed to a hook.
* Added the `psst/login-form`, `psst/register-form` and `psst/account` blocks with matching patterns.
* Added a `recipient` field to the create form and an optional requirement that senders be signed in.
* Registration on multisite goes through the network signup path and adds the new account to the current site.

= 2.0.0 =

* Rewritten as end-to-end encrypted Gutenberg blocks. Secrets are encrypted in the browser with AES-256-GCM and the server stores only ciphertext.
* The key travels in the link fragment and never reaches the server. A server-side `PSST_CRYPTO_KEY` is no longer used.
* Added the `psst/secret-form` and `psst/secret-viewer` blocks and the `psst/create-page` and `psst/cross-sell` patterns.
* Added an interstitial so opening a link never consumes the secret. Reveal is an atomic one-shot claim.
* Added an optional pass phrase folded into the key in the browser, with local retry.
* Added expiry through Action Scheduler with a daily sweep and read-time enforcement.
* Added per-IP and site-wide rate limits, a honeypot, and optional Cloudflare Turnstile.
* Added hardening headers, sitemap and cache exclusion, and an opt-in Content-Security-Policy filter.
* Added an admin screen with settings, a metadata-only secrets list with shred, and a health check.
* Added a `psst/v1` REST API and a documented set of actions and filters.
* Upgrading from 1.x removes the old secrets, options, and cron events, and answers legacy `/secret/` links with `410`.
* Requires WordPress 6.9 and PHP 8.3.

= 1.0.5 and earlier =

* The original Psst. Secrets were encrypted server-side with a key defined in `wp-config.php`. See the [release history on GitHub](https://github.com/linchpin/psst/releases).

== Upgrade Notice ==

= 2.0.0 =
Major rewrite with browser-side encryption. Existing 1.x secrets are removed on upgrade because they cannot be migrated to the new zero-knowledge model. Share anything still needed before upgrading. Requires WordPress 6.9 and PHP 8.3.
