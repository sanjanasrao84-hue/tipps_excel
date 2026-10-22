<?php
// CLI: builds the site home for the Psychiatry department from the theme_eadtraining homepage sections
// (Site administration > Appearance > Homepage sections), filled with psychiatry content.
// Usage: php install_psychiatry_homepage.php [--restore] [--config=C:/xampp/htdocs/tipps_excel_lms/config.php]
// Sections that are not part of this homepage are hidden (not deleted) and the previous theme colours are backed up,
// so --restore brings the old homepage back. Running the script again rebuilds the psychiatry sections.
// Every text, link and image set here can be changed afterwards on the Homepage sections page.

define('CLI_SCRIPT', true);

$configpath = 'C:/xampp/htdocs/tipps_excel_lms/config.php';
foreach ($argv as $arg) {
    if (strpos($arg, '--config=') === 0) {
        $configpath = substr($arg, 9);
    }
}
require($configpath);

use theme_eadtraining\section_manager;

\core\cron::setup_user(get_admin());

// Calm teal brand with a white menu bar, matching the sections ("plugin/name" => value).
// The theme reads its primary colour from theme_boost/brandcolor.
const THEME_SETTINGS = [
    'theme_boost/brandcolor' => '#0f7c80',
    'theme_eadtraining/brandcolor' => '#0f7c80',
    'theme_eadtraining/brandcolor_background_menu' => 0,
    'theme_eadtraining/top_scroll_background_color' => '#0f7c80',
    'theme_eadtraining/footer_background_color' => '#0b4f57',
];
const BACKUP_SETTING = 'psyhome_backup';
// Marks the sections created by this script (in the section info), so a re-run or --restore finds them.
const MARKER = 'psyhome';

/**
 * A symbol followed by the text presentation selector, so Windows shows it as a glyph and not as a colour emoji.
 */
function text_glyph(string $symbol): string {
    return $symbol . "\u{FE0E}";
}

$restore = in_array('--restore', $argv);

/**
 * Original theme settings saved by a previous run, as "plugin/name" => value
 * (backups from the first version of this script only held theme_eadtraining names).
 */
function theme_backup(): array {
    $backup = [];
    foreach (json_decode(get_config('theme_eadtraining', BACKUP_SETTING) ?: '[]', true) as $name => $value) {
        $backup[strpos($name, '/') === false ? "theme_eadtraining/{$name}" : $name] = $value;
    }
    return $backup;
}

// Remove the sections of a previous run (and the psy-* sections of the first version); the rest is kept.
foreach (section_manager::get_home_pages() as $page) {
    $info = json_decode($page->info);
    if (!empty($info->{MARKER}) || strpos($page->template, 'psy-') === 0) {
        section_manager::remove_from_home($page->id);
    }
}

if ($restore) {
    foreach (section_manager::get_home_pages() as $page) {
        section_manager::set_hidden($page->id, false);
    }
    foreach (theme_backup() as $setting => $value) {
        [$plugin, $name] = explode('/', $setting);
        set_config($name, $value, $plugin);
    }
    unset_config(BACKUP_SETTING, 'theme_eadtraining');
    theme_reset_all_caches();
    mtrace('Previous homepage restored.');
    exit(0);
}

$url = fn(string $path, array $params = []) => (new moodle_url($path, $params))->out(false);
$asset = fn(string $file) => "{$CFG->wwwroot}/theme/eadtraining/_editor/model/{$file}";
$courseurl = function(string $shortname) use ($DB, $url) {
    $id = $DB->get_field('course', 'id', ['shortname' => $shortname]);
    return $id ? $url('/course/view.php', ['id' => $id]) : $url('/course/index.php');
};
$category = $DB->get_record_select('course_categories', "LOWER(name) LIKE '%psych%'", [], 'id', IGNORE_MULTIPLE);
$avatars = [$asset('_assets/psychiatry/avatar-clinician.webp'), $asset('_assets/psychiatry/avatar-student.webp'),
    $asset('_assets/psychiatry/avatar-nurse.webp')];

