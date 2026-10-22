<?php
// CLI: adds the Health Psychology (Section 1) and Stress and Coping (Section 2) learning materials to the
// Level 2 Psychiatry course: a Page, an Applied Assessment (assignment with a marking guide) and a Knowledge
// Assessment (quiz) in each section. The content lives in level2_health_stress/.
// Usage: php create_level2_health_stress_content.php [--replace] [--course=LP02]
//        [--config=C:/xampp/htdocs/tipps_excel_lms/config.php]
// Activities are identified by their ID number (L2-S1-PAGE...). Existing ones are left untouched unless --replace
// is given, which deletes and recreates only these six activities. Nothing else in the course is changed.

define('CLI_SCRIPT', true);

$configpath = 'C:/xampp/htdocs/tipps_excel_lms/config.php';
$shortname = 'LP02';
foreach ($argv as $arg) {
    if (strpos($arg, '--config=') === 0) {
        $configpath = substr($arg, 9);
    } else if (strpos($arg, '--course=') === 0) {
        $shortname = substr($arg, 9);
    }
}
require($configpath);
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');

\core\cron::setup_user(get_admin());

$replace = in_array('--replace', $argv);
$course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
$gen = new testing_data_generator();
$sections = [
    require(__DIR__ . '/level2_health_stress/section1_health_psychology.php'),
    require(__DIR__ . '/level2_health_stress/section2_stress_coping.php'),
];

/**
 * Finds an activity of this course by ID number; with --replace it is deleted so it can be recreated.
 *
 * @return bool true when the activity exists and must be kept
 */
function level2_existing(stdClass $course, string $idnumber, bool $replace): bool {
    global $DB;
    $cm = $DB->get_record('course_modules', ['course' => $course->id, 'idnumber' => $idnumber, 'deletioninprogress' => 0]);
    if (!$cm) {
        return false;
    }
    if ($replace) {
        course_delete_module($cm->id);
        mtrace("  Deleted previous {$idnumber} (cmid {$cm->id}).");
        return false;
    }
    mtrace("  {$idnumber} already exists (cmid {$cm->id}); kept. Use --replace to recreate it.");
    return true;
}

/**
 * Saves a question into a category using the question type's own save API.
 */
function level2_save_question(string $qtype, int $categoryid, array $form): stdClass {
    $question = (object) ['qtype' => $qtype, 'createdby' => 0, 'idnumber' => null];
    $form = (object) ($form + [
        'category' => $categoryid, 'defaultmark' => 1, 'penalty' => 0,
        'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
    ]);
    return question_bank::get_qtype($qtype)->save_question($question, $form);
}

/**
 * Combined feedback fields shared by multiple-choice and matching questions.
 */
function level2_combined_feedback(): array {
    return [
        'correctfeedback' => ['text' => 'Correct.', 'format' => FORMAT_HTML],
        'partiallycorrectfeedback' => ['text' => 'Partially correct.', 'format' => FORMAT_HTML],
        'incorrectfeedback' => ['text' => 'Not quite. Review the explanation below and the section page.', 'format' => FORMAT_HTML],
        'shownumcorrect' => 1,
        'hint' => [], 'hintclearwrong' => [], 'hintshownumcorrect' => [],
    ];
}

/**
 * Student-facing instructions, marking criteria and questions of an applied assessment.
 */
