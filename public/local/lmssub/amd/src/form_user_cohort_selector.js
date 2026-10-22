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
 * Autocomplete source that finds users and cohorts in one list.
 *
 * Users keep their id as the value; cohorts use "c" + the cohort id, and the server adds all their members.
 *
 * @module     local_lmssub/form_user_cohort_selector
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {render as renderTemplate} from 'core/templates';
import {getString} from 'core/str';

/**
 * Escapes text for use inside a label.
 *
 * @param {String} text
 * @returns {String}
 */
const escape = text => {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

/**
 * Load the users and cohorts matching the query and render their labels.
 *
 * @param {String} selector The selector of the auto complete element.
 * @param {String} query The query string.
 * @param {Function} callback A callback function receiving an array of results.
 * @param {Function} failure A function to call in case of failure, receiving the error message.
 */
export async function transport(selector, query, callback, failure) {
    const [users, cohorts] = Ajax.call([
        {methodname: 'core_user_search_identity', args: {query: query}},
        {methodname: 'core_cohort_search_cohorts', args: {query: query, context: {contextlevel: 'system', instanceid: 0},
            includes: 'all', limitfrom: 0, limitnum: 50}},
    ]);

    try {
        const [userresponse, cohortresponse, cohortword] = await Promise.all([users, cohorts,
            getString('cohort', 'cohort')]);

        const results = cohortresponse.cohorts.map(cohort => ({
            value: 'c' + cohort.id,
            label: '<span class="badge bg-info text-dark me-1">' + escape(cohortword) + '</span>' + escape(cohort.name) +
                (cohort.idnumber ? ' <small class="text-muted">(' + escape(cohort.idnumber) + ')</small>' : ''),
        }));

        if (userresponse.overflow) {
            // Too many users to list: still offer the matching cohorts.
            const msg = await getString('toomanyuserstoshow', 'core', '>' + userresponse.maxusersperpage);
            callback(results.length ? results : msg);
            return;
        }
        const labels = await Promise.all(userresponse.list.map(user =>
            renderTemplate('core_user/form_user_selector_suggestion', user)));
        userresponse.list.forEach((user, index) => {
            results.push({value: String(user.id), label: labels[index]});
        });
        callback(results);
    } catch (e) {
        failure(e);
    }
}

/**
 * Process the results for auto complete elements.
 *
 * @param {String} selector The selector of the auto complete element.
 * @param {Array} results An array or results returned by {@see transport()}.
 * @return {Array} New array of the selector options.
 */
export function processResults(selector, results) {
    // Results are already {value, label}; a string is a message to show instead.
    return results;
}
