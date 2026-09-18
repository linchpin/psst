# REST API

Namespace `psst/v1`. All responses carry `Cache-Control: no-store`, `X-Robots-Tag:
noindex, nofollow` and `Referrer-Policy: no-referrer`.

## `POST /secrets`

Public. Body:

| Field | Type | Notes |
| --- | --- | --- |
| `version` | `2` | |
| `ciphertext` | base64url | AES-GCM output including the 16-byte tag |
| `iv` | base64url | 12 bytes |
| `has_passphrase` | boolean | |
| `salt` | base64url | 16 bytes, only with a pass phrase |
| `kdf` | object | `{ name: "PBKDF2", hash: "SHA-256", iterations }`, only with a pass phrase |
| `check` | base64url | 16 bytes |
| `ttl_minutes` | integer | one of the enabled choices |
| `hp` | string | honeypot, must be empty |
| `challenge` | string | Turnstile token when enabled |

Returns `201` with `id`, `url`, `manage_token` (shown once), `expires_at`, `expires_in`,
`expires_label`, `has_passphrase`, `ttl_minutes`. Errors: `413 psst_payload_too_large`,
`429 psst_rate_limited` with `retry_after`, `400 psst_invalid_envelope` naming `data.field`,
`403 psst_challenge_failed`.

## `POST /secrets/{id}/reveal`

The only consuming call. Requires `Content-Type: application/json`. Returns `200` with
`envelope` once; every later call, and every call for an expired or shredded id, is
`404 psst_not_found`.

## `DELETE /secrets/{id}`

Shred. Send the sender's token in `X-Psst-Manage-Token`, or call it as a logged-in
administrator with a REST nonce.

## `GET /config`

Public client configuration: expiration options and default, size cap, KDF iterations,
Turnstile site key, create page URL.

## Administrator routes

`GET /settings`, `POST /settings` (`{ settings, turnstile_secret? }`), `POST /settings/reset`,
`GET /admin/secrets?page&per_page&orderby&order` (metadata only), `GET /admin/health`.
