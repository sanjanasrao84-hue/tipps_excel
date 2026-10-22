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

namespace report_orgreports\local\tables;

/**
 * Search, filter, sort and paging of a table request. Values are checked by the table before use.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table_request {

    /** @var int[] allowed page sizes. */
    const PERPAGE = [10, 25, 50, 100];

    /**
     * Constructor.
     *
     * @param string $search
     * @param int $courseid
     * @param string $status
     * @param string $sort
     * @param string $dir
     * @param int $page
     * @param int $perpage
     * @param string $method payment method filter
     * @param int $planid subscription plan filter
     */
    public function __construct(
        /** @var string free text search */
        public string $search = '',
        /** @var int course filter */
        public int $courseid = 0,
        /** @var string status filter */
        public string $status = '',
        /** @var string sort column key */
        public string $sort = '',
        /** @var string ASC or DESC */
        public string $dir = 'ASC',
        /** @var int page */
        public int $page = 0,
        /** @var int rows per page */
        public int $perpage = 25,
        /** @var string payment method filter */
        public string $method = '',
        /** @var int subscription plan filter */
        public int $planid = 0,
    ) {
        $this->method = \core_text::substr(clean_param($this->method, PARAM_ALPHANUMEXT), 0, 30);
        $this->search = \core_text::substr(trim($this->search), 0, 100);
        $this->dir = strtoupper($this->dir) === 'DESC' ? 'DESC' : 'ASC';
        $this->page = max(0, $this->page);
        $this->perpage = in_array($this->perpage, self::PERPAGE) ? $this->perpage : 25;
    }
}
