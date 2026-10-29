<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

// Multi-tenancy: one codebase, one database + dataroot per tenant, picked by subdomain.
//   <slug>.<basedomain>  ->  database tipps_<slug>, dataroot <tenantsroot>/<slug>
//   <basedomain>         ->  the main site ($maindbname, $maindataroot).
// CLI scripts pick the tenant from the TENANT environment variable (unset = main site).
// Tenants are created with: php tenants.php create <slug> <adminpass>
//
// Machine-specific values (domain, paths, DB credentials) live in config-local.php, which is not in git.
// Template: config-local.example.php
if (!is_file(__DIR__ . '/config-local.php')) {
    http_response_code(500);
    exit("Missing config-local.php (copy config-local.example.php).\n");
}
require(__DIR__ . '/config-local.php');

if (PHP_SAPI === 'cli') {
    $slug = (string)getenv('TENANT');
    $host = $slug === '' ? $basedomain : "$slug.$basedomain";
} else {
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if (!preg_match('/^(?:([a-z0-9]+)\.)?' . preg_quote($basedomain, '/') . '(?::\d+)?$/', $host, $m)) {
        http_response_code(404);
        exit('Unknown site');
    }
    $slug = $m[1] ?? '';
}

$CFG->wwwroot = sprintf($urlpattern, $host);
if ($slug === '') {
    $CFG->dbname   = $maindbname;
    $CFG->dataroot = $maindataroot;
} else {
    // The tenant's dataroot existing is what marks the tenant as provisioned.
    if (!preg_match('/^[a-z0-9]{1,30}$/', $slug) || !is_dir("$tenantsroot/$slug")) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Unknown tenant: $slug\n");
            exit(1);
        }
        http_response_code(404);
        exit('Unknown site');
    }
    $CFG->dbname   = "tipps_$slug";
    $CFG->dataroot = "$tenantsroot/$slug";
}
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
