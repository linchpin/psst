---
title: Blocks and patterns
---

Two dynamic blocks, two patterns, and one block binding source. Both blocks are `apiVersion` 3, render on the server through `render.php`, and run their front-end behaviour as Interactivity API stores loaded as script modules. Nothing sensitive is ever placed in Interactivity context: the form keeps the management token and the viewer keeps the key and envelope in module scope, where they cannot be serialized into the page.

## psst/secret-form

The create form. It encrypts in the browser, posts ciphertext, and swaps in place to a confirmation panel.

<!-- wp:docspress/fields {"title":"Attributes","description":"Set in the block sidebar. The FAQ content is inner blocks, saved with the post.","fields":[{"name":"defaultExpiry","type":"number","required":false,"defaultValue":"0","description":"Preselected expiry in minutes. Zero means use the site default from settings.","values":"","deprecated":false},{"name":"showPassphrase","type":"boolean","required":false,"defaultValue":"true","description":"Whether the optional pass-phrase field is rendered.","values":"","deprecated":false},{"name":"showTip","type":"boolean","required":false,"defaultValue":"true","description":"Whether the dismissible tip callout is rendered above the pass-phrase field.","values":"","deprecated":false},{"name":"tipHeading","type":"string","required":false,"defaultValue":"Quick Tip!","description":"Heading of the tip callout.","values":"","deprecated":false},{"name":"tipText","type":"string","required":false,"defaultValue":"","description":"Body of the tip callout.","values":"","deprecated":false}],"searchable":false,"compact":true} /-->

Supports: `interactivity`, `align` wide and full, spacing margin and padding, and color background and text. `multiple` is false, so only one form fits on a page. `html`, `renaming`, and `reusable` are off.

**Inner blocks.** The FAQ below the confirmation panel is an inner-blocks area limited to `core/heading`, `core/paragraph`, and `core/list`. The editor seeds it with a "Need some help?" heading and two question-and-answer pairs. The editor view is intentionally static; running the real form in the editor would encrypt and store secrets from inside the editor.

**States.** The store exposes one status at a time.

| Status | What is shown |
| --- | --- |
| `idle` | The form: textarea with a bytes-remaining counter, tip, pass phrase, expiry select, Turnstile widget if enabled, honeypot, submit |
| `busy` | The form, disabled, with the button reading *Encrypting…* |
| `error` | The form with a `role="alert"` message such as *Enter a secret message.*, *Your secret is too long.*, *Too many requests. Please try again later.*, or *Something went wrong. Please try again.* |
| `confirmed` | Share link input, **Copy**, live region, expiry label, **Shred**, "create a new secret", and the FAQ |
| `shredded` | Confirmation that the secret was destroyed, and "create a new secret" |
| `unsupported` | *Your browser does not support the encryption this page needs.* in place of the form |

**Submit flow.** Normalize line endings, require a non-empty message, enforce the byte cap, read the Turnstile token if present, encrypt with the site's iteration count, `POST /secrets` with no cookies, then keep the returned id and token in module scope and build the share link as the returned URL plus `#` plus the key. On `429` the rate-limit message is shown; on other failures the server's message or the generic one. Turnstile is reset after any failure.

**Copy and shred.** Copy uses the async clipboard API and falls back to selecting the input. Shred sends `DELETE /secrets/{id}` with the `X-Psst-Manage-Token` header; a `404` is treated as already shredded.

## psst/secret-viewer

The recipient's page. It renders the interstitial, the pass-phrase prompt, the revealed secret, and the gone state.

<!-- wp:docspress/fields {"title":"Attributes","fields":[{"name":"heading","type":"string","required":false,"defaultValue":"Your Shared Secret","description":"The interstitial heading.","values":"","deprecated":false},{"name":"intro","type":"string","required":false,"defaultValue":"","description":"The interstitial paragraph.","values":"","deprecated":false}],"searchable":false,"compact":true} /-->

Same supports as the form. The block saves nothing to post content; the page holds only `<!-- wp:psst/secret-viewer /-->`.

**Server-side resolution.** The id never comes from JavaScript. `render.php` reads the `psst_id` and `psst_view` query variables set by the rewrite rule and resolves one of these states before any markup is emitted.

| Resolved state | HTTP status | Rendered |
| --- | --- | --- |
| No route, for example `/s/` alone | 404 | Gone panel with a link to the create page |
| Legacy `/secret/...` URL | 410 | Gone panel |
| Unknown, expired, or already-revealed id | 404 | Gone panel |
| Active, no pass phrase | 200 | Interstitial, status `ready` |
| Active, pass phrase set | 200 | Interstitial with the pass-phrase field, status `protected` |

The gone panel is the same markup regardless of why, and in that case the interstitial is not rendered at all. The page therefore cannot be used to tell an unknown id from a consumed one. The rendered page never contains ciphertext; the end-to-end suite asserts this.

**Client-side statuses.** `ready`, `protected`, `revealing`, `wrong-passphrase`, `revealed`, `error`, `gone`, and `unsupported`. The reveal action is described in [How the encryption works](../guides/encryption.md). After a successful reveal the plaintext is placed in a focused `<pre class="psst-secret" tabindex="-1">` with a **Copy secret** button and a dismissible warning. The fragment is removed from the URL with `history.replaceState`.

## Patterns

Both patterns are registered in the `psst` pattern category.

| Slug | Title | Contents |
| --- | --- | --- |
| `psst/create-page` | Share a secret | A constrained `core/group` as `<main>`, an `h1`, an intro paragraph, and the form with four starter FAQ inner blocks. Activation places this on the **Share a Secret** page. |
| `psst/cross-sell` | Create your own secret | A `core/group` with an `h3`, a paragraph, and a `core/button` whose URL is bound to the create page. |

The cross-sell button uses a block binding source, `psst/create-url`, whose value is the create page permalink or the home URL. Move the create page and every bound button follows.

<!-- wp:docspress/colorful-code {"language":"html","filename":"Bind any core/button to the create page","code":"<!-- wp:button {\"metadata\":{\"bindings\":{\"url\":{\"source\":\"psst/create-url\"}}}} -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\">Share a secret</a></div>\n<!-- /wp:button -->","highlightedLines":"1","showLineNumbers":false,"caption":"The binding replaces the href at render time."} /-->

## Styling

The front-end stylesheets reference theme presets only. Every color, spacing, font-size, and font-family value is a `var(--wp--preset--…)` lookup with a literal fallback, plus `var(--wp--custom--border-radius, 4px)` for corners. Buttons render with core's `wp-block-button` and `wp-block-button__link wp-element-button` classes, so the theme's button styles and any style variations apply unchanged.

To retheme the blocks, edit the theme's `theme.json` palette, spacing, and typography presets. Override the plugin's CSS only for layout changes the presets cannot express. Class names are stable and prefixed `psst-form__` and `psst-viewer__`.
