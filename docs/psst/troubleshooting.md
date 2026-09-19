---
title: Troubleshooting
---

Psst fails closed in several places: the blocks refuse to run without Web Crypto, the reveal refuses anything but JSON, and Turnstile refuses on any doubt. This page pairs each symptom with the check that identifies it. None of the checks can recover a secret's content, because nothing on the server can.

## Start here

<!-- wp:docspress/troubleshooter {"title":"Narrow the problem","intro":"Three questions cover most reports.","startId":"page","questions":[{"id":"page","question":"Does the create page or the viewer page load with the block visible?","yesLabel":"Yes, the block renders","yesNext":"crypto","noLabel":"No, 404 or missing block","noNext":"pages"},{"id":"crypto","question":"Does it say your browser does not support the encryption?","yesLabel":"Yes","yesNext":"https","noLabel":"No, something else","noNext":"which"},{"id":"which","question":"Is the problem on the sender's side or the recipient's?","yesLabel":"Sender: cannot create","yesNext":"create","noLabel":"Recipient: cannot open","noNext":"reveal"}],"outcomes":[{"id":"pages","status":"error","title":"Pages or rewrite rules are missing","content":"<p>Open <strong>Settings → Psst → Health</strong>. If the viewer page reads <em>Not set</em>, pick a page on the Settings tab. Then save <strong>Settings → Permalinks</strong> once. See <a href=\"#secret-links-return-404\">Secret links return 404</a>.</p>"},{"id":"https","status":"warning","title":"Not a secure context","content":"<p>Browsers expose the Web Crypto API only on HTTPS and localhost. Serve the site over HTTPS. See <a href=\"#the-blocks-say-the-browser-is-unsupported\">The blocks say the browser is unsupported</a>.</p>"},{"id":"create","status":"neutral","title":"Check the create response","content":"<p>Open the network panel and read the JSON error code on the <code>POST /secrets</code> call. See <a href=\"#creating-a-secret-fails\">Creating a secret fails</a>.</p>"},{"id":"reveal","status":"neutral","title":"Check the link and the reveal response","content":"<p>Confirm the link still has its <code>#</code> fragment, then read the status of <code>POST /secrets/{id}/reveal</code>. See <a href=\"#the-recipient-cannot-open-the-secret\">The recipient cannot open the secret</a>.</p>"}],"showProgress":true} /-->

## Psst could not load its dependencies

<!-- wp:docspress/result {"status":"error","title":"Admin notice on a source checkout","content":"<p><em>Psst could not load its dependencies. Run composer install inside the plugin directory, or install a release build.</em></p>","meta":"admin notice"} /-->

The Composer autoloader is missing, so the bootstrap class cannot be found. This only happens on a git checkout; release zips ship `vendor/`.

<!-- wp:docspress/terminal-session {"title":"Build the autoloader and assets","shell":"bash","prompt":"$","command":"cd wp-content/plugins/psst\ncomposer install\nnpm run install:all && npm run build:all","output":""} /-->

## Secret links return 404

The `/s/{id}/` rewrite rule exists only while the **Viewer page** setting points at a published page. Check in this order.

<!-- wp:docspress/fields {"title":"Causes of a 404 on /s/{id}/","description":"In order of likelihood.","fields":[{"name":"Viewer page not set","type":"string","required":false,"defaultValue":"","description":"Composer deploys do not run activation, so the pages may never have been created. The Health tab shows <em>Not set. Secret links will not resolve.</em> Create a page containing the <code>psst/secret-viewer</code> block and select it as the viewer page on the Settings tab.","values":"","deprecated":false},{"name":"Viewer page not published","type":"string","required":false,"defaultValue":"","description":"A draft or trashed page disables the rule. Publish it.","values":"","deprecated":false},{"name":"Rewrite rules not flushed","type":"string","required":false,"defaultValue":"","description":"Changing the page flags a flush on the next <code>init</code>. If a cache or an early exit skipped it, save <strong>Settings → Permalinks</strong> or run <code>wp rewrite flush</code>.","values":"","deprecated":false},{"name":"Plain permalinks","type":"string","required":false,"defaultValue":"","description":"The rule needs pretty permalinks. Choose any structure other than Plain.","values":"","deprecated":false},{"name":"The secret is genuinely gone","type":"string","required":false,"defaultValue":"","description":"A 404 with the \"no longer available\" panel rendered is the correct answer for a read, expired, shredded, or unknown id. Confirm on the Secrets tab whether it is still listed.","values":"","deprecated":false}],"searchable":false,"compact":false} /-->

