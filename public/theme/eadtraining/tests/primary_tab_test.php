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

use theme_eadtraining\local\primary_tab;

/**
 * Tests of the primary navigation tab of the custom pages.
 *
 * @package   theme_eadtraining
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \theme_eadtraining\local\primary_tab
 */
final class primary_tab_test extends \advanced_testcase {

    /**
     * Script paths and their tab.
     *
     * @return array
     */
    public static function script_provider(): array {
        return [
            ['/report/orgreports/index.php', primary_tab::ADMIN],
            ['/report/orgreports/payments.php', primary_tab::ADMIN],
            ['/payment/gateway/razorpay/upi_payments.php', primary_tab::ADMIN],
            ['/local/lmssub/attempts.php', primary_tab::ADMIN],
            ['/theme/eadtraining/banners/edit.php', primary_tab::ADMIN],
            ['/local/lmssub/index.php', primary_tab::PLANS],
            ['/local/lmssub/checkout.php', primary_tab::PLANS],
            ['/local/lmssub/my.php', primary_tab::PLANS],
            ['/payment/gateway/razorpay/renew.php', primary_tab::PLANS],
            ['/local/gcal/index.php', primary_tab::DASHBOARD],
            ['/mod/customcert/my_certificates.php', primary_tab::MYCOURSES],
            ['/theme/eadtraining/_editor/editor.php', primary_tab::HOME],
            ['report/orgreports/index.php', primary_tab::ADMIN],
            ['/my/index.php', null],
            ['/course/view.php', null],
            ['/local/lmssub/indexes.php', null],
        ];
    }

    /**
     * Script path to tab.
     *
     * @dataProvider script_provider
     * @param string $script
     * @param string|null $expected
     */
    public function test_for_script(string $script, ?string $expected): void {
        $this->assertSame($expected, primary_tab::for_script($script));
    }

    /**
     * The tab is applied only when the page did not choose one and the user has that tab.
     */
    public function test_apply(): void {
        global $SCRIPT;
        $this->resetAfterTest();

        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->set_url('/report/orgreports/index.php', ['section' => 'sales']);
        $SCRIPT = '/report/orgreports/index.php';

        // A user without Site administration keeps the core choice.
        $this->setUser($this->getDataGenerator()->create_user());
        $primary = new \core\navigation\views\primary($page);
        primary_tab::apply($page, $primary);
        $this->assertNull($page->get_primary_activate_tab());

        // An administrator gets Site administration.
        $this->setAdminUser();
        $primary = new \core\navigation\views\primary($page);
        $primary->add('Site administration', new \moodle_url('/admin/search.php'), \navigation_node::TYPE_SITE_ADMIN, null,
            primary_tab::ADMIN);
        primary_tab::apply($page, $primary);
        $this->assertSame(primary_tab::ADMIN, $page->get_primary_activate_tab());

        // A tab chosen by the page stays.
        $page->set_primary_active_tab(primary_tab::HOME);
        primary_tab::apply($page, $primary);
        $this->assertSame(primary_tab::HOME, $page->get_primary_activate_tab());
    }
}
