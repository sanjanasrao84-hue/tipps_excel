# Server setup guide (Ubuntu)

A full setup from a bare Ubuntu server to a running site with multi-tenancy:

1. [Requirements](#1-requirements)
2. [Install Apache, PHP, MariaDB](#2-install-apache-php-mariadb)
3. [Get the code](#3-get-the-code)
4. [Database: create and import](#4-database-create-and-import)
5. [Data folder (moodledata)](#5-data-folder-moodledata)
6. [config-local.php](#6-config-localphp)
7. [Apache site + SSL](#7-apache-site--ssl)
8. [Cron](#8-cron)
9. [Multi-tenant setup](#9-multi-tenant-setup)
10. [Deploying updates](#10-deploying-updates)
11. [Backups](#11-backups)
12. [Troubleshooting](#12-troubleshooting)

Examples below use:

| Thing | Value |
|---|---|
| Main site | `https://appointment.samadhana.co.in` |
| Tenant sites | `https://<slug>.appointment.samadhana.co.in` |
| Code | `/var/www/tipps_excel` |
| Main data folder | `/var/www/moodledata` |
| Tenant data folders | `/var/www/moodledata-tenants/<slug>` |
| Main database | `tipps_moodle` |
| Tenant databases | `tipps_<slug>` |
| DB user | `moodle` |

Replace them with your own values where they differ.

---

## 1. Requirements

This is Moodle 5.3. It needs:

- **PHP 8.3 or newer** with these extensions: curl, gd, intl, mbstring, mysql, soap, xml, zip, sodium (plus
  iconv, ctype, fileinfo, exif, tokenizer, which are bundled with PHP on Ubuntu).
- **MariaDB 11.4 or newer** (or MySQL 8.4+).
- Apache 2.4.
- Ubuntu 24.04 LTS or newer.

> Ubuntu 24.04 ships MariaDB **10.11**, which is too old, so step 2 installs MariaDB 11.4 from the
> official MariaDB repository.

---

## 2. Install Apache, PHP, MariaDB

### 2.1 System update and basic tools

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl unzip
```

### 2.2 Apache

```bash
sudo apt install -y apache2
sudo a2enmod rewrite ssl headers
sudo systemctl enable --now apache2
```

### 2.3 PHP

```bash
sudo apt install -y php libapache2-mod-php php-cli \
  php-mysql php-curl php-gd php-intl php-mbstring php-soap php-xml php-zip php-bcmath php-opcache
php -v        # must show 8.3 or newer
```

PHP settings for Moodle. These apply to both the website and command-line scripts:

```bash
PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
sudo tee /etc/php/$PHPV/mods-available/moodle.ini > /dev/null <<'EOF'
memory_limit = 256M
upload_max_filesize = 200M
post_max_size = 200M
max_input_vars = 5000
max_execution_time = 300
opcache.enable = 1
EOF
sudo phpenmod moodle
sudo systemctl restart apache2
php -i | grep max_input_vars   # should show 5000
```

### 2.4 MariaDB 11.4

```bash
curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup | sudo bash -s -- --mariadb-server-version="mariadb-11.4"
sudo apt update
sudo apt install -y mariadb-server mariadb-client
sudo systemctl enable --now mariadb
mariadb --version   # must show 11.4 or newer
sudo mariadb-secure-installation   # set a root password, remove anonymous users and test DB
```

Character set for Moodle:

```bash
sudo tee /etc/mysql/mariadb.conf.d/90-moodle.cnf > /dev/null <<'EOF'
[mysqld]
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
innodb_file_per_table = 1
max_allowed_packet = 256M
EOF
sudo systemctl restart mariadb
```

### 2.5 Database user

One user serves the main site and all tenants. It must be allowed to create the `tipps_*` databases, because
`tenants.php` creates one per tenant.

```bash
sudo mariadb <<'EOF'
CREATE USER 'moodle'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON `tipps\_%`.* TO 'moodle'@'localhost';
FLUSH PRIVILEGES;
EOF
```

If your main database is **not** named `tipps_...`, also grant it:
``GRANT ALL PRIVILEGES ON `your_db_name`.* TO 'moodle'@'localhost';``

---

## 3. Get the code

```bash
sudo git clone https://github.com/sanjanasrao84-hue/tipps_excel.git /var/www/tipps_excel
```

If the repository is private, GitHub will ask for a username plus a
[personal access token](https://github.com/settings/tokens) as the password (or set up a deploy key).

The web server only needs to **read** the code:

```bash
sudo chown -R root:root /var/www/tipps_excel
sudo find /var/www/tipps_excel -type d -exec chmod 755 {} \;
sudo find /var/www/tipps_excel -type f -exec chmod 644 {} \;
```

No `composer install` is needed: the required libraries are already in the repository.

---

## 4. Database: create and import

Choose **A** (move an existing site, e.g. from a local XAMPP machine) or **B** (start a brand-new empty site).

### A. Import an existing database

**On the source machine (Windows/XAMPP):** export the database.

```
D:\xampp-8.5\mysql\bin\mysqldump.exe -u root --single-transaction --routines --default-character-set=utf8mb4 tipps_moodle > tipps_moodle.sql
```

On Linux the command is the same: `mysqldump -u root -p --single-transaction ... tipps_moodle > tipps_moodle.sql`.

Copy `tipps_moodle.sql` to the server, e.g. with WinSCP or:

```
scp tipps_moodle.sql user@SERVER_IP:/tmp/
```

**On the server:** create the database and import it.

```bash
sudo mariadb -e "CREATE DATABASE tipps_moodle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mariadb tipps_moodle < /tmp/tipps_moodle.sql
rm /tmp/tipps_moodle.sql
```

You also need the **data folder** from the source machine (step 5A). The database alone isn't enough:
uploaded files, course images and certificates live in the data folder.

After step 7 (once the site URL works), fix links stored with the old URL:

```bash
cd /var/www/tipps_excel
sudo -u www-data php public/admin/tool/replace/cli/replace.php \
  --search="http://localhost/tipps_excel" --replace="https://appointment.samadhana.co.in" --shorten --non-interactive
sudo -u www-data php admin/cli/purge_caches.php
```

### B. Brand-new empty site

Create only the database. The tables are created in step 7.3.

```bash
sudo mariadb -e "CREATE DATABASE tipps_moodle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

## 5. Data folder (moodledata)

The data folder must be **outside** the web root and writable by Apache (`www-data`).

```bash
sudo mkdir -p /var/www/moodledata /var/www/moodledata-tenants
```

### A. Moving an existing site

Zip the source data folder (on XAMPP: `D:\xampp-8.5\tippsmoodledata`), copy it to the server and extract it
into `/var/www/moodledata`. You can leave out `cache`, `localcache`, `sessions` and `temp`; they are rebuilt.

```bash
sudo unzip /tmp/tippsmoodledata.zip -d /var/www/moodledata
# If the zip contained a top-level folder, move its contents up one level so files sit directly
# in /var/www/moodledata (filedir/, lang/, ...).
```

### Permissions (A and B)

```bash
sudo chown -R www-data:www-data /var/www/moodledata /var/www/moodledata-tenants
sudo chmod -R 2770 /var/www/moodledata /var/www/moodledata-tenants
```

---

## 6. config-local.php

`config.php` is shared code in git. Everything that differs per machine (domain, paths, DB credentials)
lives in **`config-local.php`**, which is **not in git**. `git pull` never touches it, and a push from your
PC can never overwrite the server's credentials.

```bash
cd /var/www/tipps_excel
sudo cp config-local.example.php config-local.php
sudo nano config-local.php
```

Fill in:

```php
$basedomain  = 'appointment.samadhana.co.in';
$urlpattern  = 'https://%s';
$tenantsroot = '/var/www/moodledata-tenants';

$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbuser    = 'moodle';
$CFG->dbpass    = 'CHANGE_ME_STRONG_PASSWORD';
$CFG->prefix    = 'mdl_';
// keep $CFG->dboptions from the example

$maindbname   = 'tipps_moodle';
$maindataroot = '/var/www/moodledata';
```

Other machine-specific settings go into this file too, e.g. `$CFG->sslproxy = true;` if the server is
behind a proxy or load balancer that handles HTTPS.

Protect it, since it contains the DB password:

```bash
sudo chown root:www-data config-local.php
sudo chmod 640 config-local.php
```

Quick check (it should print the database name and URL without errors):

```bash
sudo -u www-data php -r 'define("CLI_SCRIPT",1);define("ABORT_AFTER_CONFIG",1);require "config.php";echo $CFG->dbname," ",$CFG->wwwroot,"\n";'
```

---

## 7. Apache site + SSL

### 7.1 DNS

Where the `samadhana.co.in` DNS is managed (GoDaddy, Cloudflare, ...), add:

| Type | Name | Value | Purpose |
|---|---|---|---|
| A | `appointment` | server IP | main site |
| A | `*.appointment` | server IP | all tenant sites |

Check from any PC: `nslookup test.appointment.samadhana.co.in` should return the server IP.

### 7.2 SSL certificate (wildcard)

One certificate covers the main site and all tenants. Wildcard certificates need a **DNS** challenge:

```bash
sudo apt install -y certbot
sudo certbot certonly --manual --preferred-challenges dns \
  -d appointment.samadhana.co.in -d '*.appointment.samadhana.co.in'
```

Certbot shows a TXT record (`_acme-challenge.appointment`) to add in DNS. Add it, wait 1–2 minutes, then
press Enter. It may ask for two TXT values with the same name; add both.

> **Renewal:** `--manual` certificates do **not** renew automatically. Re-run the command before the
> 90 days are up. If the DNS is on Cloudflare, use the Cloudflare plugin instead, which renews by itself:
> `sudo apt install python3-certbot-dns-cloudflare`, then
> `certbot certonly --dns-cloudflare --dns-cloudflare-credentials /root/cloudflare.ini -d ... -d '*....'`.

### 7.3 Apache virtual host

```bash
sudo nano /etc/apache2/sites-available/tipps.conf
```

```apache
<VirtualHost *:80>
    ServerName  appointment.samadhana.co.in
    ServerAlias *.appointment.samadhana.co.in
    RewriteEngine On
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</VirtualHost>

<VirtualHost *:443>
    ServerName  appointment.samadhana.co.in
    ServerAlias *.appointment.samadhana.co.in

    # Only the public/ folder is reachable from the web.
    DocumentRoot /var/www/tipps_excel/public
    <Directory /var/www/tipps_excel/public>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
        AcceptPathInfo On
        FallbackResource /r.php
        DirectoryIndex index.php
    </Directory>

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/appointment.samadhana.co.in/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/appointment.samadhana.co.in/privkey.pem

    ErrorLog  ${APACHE_LOG_DIR}/tipps_error.log
    CustomLog ${APACHE_LOG_DIR}/tipps_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite tipps
sudo a2dissite 000-default
sudo apache2ctl configtest     # must say "Syntax OK"
sudo systemctl reload apache2
```

Firewall (if `ufw` is enabled): `sudo ufw allow 'Apache Full'`.

### 7.4 Finish the main site

- **Imported (4A):** upgrade the database to this code version:

  ```bash
  cd /var/www/tipps_excel
  sudo -u www-data php admin/cli/upgrade.php --non-interactive
  sudo -u www-data php admin/cli/purge_caches.php
  ```

  Then run the URL replace from step 4A.

- **New (4B):** install the tables and the admin account:

  ```bash
  cd /var/www/tipps_excel
  sudo -u www-data php admin/cli/install_database.php --agree-license \
    --adminpass='Admin#Pass123' --fullname="Samadhana" --shortname="samadhana"
  ```

Open `https://appointment.samadhana.co.in` and log in. Then check **Site administration → Notifications**
and **Site administration → Server → Environment** for warnings.

---

## 8. Cron

Moodle needs its cron every minute. This one line runs it for the main site **and** every tenant:

```bash
sudo crontab -u www-data -e
```

```
* * * * * cd /var/www/tipps_excel && php tenants.php each admin/cli/cron.php --keep-alive=0 > /dev/null 2>&1
```

Remove any older single-site Moodle cron line (check `sudo crontab -l` and `sudo crontab -u www-data -l`).

---

## 9. Multi-tenant setup

Each tenant (client) gets its own database, its own data folder and its own subdomain, all on the same code:

| URL | Database | Data folder |
|---|---|---|
| `https://appointment.samadhana.co.in` (main) | `tipps_moodle` | `/var/www/moodledata` |
| `https://acme.appointment.samadhana.co.in` | `tipps_acme` | `/var/www/moodledata-tenants/acme` |

`config.php` reads the subdomain and picks that tenant's database and folder. A tenant exists when its folder
exists in `$tenantsroot`. Any unknown subdomain gets a 404 "Unknown site". The main site is unaffected.

The steps above already prepared everything tenants need:

- [x] wildcard DNS `*.appointment` (7.1)
- [x] wildcard SSL certificate (7.2)
- [x] `ServerAlias *.appointment.samadhana.co.in` (7.3)
- [x] DB user may create `tipps_*` databases (2.5)
- [x] `/var/www/moodledata-tenants` owned by `www-data` (5)
- [x] `$basedomain`, `$urlpattern`, `$tenantsroot` in `config-local.php` (6)
- [x] cron runs `tenants.php each` (8)

> Always run `tenants.php` as `www-data` (`sudo -u www-data php tenants.php ...`). Run as root, it creates
> folders the website can't write to.

### Create a tenant

```bash
cd /var/www/tipps_excel
sudo -u www-data php tenants.php create acme 'Acme#Pass123' "Acme Training"
```

- The slug (`acme`) is 1–30 lowercase letters/digits. It becomes the subdomain and the database name `tipps_acme`.
- It takes a few minutes (it runs the full Moodle installer).
- If it fails, it rolls back (drops the database and folder), so you can run it again.

Open `https://acme.appointment.samadhana.co.in` and log in as `admin` / `Acme#Pass123`.
Each tenant has its own admin, users, courses, theme and settings.

### Other tenant commands

```bash
sudo -u www-data php tenants.php list                    # list tenants
sudo -u www-data php tenants.php seed acme XS            # fill a TEST tenant with sample courses/users
sudo -u www-data php tenants.php delete acme             # drop its database + folder (asks to type the slug)
sudo -u www-data php tenants.php each admin/cli/purge_caches.php   # run a CLI script on every site
```

To run any Moodle CLI script for one tenant only, set `TENANT`:

```bash
sudo -u www-data TENANT=acme php admin/cli/purge_caches.php
```

Without `TENANT`, scripts run on the main site.

`seed` details (sizes XS/S/M, test user names) are in [TENANTS.md](TENANTS.md).

### Test tenants before DNS/SSL are ready

1. Create a tenant (above).
2. On your PC, edit `C:\Windows\System32\drivers\etc\hosts` as administrator and add
   `SERVER_IP  acme.appointment.samadhana.co.in`.
3. Open `https://acme.appointment.samadhana.co.in` and accept the certificate warning.
4. Check that the main site and the tenant show different users and courses.
5. Remove the hosts line afterwards.

---

## 10. Deploying updates

```bash
cd /var/www/tipps_excel
sudo git pull
sudo -u www-data php tenants.php each admin/cli/upgrade.php --non-interactive
```

`each` upgrades the main site and every tenant, one after another, and lists any that failed at the end.

`config-local.php` is never touched by `git pull`. If `git pull` ever complains about local changes to a
tracked file, check them with `git diff` before discarding anything.

---

## 11. Backups

Every site has **two** parts to back up: its database and its data folder.

```bash
# All databases (main + tenants)
for db in $(sudo mariadb -N -e "SHOW DATABASES LIKE 'tipps\_%'"); do
  sudo mariadb-dump --single-transaction --routines "$db" | gzip > /backup/$db-$(date +%F).sql.gz
done
# Data folders
sudo tar czf /backup/moodledata-$(date +%F).tgz -C /var/www moodledata moodledata-tenants \
  --exclude='*/cache' --exclude='*/localcache' --exclude='*/sessions' --exclude='*/temp'
# Server-only config
sudo cp /var/www/tipps_excel/config-local.php /backup/config-local-$(date +%F).php
```

Run this nightly from root's crontab and copy `/backup` off the server.

---

## 12. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `Missing config-local.php` | Create it from `config-local.example.php` (step 6). |
| `Unknown site` (404) | The subdomain isn't a tenant (`tenants.php list`), or `$basedomain` doesn't match the URL. |
| `Unknown tenant: x` from a CLI script | `TENANT=x` is set but `/var/www/moodledata-tenants/x` doesn't exist. |
| Tenant URL doesn't open at all | DNS: `nslookup acme.appointment.samadhana.co.in`. Apache: the `ServerAlias` line. |
| Certificate warning on tenant URL | The certificate isn't the wildcard one: check `sudo certbot certificates`. |
| `Access denied ... CREATE DATABASE` on `tenants.php create` | The DB grant from step 2.5 is missing. |
| `Can't create database ... database exists` | That slug's database already exists; pick another slug. |
| Moodle says data folder not writable | `sudo chown -R www-data:www-data /var/www/moodledata /var/www/moodledata-tenants` |
| Pages without styles / 404 on `/r.php` routes | `DocumentRoot` must point to `.../public` and the vhost needs `FallbackResource /r.php`. |
| Errors after `git pull` | Run the upgrade from step 10; check `/var/log/apache2/tipps_error.log`. |
