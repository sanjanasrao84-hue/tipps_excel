<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Homepage section management for the "Home with Page Editor" mode.
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_eadtraining;

use cache;
use context_system;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Helper for the sections (blocks) of the "Home with Page Editor" homepage.
 *
 * Every section is a theme_eadtraining_pages record created from an editor model (_editor/model/<name>).
 * What can be edited comes from the model itself, so each section only offers the settings it supports:
 * - texts: the "lang::" placeholders of the model editor.html;
 * - links and images: the <a href> and <img src> of the model editor.html, by position;
 * - options and repeatable items: the "block" and "blocks" fields of the model info.json form.
 * The customisations are stored in the record (html and info JSON), so the visual editor keeps working.
 *
 * Custom banners live in theme_eadtraining_banners and are placed on the homepage through a section
 * that uses the hidden "banner-custom" model.
 */
class section_manager {

    /** @var string Editor model used to place custom banners on the homepage. */
    public const CUSTOM_TEMPLATE = 'banner-custom';

    /** @var string File area holding custom banner background images. */
    public const FILEAREA = 'custombanner';

    /** @var string File area holding images uploaded for homepage sections (itemid = section id). */
    public const IMAGEAREA = 'sectionimage';

    /** @var string Custom banners table. */
    private const TABLE = 'theme_eadtraining_banners';

    /** @var string Editor pages table. */
    private const PAGES = 'theme_eadtraining_pages';

    /** @var string Local used by the homepage sections. */
    private const HOME = 'home';

    /** @var string[] Allowed horizontal positions. */
    public const POSITIONS = ['left', 'center', 'right'];

    /** @var string[] Allowed vertical positions. */
    public const VERTICALPOSITIONS = ['top', 'middle', 'bottom'];

