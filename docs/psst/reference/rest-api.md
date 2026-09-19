---
title: REST API
---

Every route lives under the `psst/v1` namespace and is hidden from the REST index. The public routes take no nonce and no cookies by design: the create form and the viewer send requests with `credentials: 'omit'`, so a logged-in administrator and an anonymous visitor look identical to the server. Authorization for the one sensitive public action, shredding, is a bearer token issued at create time.

<!-- wp:docspress/callout {"tone":"note","title":"Nothing here can return plaintext","content":"<p>The server holds ciphertext and no key. The reveal route returns the encrypted envelope for the recipient's browser to decrypt. The administrator list returns metadata only. There is no route, header, or parameter that yields a secret's content.</p>","collapsible":false} /-->

## Common response headers

Every response under `psst/v1` carries these headers, added on `rest_post_dispatch`.

```text
Cache-Control: no-store, no-cache, must-revalidate, max-age=0
Pragma: no-cache
X-Robots-Tag: noindex, nofollow
Referrer-Policy: no-referrer
```

## Public routes

### POST /secrets

Create a secret. Public and rate limited. The body is the envelope produced by the browser plus delivery fields.

<!-- wp:docspress/fields {"title":"Request body","description":"Validated first by the REST schema, then field by field against the raw JSON so that size limits and forbidden names are enforced on the actual payload.","fields":[{"name":"version","type":"number","required":true,"defaultValue":"","description":"Protocol version. Must be <code>2</code>.","values":"2","deprecated":false},{"name":"ciphertext","type":"string","required":true,"defaultValue":"","description":"base64url. AES-GCM output with the 16-byte tag appended. Decoded length must be at least 17 and at most the plaintext cap plus 16.","values":"","deprecated":false},{"name":"iv","type":"string","required":true,"defaultValue":"","description":"base64url, exactly 12 bytes decoded.","values":"","deprecated":false},{"name":"check","type":"string","required":true,"defaultValue":"","description":"base64url, exactly 16 bytes decoded.","values":"","deprecated":false},{"name":"has_passphrase","type":"boolean","required":true,"defaultValue":"","description":"Whether the sender set a pass phrase. Drives which of the following fields are required or forbidden.","values":"","deprecated":false},{"name":"salt","type":"string","required":false,"defaultValue":"null","description":"base64url, exactly 16 bytes. Required with a pass phrase, forbidden without one.","values":"","deprecated":false},{"name":"kdf","type":"object","required":false,"defaultValue":"null","description":"<code>{ name, hash, iterations }</code>. Required with a pass phrase, forbidden without one. <code>name</code> must be <code>PBKDF2</code>, <code>hash</code> must be <code>SHA-256</code>, and <code>iterations</code> must be an integer between the site minimum (600000 by default) and 5000000.","values":"","deprecated":false},{"name":"ttl_minutes","type":"number","required":true,"defaultValue":"","description":"One of the enabled expiration choices.","values":"10080, 4320, 1440, 720, 360, 240, 120, 60, 30, 15, 5","deprecated":false},{"name":"hp","type":"string","required":false,"defaultValue":"''","description":"Honeypot. Must be empty.","values":"","deprecated":false},{"name":"challenge","type":"string","required":false,"defaultValue":"null","description":"Cloudflare Turnstile response token. Required only when Turnstile is enabled.","values":"","deprecated":false}],"searchable":true,"compact":false} /-->

A body containing a field named `key`, `passphrase`, `secret`, `plaintext`, or `message` anywhere is refused with `400 psst_unexpected_field` before any other validation. The whole request body is capped at 65536 bytes by default.

