---
title: Hooks
---

Sixteen actions and thirty-six filters. No hook receives an envelope, a key, a pass phrase, or plaintext, because the server never has the last three and treats the first as opaque.

There is exactly one exception, and it exists only when the optional email delivery feature is switched on: `psst_share_email_body` is handed the share URL it is filtering, key fragment and all, because that URL is the message. It is listed with the other [account hooks](#account-hooks) and explained in [Accounts](../guides/accounts.md#emailing-a-link).

The two unprefixed actions are kept from 1.x for compatibility.

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
| `psst_secret_destroyed` | `int $post_id, string $reason, string $public_id` | Before any hard delete. `$reason` is `expired`, `revealed`, `shredded`, `admin`, or `sweep` |
| `psst_secrets_swept` | `int $count` | After the daily sweep, with the number of rows removed |

<!-- wp:docspress/colorful-code {"language":"php","filename":"Example: log every destruction","code":"add_action(\n\t'psst_secret_destroyed',\n\tstatic function ( int $post_id, string $reason, string $public_id ): void {\n\t\terror_log( sprintf( 'psst: %s destroyed (%s)', substr( $public_id, 0, 8 ), $reason ) );\n\t},\n\t10,\n\t3\n);","highlightedLines":"4","showLineNumbers":true,"caption":"Log a short prefix rather than the full id so logs cannot be used to reconstruct links."} /-->

## Filters

Several filters apply a floor after the callback returns, so a filter cannot weaken the protocol or open a denial-of-service path. The floor is listed where one exists.

<!-- wp:docspress/fields {"title":"Filters","description":"Listed with their default value and any extra arguments after the filtered value.","fields":[{"name":"psst_secret_args","type":"array","required":false,"defaultValue":"register_post_type() args","description":"Arguments for the <code>psst_secret</code> post type.","values":"","deprecated":false},{"name":"psst_secret_labels","type":"array","required":false,"defaultValue":"labels array","description":"Labels for the post type.","values":"","deprecated":false},{"name":"psst_schedule_options","type":"array","required":false,"defaultValue":"the enabled choices","description":"Minutes to label. Applied by both the option list and the allowed-value check, so removing a key here also rejects it on create.","values":"","deprecated":false},{"name":"psst_slug_length","type":"number","required":false,"defaultValue":"16","description":"Bytes of entropy in a public id. Floor 16, which produces 22 characters.","values":"","deprecated":false},{"name":"psst_date_time_format","type":"string","required":false,"defaultValue":"date_format @ time_format","description":"Format for the expiry label returned on create.","values":"","deprecated":false},{"name":"psst_url_base","type":"string","required":false,"defaultValue":"s","description":"The first path segment of secret links. Passed through <code>sanitize_title()</code>; an empty result falls back to <code>s</code>. Changing it flags a rewrite flush.","values":"","deprecated":false},{"name":"psst_create_page_url","type":"url","required":false,"defaultValue":"the create page permalink","description":"Where the viewer's \"create your own\" link and the cross-sell pattern point. Falls back to the home URL when no page is set.","values":"","deprecated":false},{"name":"psst_client_config","type":"array","required":false,"defaultValue":"the config array","description":"The object handed to the blocks and served at <code>GET /config</code>.","values":"","deprecated":false},{"name":"psst_create_response","type":"array","required":false,"defaultValue":"the response array","description":"The <code>201</code> body. Extra argument: <code>int $post_id</code>.","values":"","deprecated":false},{"name":"psst_create_challenge","type":"any","required":false,"defaultValue":"true","description":"Return a <code>WP_Error</code> to refuse a create. Extra argument: <code>WP_REST_Request $request</code>. Turnstile hooks here at priority 10.","values":"","deprecated":false},{"name":"psst_rate_limit","type":"number","required":false,"defaultValue":"the configured limit","description":"Hits per hour for a scope. Extra argument: <code>string $scope</code>, one of <code>create</code>, <code>create_global</code>, <code>reveal</code>, <code>shred</code>. Zero or less disables.","values":"","deprecated":false},{"name":"psst_client_ip","type":"string","required":false,"defaultValue":"the resolved address","description":"The address used for rate limiting and Turnstile. Return an empty string to skip per-IP limits entirely.","values":"","deprecated":false},{"name":"psst_max_plaintext_bytes","type":"number","required":false,"defaultValue":"the setting","description":"Plaintext cap. Floor 1024.","values":"","deprecated":false},{"name":"psst_max_request_bytes","type":"number","required":false,"defaultValue":"65536","description":"Cap on the create request body. Floor 4096.","values":"","deprecated":false},{"name":"psst_min_kdf_iterations","type":"number","required":false,"defaultValue":"600000","description":"Minimum PBKDF2 iterations accepted on create and advertised to senders. Floor 1000, ceiling 5000000.","values":"","deprecated":false},{"name":"psst_sweep_batch_size","type":"number","required":false,"defaultValue":"200","description":"Rows per sweep pass. Floor 1. The sweep loops while a pass fills the batch.","values":"","deprecated":false},{"name":"psst_frame_options","type":"string","required":false,"defaultValue":"DENY","description":"<code>X-Frame-Options</code> on secret pages. Empty string omits the header.","values":"","deprecated":false},{"name":"psst_disable_page_cache","type":"boolean","required":false,"defaultValue":"true","description":"Whether to define <code>DONOTCACHEPAGE</code> on secret pages.","values":"","deprecated":false},{"name":"psst_content_security_policy","type":"string","required":false,"defaultValue":"''","description":"Opt-in <code>Content-Security-Policy</code> header on secret pages. Empty means no header. See <a href=\"../guides/hardening.md\">Hardening</a> for a starting policy.","values":"","deprecated":false}],"searchable":true,"compact":false} /-->

<!-- wp:docspress/colorful-code {"language":"php","filename":"Example: allow only two expiry choices and default to the shorter","code":"add_filter(\n\t'psst_schedule_options',\n\tstatic fn( array $options ): array => array_intersect_key(\n\t\t$options,\n\t\t[ 60 => true, 1440 => true ]\n\t)\n);","highlightedLines":"5","showLineNumbers":true,"caption":"Keys are minutes. Anything not in the returned array is rejected on create as well as hidden from the form."} /-->

## Account hooks

These exist only when the optional account layer is enabled. See [Accounts](../guides/accounts.md) for what each feature does.

### Actions

| Hook | Arguments | When |
| --- | --- | --- |
| `psst_user_registered` | `int $user_id, string $email` | After an account is created from the front end |
| `psst_sent_secret_recorded` | `int $post_id, int $user_id, string $public_id` | After a history row is written |
| `psst_sent_secret_state` | `string $public_id, string $state, int $post_id` | When a history row reaches `revealed`, `shredded` or `expired` |
| `psst_share_email_sent` | `string $public_id, string $recipient, bool $sent` | After a share email is handed to the mailer |
| `psst_admin_access_blocked` | `int $user_id, string $destination` | Just before a user is redirected out of wp-admin |
| `psst_register_form` | none | Inside the registration form, before the submit button. Where a challenge widget goes |

### Filters

| Hook | Default | Purpose |
| --- | --- | --- |
| `psst_accounts_enabled` | the setting | Force the whole layer on or off |
| `psst_registration_open` | WordPress's own registration setting | Whether the registration form is offered |
| `psst_registration_email_allowed` | `true` | Return false to refuse an address. Extra argument: `string $email` |
| `psst_registration_role` | the site's default role | The role a new account gets |
| `psst_registration_auto_login` | `true` | Whether a new account is signed in immediately. Extra argument: `int $user_id` |
| `psst_registration_rate_limit` | `5` | Registrations per IP per hour. Zero disables |
| `psst_register_challenge` | `true` | Return a `WP_Error` to refuse. Extra argument: `string $email` |
| `psst_registration_error_message` | a generic message | Wording for a registration error code. Extra argument: `string $code` |
| `psst_login_error_message` | a generic message | Wording for a login error code. Extra argument: `string $code` |
| `psst_admin_access_capability` | `edit_posts` | The capability that earns a way into wp-admin |
| `psst_user_locked_out_of_admin` | computed | The final say on one user. Extra argument: `WP_User $user` |
| `psst_admin_lockout_destination` | the account page | Where a blocked user is sent |
| `psst_sent_secret_args` | `register_post_type()` args | Arguments for the `psst_sent` post type |
| `psst_share_email_subject` | a template | The subject line. Extra argument: `string $sender_name` |
| `psst_share_email_body` | a template | The message. **Receives the URL, key fragment and all** |
| `psst_share_email_headers` | plain text | Mail headers. Extra argument: `string $recipient` |
| `psst_share_email_sender_name` | display name, else address | What the sender is called in the email. Extra argument: `int $user_id` |

<!-- wp:docspress/colorful-code {"language":"php","filename":"Example: administrators only, and one domain may register","code":"add_filter(\n\t'psst_admin_access_capability',\n\tstatic fn(): string => 'manage_options'\n);\n\nadd_filter(\n\t'psst_registration_email_allowed',\n\tstatic fn( bool $allowed, string $email ): bool => str_ends_with( $email, '@example.com' ),\n\t10,\n\t2\n);","highlightedLines":"8","showLineNumbers":true,"caption":"The capability filter reads the lockout strictly. A super admin is never locked out either way."} /-->

## Scheduled actions

These are Action Scheduler hooks in the `psst` group, not extension points, but they appear in the Action Scheduler admin and in the Health tab.

| Hook | Schedule | Purpose |
| --- | --- | --- |
| `psst_expire_secret` | Once per secret, at its expiry time, with the post id | Destroys one secret |
| `psst_sweep_secrets` | Daily, first run one hour after activation | Removes expired rows, rows stuck mid-reveal for more than five minutes, rows missing an expiry, and 1.x rows |
| `psst_purge_legacy` | Async, queued by the upgrade while 1.x rows remain | Continues the 1.x purge in batches |

When Action Scheduler is not loaded the same hooks fall back to WP-Cron single and daily events.