function level2_assessment_intro(array $assessment): string {
    $shortcount = count($assessment['shortanswers']);
    $scenariocount = count($assessment['scenarios']);
    $shortmarks = array_sum(array_column($assessment['shortanswers'], 'marks'));
    $scenariomarks = array_sum(array_column($assessment['scenarios'], 'marks'));
    $total = $shortmarks + $scenariomarks;
    $each = $assessment['shortanswers'][0]['marks'];
    $eachscenario = $assessment['scenarios'][0]['marks'];

    $html = '<p>This assessment checks your applied understanding of ' . s($assessment['topic']) . '. Answer all '
        . ($shortcount + $scenariocount) . ' questions in your own words.</p>'
        . '<h4>Instructions</h4><ul>'
        . "<li><strong>Part A</strong> has {$shortcount} short-answer questions ({$each} marks each). "
        . 'Answer each in about 50–100 words.</li>'
        . "<li><strong>Part B</strong> has {$scenariocount} scenario-based questions ({$eachscenario} marks each). "
        . 'Answer every sub-part, applying the concepts to the details of the scenario (about 200–300 words per scenario).</li>'
        . '<li>Number your answers to match the questions (for example 11a, 11b).</li>'
        . '<li>Type your answers in the online text box, or upload a single document (Word or PDF).</li>'
        . '<li>Do not include any real patient-identifiable information.</li></ul>'
        . '<h4>Marks</h4><div class="table-responsive"><table class="table table-bordered">'
        . '<thead><tr><th>Part</th><th>Questions</th><th>Marks</th></tr></thead><tbody>'
        . "<tr><td>Part A: Short-answer questions</td><td>1–{$shortcount}</td><td>{$shortcount} × {$each} = {$shortmarks}</td></tr>"
        . '<tr><td>Part B: Scenario-based questions</td><td>' . ($shortcount + 1) . '–' . ($shortcount + $scenariocount)
        . "</td><td>{$scenariocount} × {$eachscenario} = {$scenariomarks}</td></tr>"
        . "<tr><th colspan=\"2\">Total</th><th>{$total}</th></tr></tbody></table></div>"
        . '<h4>Marking criteria</h4><div class="table-responsive"><table class="table table-bordered">'
        . '<thead><tr><th>Question type</th><th>Full marks</th><th>Partial marks</th><th>No marks</th></tr></thead><tbody>'
        . '<tr><td>Short answer</td><td>Accurate and complete, using correct terminology</td>'
        . '<td>Partly correct or incomplete</td><td>Incorrect, irrelevant or not answered</td></tr>'
        . '<tr><td>Scenario (marks shown for each sub-part)</td><td>Correctly identifies the concept, applies it to the '
        . 'scenario details and justifies the answer</td><td>Identifies the concept but applies or justifies it only '
        . 'partly</td><td>Concept missing or incorrect, or no link to the scenario</td></tr></tbody></table></div>'
        . "<h4>Part A: Short-answer questions ({$shortmarks} marks)</h4><ol>";
    foreach ($assessment['shortanswers'] as $item) {
        $html .= '<li>' . s($item['question']) . " <strong>({$item['marks']} marks)</strong></li>";
    }
    $html .= "</ol><h4>Part B: Scenario-based questions ({$scenariomarks} marks)</h4><ol start=\"" . ($shortcount + 1) . '">';
    foreach ($assessment['scenarios'] as $item) {
        $html .= '<li>' . $item['question'] . "<p><strong>Total: {$item['marks']} marks</strong></p></li>";
    }
    return $html . '</ol>';
}

/**
 * Marking guide definition: one criterion per question, the model answer visible to markers only.
 */
function level2_marking_guide(array $assessment): stdClass {
    $criteria = [];
    $number = 0;
    foreach (array_merge($assessment['shortanswers'], $assessment['scenarios']) as $item) {
        $number++;
        $text = trim(html_to_text($item['question'], 0, false));
        $criteria['NEWID' . $number] = [
            'sortorder' => $number,
            'shortname' => "Question {$number}",
            'description' => $text,
            'descriptionmarkers' => $item['guide'],
            'maxscore' => $item['marks'],
        ];
    }
    return (object) [
        'name' => $assessment['name'] . ' – marking guide',
        'description_editor' => [
            'text' => '<p>One criterion per question. The description for markers holds the model answer and how to '
                . 'award marks; students see only the question and its maximum mark.</p>',
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ],
        'guide' => [
            'criteria' => $criteria,
            'options' => ['alwaysshowdefinition' => 1, 'showmarkspercriterionstudents' => 1],
            'comments' => [],
        ],
        'status' => gradingform_controller::DEFINITION_STATUS_READY,
    ];
}