// Sections in display order. "texts" follow the order of the fields on the Homepage sections page,
// "links" and "images" their position in the section, "html" replaces fixed markup (e.g. icons) in the result.
$sections = [
    'hero-learning-modern' => [
        'texts' => [
            'Department of Psychiatry',
            'Advance Your Knowledge in Psychiatry',
            'Evidence-based courses that build clinical knowledge, deepen mental health awareness and support your '
                . 'professional development, from your first psychiatry rotation to specialist practice.',
            'Explore Courses',
            'Continue Learning',
            'Clinician',
            'Medical student',
            'MDT',
            'For the whole care team',
            'Students, doctors, nurses and allied health',
            'A psychiatrist listening attentively to a patient during a consultation',
            'Your progress',
            'Tracked in every course',
            'Start here',
            'The Mental Status Examination',
            'Foundations of Clinical Psychiatry • Lesson 1.2',
        ],
        'links' => [$url('/course/index.php'), $url('/my/courses.php')],
        'images' => [$avatars[0], $avatars[1], $avatars[2], $asset('_assets/psychiatry/hero-consultation.webp')],
        'html' => ['__play">▶<' => '__play">' . text_glyph('▶') . '<'],
    ],
    'quick-links-modern' => [
        'texts' => [
            'Quick access',
            'Pick up where you left off',
            'Shortcuts to the places you use most.',
            'My courses',
            'Continue your enrolled courses',
            'Course catalogue',
            'Browse every psychiatry course',
            'My subscriptions',
            'Plans, access dates and renewals',
            'Help and support',
            'Send a message to the site administrator',
        ],
        'links' => [$url('/my/courses.php'), $url('/course/index.php'), $url('/local/lmssub/my.php'),
            $url('/user/contactsitesupport.php')],
        'html' => ['__icon">▶<' => '__icon">' . text_glyph('▶') . '<'],
    ],
    // All courses, as course cards with an action that matches the visitor's enrolment.
    'psy-featured-courses' => [],
    'category-explorer-modern' => [
        'texts' => [
            'Find your focus',
            'Explore key areas of psychiatry',
            'Go straight to the courses that cover the topics you need.',
            'Psychiatric assessment',
            'Interview, MSE and risk',
            'Depression and anxiety',
            'Recognition and stepped care',
            'Bipolar disorder',
            'Mania, mood stabilisers and lithium',
            'Psychosis and schizophrenia',
            'First episode and early care',
            'Psychopharmacology',
            'Antidepressants and antipsychotics',
            'All psychiatry courses',
            'Browse the full catalogue',
        ],
        'links' => [
            $url('/course/search.php', ['search' => 'Mental Status']),
            $url('/course/search.php', ['search' => 'depression']),
            $url('/course/search.php', ['search' => 'bipolar']),
            $url('/course/search.php', ['search' => 'psychosis']),
            $url('/course/search.php', ['search' => 'antipsychotics']),
            $category ? $url('/course/index.php', ['categoryid' => $category->id]) : $url('/course/index.php'),
        ],
        // The model's generic icons (technology, languages...) replaced by psychiatry ones.
        'html' => [
            '__icon">⌘<' => '__icon">▤<',
            '__icon">◫<' => '__icon">◐<',
            '__icon">✦<' => '__icon">⇅<',
            '__icon">文<' => '__icon">℞<',
            '__icon">↗<' => '__icon">' . text_glyph('↗') . '<',
        ],
    ],
    'learning-paths-modern' => [
        'texts' => [
            'Recommended learning path',
            'From foundations to advanced practice',
            'Follow the courses in order, or join at the level that matches your experience.',
            'Beginner',
            'Foundations',
            'Core concepts of psychiatry for learners who are new to mental health.',
            'Self-paced',
            'Core concepts',
            'Track progress',
            'Start the course',
            'Intermediate',
            'Clinical practice',
            'Build confidence applying psychiatric knowledge to everyday clinical work.',
            'Self-paced',
            'Structured modules',
            'Track progress',
            'Advanced',
            'Bipolar and psychotic disorders',
            'Severe mental illness, mood stabilisers and safe antipsychotic prescribing.',
            'Two sections',
            'Section quizzes',
            'Case study',
        ],
        'links' => [$courseurl('PsyBeg01'), $courseurl('LP02'), $courseurl('PSY-SAMPLE-201')],
        // The model's bars look like personal progress: they show the level of each path instead.
        'html' => [
            '__bar"><span style="width:35%">' => '__bar" title="Beginner level"><span style="width:33%">',
            '__bar"><span style="width:58%">' => '__bar" title="Intermediate level"><span style="width:66%">',
            '__bar"><span style="width:82%">' => '__bar" title="Advanced level"><span style="width:100%">',
        ],
    ],
    'steps' => [
        'texts' => [
            'How it works',
            'Four steps from sign-in to progress',
            'Sign in',
            'Log in with the account provided for you.',
            'Choose a plan',
            'Pick the subscription that covers the courses you need.',
            'Start learning',
            'Work through lessons, cases and quizzes at your own pace.',
            'Track progress',
            'See your completion on every course card and course page.',
        ],
    ],
    'faq-support-modern' => [
        'texts' => [
            'Frequently asked questions',
            'Answers to common questions',
            'What you need to know about access, subscriptions and studying on the platform.',
            'Psychiatry learner',
            'Still need help?',
            'Send a message to the site administrator.',
            'Contact support',
            'How do I get access to a course?',
            'Open the course and choose a subscription plan. Once your payment is confirmed, the course appears in My courses.',
            'Can I study on my phone?',
            'Yes. The platform works on computers, tablets and phones.',
            'What happens if I cancel my subscription?',
            'Your subscription will not renew, but you keep access to its courses until the end date. You will receive a '
                . 'notification confirming the cancellation.',
            'How long will I have access to the content?',
            'Access lasts for the duration of your plan. You can see the end date at any time in My subscriptions.',
            'How do I track my progress?',
            'Each course shows your completion percentage, and quizzes show your grade when you finish them.',
        ],
        'links' => [$url('/user/contactsitesupport.php')],
        'images' => $avatars,
    ],
    'psy-cta' => [],
];

