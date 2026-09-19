---
title: Your first secret
---

Send one secret from the create page to a private browser window. Along the way you will see every state the two blocks can render and confirm that nothing is consumed until the recipient clicks **View Secret**.

## Prerequisites

- The plugin is active and **Settings → Psst → Health** shows both page URLs.
- The site is served over HTTPS, or you are on `localhost`.
- A second browser profile or a private window, so the recipient has no WordPress cookies. This is not required by the plugin, but it demonstrates that the reveal does not depend on being logged in.

## Send

<!-- wp:docspress/flow {"start":1,"steps":[{"title":"Open the create page","content":"<p>By default it is <code>/share/</code>. If you made it the front page under <strong>Settings → Reading</strong>, open the home URL instead.</p>"},{"title":"Type the secret","content":"<p>The counter under the field shows bytes remaining against the site's cap. Line endings are normalized, and tabs, repeated spaces, and non-ASCII text survive the round trip byte for byte.</p>"},{"title":"Optionally add a pass phrase","content":"<p>The pass phrase is turned into key material in your browser and never sent. Share it with the recipient through a different channel than the link.</p>"},{"title":"Pick an expiration","content":"<p>The choices are the ones enabled in settings. The default is one week.</p>"},{"title":"Click Create Secret Link","content":"<p>The button reads <em>Encrypting…</em> while the browser generates a key and encrypts. The form then swaps in place to the confirmation panel.</p>"}]} /-->

The confirmation shows the share link, a **Copy** button, the expiry label, a **Shred** button, a link to create another secret, and the FAQ content from the block.

```text
https://example.com/s/Vq7m2x9kLp3nR8tYwZ1aBc/#Xk3yPq9mR2vL7nT4wB8cF1hJ6dG0sA5uE9iO2lY3zM
```

<!-- wp:docspress/callout {"tone":"tip","title":"The link is shown once","content":"<p>The part after <code>#</code> is the only copy of the decryption key. It is not stored anywhere, so if you navigate away before copying the link, the secret is unrecoverable. Shred it and create a new one.</p>","collapsible":false} /-->

## Receive

Paste the link into the private window.

1. The page loads an interstitial with the heading, an intro, a pass-phrase field if you set one, and a **View Secret** button. Reload it as many times as you like. Nothing has been consumed.
2. Click **View Secret**. The button reads *Decrypting…* while the browser fetches the envelope and decrypts it locally.
3. The plaintext appears in a focused, copyable box with a dismissible warning that it will not be shown again. The `#` fragment disappears from the address bar at the same moment.
4. Reload the page. It now shows *This secret is no longer available.* and a link back to the create page. The server answered 404 because the row was deleted during step 2.

If you set a pass phrase, try a wrong one first. The field shows *That pass phrase did not unlock the secret. Try again.* and refocuses. The retry succeeds without a second request to the server, because the browser kept the envelope and checked your pass phrase locally.

## Shred

Back in the sender's window, click **Shred**. The plugin sends the one-time management token it received on create, the server deletes the row, and the panel confirms. A recipient who opens the link afterwards sees only the "no longer available" state.

Administrators can do the same from **Settings → Psst → Secrets**, which lists live secrets by metadata only. Neither path can show the content.

## What you just verified

| Observation | What it proves |
| --- | --- |
| The link had a `#` fragment | The key travels only in the fragment, which browsers never send to the server |
| Reloading the interstitial changed nothing | Only the View button's JSON `POST` consumes a secret |
| The plaintext appeared after View | Decryption ran in the recipient's browser with the key from the fragment |
| The fragment vanished after reveal | The viewer scrubs the key from the URL and history |
| The second load was a 404 | The row was deleted in the same request that handed over the envelope |
| The wrong pass phrase cost nothing | The check value lets the browser distinguish a typo from corruption |

For the mechanism behind each row, read [How the encryption works](../guides/encryption.md).
