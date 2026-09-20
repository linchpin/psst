---
title: Accounts
---

Psst works without accounts and always has: a visitor opens the create page, types a secret, and gets a link. Nobody signs in, and the server is told nothing about who sent it.

The account layer is optional and adds a second way to use the plugin. A signed-in sender gets a record of the secrets they have sent — who each one was for, when it expires, and whether it has been read — plus, if the site allows it, the ability to have Psst email the link for them. It is designed for a site where the same people share secrets every week and want to know what happened to them.

**Every setting on this page is off by default.** A site that updates Psst gets no login page, no registration, no new data store and no change to wp-admin unless somebody turns it on.

## What a sender can see

This is the part worth being precise about, because it is the part people assume wrongly.

An account shows **metadata**. It cannot show a secret, and no setting makes it. The reason is the same one the rest of the plugin rests on: the key that decrypts a secret is generated in the sender's browser and travels in the link's `#fragment`, which browsers never send to a server. WordPress has ciphertext and no key. After a reveal it does not even have that, because the row is deleted in the same request that hands it over.

So a history row records:

| Field | Where it comes from |
| --- | --- |
| Short identifier | The first eight characters of the public id |
| Created, expires | The secret's own timestamps |
| State | `active`, `revealed`, `shredded` or `expired` |
| Recipient | Whatever the sender typed in the "who is it for?" box — an address, or a name |
| Emailed | Whether Psst sent the link, or the sender copied it |
| Pass phrase | Whether there was one. Never what it was |
| Size | The byte length of the ciphertext |

A history row outlives the secret it describes, which is the whole point — by the time anyone wants to look, the secret is usually gone. Rows are deleted on their own schedule, after **Keep records for** days, and they are deleted with the user if the account is.

## Turning it on

**Settings → Psst → Accounts**. The master toggle creates three pages the first time it is switched on:

| Page | Block | Default slug |
| --- | --- | --- |
| Sign In | `psst/login-form` | `/sign-in/` |
| Create an Account | `psst/register-form` | `/register/` |
| Your Account | `psst/account` | `/account/` |

They are ordinary pages. Edit them, restyle them, move the blocks into your own layout, or point the settings at pages you built yourself.

The remaining toggles are independent:

- **Let visitors create an account.** WordPress still has the final say. If registration is closed in **Settings → General**, or network-wide on multisite, it stays closed and the registration block says so.
- **Require an account to send a secret.** Off by default, which keeps anonymous sending working. On, the create page shows a sign-in prompt and the REST route refuses an unauthenticated create.
- **Keep users out of the WordPress admin.** Covered below.
- **Record the secrets a signed-in user sends.** The history described above.
- **Let senders email the link from this site.** Covered below, and read that section before switching it on.

## Signing in

The front end pages do not replace WordPress's authentication; they wrap it. The sign-in form posts to `wp-login.php`, which is the only thing that checks a password. What changes is the routing around it:

- A plain `GET` of `wp-login.php` redirects to the sign-in page.
- `wp_login_url()`, `wp_logout_url()`, `wp_lostpassword_url()` and `wp_registration_url()` return the front end pages.
- A failed attempt comes back to the sign-in page with a message, rather than rendering core's error screen.
- Password reset requests are handled on the sign-in page; the reset link itself still goes to core's screen, which owns the token.

Actions core must keep — `logout`, `resetpass`, `rp`, `postpass`, `confirmaction`, the interim-login modal — are never intercepted.

### The escape hatch

`https://example.com/wp-login.php?psst=bypass` always renders WordPress's own login form, whatever the settings say.

This exists because the two features on this page can combine badly. If the sign-in page is deleted, unpublished, or simply has no login block on it, and wp-admin is locked to non-administrators, then every route in is a page that cannot log anyone in. Bookmark the bypass URL before you turn the lockout on.

## Keeping accounts out of wp-admin

**Keep users out of the WordPress admin** redirects anyone without a content role to the account page when they try to reach wp-admin — their profile screen included — and hides the admin bar for them.

The gate is a capability, not a role name. By default it is `edit_posts`, which is the capability that means "you have work to do in there":

| Role | Reaches wp-admin |
| --- | --- |
| Subscriber (what a new account gets) | No |
| Contributor, Author, Editor | Yes |
| Administrator | Yes |
| Super admin / network admin | Yes, always |

To read it strictly as "administrators only", filter it:

```php
add_filter( 'psst_admin_access_capability', static fn(): string => 'manage_options' );
```

A super administrator is never locked out of a subsite, whatever the capability is set to. Locking one out would mean losing the only account that can put the site right.

Three entry points are always allowed through, because they boot the admin without being the admin: `admin-ajax.php`, `admin-post.php`, and anything running as REST or WP-Cron. Blocking those would break front end code across the site, including Psst's own.

