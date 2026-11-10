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
 * Tests of the homepage button route map.
 *
 * @package   theme_eadtraining
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \theme_eadtraining\link_map
 */
final class link_map_test extends \advanced_testcase {

    /**
     * Labels and the route they suggest.
     *
     * @return array
     */
    public static function suggest_provider(): array {
        return [
            ['Explore Courses', 'courses'],
            ['Browse every psychiatry course', 'courses'],
            ['View Pricing', 'pricing'],
            ['Get premium', 'pricing'],
            ['Contact Us', 'contact'],
            ['Help and support', 'contact'],
            ['Sign Up', 'signup'],
            ['Create my account', 'signup'],
            ['Login', 'login'],
            ['Log in', 'login'],
            ['My courses', 'mycourses'],
            ['Continue your enrolled courses', 'mycourses'],
            ['My subscriptions', 'mysubscriptions'],
            ['EXPLORE   courses!', 'courses'],
            ['Learn more', null],
            ['', null],
        ];
    }

    /**
     * Label to route mapping.
     *
     * @dataProvider suggest_provider
     * @param string $label
     * @param string|null $expected
     */
    public function test_suggest(string $label, ?string $expected): void {
        $this->assertSame($expected, link_map::suggest($label));
    }

    /**
     * Suggestions are limited to the allowed routes.
     */
    public function test_suggest_allowed_routes(): void {
        $this->assertNull(link_map::suggest('Sign up', ['courses', 'login']));
    }

    /**
     * Every route has a label and points to a page of this site.
     */
    public function test_routes_exist(): void {
        global $CFG;
        foreach (link_map::ROUTES as $key => [$path]) {
            $this->assertTrue(get_string_manager()->string_exists("link_route_{$key}", 'theme_eadtraining'), $key);
            $file = $CFG->dirroot . rtrim(parse_url($path, PHP_URL_PATH), '/');
            $this->assertTrue(is_file($file) || is_file("{$file}/index.php"), $path);
        }
    }

    /**
     * Links to this site are stored as site paths.
     */
    public function test_normalise(): void {
        global $CFG;
        $this->assertSame('/my/courses.php', link_map::normalise("{$CFG->wwwroot}/my/courses.php"));
        $this->assertSame('/course/index.php', link_map::normalise('course/index.php'));
        $this->assertSame('/', link_map::normalise($CFG->wwwroot));
        $this->assertSame('', link_map::normalise('#'));
        $this->assertSame('#faq', link_map::normalise('#faq'));
        $this->assertSame('https://example.com/a', link_map::normalise('https://example.com/a'));
    }

    /**
     * Form values: routes, custom URLs and validation.
     */
    public function test_form_values(): void {
        $this->assertSame(['courses', ''], link_map::to_form('', 'Explore Courses'));
        $this->assertSame(['home', ''], link_map::to_form('', 'Learn more'));
        $this->assertSame(['contact', ''], link_map::to_form('/user/contactsitesupport.php'));
        $this->assertSame([link_map::CUSTOM, 'https://example.com'], link_map::to_form('https://example.com'));

        $this->assertSame('/course/index.php', link_map::from_form('courses', ''));
        $this->assertSame('https://example.com', link_map::from_form(link_map::CUSTOM, ' https://example.com '));

        $this->assertNull(link_map::validate('courses', ''));
        $this->assertNull(link_map::validate(link_map::CUSTOM, 'https://example.com/page'));
        $this->assertNull(link_map::validate(link_map::CUSTOM, '/course/view.php?id=2'));
        $this->assertSame('link_error_required', link_map::validate(link_map::CUSTOM, ''));
        $this->assertSame('link_error_required', link_map::validate('nosuchroute', ''));
        $this->assertSame('link_error_invalid', link_map::validate(link_map::CUSTOM, 'not a url'));
        $this->assertSame('link_error_invalid', link_map::validate(link_map::CUSTOM, '/nosuchpage.php'));
    }

    /**
     * Rendering never leaves a dead button, and external links open in a new tab.
     */
    public function test_resolve_and_fix_html(): void {
        global $CFG;
        $this->assertSame(["{$CFG->wwwroot}/course/index.php", false], link_map::resolve('', 'Explore Courses'));
        $this->assertSame(["{$CFG->wwwroot}/", false], link_map::resolve('#', 'Learn more'));
        $this->assertSame(["{$CFG->wwwroot}/", false], link_map::resolve('/nosuchpage.php', ''));
        $this->assertSame(['https://example.com', true], link_map::resolve('https://example.com'));

        $html = link_map::fix_html('<a class="btn" href="#">Explore Courses</a><a href="https://example.com">Out</a>' .
            '<a href="#" data-bs-toggle="dropdown">Menu</a>');
        $this->assertStringContainsString("href=\"{$CFG->wwwroot}/course/index.php\">Explore Courses", $html);
        $this->assertStringContainsString('href="https://example.com" target="_blank" rel="noopener noreferrer">Out', $html);
        $this->assertStringContainsString('<a href="#" data-bs-toggle="dropdown">Menu</a>', $html);
    }

    /**
     * Custom banners render their button with the mapped page.
     */
    public function test_banner_button(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = section_manager::save_banner((object) ['name' => 'Test', 'buttontext' => 'Explore Courses',
            'buttonurl' => link_map::from_form('courses', '')]);
        $banner = section_manager::get_banner($id);
        $this->assertSame('/course/index.php', $banner->buttonurl);

        $html = section_manager::render_banner($banner, null);
        $this->assertStringContainsString("href=\"{$CFG->wwwroot}/course/index.php\"", $html);
    }

    /**
     * Sections built on a development machine (http://localhost/...) and deployed load their links and images from the
     * deployed site.
     */
    public function test_content_from_dev_host(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->wwwroot = 'https://lms.example.org';
        $model = '/theme/eadtraining/_editor/model/_assets/psychiatry/hero-consultation.webp';

        $html = link_map::fix_html('<a href="http://localhost/tipps_excel/course/index.php">Explore</a>'
            . '<a href="https://www.example.com/x">Out</a>'
            . "<img src=\"http://localhost/tipps_excel{$model}\" srcset=\"http://localhost/tipps_excel{$model} 2x\">"
            . "<div style=\"background: url('http://127.0.0.1{$model}')\"></div>"
            . '<img src="https://cdn.example.com/a.png">');

        $this->assertStringNotContainsString('localhost', $html);
        $this->assertStringNotContainsString('127.0.0.1', $html);
        $this->assertStringContainsString('href="https://lms.example.org/course/index.php"', $html);
        $this->assertStringContainsString('href="https://www.example.com/x" target="_blank"', $html);
        $this->assertStringContainsString("src=\"https://lms.example.org{$model}\"", $html);
        $this->assertStringContainsString("srcset=\"https://lms.example.org{$model} 2x\"", $html);
        $this->assertStringContainsString("url('https://lms.example.org{$model}')", $html);
        $this->assertStringContainsString('src="https://cdn.example.com/a.png"', $html);

        $this->assertTrue(link_map::is_dev_host('localhost'));
        $this->assertTrue(link_map::is_dev_host('tipps.test'));
        $this->assertFalse(link_map::is_dev_host('lms.example.org'));
    }
}