<!-- wp:docspress/terminal-session {"title":"Confirm the rule is registered","shell":"bash","prompt":"$","command":"wp rewrite list --match=/s/Vq7m2x9kLp3nR8tYwZ1aBc/ --fields=match,query","output":"match                             query\n^s/([A-Za-z0-9_-]{22,64})/?$      index.php?page_id=12&psst_id=$matches[1]"} /-->

## The blocks say the browser is unsupported

<!-- wp:docspress/result {"status":"warning","title":"Your browser does not support the encryption this page needs.","content":"<p>Shown by both blocks in place of their content.</p>","meta":"front end"} /-->

The blocks check for `crypto.subtle` and `crypto.getRandomValues` on load. Browsers expose `crypto.subtle` only in secure contexts.

- The site is on plain HTTP. Serve it over HTTPS. `localhost` is treated as secure, but a LAN IP address is not.
- A very old browser. Every browser released in the last several years supports Web Crypto.
- A content blocker or privacy extension has disabled the API. Try a clean profile.

## Creating a secret fails

Read the error code on the `POST /psst/v1/secrets` response in the network panel.

| Code | Status | Cause and fix |
| --- | --- | --- |
| `psst_rate_limited` | 429 | The per-IP or global create limit was hit. `retry_after` gives the wait in seconds. Raise the limit in settings, or set the trusted proxy header if every visitor appears to share one address behind Cloudflare. |
| `psst_payload_too_large` | 413 | The secret is over the plaintext cap or the request over 64 KB. Raise `max_plaintext_bytes`, up to 256 KB. |
| `psst_challenge_failed` | 403 | Turnstile refused. Check the site key and secret match the same widget, that the widget allows this hostname, and that the server can reach `challenges.cloudflare.com`. Verification fails closed on any network error. |
| `psst_rejected` | 400 | The honeypot was filled. A form autofill extension is the usual cause. |
| `psst_invalid_envelope` | 400 | A field failed validation. `data.field` names it. If `ttl_minutes` is named, the sender's page was rendered before an expiry option was disabled; reload. |
| `psst_unexpected_field` | 400 | A field named `key`, `passphrase`, `secret`, `plaintext`, or `message` was in the body. Only a modified client does this. |
| `psst_storage_failed` | 500 | The database insert failed. Check the database error log. |

A create that never leaves the page with *Your secret is too long.* is the browser enforcing the same cap before encrypting. The counter under the field is in bytes, so non-ASCII text counts more than its character length.

## The recipient cannot open the secret

