---
title: Hooks
---

Ten actions and nineteen filters. No hook ever receives an envelope, a key, a pass phrase, or plaintext, because the server never has the last three and treats the first as opaque. The two unprefixed actions are kept from 1.x for compatibility.

## Actions

| Hook | Arguments | When |
| --- | --- | --- |
| `before_psst_init` | none | Start of `psst_init()` on `plugins_loaded` |
| `after_psst_init` | none | End of `psst_init()` |
| `psst_ready` | `Bootstrap $bootstrap` | After every controller has registered its hooks |
| `psst_activated` | none | End of activation |
| `psst_secret_created` | `int $post_id, string $public_id` | After the envelope is stored and expiry is scheduled |
| `psst_secret_revealed` | `string $public_id` | After the one-shot claim succeeded, before the response is sent |
| `psst_secret_shredded` | `string $public_id, string $actor` | After a shred. `$actor` is `sender` when the token matched, otherwise `admin` |
| `psst_secret_expired` | `int $post_id` | When the scheduled expiry action runs for a secret |
| `psst_secret_destroyed` | `int $post_id, string $reason, string $public_id` | Before any hard delete. `$reason` is `expired`, `revealed`, `shredded`, or `sweep` |
| `psst_secrets_swept` | `int $count` | After the daily sweep, with the number of rows removed |

<!-- wp:docspress/colorful-code {"language":"php","filename":"Example: log every destruction","code":"add_action(\n\t'psst_secret_destroyed',\n\tstatic function ( int $post_id, string $reason, string $public_id ): void {\n\t\terror_log( sprintf( 'psst: %s destroyed (%s)', substr( $public_id, 0, 8 ), $reason ) );\n\t},\n\t10,\n\t3\n);","highlightedLines":"4","showLineNumbers":true,"caption":"Log a short prefix rather than the full id so logs cannot be used to reconstruct links."} /-->

## Filters

Several filters apply a floor after the callback returns, so a filter cannot weaken the protocol or open a denial-of-service path. The floor is listed where one exists.

<!-- wp:docspress/fields {"title":"Filters","description":"Listed with their default value and any extra arguments after the filtered value.","fields":[{"name":"psst_secret_args","type":"array","required":false,"defaultValue":"register_post_type() args","description":"Arguments for the <code>psst_secret</code> post type.","values":"","deprecated":false},{"name":"psst_secret_labels","type":"array","required":false,"defaultValue":"labels array","description":"Labels for the post type.","values":"","deprecated":false},{"name":"psst_schedule_options","type":"array","required":false,"defaultValue":"the enabled choices","description":"Minutes to label. Applied by both the option list and the allowed-value check, so removing a key here also rejects it on create.","values":"","deprecated":false},{"name":"psst_slug_length","type":"number","required":false,"defaultValue":"16","description":"Bytes of entropy in a public id. Floor 16, which produces 22 characters.","values":"","deprecated":false},{"name":"psst_date_time_format","type":"string","required":false,"defaultValue":"date_format @ time_format","description":"Format for the expiry label returned on create.","values":"","deprecated":false},{"name":"psst_url_base","type":"string","required":false,"defaultValue":"s","description":"The first path segment of secret links. Passed through <code>sanitize_title()</code>; an empty result falls back to <code>s</code>. Changing it flags a rewrite flush.","values":"","deprecated":false},{"name":"psst_create_page_url","type":"url","required":false,"defaultValue":"the create page permalink","description":"Where the viewer's \"create your own\" link and the cross-sell pattern point. Falls back to the home URL when no page is set.","values":"","deprecated":false},{"name":"psst_client_config","type":"array","required":false,"defaultValue":"the config array","description":"The object handed to the blocks and served at <code>GET /config</code>.","values":"","deprecated":false},{"name":"psst_create_response","type":"array","required":false,"defaultValue":"the response array","description":"The <code>201</code> body. Extra argument: <code>int $post_id</code>.","values":"","deprecated":false},{"name":"psst_create_challenge","type":"any","required":false,"defaultValue":"true","description":"Return a <code>WP_Error</code> to refuse a create. Extra argument: <code>WP_REST_Request $request</code>. Turnstile hooks here at priority 10.","values":"","deprecated":false},{"name":"psst_rate_limit","type":"number","required":false,"defaultValue":"the configured limit","description":"Hits per hour for a scope. Extra argument: <code>string $scope</code>, one of <code>create</code>, <code>create_global</code>, <code>reveal</code>, <code>shred</code>. Zero or less disables.","values":"","deprecated":false},{"name":"psst_client_ip","type":"string","required":false,"defaultValue":"the resolved address","description":"The address used for rate limiting and Turnstile. Return an empty string to skip per-IP limits entirely.","values":"","deprecated":false},{"name":"psst_max_plaintext_bytes","type":"number","required":false,"defaultValue":"the setting","description":"Plaintext cap. Floor 1024.","values":"","deprecated":false},{"name":"psst_max_request_bytes","type":"number","required":false,"defaultValue":"65536","description":"Cap on the create request body. Floor 4096.","values":"","deprecated":false},{"name":"psst_min_kdf_iterations","type":"number","required":false,"defaultValue":"600000","description":"Minimum PBKDF2 iterations accepted on create and advertised to senders. Floor 1000, ceiling 5000000.","values":"","deprecated":false},{"name":"psst_sweep_batch_size","type":"number","required":false,"defaultValue":"200","description":"Rows per sweep pass. Floor 1. The sweep loops while a pass fills the batch.","values":"","deprecated":false},{"name":"psst_frame_options","type":"string","required":false,"defaultValue":"DENY","description":"<code>X-Frame-Options</code> on secret pages. Empty string omits the header.","values":"","deprecated":false},{"name":"psst_disable_page_cache","type":"boolean","required":false,"defaultValue":"true","description":"Whether to define <code>DONOTCACHEPAGE</code> on secret pages.","values":"","deprecated":false},{"name":"psst_content_security_policy","type":"string","required":false,"defaultValue":"''","description":"Opt-in <code>Content-Security-Policy</code> header on secret pages. Empty means no header. See <a href=\"../guides/hardening.md\">Hardening</a> for a starting policy.","values":"","deprecated":false}],"searchable":true,"compact":false} /-->

<!-- wp:docspress/colorful-code {"language":"php","filename":"Example: allow only two expiry choices and default to the shorter","code":"add_filter(\n\t'psst_schedule_options',\n\tstatic fn( array $options ): array => array_intersect_key(\n\t\t$options,\n\t\t[ 60 => true, 1440 => true ]\n\t)\n);","highlightedLines":"5","showLineNumbers":true,"caption":"Keys are minutes. Anything not in the returned array is rejected on create as well as hidden from the form."} /-->

## Scheduled actions

These are Action Scheduler hooks in the `psst` group, not extension points, but they appear in the Action Scheduler admin and in the Health tab.

| Hook | Schedule | Purpose |
| --- | --- | --- |
| `psst_expire_secret` | Once per secret, at its expiry time, with the post id | Destroys one secret |
| `psst_sweep_secrets` | Daily, first run one hour after activation | Removes expired rows, rows stuck mid-reveal for more than five minutes, rows missing an expiry, and 1.x rows |
| `psst_purge_legacy` | Async, queued by the upgrade while 1.x rows remain | Continues the 1.x purge in batches |

When Action Scheduler is not loaded the same hooks fall back to WP-Cron single and daily events.
