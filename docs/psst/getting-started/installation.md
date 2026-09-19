---
title: Installation
---

Psst is distributed through the WordPress.org plugin directory, Linchpin's private Composer repository, and as a release zip. Every route ships the same built package; the difference is who runs the build and how updates arrive.

## Requirements

| Requirement | Value |
| --- | --- |
| WordPress | 6.9 or later, block theme recommended |
| PHP | 8.3 or later |
| HTTPS | Required. The Web Crypto API runs only in secure contexts. |
| Composer | Only for the Composer route or a source checkout |

## Install from WordPress.org

The simplest route, and the one that receives updates through the WordPress updater.

1. Open **Plugins → Add New**, search for **Psst**, and click **Install Now**.
2. Activate **Psst** on the Plugins screen.

The directory listing is at [wordpress.org/plugins/psst](https://wordpress.org/plugins/psst/). The package there is the same one `scripts/build.sh` produces for the release zip, committed to the plugin directory's SVN by the release workflow.

## Install with Composer

The project's `composer.json` must list Linchpin's package repository before the package resolves.

<!-- wp:docspress/colorful-code {"language":"json","filename":"composer.json","code":"{\n  \"repositories\": [\n    { \"type\": \"composer\", \"url\": \"https://packagist.linchpin.com\" }\n  ]\n}","highlightedLines":"3","showLineNumbers":true,"caption":"Add the repository once per project."} /-->

<!-- wp:docspress/terminal-session {"title":"Require the plugin","shell":"bash","prompt":"$","command":"composer require linchpin/psst","output":""} /-->

The package type is `wordpress-plugin`, so `composer/installers` places it under `wp-content/plugins/psst/`. The plugin's own `vendor/` directory ships inside the package and includes Action Scheduler.

<!-- wp:docspress/callout {"tone":"note","title":"Composer deploys never fire the activation hook","content":"<p>When a deploy pipeline installs the plugin and the option that marks it active is already set, WordPress does not run activation. Psst accounts for this: capabilities are granted on <code>init</code> when the <code>psst_caps_added</code> option is missing, and the 1.x upgrade runs on <code>init</code> as well. The two pages, however, are created only by activation. If secret links 404 after a fresh deploy, open <strong>Settings → Psst</strong>, and if no viewer page is set, create a page containing the viewer block and select it there.</p>","collapsible":false} /-->

## Install from a release zip

Release zips are built by `scripts/build.sh` and attached to each GitHub release. The zip has a single top-level `psst/` folder.

1. Upload the zip under **Plugins → Add New → Upload Plugin**, or unzip it into `wp-content/plugins/`.
2. Activate **Psst** on the Plugins screen.

A release zip already contains the compiled admin app, the compiled blocks, and production Composer dependencies. Nothing needs to be built on the server.

## Install from a source checkout

A git checkout has no built assets and no `vendor/` directory. Until you build them, activating the plugin shows *Psst could not load its dependencies. Run composer install inside the plugin directory, or install a release build.* in the admin.

<!-- wp:docspress/terminal-session {"title":"Build a source checkout","shell":"bash","prompt":"$","command":"composer install\nnpm run install:all\nnpm run build:all","output":""} /-->

There are two npm projects. The root one builds the admin app into `build/`, and `blocks/` builds the two blocks into `blocks/build/`. The `build:all` script runs both. Building only one leaves the other stale. See [Development](../reference/development.md) for the full command list.

## What activation does

`Install::activate()` runs these steps in order.

<!-- wp:docspress/flow {"start":1,"steps":[{"title":"Grant capabilities","content":"<p>The administrator role receives the ten <code>psst_secret</code> capabilities. No other role is touched.</p>"},{"title":"Seed settings","content":"<p>The <code>psst_settings</code> option is created with defaults if it does not exist. Existing values are never overwritten.</p>"},{"title":"Create the pages","content":"<p><strong>Secret</strong> at slug <code>s</code> containing the viewer block, and <strong>Share a Secret</strong> at slug <code>share</code> containing the create pattern. A published page already at either slug is reused rather than duplicated.</p>"},{"title":"Run the upgrade check","content":"<p>If 1.x data is present it is removed. See <a href=\"../guides/upgrading-from-1x.md\">Upgrading from 1.x</a>.</p>"},{"title":"Schedule the sweep","content":"<p>A daily <code>psst_sweep_secrets</code> action is registered with Action Scheduler, and any active secret missing an expiry action is re-armed.</p>"},{"title":"Flag a rewrite flush","content":"<p>The <code>/s/{id}/</code> rule is added on the next <code>init</code>, then rules are flushed once.</p>"}]} /-->

## Verify

<!-- wp:docspress/terminal-session {"title":"Confirm the public configuration endpoint answers","shell":"bash","prompt":"$","command":"curl -s https://example.com/wp-json/psst/v1/config | head -c 200","output":"{\"restUrl\":\"https://example.com/wp-json/psst/v1/\",\"createUrl\":\"https://example.com/share/\",\"ttlOptions\":{\"10080\":\"1 Week\", ..."} /-->

Then open **Settings → Psst → Health**. It should report Action Scheduler as available, a next sweep time, and both page URLs. A viewer page reported as *Not set* means secret links will not resolve.

## Deactivation and uninstall

Deactivation clears the scheduled actions and flushes rewrite rules. Secrets, settings, and the two pages are kept.

Uninstall, run from the Plugins screen after deactivation, deletes every secret, the plugin's options, transients, capabilities, and Action Scheduler rows. It leaves Action Scheduler's own tables and the two pages in place. Set **Delete everything on uninstall** to off in the settings to keep the data instead. The default is on.