## On multisite

Registration goes through the network path. `wpmu_validate_user_signup()` checks the name and address against the network's rules — including its banned-domains list — and `wpmu_create_user()` creates an account that belongs to the network and to no site on it. Psst then calls `add_user_to_blog()` for the current site, because without it the new user signs in to nothing.

Two things worth knowing:

- **Registration follows the network setting.** `registration` must be `user` or `all` in network settings, not `none`.
- **History is per site.** A user on three sites of a network has three separate records, because a secret belongs to the site it was created on.

## Emailing a link

> **This weakens the guarantee the rest of Psst makes.** It is off by default and should stay off unless the tradeoff below is one you want.

Everywhere else in this plugin, the decryption key never reaches the server. The create route goes as far as rejecting any request carrying a field named `key`, `passphrase`, `secret`, `plaintext` or `message`.

For Psst to email a link the recipient can actually open, that has to stop being true, because the server is what sends the mail. Two things follow:

1. **The key is in a request body.** For the length of one request. Anything that logs request bodies — a debugging plugin, an application firewall, a proxy someone configured verbosely — can capture it.
2. **The key is then in a mailbox.** The recipient's, and their provider's, and any backup either keeps, for as long as the message exists. Anyone who can read that email can read the secret.

What the implementation does guarantee is narrower:

- The fragment composes one message and is never written to the database, never passed to a hook, and never logged.
- Only the fragment comes from the browser. The rest of the URL is rebuilt server-side from the public id, so the route cannot be used to mail an arbitrary link from your domain.
- The route requires the sender's one-time management token, so only whoever created a secret can trigger mail about it.
- It is rate limited, and the message is a fixed plain-text template. A sender's free-text note is available only to signed-in users and is capped.

The sender sees the tradeoff in the form, next to the checkbox, before they tick it — not in a footnote.

If you want the convenience without the cost, copy the link and send it through something you already trust. That path is unchanged and still involves no key ever reaching the server.

## Hooks

Filters:

| Hook | Default | Purpose |
| --- | --- | --- |
| `psst_accounts_enabled` | the setting | Force the whole layer on or off |
| `psst_registration_open` | WordPress's own setting | Whether the registration form is offered |
| `psst_registration_email_allowed` | `true` | Return false to refuse an address. Domain allow-lists go here |
| `psst_registration_role` | the site default role | The role a new account gets |
| `psst_registration_auto_login` | `true` | Whether a new account is signed in immediately |
| `psst_registration_rate_limit` | `5` | Registrations per IP per hour. Zero disables |
| `psst_register_challenge` | `true` | Return a `WP_Error` to refuse. The registration twin of `psst_create_challenge` |
| `psst_admin_access_capability` | `edit_posts` | The capability that earns a way into wp-admin |
| `psst_user_locked_out_of_admin` | computed | The final say on one user |
| `psst_admin_lockout_destination` | the account page | Where a blocked user is sent |
| `psst_share_email_subject` | a template | The subject line |
| `psst_share_email_body` | a template | The message. **Receives the URL, key and all** |
| `psst_share_email_headers` | plain text | Mail headers |
| `psst_share_email_sender_name` | display name | What the sender is called in the email |
| `psst_login_error_message` | a generic message | Wording for a login error code |
| `psst_registration_error_message` | a generic message | Wording for a registration error code |

Actions:

| Hook | Arguments | When |
| --- | --- | --- |
| `psst_user_registered` | `int $user_id, string $email` | After an account is created from the front end |
| `psst_sent_secret_recorded` | `int $post_id, int $user_id, string $public_id` | After a history row is written |
| `psst_sent_secret_state` | `string $public_id, string $state, int $post_id` | When a history row reaches a terminal state |
| `psst_share_email_sent` | `string $public_id, string $recipient, bool $sent` | After a share email is handed to the mailer |
| `psst_admin_access_blocked` | `int $user_id, string $destination` | Just before a user is redirected out of wp-admin |
| `psst_register_form` | none | Inside the registration form, before the submit button |

Note the one exception to the rule that no Psst hook ever sees a key: `psst_share_email_body` does, because it is handed the message it is filtering. A callback that logs or stores that URL defeats the point of the rest of this plugin.

## Removing it

Switching the master toggle off disables the whole layer — login routing, registration, the lockout, history recording and email — in one step. Nothing is deleted:

- The three pages stay. They are your content; delete them yourself if you want them gone.
- History rows stay and are still pruned on schedule. The post type is registered whether or not the setting is on, precisely so that turning it off cannot orphan rows that nothing can read or delete.
- Accounts stay. They are the site's users, not the plugin's.

Uninstalling Psst with **Delete all secrets and settings on uninstall** enabled removes the history rows along with the secrets. It never removes user accounts.
