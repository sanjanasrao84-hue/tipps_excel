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

namespace theme_eadtraining\external;

use core\context\course;
use core\context\module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_question\local\bank\question_bank_helper;
use core_question\local\bank\question_counts;
use core_question\local\bank\question_edit_contexts;
use core_question\output\question_category_selector;
use invalid_parameter_exception;

/**
 * The question bank REST API requests (/api/rest/v2/question/counts, /banks and /categories, see
 * core_question\route\api\bank) as an AJAX web service, so the question bank works when the REST router of the web
 * server does not (see js/esm/fetch_fallback.js). Same checks and the same JSON as the REST routes.
 *
 * @package    theme_eadtraining
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_api extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action' => new external_value(PARAM_ALPHA, 'counts, banks or categories'),
            'course' => new external_value(PARAM_INT, 'Course id (counts, banks)', VALUE_DEFAULT, 0),
            'currentmodule' => new external_value(PARAM_INT, 'Course module of the current bank (banks)', VALUE_DEFAULT, 0),
            'includeshared' => new external_value(PARAM_BOOL, 'Include shared banks (banks)', VALUE_DEFAULT, true),
            'includerecent' => new external_value(PARAM_BOOL, 'Include recently viewed banks (banks)', VALUE_DEFAULT, false),
            'includeprivate' => new external_value(PARAM_BOOL, 'Include private banks (banks)', VALUE_DEFAULT, false),
            'coursemodule' => new external_value(PARAM_INT, 'Course module of the bank (categories)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Runs a question bank request.
     *
     * @param string $action
     * @param int $course
     * @param int $currentmodule
     * @param bool $includeshared
     * @param bool $includerecent
     * @param bool $includeprivate
     * @param int $coursemodule
     * @return string JSON, as the REST API returns it
     */
    public static function execute(string $action, int $course = 0, int $currentmodule = 0, bool $includeshared = true,
            bool $includerecent = false, bool $includeprivate = false, int $coursemodule = 0): string {
        $params = self::validate_parameters(self::execute_parameters(), compact('action', 'course', 'currentmodule',
            'includeshared', 'includerecent', 'includeprivate', 'coursemodule'));

        switch ($params['action']) {
            case 'counts':
                $coursecontext = course::instance($params['course']);
                self::validate_context($coursecontext);
                $payload = self::counts($coursecontext);
                break;

            case 'banks':
                $coursecontext = course::instance($params['course']);
                self::validate_context($coursecontext);
                $modulecontext = $params['currentmodule'] ? module::instance($params['currentmodule']) : null;
                if ($modulecontext && $modulecontext->get_course_context()->id != $coursecontext->id) {
                    throw new invalid_parameter_exception('The current module is not in this course');
                }
                $payload = ['banks' => question_bank_helper::get_banks_for_course($coursecontext, $modulecontext,
                    $params['includeshared'], $params['includerecent'], $params['includeprivate'])];
                break;

            case 'categories':
                $modulecontext = module::instance($params['coursemodule']);
                self::validate_context($modulecontext);
                // The REST route only requires a login; the categories are only listed to users who work with the bank.
                $caps = array_values(array_unique(array_merge(...array_values(question_edit_contexts::$caps))));
                if (!has_any_capability($caps, $modulecontext)) {
                    throw new \required_capability_exception($modulecontext, $caps[0], 'nopermissions', '');
                }
                $categories = \core\di::get(question_category_selector::class)
                    ->get_categories_for_contexts($modulecontext->id, top: true);
                $payload = [
                    'context' => [
                        'id' => $modulecontext->id,
                        'name' => $modulecontext->get_context_name(false),
                        'prefixedname' => $modulecontext->get_context_name(),
                    ],
                    'categories' => $categories,
                ];
                break;

            default:
                throw new invalid_parameter_exception("Unknown action '{$params['action']}'");
        }

        return (string) json_encode($payload);
    }

    /**
     * Question counts of the course's banks the user can edit, by course module (as /question/counts).
     *
     * @param course $coursecontext
     * @return array
     */
    private static function counts(course $coursecontext): array {
        $capabilities = array_merge(question_edit_contexts::$caps['editq'], question_edit_contexts::$caps['categories']);
        $sharedbanks = question_bank_helper::get_activity_instances_with_shareable_questions(
            [$coursecontext->instanceid],
            havingcap: $capabilities,
            filtercontext: $coursecontext,
        );
        $privatebanks = question_bank_helper::get_activity_instances_with_private_questions(
            [$coursecontext->instanceid],
            havingcap: $capabilities,
            filtercontext: $coursecontext,
        );
        $contextids = [
            ...array_map(fn($bank) => $bank->cminfo->context->id, $sharedbanks),
            ...array_map(fn($bank) => $bank->cminfo->context->id, $privatebanks),
        ];
        return ['counts' => \core\di::get(question_counts::class)->by_course_modules($contextids)];
    }

    /**
     * Result.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_RAW, 'JSON response of the REST API request');
    }
}
