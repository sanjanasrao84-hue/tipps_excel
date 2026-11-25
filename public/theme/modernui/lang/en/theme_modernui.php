<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Modern UI';
$string['choosereadme'] = 'Modern UI is a clean, card-based, fully customisable child theme of Boost. Admins set the defaults; every user can choose their own look.';
$string['configtitle'] = 'Modern UI settings';
$string['region-side-pre'] = 'Right';

// Tabs.
$string['tabpresets'] = 'Presets';
$string['tabbrand'] = 'Brand';
$string['tabtypography'] = 'Typography';
$string['tabshape'] = 'Shape';
$string['tablayout'] = 'Layout';
$string['tablogin'] = 'Login page';
$string['tabdark'] = 'Dark mode';
$string['tabadvanced'] = 'Advanced';

// Presets.
$string['designpreset'] = 'Design preset';
$string['designpreset_desc'] = 'Click a preset to fill in the colour, font and corner fields on the other tabs, then press Save changes. You can still edit any value afterwards.';
$string['presetmatches'] = 'Current values match';
$string['presetreset'] = 'Reset to preset defaults';
$string['preset_indigo'] = 'Indigo';
$string['preset_emerald'] = 'Emerald';
$string['preset_sunset'] = 'Sunset';
$string['preset_mono'] = 'Monochrome';
$string['preset_custom'] = 'Custom';

// Brand.
$string['primarycolor'] = 'Primary colour';
$string['primarycolor_desc'] = 'Buttons, links and highlights. Text colour on top of it is chosen automatically for readability.';
$string['accentcolor'] = 'Accent colour';
$string['accentcolor_desc'] = 'Secondary highlight colour.';
$string['backgroundcolor'] = 'Page background colour';
$string['backgroundcolor_desc'] = 'Background of the page in light mode.';
$string['logonote'] = 'The site logo, compact logo and favicon are set in Site administration > Appearance > Logos. The theme uses them automatically.';

// Typography.
$string['fontfamily'] = 'Font';
$string['fontfamily_desc'] = 'Inter, Roboto and Poppins are loaded from Google Fonts. Choose "System font" to avoid any external request.';
$string['fontsystem'] = 'System font (no external request)';
$string['customfont'] = 'Other Google font name';
$string['customfont_desc'] = 'Optional. Exact Google Fonts family name, for example "Nunito". Overrides the font above.';
$string['basefontsize'] = 'Base font size';

// Shape.
$string['radius'] = 'Corner radius';
$string['radius_sharp'] = 'Sharp';
$string['radius_medium'] = 'Medium';
$string['radius_rounded'] = 'Rounded';
$string['shadow'] = 'Shadow strength';
$string['shadow_none'] = 'None';
$string['shadow_soft'] = 'Soft';
$string['shadow_strong'] = 'Strong';

// Layout.
$string['contentwidth'] = 'Content width';
$string['contentwidth_desc'] = 'Boxed keeps text lines readable; full width uses the whole screen.';
$string['width_boxed'] = 'Boxed';
$string['width_full'] = 'Full width';

// Login.
$string['loginlayout'] = 'Login layout';
$string['login_split'] = 'Split screen (image beside the form)';
$string['login_centered'] = 'Centered card over the image';
$string['loginbg'] = 'Login background image';
$string['loginbg_desc'] = 'Optional image for the brand side of the login page (the whole page in the centred layout). It is shown under a tint of the primary colour so the welcome text stays readable. Without an image, a primary-colour gradient is used.';
$string['loginwelcometitle'] = 'Welcome heading';
$string['loginwelcometitle_desc'] = 'Heading on the login page. Leave empty for "Welcome to" followed by the site name.';
$string['loginwelcometitle_default'] = 'Welcome to {$a}';
$string['loginwelcometext'] = 'Welcome text';
$string['loginwelcometext_desc'] = 'Text under the welcome heading. Leave empty to use the site\'s login instructions (Site administration > Plugins > Authentication > Manage authentication > Instructions), or Moodle\'s default welcome text if those are empty too.';

// Dark mode.
$string['darkmodeenabled'] = 'Allow dark mode';
$string['darkmodeenabled_desc'] = 'If disabled, the dark option is hidden and everyone sees light mode.';
$string['defaultmode'] = 'Default mode';
$string['defaultmode_desc'] = 'Used for users who have not chosen their own. Auto follows the device setting.';
$string['mode_light'] = 'Light';
$string['mode_dark'] = 'Dark';
$string['mode_auto'] = 'Auto (follow device)';

// Advanced.
$string['scsspost'] = 'Custom SCSS';
$string['scsspost_desc'] = 'Extra SCSS or CSS added at the end of the theme.';

// Appearance panel.
$string['appearance'] = 'Appearance';
$string['appearance_close'] = 'Close';
$string['appearance_mode'] = 'Mode';
$string['appearance_accent'] = 'Accent colour';
$string['appearance_custom'] = 'Custom colour';
$string['appearance_fontsize'] = 'Font size';
$string['appearance_density'] = 'Density';
$string['appearance_motion'] = 'Reduce motion';
$string['appearance_contrast'] = 'High contrast';
$string['appearance_reset'] = 'Reset to site defaults';
$string['appearance_intro'] = 'These choices change how the site looks for you only. They are saved to your account.';
$string['appearance_darkdisabled'] = 'Dark mode is turned off on this site.';
$string['appearance_accessibility'] = 'Accessibility';
$string['appearance_saved'] = 'Appearance saved';
$string['appearance_resetdone'] = 'Appearance reset to the site defaults';
$string['swatch_indigo'] = 'Indigo';
$string['swatch_sky'] = 'Sky blue';
$string['swatch_emerald'] = 'Emerald';
$string['swatch_orange'] = 'Orange';
$string['swatch_pink'] = 'Pink';
$string['swatch_violet'] = 'Violet';
$string['size_small'] = 'Small';
$string['size_medium'] = 'Medium';
$string['size_large'] = 'Large';
$string['density_comfortable'] = 'Comfortable';
$string['density_compact'] = 'Compact';

// Privacy.
$string['privacy:metadata:preference:mode'] = 'The light, dark or auto mode the user chose.';
$string['privacy:metadata:preference:accent'] = 'The accent colour the user chose.';
$string['privacy:metadata:preference:fontsize'] = 'The font size the user chose.';
$string['privacy:metadata:preference:density'] = 'The layout density the user chose.';
$string['privacy:metadata:preference:motion'] = 'Whether the user chose to reduce motion.';
$string['privacy:metadata:preference:contrast'] = 'Whether the user chose high contrast.';
