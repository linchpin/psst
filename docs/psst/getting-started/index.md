---
title: Getting started
---

Install Psst, let activation create its two pages, choose which expiration options senders see, and send a first secret. On a site that already runs over HTTPS this takes about ten minutes.

<!-- wp:docspress/flow {"start":1,"steps":[{"title":"Install","content":"<p>Add the plugin with Composer from Linchpin's package repository, or upload a release zip. See <a href=\"installation.md\">Installation</a>.</p>"},{"title":"Activate","content":"<p>Activation creates the <strong>Share a Secret</strong> page at <code>/share/</code> and the <strong>Secret</strong> viewer page at <code>/s/</code>, grants the administrator role the plugin's capabilities, and schedules the daily sweep.</p>"},{"title":"Configure","content":"<p>Open <strong>Settings → Psst</strong> to pick the expiration choices, the size cap, rate limits, and optionally Cloudflare Turnstile. See <a href=\"configuration.md\">Configuration</a>.</p>"},{"title":"Send a secret","content":"<p>Open the create page, type a message, copy the link, and open it in a private window to watch it reveal once and then disappear. See <a href=\"first-secret.md\">Your first secret</a>.</p>"}]} /-->

## In this section

- **[Installation](installation.md)** covers Composer and zip installs, what activation creates, and what deactivation leaves behind.
- **[Configuration](configuration.md)** covers the admin screen, the constants the plugin reads, and where the Turnstile secret lives.
- **[Your first secret](first-secret.md)** walks through the sender and recipient experience end to end.

## Before you begin

<!-- wp:docspress/callout {"tone":"warning","title":"The site must be served over HTTPS","content":"<p>Encryption runs in the browser through the Web Crypto API, which browsers expose only on secure origins. On a plain HTTP site the create form and the viewer both show <em>Your browser does not support the encryption this page needs.</em> and do nothing else. <code>localhost</code> counts as secure for local development.</p>","collapsible":false} /-->

Psst also assumes a block theme. The two pages it creates are built from blocks and a pattern, and the block styles lean on the theme's preset colors, spacing, and button styles so they match the site without extra CSS.
