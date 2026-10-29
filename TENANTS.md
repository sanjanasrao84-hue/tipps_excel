# Multi-tenant setup

One codebase serves many clients (tenants). Each tenant has its own database and data folder and is
picked by subdomain:

| URL | Database | Data folder |
|---|---|---|
| `http://localhost/tipps_excel` (main site) | `tipps_moodle` | `D:\xampp-8.5\tippsmoodledata` |
| `http://<slug>.localhost/tipps_excel` | `tipps_<slug>` | `D:\xampp-8.5\tenants\<slug>` |

How it works: `config.php` reads the subdomain (or the `TENANT` environment variable for CLI scripts)
and points Moodle at that tenant's database and data folder. A tenant exists when its data folder exists;
any other subdomain gets a 404. `config-local.php` (not in git) holds the settings to change per machine:
`$basedomain`, `$urlpattern` and `$tenantsroot`.

All commands below run from the project root (`D:\xampp-8.5\htdocs\tipps_excel`). On this machine use
`D:\xampp-8.5\php\php.exe` in place of `php`.

## Create a tenant

```
php tenants.php create <slug> <adminpass> "Site full name"
```

- `<slug>`: 1–30 lowercase letters/digits, e.g. `acme`. It becomes the subdomain and the database name.
- Takes a few minutes (it runs the full Moodle installer).
- If it fails, the database and folder are removed again, so you can rerun the same command.

Example:

```
php tenants.php create acme "Acme#Pass123" "Acme Training"
```

Then open `http://acme.localhost/tipps_excel` and log in as `admin` / `Acme#Pass123`.
On first login Moodle asks the admin to fill in an email address.

## Seed test data

```
php tenants.php seed <slug> [XS|S|M] [userpass]
```

Fills a tenant with generated courses, users, enrolments and activities using Moodle's built-in
generator (`admin/tool/generator`). Use it on **test tenants only**: the data can't be removed
separately — delete the tenant instead.

| Size | Courses | Approx. data | Approx. time |
|---|---|---|---|
| XS (default) | 3 | 10 MB | 30 s |
| S | 8 | 50 MB | 2 min |
| M | 73 | 200 MB | 10 min |

Test users are `tool_generator_000001`, `tool_generator_000002`, … with password `userpass`
(default `Test#Pass123`). The data set is fixed, so every seeded tenant gets the same content.

Example — a full test tenant from scratch:

```
php tenants.php create demo "Demo#Pass123" "Demo Client"
php tenants.php seed demo S
```

## Other commands

```
php tenants.php list                      # list tenants
php tenants.php delete <slug>             # drop database + data folder (asks you to type the slug)
php tenants.php each <cli script> [args]  # run a Moodle CLI script for the main site and every tenant
```

`each` is how you maintain all sites at once:

```
php tenants.php each admin/cli/upgrade.php --non-interactive   # after every code update
php tenants.php each admin/cli/cron.php --keep-alive=0          # schedule every minute
php tenants.php each admin/cli/purge_caches.php
```

To run any Moodle CLI script for one tenant, set `TENANT` (PowerShell: `$env:TENANT="acme"`;
cmd: `set TENANT=acme`; Linux: `TENANT=acme php ...`). Unset means the main site.

## Local testing

Browsers resolve `*.localhost` to your own machine, so `http://<slug>.localhost/tipps_excel` works with
no setup. If a tool can't find the host, add a line per tenant to `C:\Windows\System32\drivers\etc\hosts`
(edit as administrator): `127.0.0.1 acme.localhost`.

Each subdomain has its own login cookie, so you can be logged in to several tenants at once.

## Production checklist

- DNS: wildcard record `*.yourdomain.com` → server IP.
- SSL: wildcard certificate (Let's Encrypt needs DNS validation for wildcards).
- Apache: one VirtualHost with `ServerAlias *.yourdomain.com` and `DocumentRoot` set to `<project>/public`.
- `config-local.php`: `$basedomain = 'yourdomain.com'`, `$urlpattern = 'https://%s'`, `$tenantsroot` outside the web root.
- Database: a dedicated user that may create databases, not `root` with an empty password.
- Cron: `php tenants.php each admin/cli/cron.php --keep-alive=0` every minute. Tenants run one after another;
  with many tenants a pass can take longer than a minute.
- Backups: each tenant's database (`tipps_<slug>`) plus its data folder.

Full server installation (Apache, PHP, MariaDB, import, SSL, tenants): see [SERVER_SETUP.md](SERVER_SETUP.md).