    /** @var string[] HTML elements without closing tag. */
    private const VOIDTAGS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];

    /** @var string Pattern of the links of a section. */
    private const LINKPATTERN = '/<a\b[^>]*>/i';

    /** @var string Pattern of the images of a section. */
    private const IMAGEPATTERN = '/<img\b[^>]*>/i';

    /**
     * Loads the editor library (lang helpers, page creation and compilation).
     *
     * @return void
     */
    private static function require_editor_lib(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/theme/eadtraining/_editor/editor-lib.php");
    }

    /**
     * Path of the editor models.
     *
     * @return string
     */
    private static function model_dir(): string {
        global $CFG;
        return "{$CFG->dirroot}/theme/eadtraining/_editor/model";
    }

    /**
     * Reads the info.json of an editor model, with lang strings resolved.
     *
     * @param string $template
     * @return stdClass|null
     * @throws \Exception
     */
    public static function get_template_info(string $template): ?stdClass {
        static $infos = [];

        if (!preg_match('/^[a-z0-9-]+$/', $template)) {
            return null;
        }
        if (!array_key_exists($template, $infos)) {
            $infos[$template] = null;
            $file = self::model_dir() . "/{$template}/info.json";
            if (file_exists($file)) {
                self::require_editor_lib();
                $infos[$template] = json_decode(theme_eadtraining_load_info_json($file)) ?: null;
            }
        }

        return $infos[$template];
    }

    /**
     * Checks whether an editor model is a banner.
     *
     * @param string $template
     * @return bool
     * @throws \Exception
     */
    public static function is_banner_template(string $template): bool {
        $info = self::get_template_info($template);
        return $info && isset($info->category) && $info->category == 'banner';
    }

    /**
     * Readable name of a model category.
     *
     * @param string $category
     * @return string
     */
    public static function category_name(string $category): string {
        $key = 'sections_category_' . str_replace('-', '', $category);
        if (get_string_manager()->string_exists($key, 'theme_eadtraining')) {
            return get_string($key, 'theme_eadtraining');
        }

        return ucfirst(str_replace('-', ' ', $category));
    }

    /**
     * Lists the section models that can be added to the homepage, grouped by category.
     *
     * @return array[] category, name, templates[] (template, title, image)
     * @throws \Exception
     */
    public static function get_section_templates(): array {
        global $CFG;

        $groups = [];
        foreach (glob(self::model_dir() . '/*/info.json') as $file) {
            $template = basename(dirname($file));
            $info = self::get_template_info($template);
            if (!$info || !empty($info->disable)) {
                continue;
            }
            $category = $info->category ?? 'section';
            if (!isset($groups[$category])) {
                $groups[$category] = ['category' => $category, 'name' => self::category_name($category), 'templates' => []];
            }
            $groups[$category]['templates'][] = (object) [
                'template' => $template,
                'title' => $info->title,
                'image' => "{$CFG->wwwroot}/theme/eadtraining/_editor/model/{$template}/print.png",
            ];
        }

        // Banners first, the rest alphabetically.
        uasort($groups, function ($a, $b) {
            return [$a['category'] != 'banner', $a['name']] <=> [$b['category'] != 'banner', $b['name']];
        });

        return array_values($groups);
    }

    /**
     * Returns every homepage section, in display order.
     *
     * @return stdClass[] Each record also has "position" (1-based).
     * @throws \dml_exception
     */
    public static function get_home_pages(): array {
        global $DB;

        $pages = array_values($DB->get_records(self::PAGES, ['local' => self::HOME], 'sort ASC, id ASC'));
        foreach ($pages as $index => $page) {
            $page->position = $index + 1;
        }

        return $pages;
    }

    /**
     * Gets a homepage section.
     *
     * @param int $pageid
     * @return stdClass
     * @throws \Exception
     */
    public static function get_home_section(int $pageid): stdClass {
        global $DB;

        $page = $DB->get_record(self::PAGES, ['id' => $pageid, 'local' => self::HOME], '*', MUST_EXIST);
        if (!self::get_template_info($page->template)) {
            throw new moodle_exception('sections_invalidsection', 'theme_eadtraining');
        }

        return $page;
    }

    /**
     * Gets the custom banner id linked to a homepage section, or 0.
     *
     * @param stdClass $page
     * @return int
     */
    public static function get_page_bannerid(stdClass $page): int {
        if ($page->template != self::CUSTOM_TEMPLATE) {
            return 0;
        }
        $info = is_string($page->info) ? json_decode($page->info) : $page->info;
        return (int) ($info->bannerid ?? 0);
    }

    /**
     * Whether a homepage section is hidden from users.
     *
     * @param stdClass $page
     * @return bool
     */
    public static function is_hidden(stdClass $page): bool {
        $info = is_string($page->info) ? json_decode($page->info) : $page->info;
        return !empty($info->hidden);
    }

    /**
     * Shows or hides a homepage section. Hidden sections are only displayed in editing mode.
     *
     * @param int $pageid
     * @param bool $hidden
     * @return void
     * @throws \Exception
     */
    public static function set_hidden(int $pageid, bool $hidden): void {
        global $DB;

        $page = self::get_home_section($pageid);
        $info = json_decode($page->info) ?: new stdClass();
        $info->hidden = $hidden ? 1 : 0;
        $DB->set_field(self::PAGES, 'info', json_encode($info, JSON_PRETTY_PRINT), ['id' => $page->id]);

        self::purge_caches();
    }

    /**
     * Adds a section model to the end of the homepage.
     *
     * @param string $template
     * @return stdClass The homepage section.
     * @throws \Exception
     */
    public static function add_template_to_home(string $template): stdClass {
        global $CFG, $DB, $USER;

        $info = self::get_template_info($template);
        if (!$info || !empty($info->disable)) {
            throw new moodle_exception('sections_invalidsection', 'theme_eadtraining');
        }

        self::require_editor_lib();
        $sort = self::next_sort();
        $page = theme_eadtraining_editor_create_page($template, $USER->lang ?? $CFG->lang, self::HOME);
        $page->sort = $sort;
        $DB->set_field(self::PAGES, 'sort', $page->sort, ['id' => $page->id]);

        self::purge_caches();

        return $page;
    }

    /**
     * Removes a section from the homepage, with its uploaded images. A custom banner itself is kept.
     *
     * @param int $pageid
     * @return void
     * @throws \Exception
     */
    public static function remove_from_home(int $pageid): void {
        global $DB;

        $page = self::get_home_section($pageid);
        get_file_storage()->delete_area_files(context_system::instance()->id, 'theme_eadtraining', self::IMAGEAREA, $page->id);
        $DB->delete_records(self::PAGES, ['id' => $page->id]);

        self::purge_caches();
    }

    /**
     * Moves a homepage section.
     *
     * @param int $pageid
     * @param string $direction "up", "down" or "top"
     * @return void
     * @throws \Exception
     */
    public static function move(int $pageid, string $direction): void {
        global $DB;

        self::get_home_section($pageid);

        $ids = array_map(function ($page) {
            return (int) $page->id;
        }, self::get_home_pages());
        $index = array_search($pageid, $ids);

        if ($direction == 'top') {
            array_splice($ids, $index, 1);
            array_unshift($ids, $pageid);
        } else {
            $target = $direction == 'up' ? $index - 1 : $index + 1;
            if ($target < 0 || $target >= count($ids)) {
                return;
            }
            [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        }

        foreach ($ids as $sort => $id) {
            $DB->set_field(self::PAGES, 'sort', $sort, ['id' => $id]);
        }

        self::purge_caches();
    }

    /**
     * Sort value that places a section at the end of the homepage.
     *
     * @return int
     * @throws \dml_exception
     */
    private static function next_sort(): int {
        global $DB;
        return (int) $DB->get_field_sql('SELECT MAX(sort) FROM {' . self::PAGES . '} WHERE local = ?', [self::HOME]) + 1;
    }

    /**
     * Purges the homepage caches.
     *
     * @return void
     */
    public static function purge_caches(): void {
        cache::make('theme_eadtraining', 'frontpage_cache')->purge();
    }

    /**
     * Saves a section.
     *
     * @param stdClass $page
     * @return void
     * @throws \dml_exception
     */
    public static function save_page(stdClass $page): void {
        global $DB;

        $DB->update_record(self::PAGES, (object) [
            'id' => $page->id,
            'html' => $page->html,
            'info' => $page->info,
        ]);

        self::purge_caches();
    }

    // Custom banners.

    /**
     * Gets a custom banner.
     *
     * @param int $id
     * @return stdClass
     * @throws \dml_exception
     */
    public static function get_banner(int $id): stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Lists the custom banners.
     *
     * @return stdClass[]
     * @throws \dml_exception
     */
    public static function get_banners(): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'name ASC, id ASC');
    }

    /**
     * Default values of a new custom banner.
     *
     * @return stdClass
     */
    public static function get_banner_defaults(): stdClass {
        return (object) [
            'id' => 0,
            'name' => '',
            'title' => '',
            'subtitle' => '',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'buttontext' => '',
            'buttonurl' => '',
            'textposition' => 'left',
            'verticalposition' => 'middle',
            'textalign' => 'left',
            'textcolor' => '#ffffff',
            'overlaycolor' => '#000000',
            'overlayopacity' => 40,
            'height' => 480,
        ];
    }

    /**
     * Normalises custom banner values so only known, valid values are stored and rendered.
     *
     * @param stdClass $data
     * @return stdClass
     */
    public static function normalise_banner(stdClass $data): stdClass {
        $defaults = self::get_banner_defaults();
        $banner = new stdClass();
        foreach ($defaults as $field => $default) {
            $banner->$field = isset($data->$field) ? $data->$field : $default;
        }

        $banner->id = (int) $banner->id;
        foreach (['name', 'title', 'subtitle', 'buttontext'] as $field) {
            $banner->$field = trim(clean_param((string) $banner->$field, PARAM_TEXT));
        }
        $banner->description = (string) $banner->description;
        $banner->descriptionformat = (int) $banner->descriptionformat;
        $banner->buttonurl = trim(clean_param((string) $banner->buttonurl, PARAM_URL));
        if (!in_array($banner->textposition, self::POSITIONS)) {
            $banner->textposition = $defaults->textposition;
        }
        if (!in_array($banner->verticalposition, self::VERTICALPOSITIONS)) {
            $banner->verticalposition = $defaults->verticalposition;
        }
        if (!in_array($banner->textalign, self::POSITIONS)) {
            $banner->textalign = $defaults->textalign;
        }
        foreach (['textcolor', 'overlaycolor'] as $field) {
            $banner->$field = self::is_color($banner->$field) ? strtolower($banner->$field) : $defaults->$field;
        }
        $banner->overlayopacity = min(100, max(0, (int) $banner->overlayopacity));
        $banner->height = min(1200, max(200, (int) $banner->height));

        return $banner;
    }

    /**
     * Checks a "#rgb" or "#rrggbb" color.
     *
     * @param mixed $color
     * @return bool
     */
    public static function is_color($color): bool {
        return is_string($color) && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color);
    }

    /**
     * Creates or updates a custom banner.
     *
     * @param stdClass $data
     * @return int The banner id.
     * @throws \dml_exception
     */
    public static function save_banner(stdClass $data): int {
        global $DB, $USER;

        $banner = self::normalise_banner($data);
        $banner->usermodified = $USER->id;
        $banner->timemodified = time();

        if ($banner->id) {
            $DB->update_record(self::TABLE, $banner);
            foreach (self::get_banner_pages($banner->id) as $page) {
                $DB->set_field(self::PAGES, 'title', $banner->name, ['id' => $page->id]);
            }
        } else {
            unset($banner->id);
            $banner->timecreated = $banner->timemodified;
            $banner->id = $DB->insert_record(self::TABLE, $banner);
        }

        self::purge_caches();

        return $banner->id;
    }

    /**
     * Deletes a custom banner, its image and its homepage sections.
     *
     * @param int $id
     * @return void
     * @throws \dml_exception
     */
    public static function delete_banner(int $id): void {
        global $DB;

        foreach (self::get_banner_pages($id) as $page) {
            $DB->delete_records(self::PAGES, ['id' => $page->id]);
        }
        get_file_storage()->delete_area_files(context_system::instance()->id, 'theme_eadtraining', self::FILEAREA, $id);
        $DB->delete_records(self::TABLE, ['id' => $id]);

        self::purge_caches();
    }

    /**
     * Homepage sections that display a custom banner.
     *
     * @param int $bannerid
     * @return stdClass[]
     * @throws \dml_exception
     */
    public static function get_banner_pages(int $bannerid): array {
        global $DB;

        $pages = $DB->get_records(self::PAGES, ['local' => self::HOME, 'template' => self::CUSTOM_TEMPLATE], 'sort ASC, id ASC');

        return array_filter($pages, function ($page) use ($bannerid) {
            return self::get_page_bannerid($page) == $bannerid;
        });
    }

    /**
     * Adds a custom banner to the end of the homepage.
     *
     * @param int $bannerid
     * @return stdClass The homepage section.
     * @throws \dml_exception
     */
    public static function add_banner_to_home(int $bannerid): stdClass {
        global $DB;

        $banner = self::get_banner($bannerid);
        $info = (object) [
            'title' => $banner->name,
            'category' => 'banner',
            'type' => 'form',
            'bannerid' => (int) $banner->id,
        ];
        $page = (object) [
            'local' => self::HOME,
            'type' => 'form',
            'title' => $banner->name,
            'html' => '[[change-to-blocks]]',
            'info' => json_encode($info, JSON_PRETTY_PRINT),
            'template' => self::CUSTOM_TEMPLATE,
            'lang' => 'all',
            'sort' => self::next_sort(),
        ];
        $page->id = $DB->insert_record(self::PAGES, $page);

        self::purge_caches();

        return $page;
    }

    /**
     * File manager options for the custom banner background image.
     *
     * @return array
     */
    public static function file_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg'],
        ];
    }

    /**
     * Background image URL of a saved custom banner.
     *
     * @param int $bannerid
     * @return moodle_url|null
     */
    public static function get_image_url(int $bannerid): ?moodle_url {
        $files = get_file_storage()->get_area_files(
            context_system::instance()->id, 'theme_eadtraining', self::FILEAREA, $bannerid, 'itemid, filepath, filename', false
        );
        $file = reset($files);
        if (!$file) {
            return null;
        }

        return moodle_url::make_pluginfile_url(
            $file->get_contextid(), 'theme_eadtraining', self::FILEAREA, $bannerid, $file->get_filepath(), $file->get_filename()
        );
    }

    /**
     * URL of the file in a draft area (used to preview unsaved images).
     *
     * @param int $draftitemid
     * @return moodle_url|null
     */
    public static function get_draft_image_url(int $draftitemid): ?moodle_url {
        global $USER;

        if (!$draftitemid) {
            return null;
        }
        $usercontext = \context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
        $file = reset($files);
        if (!$file) {
            return null;
        }

        return moodle_url::make_draftfile_url($draftitemid, $file->get_filepath(), $file->get_filename());
    }

    /**
     * Template context of a custom banner.
     *
     * @param stdClass $banner
     * @param moodle_url|null $imageurl
     * @return array
     */
    public static function export_banner(stdClass $banner, ?moodle_url $imageurl): array {
        $banner = self::normalise_banner($banner);
        $context = context_system::instance();

        [$r, $g, $b] = self::hex_to_rgb($banner->overlaycolor);
        $opacity = $banner->overlayopacity / 100;
        $style = "--eadtraining-banner-height: {$banner->height}px;" .
            " --eadtraining-banner-color: {$banner->textcolor};" .
            " --eadtraining-banner-overlay: rgba({$r}, {$g}, {$b}, {$opacity});";
        if ($imageurl) {
            $style .= " background-image: url(\"" . $imageurl->out(false) . "\");";
        }

        $alignclasses = ['left' => 'text-start', 'center' => 'text-center', 'right' => 'text-end'];
        $description = '';
        if (trim(strip_tags($banner->description, '<img>')) !== '') {
            $description = format_text($banner->description, $banner->descriptionformat, ['context' => $context]);
        }

        return [
            'title' => format_string($banner->title, true, ['context' => $context]),
            'subtitle' => format_string($banner->subtitle, true, ['context' => $context]),
            'description' => $description,
            'buttontext' => format_string($banner->buttontext, true, ['context' => $context]),
            'buttonurl' => $banner->buttonurl,
            'hasbutton' => $banner->buttontext !== '' && $banner->buttonurl !== '',
            'textposition' => $banner->textposition,
            'verticalposition' => $banner->verticalposition,
            'alignclass' => $alignclasses[$banner->textalign],
            'hasimage' => (bool) $imageurl,
            'style' => $style,
        ];
    }

    /**
     * Renders a custom banner.
     *
     * @param stdClass $banner
     * @param moodle_url|null $imageurl
     * @return string
     */
    public static function render_banner(stdClass $banner, ?moodle_url $imageurl): string {
        global $OUTPUT;
        return $OUTPUT->render_from_template('theme_eadtraining/custom_banner', self::export_banner($banner, $imageurl));
    }

    /**
     * Renders the custom banner linked to a homepage section (used by the banner-custom model).
     *
     * @param stdClass $page
     * @return string
     * @throws \dml_exception
     */
    public static function render_home_block(stdClass $page): string {
        global $DB;

        $bannerid = self::get_page_bannerid($page);
        $banner = $bannerid ? $DB->get_record(self::TABLE, ['id' => $bannerid]) : false;
        if (!$banner) {
            return '';
        }

        return self::render_banner($banner, self::get_image_url($banner->id));
    }

    /**
     * Stylesheets needed to display a custom banner outside the homepage.
     *
     * @return string
     */
    public static function custom_banner_css(): string {
        global $CFG;

        $html = '';
        foreach (['_assets/style.css', self::CUSTOM_TEMPLATE . '/style.css'] as $file) {
            $html .= "<link rel=\"stylesheet\" href=\"{$CFG->wwwroot}/theme/eadtraining/_editor/model/{$file}\">";
        }

        return $html;
    }

    /**
     * Renders a homepage section outside the homepage (preview), with its CSS and JS, without caching it.
     *
     * @param stdClass $page
     * @return string
     * @throws \Exception
     */
    public static function render_page_preview(stdClass $page): string {
        global $CFG, $PAGE;

        self::require_editor_lib();

        $page = clone($page);
        // A negative id keeps the preview out of the cached copy of the real section.
        $page->id = -abs((int) $page->id) - 1;
        $compiled = theme_eadtraining_compile_pages([$page], current_language(), true);

        $html = '';
        foreach ($compiled->css as $cssfile) {
            $html .= "<link rel=\"stylesheet\" href=\"{$CFG->wwwroot}{$cssfile}\">";
        }
        $PAGE->requires->jquery();
        foreach ($compiled->js as $jsfile) {
            if (strpos($jsfile, 'require') === 0) {
                $PAGE->requires->js_init_code($jsfile);
            } else if (strpos($jsfile, '/') === 0) {
                $PAGE->requires->js($jsfile);
            }
        }
        foreach ($compiled->pages as $compiledpage) {
            $html .= "<div class=\"block block-home-{$compiledpage->template}\">{$compiledpage->html}</div>";
        }

        return $html;
    }

    /**
     * Converts "#rgb"/"#rrggbb" into [r, g, b].
     *
     * @param string $color
     * @return int[]
     */
    private static function hex_to_rgb(string $color): array {
        $hex = ltrim($color, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    // Section settings.

    /**
     * Lists the settings of a homepage section, as supported by its model.
     *
     * @param stdClass $page
     * @return array texts[], links[], images[], block[], rowdefs[], rows[], hashtml
     * @throws \Exception
     */
    public static function get_section_fields(stdClass $page): array {
        $info = json_decode($page->info) ?: new stdClass();
        $savedtexts = isset($info->bannertext) ? (array) $info->bannertext : [];
        $savedlinks = isset($info->sectionlinks) ? (array) $info->sectionlinks : [];
        $savedimages = isset($info->sectionimages) ? (array) $info->sectionimages : [];
        $template = self::get_template_html($page->template) ?? '';

        $fields = ['texts' => [], 'links' => [], 'images' => [], 'block' => [], 'rowdefs' => [], 'rows' => []];

        foreach (self::get_template_tokens($page->template) as $index => $token) {
            $value = array_key_exists($token->key, $savedtexts) ? $savedtexts[$token->key] : $token->default;
            $fields['texts'][] = [
                'name' => "text_{$index}",
                'key' => $token->key,
                'label' => get_string("banners_kind_{$token->kind}", 'theme_eadtraining', $token->number),
                'default' => $token->default,
                'value' => $value,
                'multiline' => $token->kind == 'paragraph' || \core_text::strlen($token->default) > 90,
            ];
        }

        foreach (self::get_template_tags($template, self::LINKPATTERN) as $index => $tag) {
            $default = self::get_attribute($tag['tag'], 'href') ?? '';
            $fields['links'][] = [
                'name' => "link_{$index}",
                'index' => $index,
                'label' => get_string('sections_link', 'theme_eadtraining', (object) [
                    'number' => $index + 1, 'text' => self::link_text($template, $tag['offset']),
                ]),
                'default' => $default,
                'value' => array_key_exists($index, $savedlinks) ? $savedlinks[$index] : $default,
            ];
        }

        foreach (self::get_template_tags($template, self::IMAGEPATTERN) as $index => $tag) {
            $default = self::model_url($page->template, self::get_attribute($tag['tag'], 'src') ?? '');
            $alt = self::resolve_tokens(self::get_attribute($tag['tag'], 'alt') ?? '');
            $fields['images'][] = [
                'name' => "image_{$index}",
                'index' => $index,
                'label' => get_string('sections_image', 'theme_eadtraining', (object) [
                    'number' => $index + 1, 'text' => $alt,
                ]),
                'default' => $default,
                'value' => !empty($savedimages[$index]) ? $savedimages[$index] : $default,
                'custom' => !empty($savedimages[$index]),
            ];
        }

        $form = self::get_template_info($page->template)->form ?? null;
        $savedata = isset($info->savedata) ? (array) $info->savedata : [];

        foreach (self::field_definitions($form->block ?? null) as $key => $definition) {
            $value = array_key_exists($key, $savedata) ? $savedata[$key] : ($definition['default'] ?? '');
            $fields['block'][] = $definition + ['name' => "block_{$key}", 'key' => $key, 'value' => $value];
        }

        $fields['rowdefs'] = self::field_definitions($form->blocks ?? null);
        if ($fields['rowdefs']) {
            foreach ($savedata as $index => $row) {
                if (is_numeric($index) && is_object($row)) {
                    $fields['rows'][] = (array) $row;
                }
            }
        }

        $fields['hashtml'] = $fields['texts'] || $fields['links'] || $fields['images'];

        return $fields;
    }

    /**
     * Whether a section has any setting to edit.
     *
     * @param array $fields get_section_fields()
     * @return bool
     */
    public static function has_settings(array $fields): bool {
        return $fields['hashtml'] || $fields['block'] || $fields['rowdefs'];
    }

    /**
     * Field definitions of an info.json form section (text, textarea and select fields).
     *
     * @param mixed $definitions
     * @return array[] key => label, type, options, required, valuetype, help, default
     * @throws \dml_exception
     */
    private static function field_definitions($definitions): array {
        $out = [];
        foreach ((array) $definitions as $key => $definition) {
            if (!is_object($definition) || !in_array($definition->type ?? '', ['text', 'textarea', 'select'])) {
                continue;
            }
            $field = [
                'label' => $definition->label ?? $key,
                'type' => $definition->type,
                'required' => !empty($definition->required),
                'valuetype' => $definition->valuetype ?? 'text',
                'help' => $definition->help ?? '',
                'options' => [],
            ];
            if (property_exists($definition, 'default_data')) {
                $field['default'] = $definition->default_data;
            }
            if ($definition->type == 'select') {
                if (($definition->data ?? '') === 'courses') {
                    $field['options'] = self::course_options();
                } else {
                    foreach ((array) ($definition->data ?? []) as $option) {
                        if (is_object($option) && isset($option->id)) {
                            $field['options'][$option->id] = $option->name ?? $option->id;
                        }
                    }
                }
            }
            $out[$key] = $field;
        }

        return $out;
    }

    /**
     * Visible courses that can be selected in a section, like the visual editor offers.
     *
     * @return array id => name
     * @throws \dml_exception
     */
    private static function course_options(): array {
        global $DB;

        static $options = null;
        if ($options === null) {
            $options = [];
            foreach ($DB->get_records_select('course', 'id > 1 AND visible = 1', null, 'fullname ASC', 'id, fullname') as $course) {
                $options[$course->id] = format_string($course->fullname);
            }
        }

        return $options;
    }

    /**
     * Readable name of a repeatable item.
     *
     * @param array $row
     * @param array $rowdefs
     * @return string
     */
    public static function row_name(array $row, array $rowdefs): string {
        foreach ($rowdefs as $key => $definition) {
            $value = $row[$key] ?? '';
            if ($value === '' || $value === null) {
                continue;
            }
            if ($definition['type'] == 'select') {
                if (isset($definition['options'][$value])) {
                    return $definition['options'][$value];
                }
            } else if ($definition['type'] == 'text') {
                return shorten_text($value, 60);
            }
        }

        return '';
    }

    /**
     * Lists the "lang::" placeholders of a model editor.html, in order and without duplicates.
     *
     * @param string $template
     * @return stdClass[] key, default, kind, number
     * @throws \Exception
     */
    public static function get_template_tokens(string $template): array {
        $html = self::get_template_html($template);
        if ($html === null) {
            return [];
        }

        self::require_editor_lib();
        $strings = theme_eadtraining_get_strings_langs();

        preg_match_all('/lang::(\w+)/', $html, $matches, PREG_OFFSET_CAPTURE);
        $tokens = [];
        $counters = [];
        foreach ($matches[1] as [$key, $offset]) {
            if (isset($tokens[$key])) {
                continue;
            }
            $kind = self::describe_position($html, $offset);
            $counters[$kind] = ($counters[$kind] ?? 0) + 1;
            $tokens[$key] = (object) [
                'key' => $key,
                'default' => $strings[$key] ?? "[{$key}]",
                'kind' => $kind,
                'number' => $counters[$kind],
            ];
        }

        return array_values($tokens);
    }

    /**
     * Raw editor.html of a model.
     *
     * @param string $template
     * @return string|null
     */
    private static function get_template_html(string $template): ?string {
        if (!preg_match('/^[a-z0-9-]+$/', $template)) {
            return null;
        }
        $file = self::model_dir() . "/{$template}/editor.html";

        return file_exists($file) ? file_get_contents($file) : null;
    }

    /**
     * Finds the tags matching a pattern, with their offsets.
     *
     * @param string $html
     * @param string $pattern
     * @return array[] tag, offset
     */
    private static function get_template_tags(string $html, string $pattern): array {
        preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE);

        return array_map(function ($match) {
            return ['tag' => $match[0], 'offset' => $match[1]];
        }, $matches[0]);
    }

    /**
     * Reads an attribute of an HTML tag.
     *
     * @param string $tag
     * @param string $name
     * @return string|null
     */
    private static function get_attribute(string $tag, string $name): ?string {
        if (preg_match('/\s' . $name . '\s*=\s*"([^"]*)"/i', $tag, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    /**
     * Replaces (or adds) an attribute of an HTML tag.
     *
     * @param string $tag
     * @param string $name
     * @param string $value
     * @return string
     */
    private static function set_attribute(string $tag, string $name, string $value): string {
        $escaped = s($value);
        if (preg_match('/\s' . $name . '\s*=\s*"[^"]*"/i', $tag)) {
            return preg_replace_callback('/(\s' . $name . '\s*=\s*)"[^"]*"/i', function ($match) use ($escaped) {
                return $match[1] . '"' . $escaped . '"';
            }, $tag, 1);
        }

        return preg_replace('/^<(\w+)/', '<$1 ' . $name . '="' . $escaped . '"', $tag);
    }

    /**
     * Text of the link starting at an offset of the model HTML, with lang strings resolved.
     *
     * @param string $html
     * @param int $offset
     * @return string
     */
    private static function link_text(string $html, int $offset): string {
        $end = stripos($html, '</a>', $offset);
        $inner = $end === false ? '' : substr($html, $offset, $end - $offset);
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/^<a\b[^>]*>/i', '', $inner))));

        return shorten_text(self::resolve_tokens($text), 50);
    }

    /**
     * Replaces the "lang::" placeholders of a text by their strings.
     *
     * @param string $text
     * @return string
     */
    private static function resolve_tokens(string $text): string {
        self::require_editor_lib();
        $strings = theme_eadtraining_get_strings_langs();

        return preg_replace_callback('/lang::(\w+)/', function ($match) use ($strings) {
            return $strings[$match[1]] ?? '';
        }, $text);
    }

    /**
     * Makes a model relative URL ("../_assets/...") absolute, like a newly added section.
     *
     * @param string $template
     * @param string $url
     * @return string
     */
    private static function model_url(string $template, string $url): string {
        global $CFG;

        if (strpos($url, '../') === 0) {
            return "{$CFG->wwwroot}/theme/eadtraining/_editor/model/{$template}/{$url}";
        }

        return $url;
    }

    /**
     * Describes where a text sits in the HTML: heading, paragraph, link, attribute or generic text.
     *
     * @param string $html
     * @param int $offset
     * @return string
     */
    private static function describe_position(string $html, int $offset): string {
        $before = substr($html, 0, $offset);

        $lt = strrpos($before, '<');
        $gt = strrpos($before, '>');
        if ($lt !== false && ($gt === false || $lt > $gt)) {
            return 'attribute';
        }

        // Find the element containing the text.
        $stack = [];
        preg_match_all('/<(\/?)([a-z][a-z0-9]*)\b[^>]*?(\/?)>/i', $before, $tags, PREG_SET_ORDER);
        foreach ($tags as $tag) {
            $name = strtolower($tag[2]);
            if ($tag[1] === '/') {
                $position = array_search($name, array_reverse($stack, true), true);
                if ($position !== false) {
                    $stack = array_slice($stack, 0, $position);
                }
            } else if ($tag[3] !== '/' && !in_array($name, self::VOIDTAGS)) {
                $stack[] = $name;
            }
        }
        $parent = end($stack) ?: '';

        if (preg_match('/^h[1-6]$/', $parent)) {
            return 'heading';
        }
        if ($parent == 'p') {
            return 'paragraph';
        }
        if (in_array('a', $stack) || in_array('button', $stack)) {
            return 'link';
        }

        return 'text';
    }

    /**
     * Applies settings to a section (without saving it).
     *
     * Texts, links and images are replaced in the stored HTML, so changes made in the visual editor are kept.
     * When something cannot be found (it was changed in the visual editor) it is reported as missing,
     * unless $rebuild is set: the HTML is then rebuilt from the model with all the settings.
     *
     * @param stdClass $page
     * @param array $values form values (text_N, link_N, block_KEY, row_N_KEY, row_N_order, row_N_delete)
     * @param bool $rebuild
     * @param array $images image index => new URL ('' = back to the model image); missing = unchanged
     * @return array [stdClass $page, string[] $missing labels]
     * @throws \Exception
     */
    public static function apply_section_values(stdClass $page, array $values, bool $rebuild, array $images = []): array {
        $page = clone($page);
        $info = json_decode($page->info) ?: new stdClass();
        $fields = self::get_section_fields($page);
        $bannertext = isset($info->bannertext) ? (array) $info->bannertext : [];
        $links = isset($info->sectionlinks) ? (array) $info->sectionlinks : [];
        $savedimages = isset($info->sectionimages) ? (array) $info->sectionimages : [];
        $missing = [];

        // Texts.
        $texts = [];
        foreach ($fields['texts'] as $field) {
            if (!array_key_exists($field['name'], $values)) {
                continue;
            }
            $value = trim(clean_param((string) $values[$field['name']], PARAM_TEXT));
            if ($value === '') {
                $value = $field['value'];
            }
            $texts[$field['key']] = $value;
            if ($rebuild || $value === $field['value'] || self::replace_text($page->html, $field['value'], $value)) {
                $bannertext[$field['key']] = $value;
            } else {
                $missing[] = $field['label'];
            }
        }

        // Links and images, by position.
        $newlinks = [];
        foreach ($fields['links'] as $field) {
            if (array_key_exists($field['name'], $values)) {
                $value = trim(clean_param((string) $values[$field['name']], PARAM_URL));
                $newlinks[$field['index']] = $value === '' ? $field['default'] : $value;
            }
        }
        $newimages = [];
        foreach ($fields['images'] as $field) {
            if (array_key_exists($field['index'], $images)) {
                $newimages[$field['index']] = $images[$field['index']] === '' ? $field['default'] : $images[$field['index']];
            }
        }

        if ($rebuild && $fields['hashtml']) {
            $page->html = self::build_template_html($page->template, array_merge($bannertext, $texts));
            $missing = [];
            // Every link/image comes back from the model: re-apply all the saved ones.
            $newlinks += $links;
            foreach ($savedimages as $index => $url) {
                if ($url && !array_key_exists($index, $newimages)) {
                    $newimages[$index] = $url;
                }
            }
        }

        foreach (['links' => [self::LINKPATTERN, 'href', $newlinks], 'images' => [self::IMAGEPATTERN, 'src', $newimages]]
                as $type => [$pattern, $attribute, $changes]) {
            foreach ($fields[$type] as $field) {
                $index = $field['index'];
                if (!array_key_exists($index, $changes)) {
                    continue;
                }
                $value = $changes[$index];
                if (!$rebuild && $value === $field['value']) {
                    continue;
                }
                if (self::replace_tag_attribute($page->html, $pattern, count($fields[$type]), $index, $attribute, $value)) {
                    if ($type == 'links') {
                        $links[$index] = $value;
                    } else {
                        $savedimages[$index] = $value === $field['default'] ? '' : $value;
                    }
                } else {
                    $missing[] = $field['label'];
                }
            }
        }

        // Options and repeatable items.
        $savedata = isset($info->savedata) ? (array) $info->savedata : [];
        foreach ($fields['block'] as $field) {
            if (array_key_exists($field['name'], $values)) {
                $savedata[$field['key']] = self::clean_field_value($values[$field['name']], $field);
            }
        }
        if ($fields['rowdefs'] && !empty($values['rows_present'])) {
            $rows = self::collect_rows($values, $fields['rowdefs']);
            foreach (array_keys($savedata) as $key) {
                if (is_numeric($key)) {
                    unset($savedata[$key]);
                }
            }
            foreach ($rows as $index => $row) {
                $savedata[(string) $index] = (object) $row;
            }
        }

        $info->bannertext = (object) $bannertext;
        $info->sectionlinks = (object) $links;
        $info->sectionimages = (object) array_filter($savedimages);
        if ($savedata || isset($info->savedata)) {
            $info->savedata = (object) $savedata;
        }
        // Keep the visual editor form in sync with the model, so it offers the same fields.
        $templateinfo = self::get_template_info($page->template);
        if (isset($templateinfo->form) && isset($info->form)) {
            $info->form = $templateinfo->form;
        }
        $page->info = json_encode($info, JSON_PRETTY_PRINT);

        return [$page, $missing];
    }

    /**
     * Builds the repeatable items from the form values: removed and empty new items are skipped,
     * the others are sorted by their position.
     *
     * @param array $values
     * @param array $rowdefs
     * @return array[]
     */
    public static function collect_rows(array $values, array $rowdefs): array {
        $rows = [];
        for ($index = 0; array_key_exists("row_{$index}_order", $values); $index++) {
            if (!empty($values["row_{$index}_delete"])) {
                continue;
            }
            $row = [];
            $empty = true;
            foreach ($rowdefs as $key => $definition) {
                $value = self::clean_field_value($values["row_{$index}_{$key}"] ?? '', $definition);
                $row[$key] = $value;
                if ($value !== '' && $value !== 0) {
                    $empty = false;
                }
            }
            if ($empty) {
                continue;
            }
            $order = (int) $values["row_{$index}_order"];
            $rows[] = ['order' => $order, 'moved' => $order != $index + 1, 'index' => $index, 'row' => $row];
        }

        // On the same position, an item that was moved there goes before the one already in it.
        usort($rows, function ($a, $b) {
            return [$a['order'], !$a['moved'], $a['index']] <=> [$b['order'], !$b['moved'], $b['index']];
        });

        return array_column($rows, 'row');
    }

    /**
     * Cleans a value according to its field definition.
     *
     * @param mixed $value
     * @param array $definition
     * @return int|string
     */
    public static function clean_field_value($value, array $definition) {
        if (is_array($value) || is_object($value)) {
            $value = '';
        }
        if ($definition['type'] == 'select') {
            $value = (string) $value;
            if (!array_key_exists($value, $definition['options'])) {
                return $definition['valuetype'] == 'int' ? 0 : '';
            }
        }
        if ($definition['valuetype'] == 'int') {
            return (int) $value;
        }

        return trim(clean_param((string) $value, PARAM_TEXT));
    }

    /**
     * Replaces a text in the HTML. Only whole texts (element content or attribute values) are replaced.
     *
     * @param string $html
     * @param string $old
     * @param string $new
     * @return bool Whether the text was found.
     */
    private static function replace_text(string &$html, string $old, string $new): bool {
        $variants = array_unique([
            $old,
            htmlspecialchars($old, ENT_NOQUOTES | ENT_HTML5),
            htmlspecialchars($old, ENT_QUOTES | ENT_HTML5),
            htmlspecialchars($old, ENT_QUOTES | ENT_HTML401),
        ]);
        $replacement = s($new);

        foreach ($variants as $variant) {
            if ($variant === '') {
                continue;
            }
            $pattern = '/(?<=[>"\'\s])' . preg_quote($variant, '/') . '(?=[<"\'\s])/u';
            $count = 0;
            $result = preg_replace_callback($pattern, function () use ($replacement) {
                return $replacement;
            }, $html, -1, $count);
            if ($count && $result !== null) {
                $html = $result;
                return true;
            }
        }

        return false;
    }

    /**
     * Replaces the attribute of the Nth tag matching a pattern. The stored HTML must still have the same
     * number of tags as the model, otherwise the visual editor changed it and the tag is not replaced.
     *
     * @param string $html
     * @param string $pattern
     * @param int $expected Number of tags in the model.
     * @param int $index
     * @param string $attribute
     * @param string $value
     * @return bool Whether the tag was found.
     */
    private static function replace_tag_attribute(string &$html, string $pattern, int $expected, int $index, string $attribute,
            string $value): bool {
        $tags = self::get_template_tags($html, $pattern);
        if (count($tags) != $expected || !isset($tags[$index])) {
            return false;
        }

        $tag = $tags[$index];
        $html = substr_replace($html, self::set_attribute($tag['tag'], $attribute, $value), $tag['offset'], strlen($tag['tag']));

        return true;
    }

    /**
     * Builds a section HTML from its model, like a newly added section, with the given texts.
     *
     * @param string $template
     * @param array $texts lang key => text
     * @return string
     * @throws \Exception
     */
    private static function build_template_html(string $template, array $texts): string {
        global $CFG;

        self::require_editor_lib();
        $strings = theme_eadtraining_get_strings_langs();

        $html = preg_replace_callback('/lang::(\w+)/', function ($match) use ($texts, $strings) {
            if (array_key_exists($match[1], $texts)) {
                return s($texts[$match[1]]);
            }
            return $strings[$match[1]] ?? "[{$match[1]}]";
        }, self::get_template_html($template));

        $base = "{$CFG->wwwroot}/theme/eadtraining/_editor/model/{$template}/../";
        $html = str_replace("src=\"../", "src=\"{$base}", $html);
        $html = str_replace("url(\"../", "url(\"{$base}", $html);

        return $html;
    }

    /**
     * Stores an image uploaded for a section and returns its URL.
     *
     * @param int $pageid
     * @param int $index Image position in the section.
     * @param int $draftitemid
     * @return string|null The image URL, or null when the draft area is empty.
     */
    public static function save_section_image(int $pageid, int $index, int $draftitemid): ?string {
        global $USER;

        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $drafts = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
        $draft = reset($drafts);
        if (!$draft || !$draft->is_valid_image()) {
            return null;
        }

        $context = context_system::instance();
        $filepath = "/{$index}/";
        foreach ($fs->get_directory_files($context->id, 'theme_eadtraining', self::IMAGEAREA, $pageid, $filepath) as $old) {
            $old->delete();
        }
        $file = $fs->create_file_from_storedfile([
            'contextid' => $context->id,
            'component' => 'theme_eadtraining',
            'filearea' => self::IMAGEAREA,
            'itemid' => $pageid,
            'filepath' => $filepath,
            'filename' => $draft->get_filename(),
        ], $draft);

        return moodle_url::make_pluginfile_url($context->id, 'theme_eadtraining', self::IMAGEAREA, $pageid, $filepath,
            $file->get_filename())->out(false);
    }

    /**
     * Deletes the stored image of a section position (after the section went back to the model image).
     *
     * @param int $pageid
     * @param int $index
     * @return void
     */
    public static function delete_section_image(int $pageid, int $index): void {
        $fs = get_file_storage();
        foreach ($fs->get_directory_files(context_system::instance()->id, 'theme_eadtraining', self::IMAGEAREA, $pageid,
                "/{$index}/") as $file) {
            $file->delete();
        }
    }

    /**
     * File picker options for section images.
     *
     * @return array
     */
    public static function image_options(): array {
        return [
            'maxfiles' => 1,
            'accepted_types' => ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg'],
        ];
    }
}