<!-- wp:docspress/api-request {"method":"POST","endpoint":"/wp-json/psst/v1/secrets","headers":"Content-Type: application/json\nAccept: application/json","requestBody":"{\n  \"version\": 2,\n  \"ciphertext\": \"9Yp...\",\n  \"iv\": \"Qx2m4Zk8Tn1vW7bA\",\n  \"has_passphrase\": false,\n  \"salt\": null,\n  \"kdf\": null,\n  \"check\": \"L3r8Vb2nQw9xK5mT1cY0Hg\",\n  \"ttl_minutes\": 60,\n  \"hp\": \"\",\n  \"challenge\": null\n}","requestBodyFormat":"json","responseStatus":"201 Created","responseBody":"{\n  \"id\": \"Vq7m2x9kLp3nR8tYwZ1aBc\",\n  \"url\": \"https://example.com/s/Vq7m2x9kLp3nR8tYwZ1aBc/\",\n  \"manage_token\": \"Xk3yPq9mR2vL7nT4wB8cF1hJ6dG0sA5uE9iO2lY3zMw\",\n  \"expires_at\": \"2026-06-01T19:00:00+00:00\",\n  \"expires_in\": 3600,\n  \"expires_label\": \"June 1, 2026 @ 3:00 pm\",\n  \"has_passphrase\": false,\n  \"ttl_minutes\": 60\n}","responseBodyFormat":"json","runnable":false,"editable":false,"allowUnsafe":false,"timeout":10000} /-->

The `manage_token` is shown once and stored only as a hash. The `url` has no fragment; the browser appends `#` and the key it kept. The response is filterable through `psst_create_response`. The permission check runs in this order: body size, per-IP create limit, global create limit, honeypot, then the `psst_create_challenge` filter that Turnstile hooks.

### POST /secrets/{id}/reveal

The only consuming call. `{id}` is 22 to 64 base64url characters. The request body is ignored, but the request must carry `Content-Type: application/json`; any other content type is answered `415 psst_rejected`. This rules out HTML form posts and every fetch made by link previewers, which use `GET`.

<!-- wp:docspress/api-request {"method":"POST","endpoint":"/wp-json/psst/v1/secrets/Vq7m2x9kLp3nR8tYwZ1aBc/reveal","headers":"Content-Type: application/json\nAccept: application/json","requestBody":"{}","requestBodyFormat":"json","responseStatus":"200 OK","responseBody":"{\n  \"id\": \"Vq7m2x9kLp3nR8tYwZ1aBc\",\n  \"envelope\": {\n    \"v\": 2,\n    \"alg\": \"A256GCM\",\n    \"aad\": \"psst/2;pp=0\",\n    \"iv\": \"Qx2m4Zk8Tn1vW7bA\",\n    \"ct\": \"9Yp...\",\n    \"kdf\": null,\n    \"check\": \"L3r8Vb2nQw9xK5mT1cY0Hg\"\n  },\n  \"revealed_at\": \"2026-06-01T18:05:12+00:00\"\n}","responseBodyFormat":"json","runnable":false,"editable":false,"allowUnsafe":false,"timeout":10000} /-->

The claim is a single conditional `UPDATE` that moves the row out of the `publish` status. Only the caller whose update changed exactly one row receives the envelope, and the row is hard-deleted in the same request. Every later call, every concurrent loser, and every call for an expired, shredded, or unknown id receives `404 psst_not_found`. Note the stored envelope uses `v`, `ct`, and nests `salt` inside `kdf`, unlike the create request.

### DELETE /secrets/{id}

Shred a secret before it is read. Authorize with the sender's token in the `X-Psst-Manage-Token` header, or with a `token` body parameter. A logged-in user who can `delete_post` the underlying row, which is an administrator with a REST nonce, may shred without a token. Rate limited at three times the per-IP create limit.

<!-- wp:docspress/api-request {"method":"DELETE","endpoint":"/wp-json/psst/v1/secrets/Vq7m2x9kLp3nR8tYwZ1aBc","headers":"X-Psst-Manage-Token: Xk3yPq9mR2vL7nT4wB8cF1hJ6dG0sA5uE9iO2lY3zMw\nAccept: application/json","requestBody":"","requestBodyFormat":"raw","responseStatus":"200 OK","responseBody":"{\n  \"id\": \"Vq7m2x9kLp3nR8tYwZ1aBc\",\n  \"state\": \"shredded\"\n}","responseBodyFormat":"json","runnable":false,"editable":false,"allowUnsafe":true,"timeout":10000} /-->

A wrong token is `403 psst_bad_token`. An unknown id is `404 psst_not_found`. The `psst_secret_shredded` action fires with the actor, `sender` when the token matched and `admin` otherwise.

### GET /config

Public client configuration, the same object handed to the blocks through the Interactivity API. See [Configuration](../getting-started/configuration.md) for the keys. The callback requests `Cache-Control: public, max-age=300`, but the namespace-wide `no-store` header applied afterwards overrides it in the shipped build.