foreach ($sections as $content) {
    $sectionnum = $content['section'];
    if (!$DB->record_exists('course_sections', ['course' => $course->id, 'section' => $sectionnum])) {
        cli_error("Section {$sectionnum} does not exist in {$course->shortname}; no section is created by this script.");
    }
    mtrace("Section {$sectionnum}:");

    // Page.
    $page = $content['page'];
    if (!level2_existing($course, $page['idnumber'], $replace)) {
        $module = $gen->create_module('page', [
            'course' => $course->id, 'section' => $sectionnum, 'idnumber' => $page['idnumber'],
            'name' => $page['name'], 'intro' => $page['intro'], 'introformat' => FORMAT_HTML,
            'content' => $page['content'], 'contentformat' => FORMAT_HTML,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
        ]);
        mtrace("  Page \"{$page['name']}\" (cmid {$module->cmid}).");
    }

    // Applied assessment: assignment graded with a marking guide.
    $assessment = $content['assessment'];
    if (!level2_existing($course, $assessment['idnumber'], $replace)) {
        $total = array_sum(array_column($assessment['shortanswers'], 'marks'))
            + array_sum(array_column($assessment['scenarios'], 'marks'));
        $module = $gen->create_module('assign', [
            'course' => $course->id, 'section' => $sectionnum, 'idnumber' => $assessment['idnumber'],
            'name' => $assessment['name'],
            'intro' => level2_assessment_intro($assessment), 'introformat' => FORMAT_HTML,
            'grade' => $total,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 1,
            'assignsubmission_file_maxsizebytes' => 10 * 1024 * 1024,
            'assignsubmission_file_filetypes' => '.doc,.docx,.pdf,.odt',
            'assignfeedback_comments_enabled' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionsubmit' => 1,
        ]);
        $manager = get_grading_manager(context_module::instance($module->cmid), 'mod_assign', 'submissions');
        $manager->set_active_method('guide');
        $manager->get_controller('guide')->update_definition(level2_marking_guide($assessment));
        mtrace("  Assessment \"{$assessment['name']}\" (cmid {$module->cmid}), {$total} marks, marking guide with "
            . (count($assessment['shortanswers']) + count($assessment['scenarios'])) . ' criteria.');
    }

    // Knowledge assessment: quiz.
    $quizdef = $content['quiz'];
    if (!level2_existing($course, $quizdef['idnumber'], $replace)) {
        $count = count($quizdef['multichoice']) + count($quizdef['truefalse']) + count($quizdef['matching']);
        $quizconfig = get_config('quiz');
        $quiz = $gen->create_module('quiz', [
            'course' => $course->id, 'section' => $sectionnum, 'idnumber' => $quizdef['idnumber'],
            'name' => $quizdef['name'],
            'intro' => "<p>{$count} questions, 1 mark each ({$count} marks in total): "
                . count($quizdef['multichoice']) . ' multiple-choice, ' . count($quizdef['truefalse']) . ' true-or-false and '
                . count($quizdef['matching']) . ' matching questions. There is no negative marking. Feedback and your '
                . 'score are shown after you submit.</p>',
            'introformat' => FORMAT_HTML,
            'grade' => $count,
            'preferredbehaviour' => $quizconfig->preferredbehaviour ?? 'deferredfeedback',
            'attempts' => (int) ($quizconfig->attempts ?? 0),
            'questionsperpage' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionusegrade' => 1,
        ]);
        $context = context_module::instance($quiz->cmid);
        $qgen = $gen->get_plugin_generator('core_question');
        $top = question_get_top_category($context->id, true);
        $parent = $qgen->create_question_category(['name' => 'Psychology', 'contextid' => $context->id, 'parent' => $top->id,
            'info' => 'Psychology questions for Level 2 Psychiatry.']);
        $category = $qgen->create_question_category(['name' => $content['category'], 'contextid' => $context->id,
            'parent' => $parent->id, 'info' => "Questions for the {$quizdef['name']} quiz."]);

        foreach ($quizdef['multichoice'] as [$name, $text, $options, $explain]) {
            $answers = $fractions = $feedbacks = [];
            foreach ($options as $i => $option) {
                $answers[] = ['text' => s($option), 'format' => FORMAT_HTML];
                $fractions[] = $i === 0 ? '1.0' : '0.0';
                $feedbacks[] = ['text' => '', 'format' => FORMAT_HTML];
            }
            $question = level2_save_question('multichoice', $category->id, [
                'name' => $name, 'single' => 1, 'showstandardinstruction' => 0,
                'questiontext' => ['text' => '<p>' . s($text) . '</p>', 'format' => FORMAT_HTML],
                'generalfeedback' => ['text' => '<p>' . s($explain) . '</p>', 'format' => FORMAT_HTML],
                'answer' => $answers, 'fraction' => $fractions, 'feedback' => $feedbacks,
                'answernumbering' => 'abc', 'shuffleanswers' => 1,
            ] + level2_combined_feedback());
            quiz_add_quiz_question($question->id, $quiz, 0, 1);
        }
        foreach ($quizdef['truefalse'] as [$name, $text, $correct, $explain]) {
            $question = level2_save_question('truefalse', $category->id, [
                'name' => $name, 'showstandardinstruction' => 0,
                'questiontext' => ['text' => '<p>' . s($text) . '</p>', 'format' => FORMAT_HTML],
                'generalfeedback' => ['text' => '<p>' . s($explain) . '</p>', 'format' => FORMAT_HTML],
                'correctanswer' => $correct ? '1' : '0',
                'feedbacktrue' => ['text' => $correct ? 'Correct.' : 'Incorrect.', 'format' => FORMAT_HTML],
                'feedbackfalse' => ['text' => $correct ? 'Incorrect.' : 'Correct.', 'format' => FORMAT_HTML],
            ]);
            quiz_add_quiz_question($question->id, $quiz, 0, 1);
        }
        foreach ($quizdef['matching'] as [$name, $text, $pairs, $explain]) {
            $subquestions = $subanswers = [];
            foreach ($pairs as $term => $description) {
                $subquestions[] = ['text' => s($term), 'format' => FORMAT_HTML];
                $subanswers[] = $description;
            }
            $question = level2_save_question('match', $category->id, [
                'name' => $name,
                'questiontext' => ['text' => '<p>' . s($text) . '</p>', 'format' => FORMAT_HTML],
                'generalfeedback' => ['text' => '<p>' . s($explain) . '</p>', 'format' => FORMAT_HTML],
                'shuffleanswers' => 1, 'noanswers' => count($pairs),
                'subquestions' => $subquestions, 'subanswers' => $subanswers,
            ] + level2_combined_feedback());
            quiz_add_quiz_question($question->id, $quiz, 0, 1);
        }
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        mtrace("  Quiz \"{$quizdef['name']}\" (cmid {$quiz->cmid}), {$count} questions in "
            . "\"Psychology / {$content['category']}\".");
    }
}

rebuild_course_cache($course->id, true);
mtrace("Done: {$CFG->wwwroot}/course/view.php?id={$course->id}");
