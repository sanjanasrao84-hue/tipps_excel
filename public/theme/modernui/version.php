<?php
// Modern UI theme: a customisable child theme of Boost.
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'theme_modernui';
$plugin->version   = 2026112502;
$plugin->requires  = 2025041400; // Moodle 5.0 or newer.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1';
$plugin->dependencies = ['theme_boost' => ANY_VERSION];
