# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Moodle 5.3 site (`public/version.php`) with custom plugins added and a multi-tenant layer on top of it.
Almost all of `public/` is unmodified Moodle core. Change core files only when there is no other way, because
core updates overwrite them.

Moodle 5.x layout: the web root is `public/`, and the project root holds `config.php`, `admin/cli/`
(the CLI scripts), `lib/components.json` / `lib/plugins.json`, and the build tooling (Grunt, Jest,
composer). Point Apache's `DocumentRoot` at `public/`.

## Multi-tenancy (custom, not part of Moodle)

See `TENANTS.md` for the full operator guide.

- `config.php` is in git and holds only the tenant logic. It picks the database and dataroot from the subdomain:
  `<slug>.<basedomain>` → DB `tipps_<slug>` and dataroot `$tenantsroot/<slug>`. The bare `<basedomain>` →
  `$maindbname` / `$maindataroot`. A tenant counts as provisioned only if its dataroot directory exists.
  Any other host gets a 404.
- Machine-specific values (`$basedomain`, `$urlpattern`, `$tenantsroot`, DB credentials, main DB/dataroot)
  live in the untracked `config-local.php`. Copy `config-local.example.php` to create it. Never commit
  `config-local.php`.
- In CLI scripts, the `TENANT` env var picks the tenant. Unset means the main site
  (PowerShell: `$env:TENANT="acme"`).
- `tenants.php` (project root) manages tenants:
  ```
  php tenants.php create <slug> <adminpass> ["Full name"]   # CREATE DATABASE + install_database.php, rolls back on failure
  php tenants.php seed <slug> [XS|S|M] [userpass]           # tool_generator test data (test tenants only)
  php tenants.php list | delete <slug>
  php tenants.php each <cli script> [args]                  # run for main site + every tenant
  ```
- After any code change that bumps a plugin `version.php`, upgrade every site:
  `php tenants.php each admin/cli/upgrade.php --non-interactive`. Purge caches with
  `php tenants.php each admin/cli/purge_caches.php`.

## Custom plugins

These are the plugins that are not in Moodle's standard list. Run the check below to regenerate the list,
because it may change:

```
php -r '$s=json_decode(file_get_contents("lib/plugins.json"),true)["standard"];$c=json_decode(file_get_contents("lib/components.json"),true);foreach($c["plugintypes"] as $t=>$d)foreach(glob("$d/*",GLOB_ONLYDIR) as $p)if(is_file("$p/version.php")&&!in_array(basename($p),$s[$t]??[]))echo "$t $p\n";'
```

- **Subscriptions and payments.**
  - `local_lmssub` holds the subscription plans, access checks, attempts and checkout. It depends on
    `enrol_lmssub` and implements the `core_payment` service provider in `classes/payment/`.
  - The payment gateways `paygw_lmspay` and `paygw_razorpay` support UPI.
  - `report_orgreports` reports on organisation sales and payments and reads the lmssub payment data.
- **Google Calendar.** `local_gcal` handles the OAuth connection. `block_gcal_upcoming` depends on it
  (it was renamed so it can sit next to core `block_calendar_upcoming`).
- **Themes.** `theme_eadflix` is a child of `theme_eadtraining`, which is a child of `theme_boost`.
- **Other plugins.** `mod_customcert` (third-party, kept up to date from upstream), `mod_scheduler`, `mod_zoom`,
  `mod_zoomlink`, `block_allcourses`, `filter_vdocipher`.

Follow standard Moodle plugin conventions:
- `version.php`: bump it on every DB or capability change.
- `db/install.xml` and `db/upgrade.php` for the database schema.
- `db/access.php` for capabilities.
- `lang/en/` for strings.
- `classes/` for autoloaded namespaced classes.
- `amd/src` for JS, built into `amd/build`.

## Sample content scripts

`samples/` holds one-off CLI scripts that build demo courses (psychiatry and health/stress content). They
`require` a hardcoded config path (`C:/xampp/htdocs/tipps_excel_lms/config.php`). Pass
`--config=<path to this repo's config.php>` (and set `TENANT` to target a tenant). Use `--replace` to recreate
an existing course.

## Commands

The PHP binary depends on the machine. `TENANTS.md` uses `D:\xampp-8.5\php\php.exe` on one dev machine, and on
this one it is XAMPP's PHP.

- JS/CSS build (Node version in `.nvmrc`; run `npm install` first):
  - `npx grunt` builds everything.
  - `npx grunt amd --root=public/local/lmssub` builds one plugin's AMD modules.
  - `npx grunt scss` builds the SCSS.
- Jest: `npm test`, or `npx jest <path>` to run a single test file.
- PHPUnit (needs `composer install` and the `$CFG->phpunit_*` settings in `config-local.php`):
  - `php public/admin/tool/phpunit/cli/init.php` sets up the test environment.
  - `vendor/bin/phpunit --filter <test_name> public/<plugin>/tests/<file>_test.php` runs a single test.
- Code style: `vendor/bin/phpcs --standard=moodle <path>` (config in `phpcs.xml.dist`). ESLint and stylelint
  run through Grunt.
