# theme_modernui

Customisable child theme of Boost for Moodle 5.x.

## Install
1. Copy this folder to `<moodle>/public/theme/modernui` (older layout: `<moodle>/theme/modernui`).
2. Run `php admin/cli/upgrade.php` (or open Site administration > Notifications).
3. Select the theme only for the tenant/site you want to preview (loadtest), not the main site.
4. `php admin/cli/purge_caches.php`

## Customise
- Admin: Site administration > Appearance > Themes > Modern UI (Presets, Brand, Typography, Shape, Layout, Login, Dark mode, Advanced).
- Users: the round palette button at the bottom-right corner opens the Appearance panel.

Priority: user preference, then admin setting, then preset default.