## Administrator routes

All require `manage_options`. An anonymous request receives `401 psst_forbidden`; a logged-in user without the capability receives `403 psst_forbidden`. Send a `wp_rest` nonce in `X-WP-Nonce`.

| Route | Method | Parameters | Returns |
| --- | --- | --- | --- |
| `/settings` | GET | none | `settings`, `schema`, `ttlCatalog`, `hasTurnstileSecret`, `turnstileByConstant` |
| `/settings` | POST | `settings` object, optional `turnstile_secret` string | Same payload after save. An empty `turnstile_secret` deletes the stored secret; omitting it leaves it alone. |
| `/settings/reset` | POST | none | Same payload with defaults restored. Both page ids are preserved. |
| `/admin/secrets` | GET | `page` (1), `per_page` (20, max 100), `orderby` (`created` or `expires`), `order` (`asc` or `desc`) | Array of rows plus `X-WP-Total` and `X-WP-TotalPages` headers |
| `/admin/health` | GET | none | `actionScheduler`, `nextSweep`, `activeSecrets`, `cronDisabled`, `legacyRemaining`, `turnstileEnabled`, `version`, `createUrl`, `revealUrl` |

Each row of the secrets list contains `id`, `public_id`, `short_id`, `created_at`, `expires_at`, `expired`, `status` (`active`, `expired`, or `pending_delete`), `has_passphrase`, `size`, and `ttl_minutes`. The envelope is never included.

## Error codes

<!-- wp:docspress/fields {"title":"Error codes","description":"Returned as standard WP_Error bodies with <code>code</code>, <code>message</code>, and <code>data.status</code>. Envelope errors add <code>data.field</code> naming the offending field.","fields":[{"name":"psst_invalid_envelope","type":"string","required":false,"defaultValue":"400","description":"A field failed validation, or the body was not JSON. <code>data.field</code> says which.","values":"","deprecated":false},{"name":"psst_unexpected_field","type":"string","required":false,"defaultValue":"400","description":"The body contained <code>key</code>, <code>passphrase</code>, <code>secret</code>, <code>plaintext</code>, or <code>message</code>.","values":"","deprecated":false},{"name":"psst_rejected","type":"string","required":false,"defaultValue":"400 or 415","description":"<code>400</code> when the honeypot is non-empty. <code>415</code> when a reveal is not <code>application/json</code>.","values":"","deprecated":false},{"name":"psst_challenge_failed","type":"string","required":false,"defaultValue":"403","description":"Turnstile did not verify the token. Verification fails closed on network errors.","values":"","deprecated":false},{"name":"psst_bad_token","type":"string","required":false,"defaultValue":"403","description":"The management token did not match and the caller is not an authorized user.","values":"","deprecated":false},{"name":"psst_forbidden","type":"string","required":false,"defaultValue":"401 or 403","description":"An administrator route without <code>manage_options</code>.","values":"","deprecated":false},{"name":"psst_not_found","type":"string","required":false,"defaultValue":"404","description":"Unknown, expired, shredded, or already-revealed id. The single absence code for reveal and shred.","values":"","deprecated":false},{"name":"psst_payload_too_large","type":"string","required":false,"defaultValue":"413","description":"The request body exceeds the request cap, or the ciphertext exceeds the plaintext cap plus the tag.","values":"","deprecated":false},{"name":"psst_rate_limited","type":"string","required":false,"defaultValue":"429","description":"A per-IP or global limit was hit. <code>data.retry_after</code> gives seconds until the window resets.","values":"","deprecated":false},{"name":"psst_storage_failed","type":"string","required":false,"defaultValue":"500","description":"The database insert or update failed.","values":"","deprecated":false}],"searchable":true,"compact":true} /-->

## Rate limiting

Limits are fixed one-hour windows keyed by scope and an HMAC of the client IP, stored in transients. The client IP is `REMOTE_ADDR` unless the **Trusted proxy header** setting is `cloudflare`, in which case `CF-Connecting-IP` is consulted first. Every candidate must be a valid IP address. When no address can be resolved the check is skipped. The `psst_rate_limit` filter receives the limit and the scope (`create`, `create_global`, `reveal`, or `shred`); returning zero or less disables that scope.