$previous = section_manager::get_home_pages();
foreach ($previous as $page) {
    section_manager::set_hidden($page->id, true);
}

$sort = 0;
foreach ($sections as $template => $content) {
    $page = section_manager::add_template_to_home($template);
    $page = $DB->get_record('theme_eadtraining_pages', ['id' => $page->id], '*', MUST_EXIST);

    if ($content) {
        $values = [];
        foreach ($content['texts'] ?? [] as $index => $text) {
            $values["text_{$index}"] = $text;
        }
        foreach ($content['links'] ?? [] as $index => $link) {
            $values["link_{$index}"] = $link;
        }
        [$page, $missing] = section_manager::apply_section_values($page, $values, true, $content['images'] ?? []);
        if ($missing) {
            mtrace("  Warning: {$template} could not apply: " . implode(', ', $missing));
        }
        $page->html = strtr($page->html, $content['html'] ?? []);
    }

    $info = json_decode($page->info);
    $info->{MARKER} = 1;
    $page->info = json_encode($info, JSON_PRETTY_PRINT);
    section_manager::save_page($page);
    // Shown to every user language.
    $DB->update_record('theme_eadtraining_pages', (object) ['id' => $page->id, 'sort' => $sort++, 'lang' => 'all']);
    mtrace("Added section {$template} (id {$page->id}).");
}
foreach ($previous as $page) {
    $DB->set_field('theme_eadtraining_pages', 'sort', $sort++, ['id' => $page->id]);
    mtrace("Hid previous section \"{$page->title}\" (id {$page->id}).");
}

set_config('homemode', 1, 'theme_eadtraining');

// Back up each setting the first time it is changed, so running the script again still restores the originals.
$backup = theme_backup();
foreach (THEME_SETTINGS as $setting => $value) {
    [$plugin, $name] = explode('/', $setting);
    if (!array_key_exists($setting, $backup)) {
        $backup[$setting] = get_config($plugin, $name);
    }
    set_config($name, $value, $plugin);
}
set_config(BACKUP_SETTING, json_encode($backup), 'theme_eadtraining');

section_manager::purge_caches();
theme_reset_all_caches();
mtrace('Psychiatry homepage installed. Run with --restore to bring back the previous homepage.');
