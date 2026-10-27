<?php
// Tenant management for the multi-tenant setup in config.php.
//
//   php tenants.php create <slug> <adminpass> [site full name]
//   php tenants.php delete <slug>                    drops the tenant's database and dataroot (asks to confirm)
//   php tenants.php seed <slug> [XS|S|M] [userpass]  fill a tenant with generated test courses/users (test tenants only)
//   php tenants.php list
//   php tenants.php each <cli script> [args...]   run a Moodle CLI script for the main site and every tenant, e.g.
//       php tenants.php each admin/cli/cron.php --keep-alive=0
//       php tenants.php each admin/cli/upgrade.php --non-interactive
//       php tenants.php each admin/cli/purge_caches.php

// Some Moodle CLI scripts (e.g. the test generator) start plain `php`; make it this PHP.
putenv('PATH=' . dirname(PHP_BINARY) . PATH_SEPARATOR . getenv('PATH'));

define('CLI_SCRIPT', true);
define('ABORT_AFTER_CONFIG', true);  // Only need the settings, not a DB connection.
putenv('TENANT=');
require(__DIR__ . '/config.php');

function tenants(string $root): array {
    return array_map('basename', glob("$root/*", GLOB_ONLYDIR) ?: []);
}

function fail(string $msg): never {
    fwrite(STDERR, "$msg\n");
    exit(1);
}

function rmtree(string $dir): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f) : unlink($f);
    }
    rmdir($dir);
}

function run_for(string $slug, array $args): int {
    putenv("TENANT=$slug");
    passthru(implode(' ', array_map('escapeshellarg', [PHP_BINARY, ...$args])), $rc);
    return $rc;
}

$cmd = $argv[1] ?? '';

if ($cmd === 'list') {
    echo implode("\n", tenants($tenantsroot)), "\n";

} else if ($cmd === 'create' && isset($argv[3])) {
    [, , $slug, $adminpass] = $argv;
    $fullname = $argv[4] ?? $slug;
    if (!preg_match('/^[a-z0-9]{1,30}$/', $slug)) {
        fail('Slug must be 1-30 lowercase letters/digits.');
    }
    $dir = "$tenantsroot/$slug";
    if (is_dir($dir)) {
        fail("Tenant '$slug' already exists.");
    }

    $db = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, '', $CFG->dboptions['dbport']);
    $db->query("CREATE DATABASE `tipps_$slug` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    mkdir($dir, 0777, true);

    $rc = run_for($slug, [__DIR__ . '/admin/cli/install_database.php', '--agree-license',
        "--adminpass=$adminpass", "--fullname=$fullname", "--shortname=$slug"]);
    if ($rc !== 0) {
        // Roll back so the slug can be retried.
        $db->query("DROP DATABASE `tipps_$slug`");
        rmtree($dir);
        fail("Install failed for '$slug', rolled back.");
    }
    echo "Created tenant '$slug': " . sprintf($urlpattern, "$slug.$basedomain") . "\n";

} else if ($cmd === 'delete' && isset($argv[2])) {
    $slug = $argv[2];
    $dir = "$tenantsroot/$slug";
    if (!preg_match('/^[a-z0-9]{1,30}$/', $slug) || !is_dir($dir)) {
        fail("No tenant '$slug'.");
    }
    echo "This permanently deletes database tipps_$slug and $dir.\nType the slug to confirm: ";
    if (trim((string)fgets(STDIN)) !== $slug) {
        fail('Aborted.');
    }
    $db = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, '', $CFG->dboptions['dbport']);
    $db->query("DROP DATABASE IF EXISTS `tipps_$slug`");
    rmtree($dir);
    echo "Deleted tenant '$slug'.\n";

} else if ($cmd === 'seed' && isset($argv[2])) {
    $slug = $argv[2];
    $size = strtoupper($argv[3] ?? 'XS');
    $userpass = $argv[4] ?? 'Test#Pass123';
    if (!preg_match('/^[a-z0-9]{1,30}$/', $slug) || !is_dir("$tenantsroot/$slug")) {
        fail("No tenant '$slug'.");
    }
    if (!in_array($size, ['XS', 'S', 'M'])) {
        fail('Size must be XS, S or M.');
    }
    // Generated users only get a password if this setting exists.
    $rc = run_for($slug, [__DIR__ . '/admin/cli/cfg.php', '--name=tool_generator_users_password', "--set=$userpass"])
        ?: run_for($slug, [__DIR__ . '/public/admin/tool/generator/cli/maketestsite.php', "--size=$size",
            '--bypasscheck', '--fixeddataset']);
    if ($rc !== 0) {
        fail("Seeding '$slug' failed.");
    }
    echo "Seeded '$slug'. Test users: tool_generator_000001, tool_generator_000002, ... password: $userpass\n";

} else if ($cmd === 'each' && isset($argv[2])) {
    $failed = [];
    foreach (['', ...tenants($tenantsroot)] as $slug) {
        echo "== " . ($slug ?: '(main site)') . "\n";
        $script = $argv[2][0] === '/' || preg_match('/^[a-z]:/i', $argv[2]) ? $argv[2] : __DIR__ . '/' . $argv[2];
        if (run_for($slug, [$script, ...array_slice($argv, 3)]) !== 0) {
            $failed[] = $slug ?: '(main site)';
        }
    }
    if ($failed) {
        fail('Failed: ' . implode(', ', $failed));
    }

} else {
    fail('Usage: php tenants.php create <slug> <adminpass> [full name] | delete <slug> | seed <slug> [XS|S|M] [userpass] | list | each <cli script> [args...]');
}