<!-- wp:docspress/fields {"title":"Messages in the viewer","description":"Each message corresponds to one condition.","fields":[{"name":"This link is missing its key. Ask the sender to send the full link again.","type":"string","required":false,"defaultValue":"","description":"The URL has no <code>#</code> fragment, or it is not 43 base64url characters. Some chat tools and email clients strip or wrap fragments. Nothing was consumed; the sender can resend the same link.","values":"","deprecated":false},{"name":"This secret is no longer available.","type":"string","required":false,"defaultValue":"","description":"The reveal answered <code>404</code> or <code>410</code>. It was already read, expired, shredded, never existed, or is a 1.x link. Check the Secrets tab; if it is not listed, it is gone and cannot be recovered.","values":"","deprecated":false},{"name":"That pass phrase did not unlock the secret. Try again.","type":"string","required":false,"defaultValue":"","description":"Checked locally against the stored check value. The recipient can retry indefinitely without a second request. Pass phrases are NFKC-normalized, so smart quotes and composed characters are handled, but case and whitespace matter.","values":"","deprecated":false},{"name":"This secret could not be decrypted. The link may have been altered.","type":"string","required":false,"defaultValue":"","description":"The envelope was fetched and consumed, but the key does not decrypt it. The fragment was altered in transit, or belongs to a different secret. The secret is gone; the sender must create a new one.","values":"","deprecated":false},{"name":"Too many requests. Please try again later.","type":"string","required":false,"defaultValue":"","description":"The per-IP reveal limit was hit. Nothing was consumed. Wait, or raise <code>rate_limit_reveal_per_hour</code>.","values":"","deprecated":false},{"name":"Something went wrong. Please try again.","type":"string","required":false,"defaultValue":"","description":"The reveal returned an unexpected status or the request failed. Check that <code>/wp-json/psst/v1/</code> is reachable and not blocked by a firewall or security plugin, and that no proxy rewrote the <code>Content-Type</code>, which must be <code>application/json</code> or the server answers <code>415</code>.","values":"","deprecated":false}],"searchable":true,"compact":false} /-->

<!-- wp:docspress/callout {"tone":"danger","title":"A consumed secret cannot be recovered by anyone","content":"<p>Once the reveal has answered, the row is deleted and the server never had the key. There is no admin recovery, no backup path, and no support escalation that changes this. The only remedy is for the sender to create a new secret.</p>","collapsible":false} /-->

## Secrets never expire

The Health tab reports Action Scheduler and the next sweep time.

- **Action Scheduler unavailable.** The `vendor/` directory is missing or incomplete. Reinstall the release or run `composer install`. Expiry falls back to WP-Cron in the meantime.
- **WP-Cron disabled and no system cron.** With `DISABLE_WP_CRON` set, Action Scheduler still runs on page loads by default, but a low-traffic site may lag. Add a system cron that hits `wp-cron.php` or runs `wp action-scheduler run`.
- **Secrets past expiry still listed.** The viewer enforces expiry at read time and destroys the row on the way out, so a lagging scheduler never exposes an expired secret. The row disappears on the next sweep or on the next attempt to open it.

<!-- wp:docspress/terminal-session {"title":"Inspect pending expiries","shell":"bash","prompt":"$","command":"wp action-scheduler list --group=psst --status=pending --fields=hook,scheduled_date_gmt","output":"hook                  scheduled_date_gmt\npsst_expire_secret    2026-06-01 19:00:00\npsst_sweep_secrets    2026-06-02 03:00:00"} /-->

## The admin screen is blank

The React app is enqueued only when `build/admin.asset.php` exists. On a source checkout run `npm run build`. On a release build, confirm the `build/` directory was deployed. The browser console will show a 404 for `admin.js` if it is missing.

## The page is cached

Symptoms: a recipient sees a stale interstitial for a secret that is gone, or the create page shows another visitor's confirmation. Psst sets `Cache-Control: no-store` and defines `DONOTCACHEPAGE` on its pages. A CDN configured to ignore origin headers must be told to bypass the create page path and `/s/*`. Confirm nothing has hooked `psst_disable_page_cache` to return `false`.

## The 1.x constant notice will not go away

<!-- wp:docspress/result {"status":"neutral","title":"Psst 2.0 encrypts secrets in the browser and no longer uses PSST_CRYPTO_KEY.","content":"<p>The notice is shown while <code>PSST_CRYPTO_KEY</code> is defined and the upgrade transient is set, for up to a month after the upgrade.</p>","meta":"admin notice"} /-->

Remove the constant from `wp-config.php`. Dismissing hides the notice for the session; removing the constant hides it for good. Nothing in version 2 reads it. See [Upgrading from 1.x](guides/upgrading-from-1x.md).
