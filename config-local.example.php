<?php
// Copy to config-local.php and fill in this machine's values. config-local.php is not in git.

$basedomain  = 'lms.example.com';            // main site; tenants are <slug>.lms.example.com
$urlpattern  = 'https://%s';                 // %s = host; add '/subfolder' if not at the domain root.
$tenantsroot = '/var/www/moodledata-tenants'; // outside the web root, owned by www-data

$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbuser    = 'DBUSER';                  // must be allowed to CREATE DATABASE tipps_*
$CFG->dbpass    = 'DBPASS';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => 3306,
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);

// Main site (bare $basedomain).
$maindbname   = 'DBNAME';
$maindataroot = '/var/www/moodledata';

// Any other machine-specific $CFG settings (sslproxy, reverseproxy, ...) go here too.
