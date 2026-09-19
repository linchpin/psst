---
title: Upgrading from 1.x
---

Version 2 is a rewrite, not an update. The 1.x plugin encrypted secrets on the server with a key in `wp-config.php`, which meant anyone with the database and the config could read every secret. Version 2 moves encryption into the browser, and that change is incompatible by design: there is no way to convert a secret the server could read into one it cannot.

<!-- wp:docspress/callout {"tone":"warning","title":"Unread 1.x secrets are deleted on upgrade","content":"<p>They were encrypted with <code>PSST_CRYPTO_KEY</code>, which version 2 never uses. Carrying them forward would require decrypting them on the server and re-encrypting with a key the server would then have seen, which defeats the point. Tell senders to resend anything still outstanding before you upgrade.</p>","collapsible":false} /-->

## What the upgrade removes

The upgrade runs once, on activation or on the first `init` after the new code is deployed, and records `2` in the `psst_db_version` option so it never runs again.

<!-- wp:docspress/flow {"start":1,"steps":[{"title":"Clear 1.x cron hooks","content":"<p><code>secret_expire</code>, <code>secret_purge</code>, and <code>secret_cleanup</code> are unscheduled.</p>"},{"title":"Delete 1.x secrets","content":"<p>Posts of the old <code>secret</code> type are hard-deleted 500 at a time. If any remain, an asynchronous <code>psst_purge_legacy</code> action is queued to continue, so a large site does not block a request.</p>"},{"title":"Reset settings that have the 1.x shape","content":"<p>The <code>psst_settings</code> option is deleted only when it carries a <code>crypto_key</code> or <code>uninstall</code> key, then reseeded with version 2 defaults. A version 2 settings option is left alone.</p>"},{"title":"Remove legacy options and user meta","content":"<p>The <code>psst_options</code>, <code>psst_version</code>, and <code>psst_activation</code> options, plus the per-user notification and welcome-panel meta.</p>"},{"title":"Flag the constant","content":"<p>If <code>PSST_CRYPTO_KEY</code> is still defined, a transient is set for one month and administrators see a dismissible notice.</p>"},{"title":"Reseed and flush","content":"<p>Defaults are seeded and a rewrite flush is flagged so the new <code>/s/{id}/</code> rule takes effect.</p>"}]} /-->

The notice reads: *Psst 2.0 encrypts secrets in the browser and no longer uses PSST_CRYPTO_KEY. You can remove that constant from wp-config.php.* Removing the constant is safe at any point after the upgrade. Nothing reads it for encryption.

## What happens to old links

1.x links looked like `/secret/view/{id}/`. A rewrite rule matches anything under `/secret/`, routes it to the viewer page, and sets a `410 Gone` status. The viewer renders the "no longer available" state with a link to the create page. The interstitial is not rendered, so a legacy URL cannot be used to probe anything.

<!-- wp:docspress/terminal-session {"title":"Confirm a legacy link answers 410","shell":"bash","prompt":"$","command":"curl -s -o /dev/null -w \"%{http_code}\\n\" https://example.com/secret/view/anything/","output":"410"} /-->

## The theme

The 1.x plugin depended on a Foundation-based `psst` theme. Version 2 does not. The blocks style themselves with the active theme's preset variables and core button classes, so any block theme works, and the old theme can be removed once the two new pages look right.

## Checklist

- Confirm no unread 1.x secrets matter, or ask senders to resend.
- Deploy version 2 and, if the install was by Composer, open **Settings → Psst** once so the pages and rewrite rules exist.
- Remove `PSST_CRYPTO_KEY` from `wp-config.php`.
- Check **Settings → Psst → Health**. *Legacy 1.x data remaining* should read zero once the asynchronous purge has run.
- Retire the old theme.
- Update any hard-coded links to the create page. The default is now `/share/`.
