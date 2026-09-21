---
title: Development
---

Two npm projects, one Composer project, a WordPress-free PHPUnit suite, a Node-run crypto suite, and a Playwright suite against WordPress Playground. Everything runs locally without Docker.

## Layout

<!-- wp:docspress/file-tree {"root":"psst/","tree":"psst.php\nuninstall.php\nincludes/\n  Core/\n  Controller/\n  Model/\n  Helper/\nblocks/\n  src/\n    secret-form/\n    secret-viewer/\n    shared/crypto.js\n  build/\nsrc/\n  admin/\n  scss/\nbuild/\npatterns/\ntests/\n  Helper/\n  Model/\n  e2e/\nplayground/blueprint.json\nscripts/build.sh","caption":"The root npm project builds src/admin into build/. The blocks project builds blocks/src into blocks/build. Neither build directory is committed."} /-->

## Set up

Requires PHP 8.3, Composer, and Node 24.13 or later.

<!-- wp:docspress/terminal-session {"title":"Install and build everything","shell":"bash","prompt":"$","command":"composer install\nnpm run install:all\nnpm run build:all","output":""} /-->

`install:all` runs `npm install` in the root and in `blocks/`. `build:all` builds the admin app and then the blocks. Use `npm run start` or `npm run start:blocks` for a watch build of either project.

## The admin chrome

Everything around the four views — the brand bar, the page header and its section links, the two-column body, the help column and the footer — comes from [`@linchpinagency/ui`](https://github.com/linchpin/ui). Its `@wordpress/*` imports are peer dependencies, which this project already has; `@wordpress/admin-ui` is there for the library's `Page` wrapper and is bundled rather than mapped to a `wp.*` global.

Its compiled stylesheet is imported from `src/admin/index.js` and lands in `build/admin.css` ahead of Psst's own rules, which is the order the overrides in `src/scss/admin.scss` assume.

## Scripts

<!-- wp:docspress/code-tabs {"tabs":[{"label":"npm","language":"bash","filename":"Terminal","code":"npm run build          # admin app\nnpm run build:blocks   # the two blocks\nnpm run build:all      # both\nnpm run start          # watch the admin app\nnpm run start:blocks   # watch the blocks\nnpm run lint:js\nnpm run lint:css\nnpm run format\nnpm run test:unit      # crypto vectors under Node\nnpm run playground:start\nnpm run test:e2e"},{"label":"composer","language":"bash","filename":"Terminal","code":"composer php-lint      # parallel-lint\ncomposer phpcs         # coding standards, whole tree\ncomposer phpcbf        # auto-fix\ncomposer phpstan\ncomposer fixer:test    # php-cs-fixer dry run\ncomposer lint          # every static gate\ncomposer phpunit       # unit suite, no WordPress\ncomposer test          # lint then phpunit\ncomposer build         # scripts/build.sh"}],"showLineNumbers":false,"caption":"The composer scripts are the ones CI calls through linchpin/actions."} /-->

Husky and lint-staged run on commit: JavaScript is formatted, styles are linted and fixed, and staged PHP goes through `phpcbf` and a line-scoped `phpcs` so pre-existing issues elsewhere do not block a commit. Commit messages must satisfy Linchpin's commitlint config.

## Tests

### PHP unit suite

<!-- wp:docspress/terminal-session {"title":"Run the PHP suite","shell":"bash","prompt":"$","command":"composer phpunit","output":""} /-->

No WordPress and no database. `tests/bootstrap.php` defines the constants and stubs `add_filter`, `apply_filters`, and `__` so the models run in isolation. PHPUnit is configured to fail on warnings, notices, deprecations, and output. The suite covers base64url encoding against the RFC vectors, id and token generation and matching, the rate limiter's windows and key hashing, every envelope validation rule including each forbidden field, and the expiry catalog.

### Crypto suite

<!-- wp:docspress/terminal-session {"title":"Run the browser crypto vectors","shell":"bash","prompt":"$","command":"npm run test:unit","output":""} /-->

Jest under a Node environment, because jsdom's `crypto` lacks `subtle`. It exercises the shipped `blocks/src/shared/crypto.js` directly: encoding, fragment parsing, round trips with and without a pass phrase, wrong-versus-corrupted distinction, and tamper detection. The PHP and JavaScript suites share the fixtures in `tests/fixtures/envelope-valid.json`.

### End-to-end suite

<!-- wp:docspress/terminal-session {"title":"Run the Playwright suite","shell":"bash","prompt":"$","command":"npm run playwright:install   # once\nnpm run test:e2e","output":""} /-->

Playwright boots WordPress Playground on port 9400 with PHP 8.3 and WordPress 6.9, mounts the checkout as the plugin, activates it, and runs `tests/e2e/seed.php` to make the create page the front page, disable rate limits, and flush rewrites. Tests run serially with one worker. Recipients are opened in a fresh cookie-less browser context, and every reveal request is recorded so tests can assert how many were made. The three specs cover the sender and recipient flow, the REST surface including abuse cases, and the admin screen. The full list of what each spec proves is in [How the encryption works](../guides/encryption.md).

## Local site

<!-- wp:docspress/terminal-session {"title":"Start a throwaway site","shell":"bash","prompt":"$","command":"npm run playground:start","output":"WordPress is running at http://localhost:9400"} /-->

Same Playground the tests use, landing on `/share/`. Playground counts as a secure context, so the Web Crypto API is available. The site is discarded when the process stops.

## Release build

<!-- wp:docspress/terminal-session {"title":"Build the distributable","shell":"bash","prompt":"$","command":"composer build","output":"build/psst.zip"} /-->

The script builds both npm projects, installs production Composer dependencies, copies an allow-list of files through `.distignore`, and then asserts the result: required files exist, each block has `block.json` and `render.php`, nothing from `node_modules`, `src`, `tests`, or `.git` leaked, the packaged autoloader resolves both the plugin and Action Scheduler, and the plugin header version matches `.release-please-manifest.json`. The zip has a single top-level `psst/` folder. Dev dependencies are restored afterwards when not running in CI.

Versions are owned by release-please. Do not edit version numbers by hand; a conventional commit on `main` produces the release pull request.

## Continuous integration

| Workflow | What it runs |
| --- | --- |
| PHP | The shared `php-checks` workflow from `linchpin/actions` on PHP 8.3 and 8.5 with PHPStan and `fixer:test`, plus a grep that rejects misspelled REST arg keys |
| JavaScript | Lint, crypto unit tests, `build:all`, then the Playground end-to-end suite |
| WordPress Plugin Check | Plugin Check against the built plugin |
| Release | release-please |
| WordPress Version Checker | Keeps "Tested up to" current |
| Sync docs to WordPress | Publishes `docs/` to docs.linchpin.com. Manual trigger, dry run and draft by default. |
