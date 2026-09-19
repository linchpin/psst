---
title: Guides
---

Longer explanations of how Psst behaves and how to run it safely. Start with the encryption guide if you are reviewing the plugin for a security sign-off; it is the page every other claim in this documentation points back to.

- **[How the encryption works](encryption.md)** walks through the key derivation chain, what the browser sends, what the server stores, how a reveal is made one-shot, and which tests prove each claim.
- **[Hardening](hardening.md)** covers the headers set on secret pages, the opt-in Content-Security-Policy, page caching, rate limits, Turnstile, and the abuse cases the design already handles.
- **[Upgrading from 1.x](upgrading-from-1x.md)** explains what version 2 removes, why 1.x secrets cannot be carried forward, and what happens to old links.

For the sender and recipient walkthrough, see [Your first secret](../getting-started/first-secret.md).
