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

namespace theme_eadtraining;

/**
 * The pages a homepage banner/section button can open, in one place.
 *
 * A button destination is stored as:
 * - a site path starting with "/" (an entry of ROUTES, or any other page of this site), independent of wwwroot, so it
 *   keeps working on every tenant and after the site moves;
 * - or an absolute http(s) URL, for external links.
 *
 * To add a page: add an entry to ROUTES (key => path, label keywords) and a "link_route_<key>" string to lang/en.
 *
 * @package   theme_eadtraining
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_map {

    /** @var string Select value of the "Custom URL" option. */
    public const CUSTOM = 'custom';

    /** @var string Path used when a button has no usable destination. */
    public const FALLBACK = '/';

    /**
     * Pages of the app: key => [path, keywords of a button label that point to it].
     *
     * A keyword matches when all its words are in the label; the longest matching keyword wins, then the first route.
     */
    public const ROUTES = [
        'home' => ['/', ['home', 'homepage']],
        'courses' => ['/course/index.php', ['courses', 'course', 'catalog', 'catalogue', 'explore', 'browse', 'programs',
            'learning path', 'learning paths', 'all content', 'trails']],
        'mycourses' => ['/my/courses.php', ['my courses', 'my course', 'continue', 'resume', 'keep learning', 'my learning',
            'enrolled']],
        'dashboard' => ['/my/', ['dashboard', 'my area', 'student area']],
        'pricing' => ['/local/lmssub/index.php', ['pricing', 'price', 'prices', 'plan', 'plans', 'subscribe', 'premium',
            'buy', 'get started', 'start now', 'enrol', 'enroll', 'enrol now', 'enroll now']],
        'mysubscriptions' => ['/local/lmssub/my.php', ['my subscription', 'my subscriptions', 'subscriptions', 'renew',
            'my plan', 'my plans']],
        'contact' => ['/user/contactsitesupport.php', ['contact', 'contact us', 'support', 'help', 'sales', 'demo',
            'talk to us', 'get in touch']],
        'signup' => ['/login/signup.php', ['sign up', 'signup', 'register', 'create account', 'create my account',
            'join', 'join now']],
        'login' => ['/login/index.php', ['log in', 'login', 'sign in', 'signin']],
        'search' => ['/course/search.php', ['search', 'find course', 'find a course']],
        'calendar' => ['/calendar/view.php?view=upcoming', ['calendar', 'event', 'events', 'live class', 'schedule',
            'book my seat', 'reserve']],
        'certificates' => ['/mod/customcert/my_certificates.php', ['certificate', 'certificates', 'my certificates',
            'achievements']],
        'grades' => ['/grade/report/overview/index.php', ['grades', 'my grades', 'progress']],
        'profile' => ['/user/profile.php', ['profile', 'my profile']],
        'blog' => ['/blog/index.php', ['blog', 'articles', 'news', 'stories']],
    ];

    /**
     * Routes that exist on this site (e.g. sign up only when self registration is on).
     *
     * @return array key => path
     */
    public static function get_routes(): array {
        global $CFG;

        $routes = [];
        foreach (self::ROUTES as $key => [$path]) {
            $available = true;
            switch ($key) {
                case 'signup':
                    $available = !empty($CFG->registerauth);
                    break;
                case 'blog':
                    $available = !empty($CFG->enableblogs);
                    break;
                case 'pricing':
                case 'mysubscriptions':
                case 'certificates':
                    $available = is_file($CFG->dirroot . parse_url($path, PHP_URL_PATH));
                    break;
            }
            if ($available) {
                $routes[$key] = $path;
            }
        }
        return $routes;
    }

    /**
     * Options of the destination dropdown: route key => label, then "Custom URL".
     *
     * @return array
     */
    public static function get_options(): array {
        $options = [];
        foreach (self::get_routes() as $key => $path) {
            $options[$key] = get_string("link_route_{$key}", 'theme_eadtraining');
        }
        $options[self::CUSTOM] = get_string('link_route_custom', 'theme_eadtraining');
        return $options;
    }

    /**
     * Keywords of the available routes, for the label auto-suggest of the forms (amd link_picker).
     *
     * @return array route key => keywords
     */
    public static function get_keywords(): array {
        $keywords = [];
        foreach (array_keys(self::get_routes()) as $key) {
            $keywords[$key] = self::ROUTES[$key][1];
        }
        return $keywords;
    }

    /**
     * Suggests the route of a button label ("Explore Courses" => "courses").
     *
     * @param string $label
     * @param array|null $routes route keys allowed (default: all of ROUTES)
     * @return string|null route key, or null when nothing matches
     */
    public static function suggest(string $label, ?array $routes = null): ?string {
        $words = self::words($label);
        if (!$words) {
            return null;
        }

        $best = null;
        $bestscore = 0;
        foreach (self::ROUTES as $key => [, $keywords]) {
            if ($routes !== null && !in_array($key, $routes)) {
                continue;
            }
            foreach ($keywords as $keyword) {
                $keywordwords = self::words($keyword);
                if ($keywordwords && !array_diff($keywordwords, $words)) {
                    $score = strlen($keyword);
                    if ($score > $bestscore) {
                        $best = $key;
                        $bestscore = $score;
                    }
                }
            }
        }
        return $best;
    }

    /**
     * Lowercase words of a text.
     *
     * @param string $text
     * @return string[]
     */
    private static function words(string $text): array {
        $text = \core_text::strtolower(strip_tags($text));
        return array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', $text)));
    }

    /**
     * Turns a stored destination into the dropdown value and the custom URL field.
     *
     * @param string $value stored destination
     * @param string $label button label, used to preselect a route when there is no destination yet
     * @return array [route key or CUSTOM, custom URL]
     */
    public static function to_form(string $value, string $label = ''): array {
        $value = self::normalise($value);
        if ($value === '') {
            $suggestion = self::suggest($label, array_keys(self::get_routes()));
            return [$suggestion ?? 'home', ''];
        }
        $key = array_search($value, self::get_routes(), true);
        if ($key !== false) {
            return [$key, ''];
        }
        return [self::CUSTOM, $value];
    }

    /**
     * Destination to store for a dropdown value and custom URL (see validate()).
     *
     * @param string $route
     * @param string $custom
     * @return string
     */
    public static function from_form(string $route, string $custom): string {
        if ($route === self::CUSTOM) {
            return self::normalise($custom);
        }
        return self::get_routes()[$route] ?? self::FALLBACK;
    }

    /**
     * Checks a dropdown value and custom URL.
     *
     * @param string $route
     * @param string $custom
     * @return string|null error string identifier of theme_eadtraining, or null when valid
     */
    public static function validate(string $route, string $custom): ?string {
        if ($route === self::CUSTOM) {
            if (trim($custom) === '') {
                return 'link_error_required';
            }
            return self::is_valid(self::normalise($custom)) ? null : 'link_error_invalid';
        }
        return array_key_exists($route, self::get_routes()) ? null : 'link_error_required';
    }

    /**
     * Whether a stored destination works: a page of this site or an http(s) URL.
     *
     * @param string $value
     * @return bool
     */
    public static function is_valid(string $value): bool {
        global $CFG;

        if ($value === '') {
            return false;
        }
        if ($value[0] === '/') {
            if (in_array($value, self::get_routes(), true)) {
                return true;
            }
            $path = rtrim(parse_url($value, PHP_URL_PATH) ?: '/', '/');
            $file = $CFG->dirroot . $path;
            return is_file($file) || is_file("{$file}/index.php");
        }
        return preg_match('#^https?://#i', $value) && clean_param($value, PARAM_URL) === $value;
    }

    /**
     * Brings a destination to its stored form: links to this site become site paths ("/my/courses.php"), including
     * those written for another wwwroot of this site, so a copied database or a renamed folder do not break them.
     *
     * @param string $value
     * @return string site path, absolute external URL, "#anchor"/mailto:/tel: as they are, or '' when empty or "#"
     */
    public static function normalise(string $value): string {
        global $CFG;

        $value = trim(html_entity_decode($value, ENT_QUOTES));
        if ($value === '' || $value === '#') {
            return '';
        }
        if ($value[0] === '#' || preg_match('#^(mailto|tel|javascript):#i', $value)) {
            return $value;
        }

        // This site.
        $wwwroot = rtrim($CFG->wwwroot, '/');
        $nowww = preg_replace('#^https?:#i', '', $wwwroot);
        foreach ([$wwwroot, $nowww] as $root) {
            if (strcasecmp($value, $root) === 0) {
                return '/';
            }
            if (stripos($value, "{$root}/") === 0 || stripos($value, "{$root}?") === 0) {
                return '/' . ltrim(substr($value, strlen($root)), '/');
            }
        }

        if (!preg_match('#^(https?:)?//#i', $value)) {
            // Path relative to wwwroot ("course/index.php") or to the site ("/course/index.php").
            $relative = '/' . ltrim($value, '/');
            $sitepath = parse_url($wwwroot, PHP_URL_PATH) ?? '';
            if ($sitepath !== '' && stripos($relative, "{$sitepath}/") === 0) {
                $relative = substr($relative, strlen($sitepath));
            }
            return $relative;
        }

        // Same host, another folder, or a development host (the database came from another install, e.g. content built
        // on http://localhost/... and deployed): keep the part that is a page here.
        $parts = parse_url($value);
        $host = parse_url($wwwroot, PHP_URL_HOST);
        if (!empty($parts['host']) && (strcasecmp($parts['host'], $host) === 0 || self::is_dev_host($parts['host']))) {
            $segments = array_values(array_filter(explode('/', $parts['path'] ?? '')));
            $tail = (isset($parts['query']) ? "?{$parts['query']}" : '') . (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
            for ($skip = 0; $skip < count($segments); $skip++) {
                $candidate = '/' . implode('/', array_slice($segments, $skip));
                if (is_file($CFG->dirroot . $candidate) || is_file("{$CFG->dirroot}{$candidate}/index.php")) {
                    return $candidate . $tail;
                }
            }
            if (self::is_dev_host($parts['host'])) {
                // A development address is never reachable by visitors.
                return '/';
            }
        }

        return $value;
    }

    /**
     * Whether a host name only exists on a developer's machine (localhost, 127.0.0.1, *.test...).
     *
     * @param string $host
     * @return bool
     */
    public static function is_dev_host(string $host): bool {
        $host = strtolower(trim($host, '[]'));
        return in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
            || preg_match('/\.(localhost|local|test)$/', $host);
    }

    /**
     * URL of a file (image, stylesheet...) on this site: stored HTML holds absolute URLs written for the site where the
     * section was built (e.g. http://localhost/tipps_excel/theme/...), which do not load once the site is deployed.
     *
     * @param string $url
     * @return string
     */
    public static function asset_url(string $url): string {
        global $CFG;

        if (!preg_match('#^(?:https?:)?//([^/?\#]+)(/[^?\#]*)?(.*)$#i', $url, $parts)) {
            return $url;
        }
        $wwwroot = rtrim($CFG->wwwroot, '/');
        $host = preg_replace('/:\d+$/', '', $parts[1]);
        $path = $parts[2] ?? '';
        $tail = $parts[3];

        // Files of the homepage section models exist on every install of this theme.
        if (preg_match('#/theme/eadtraining/_editor/model/.*$#', $path, $model)) {
            return $wwwroot . $model[0] . $tail;
        }
        $samehost = strcasecmp($host, (string) parse_url($wwwroot, PHP_URL_HOST)) === 0;
        if (!$samehost && !self::is_dev_host($host)) {
            return $url;
        }
        // Another folder of this host or a development host: drop leading folders until the path is a file of this site
        // (a script such as /pluginfile.php/1/... counts by its script).
        $segments = array_values(array_filter(explode('/', $path), 'strlen'));
        for ($skip = 0; $skip < count($segments); $skip++) {
            $candidate = '/' . implode('/', array_slice($segments, $skip));
            $script = preg_match('#^(/.*?\.php)(/|$)#', $candidate, $match) ? $match[1] : $candidate;
            if (is_file($CFG->dirroot . $script)) {
                $local = $wwwroot . $candidate . $tail;
                return $samehost && $skip === 0 ? $url : $local;
            }
        }
        return $url;
    }

    /**
     * Points the images, sources and CSS url() of stored HTML at this site (see asset_url()).
     *
     * @param string $html
     * @return string
     */
    public static function fix_assets(string $html): string {
        $html = preg_replace_callback('/(\s(?:src|poster|data-src)\s*=\s*)(["\'])(.*?)\2/i', function ($match) {
            return $match[1] . $match[2] . htmlspecialchars(self::asset_url(html_entity_decode($match[3], ENT_QUOTES)),
                ENT_QUOTES) . $match[2];
        }, $html);
        $html = preg_replace_callback('/(\ssrcset\s*=\s*)(["\'])(.*?)\2/i', function ($match) {
            $items = array_map(function ($item) {
                $item = trim($item);
                $space = strpos($item, ' ');
                $url = $space === false ? $item : substr($item, 0, $space);
                return self::asset_url($url) . ($space === false ? '' : substr($item, $space));
            }, explode(',', html_entity_decode($match[3], ENT_QUOTES)));
            return $match[1] . $match[2] . htmlspecialchars(implode(', ', $items), ENT_QUOTES) . $match[2];
        }, $html);
        return preg_replace_callback('/(url\(\s*)(["\']?)([^"\')]+)\2(\s*\))/i', function ($match) {
            return $match[1] . $match[2] . self::asset_url($match[3]) . $match[2] . $match[4];
        }, $html);
    }

    /**
     * URL a button opens. Missing or broken destinations fall back to the route suggested by the label, then home.
     *
     * @param string $value stored destination
     * @param string $label button label
     * @return array [string $url, bool $external]
     */
    public static function resolve(string $value, string $label = ''): array {
        global $CFG;

        $value = self::normalise($value);
        if ($value !== '' && ($value[0] === '#' || preg_match('#^(mailto|tel|javascript):#i', $value))) {
            return [$value, false];
        }
        if (!self::is_valid($value)) {
            $routes = self::get_routes();
            $suggestion = self::suggest($label, array_keys($routes));
            $value = $suggestion ? $routes[$suggestion] : self::FALLBACK;
        }
        if ($value[0] === '/') {
            return [rtrim($CFG->wwwroot, '/') . $value, false];
        }
        return [$value, true];
    }

    /**
     * Upgrade: gives every stored homepage button a working destination. Custom banner buttons and section links are
     * stored as site paths; buttons without a link get the page of their label, or home.
     *
     * @return void
     */
    public static function migrate(): void {
        global $DB;

        foreach ($DB->get_records('theme_eadtraining_banners') as $banner) {
            $value = self::normalise((string) $banner->buttonurl);
            if (!self::is_valid($value) && trim((string) $banner->buttontext) !== '') {
                $routes = self::get_routes();
                $suggestion = self::suggest((string) $banner->buttontext, array_keys($routes));
                $value = $suggestion ? $routes[$suggestion] : self::FALLBACK;
            }
            if ($value !== (string) $banner->buttonurl) {
                $DB->set_field('theme_eadtraining_banners', 'buttonurl', $value, ['id' => $banner->id]);
            }
        }

        foreach ($DB->get_records('theme_eadtraining_pages', ['local' => 'home']) as $page) {
            $html = self::fix_html((string) $page->html);
            $info = json_decode((string) $page->info);
            if ($info && isset($info->sectionlinks)) {
                foreach ($info->sectionlinks as $index => $link) {
                    $info->sectionlinks->$index = self::normalise((string) $link);
                }
                $page->info = json_encode($info, JSON_PRETTY_PRINT);
            }
            if ($html !== $page->html || isset($info->sectionlinks)) {
                $DB->update_record('theme_eadtraining_pages', (object) ['id' => $page->id, 'html' => $html,
                    'info' => $page->info]);
            }
        }

        \cache::make('theme_eadtraining', 'frontpage_cache')->purge();
    }

    /**
     * Fixes the links of a section HTML: each <a> gets a working href (see resolve()) and external links open in a new
     * tab with rel="noopener noreferrer". Images and CSS url() written for another install load from this site.
     *
     * @param string $html
     * @return string
     */
    public static function fix_html(string $html): string {
        $html = self::fix_assets($html);
        return preg_replace_callback('/(<a\b)([^>]*)(>)(.*?)(<\/a>)/is', function ($match) {
            $attributes = $match[2];
            $value = preg_match('/\shref\s*=\s*"([^"]*)"/i', $attributes, $href) ? $href[1] : '';
            if (preg_match('/\s(role|data-[\w-]+|onclick)\s*=/i', $attributes) && in_array(trim($value), ['', '#'])) {
                // A script-driven control (dropdown, tab, carousel...), not a link.
                return $match[0];
            }

            $label = $match[4];
            if (!trim(strip_tags($label)) && preg_match('/\saria-label\s*=\s*"([^"]*)"/i', $attributes, $aria)) {
                $label = $aria[1];
            }
            [$url, $external] = self::resolve($value, $label);
            $escaped = htmlspecialchars($url, ENT_QUOTES);

            if (isset($href[0])) {
                $attributes = str_replace($href[0], " href=\"{$escaped}\"", $attributes);
            } else {
                $attributes .= " href=\"{$escaped}\"";
            }
            if ($external) {
                if (!preg_match('/\starget\s*=/i', $attributes)) {
                    $attributes .= ' target="_blank"';
                }
                if (!preg_match('/\srel\s*=/i', $attributes)) {
                    $attributes .= ' rel="noopener noreferrer"';
                }
            }
            return $match[1] . $attributes . $match[3] . $match[4] . $match[5];
        }, $html);
    }
}
