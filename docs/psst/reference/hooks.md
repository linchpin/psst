# Hooks

## Actions

| Hook | Arguments | When |
| --- | --- | --- |
| `before_psst_init`, `after_psst_init` | | Around bootstrap on `plugins_loaded` |
| `psst_ready` | `Bootstrap` | After every controller registered its hooks |
| `psst_activated` | | End of activation |
| `psst_secret_created` | `int $post_id, string $public_id` | After a secret is stored |
| `psst_secret_revealed` | `string $public_id` | After the one-time reveal |
| `psst_secret_shredded` | `string $public_id, string $actor` | `sender` or `admin` |
| `psst_secret_expired` | `int $post_id` | Scheduled expiry ran |
| `psst_secret_destroyed` | `int $post_id, string $reason, string $public_id` | Before any deletion |
| `psst_secrets_swept` | `int $count` | After the daily sweep |

No hook ever receives an envelope or plaintext.

## Filters

| Hook | Default | Purpose |
| --- | --- | --- |
| `psst_secret_args`, `psst_secret_labels` | | Post type registration |
| `psst_schedule_options` | the enabled choices | Minutes => label |
| `psst_slug_length` | `16` | Bytes of entropy in an id (floor 16) |
| `psst_date_time_format` | site date @ time | Expiry label |
| `psst_url_base` | `s` | The link prefix |
| `psst_create_page_url` | the create page permalink | Where "create your own" links go |
| `psst_client_config` | | Configuration handed to the blocks |
| `psst_create_response` | | The create response body |
| `psst_create_challenge` | `true` | Return a `WP_Error` to refuse a create |
| `psst_rate_limit` | per scope | Hits per hour, `0` disables |
| `psst_client_ip` | | The address used for rate limiting |
| `psst_max_plaintext_bytes` | setting | Plaintext cap |
| `psst_max_request_bytes` | `65536` | Request body cap |
| `psst_min_kdf_iterations` | `600000` | PBKDF2 floor |
| `psst_sweep_batch_size` | `200` | Rows per sweep pass |
| `psst_frame_options` | `DENY` | X-Frame-Options on secret pages |
| `psst_disable_page_cache` | `true` | Define `DONOTCACHEPAGE` on secret pages |
| `psst_content_security_policy` | `''` | Opt-in CSP header on secret pages |
