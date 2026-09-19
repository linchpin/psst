---
title: Psst
---

Psst, short for Pretty Secure Secret Transmissions, is a WordPress plugin by [Linchpin](https://linchpin.com) for sharing one-time, expiring secrets. A sender types a password, a token, or a note into a block, and gets back a link. The recipient opens the link once, and the secret is gone. The secret is encrypted in the sender's browser before it is sent, so the WordPress server only ever stores ciphertext it has no key for.

<!-- wp:docspress/hero {"eyebrow":"End-to-end encrypted secret sharing for WordPress","title":"The server stores what it cannot read","description":"A 256-bit key is generated in the sender's browser and travels only in the link's fragment, which browsers never send to a server. WordPress stores ciphertext, hands it over exactly once, and deletes it in the same request. Administrators can see that a secret exists and shred it. Nobody with database access can read it.","primaryLabel":"","primaryUrl":"","secondaryLabel":"","secondaryUrl":"","visualVariant":"sync-diagram","layout":"split","mediaPosition":"right","height":"standard","tone":"theme","textAlign":"left","showGrid":true,"showOrbit":false} /-->

## Where to start

<!-- wp:docspress/audience-paths {"eyebrow":"Choose a starting point","title":"What are you here to do?","description":"Psst serves the site owner who installs it, the developer who themes or extends it, and the security reviewer who has to sign off on it.","paths":[{"title":"Install and configure","description":"Requirements, Composer installation, the two pages activation creates, and the settings that shape what senders see.","url":"","cta":"","icon":"rocket","accent":"blue","newTab":false},{"title":"Understand the security model","description":"Exactly what the browser encrypts, what the server stores, how a reveal is made one-shot, and what the tests prove.","url":"","cta":"","icon":"shield","accent":"green","newTab":false},{"title":"Extend and operate","description":"REST routes, hooks, settings keys, the blocks and patterns, hardening headers, and how to diagnose a link that will not open.","url":"","cta":"","icon":"code","accent":"gold","newTab":false}],"columns":3,"tone":"theme","textAlign":"left","compact":false,"showNumbers":false} /-->

- **[Getting started](getting-started/index.md)** covers requirements, installation, configuration, and sending a first secret.
- **[Guides](guides/index.md)** explain the encryption, the sender and recipient experience, hardening, and upgrading from 1.x.
- **[Reference](reference/index.md)** lists every REST route, hook, setting, block, pattern, and development command.
- **[Troubleshooting](troubleshooting.md)** pairs each symptom with the check that identifies it.

## How a secret moves

<!-- wp:docspress/diagram {"title":"Lifecycle of a secret","type":"flow","source":"Sender browser -> Sender browser: generate key, AES-256-GCM encrypt\nSender browser -> POST /psst/v1/secrets: ciphertext only\nPOST /psst/v1/secrets -> psst_secret post: store envelope, schedule expiry\nSender browser -> Share link: url + # + key\nShare link -> Recipient browser: open /s/{id}/\nRecipient browser -> Interstitial: nothing consumed on GET\nInterstitial -> POST /secrets/{id}/reveal: click View Secret\nPOST /secrets/{id}/reveal -> Recipient browser: envelope once, row deleted\nRecipient browser -> Recipient browser: decrypt with key from fragment\npsst_secret post -> Action Scheduler: expire or sweep if unread","caption":"Plaintext exists only at the two ends. The middle stores, hands over once, and destroys."} /-->

Five properties follow from that flow, and each is covered by the checked-in test suites described in [How the encryption works](guides/encryption.md).

1. **The server cannot decrypt.** The key never reaches it. There is no server-side key, no key escrow, and no admin override.
2. **Nothing is consumed by opening the link.** Link previewers, mail scanners, and reloads fetch an interstitial. Only the View button issues the one call that reveals.
3. **A reveal happens exactly once.** The claim is a single conditional database update. Concurrent callers race for one row, and the loser receives a 404.
4. **A typo does not burn the secret.** Pass phrases are checked in the browser against a stored check value, so retries need no second request.
5. **Unread secrets expire.** Action Scheduler runs a per-secret expiry plus a daily sweep, and the viewer enforces expiry at read time even when the scheduler is behind.

## What ships

| Piece | What it does |
| --- | --- |
| `psst/secret-form` block | The create form. Encrypts in the browser, posts ciphertext, swaps in place to the share-link confirmation with a shred button and editable FAQ. |
| `psst/secret-viewer` block | The interstitial, pass-phrase prompt, reveal, and "no longer available" states. |
| `psst/create-page` pattern | Heading, intro, and the form, with starter FAQ content. Activation places it on the **Share a Secret** page. |
| `psst/cross-sell` pattern | A "create your own" call to action whose button is bound to the create page URL. |
| Admin screen | Settings, a metadata-only list of live secrets with a shred action, and a health check. Lives under **Settings → Psst**, or **Mantle → Psst** when Mantle is installed. |
| REST namespace `psst/v1` | Create, reveal, shred, public config, and administrator routes. |

## Requirements

<!-- wp:docspress/fields {"title":"Runtime requirements","description":"Taken from the plugin header in <code>psst.php</code> and the <code>require</code> block in <code>composer.json</code>.","fields":[{"name":"WordPress","type":"string","required":true,"defaultValue":"","description":"Requires at least 6.9. Tested up to 7.1. A block theme is expected because the two pages are built from blocks and patterns.","values":"6.9+","deprecated":false},{"name":"PHP","type":"string","required":true,"defaultValue":"","description":"8.3 or later, declared in both the plugin header and Composer.","values":"8.3+","deprecated":false},{"name":"HTTPS","type":"string","required":true,"defaultValue":"","description":"Browsers expose the Web Crypto API only in secure contexts. On a plain HTTP origin both blocks render an unsupported notice and refuse to run.","values":"","deprecated":false},{"name":"Action Scheduler","type":"string","required":true,"defaultValue":"bundled","description":"Installed through Composer as <code>woocommerce/action-scheduler</code> and loaded by the plugin's autoloader. WP-Cron is used as a fallback when it is unavailable.","values":"^4.1","deprecated":false}],"searchable":false,"compact":true} /-->

## License

GPL-2.0-or-later. Source and issue tracker: [github.com/linchpin/psst](https://github.com/linchpin/psst).
