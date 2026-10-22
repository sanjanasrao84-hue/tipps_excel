<?php
// CLI: creates a second sample Psychiatry course (bipolar and psychotic disorders) with two sections of content.
// Usage: php create_psychiatry_sample_course_201.php [--replace] [--config=C:/xampp/htdocs/tipps_excel_lms/config.php]
// --replace deletes an existing course with the same shortname before recreating it.

define('CLI_SCRIPT', true);

$configpath = 'C:/xampp/htdocs/tipps_excel_lms/config.php';
foreach ($argv as $arg) {
    if (strpos($arg, '--config=') === 0) {
        $configpath = substr($arg, 9);
    }
}
require($configpath);
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');

\core\cron::setup_user(get_admin());

const SHORTNAME = 'PSY-SAMPLE-201';
if ($existing = $DB->get_record('course', ['shortname' => SHORTNAME])) {
    if (!in_array('--replace', $argv)) {
        cli_error('Course ' . SHORTNAME . ' already exists; run with --replace to recreate it.');
    }
    delete_course($existing, false);
}

// Prefer the existing Psychiatry category; create one otherwise.
$category = $DB->get_record_select('course_categories', "LOWER(name) LIKE '%psych%'", [], '*', IGNORE_MULTIPLE);
if (!$category) {
    $category = core_course_category::create(['name' => 'Psychiatry'])->get_db_record();
}

$course = create_course((object) [
    'category' => $category->id,
    'fullname' => 'Sample Course: Bipolar and Psychotic Disorders',
    'shortname' => SHORTNAME,
    'summary' => '<p>An intermediate sample course for the Psychiatry department. It covers the recognition and '
        . 'management of bipolar disorder and schizophrenia spectrum disorders, safe use of mood stabilisers and '
        . 'antipsychotics, and the assessment of a first episode of psychosis.</p>'
        . '<p><em>For educational use only. This content is not a substitute for clinical supervision, local '
        . 'protocols or current guidelines.</em></p>',
    'summaryformat' => FORMAT_HTML,
    'format' => 'topics',
    'numsections' => 2,
    'enablecompletion' => 1,
    'showgrades' => 1,
    'visible' => 1,
]);

$sections = [
    1 => [
        'name' => 'Section 1: Bipolar Disorder',
        'summary' => '<p>Recognise mania, hypomania and bipolar depression, and outline acute and long-term '
            . 'management, including safe lithium prescribing.</p>'
            . '<p><strong>Learning outcomes</strong></p><ul>'
            . '<li>Distinguish mania from hypomania, and bipolar I from bipolar II disorder.</li>'
            . '<li>List the medical and substance-related causes to exclude in a first presentation of elevated mood.</li>'
            . '<li>Outline the management of acute mania and bipolar depression.</li>'
            . '<li>Describe lithium monitoring and recognise the signs of lithium toxicity.</li></ul>',
    ],
    2 => [
        'name' => 'Section 2: Schizophrenia and Psychotic Disorders',
        'summary' => '<p>Assess a first episode of psychosis, describe the schizophrenia spectrum disorders and use '
            . 'antipsychotic medication safely, then apply your knowledge to a clinical case.</p>'
            . '<p><strong>Learning outcomes</strong></p><ul>'
            . '<li>Describe the positive, negative and cognitive symptoms of schizophrenia.</li>'
            . '<li>Differentiate schizophrenia, schizophreniform disorder and brief psychotic disorder by duration.</li>'
            . '<li>Plan the initial work-up of a first episode of psychosis.</li>'
            . '<li>Recognise the important adverse effects of antipsychotics, including neuroleptic malignant syndrome.</li></ul>',
    ],
];
foreach ($sections as $num => $info) {
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $num], '*', MUST_EXIST);
    course_update_section($course, $section, [
        'name' => $info['name'],
        'summary' => $info['summary'],
        'summaryformat' => FORMAT_HTML,
    ]);
}

$gen = new testing_data_generator();

/**
 * Saves a question into the given category using the question type's own save API.
 */
function sample_save_question(string $qtype, int $categoryid, array $form): stdClass {
    $question = (object) ['qtype' => $qtype, 'createdby' => 0, 'idnumber' => null];
    $form = (object) ($form + [
        'category' => $categoryid, 'defaultmark' => 1, 'penalty' => 0.3333333,
        'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
    ]);
    return question_bank::get_qtype($qtype)->save_question($question, $form);
}
$common = ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL];

// General section: course overview.
$gen->create_module('label', $common + ['section' => 0, 'intro' =>
    '<h4>Welcome to Bipolar and Psychotic Disorders</h4>'
    . '<p><strong>Audience:</strong> medical students, junior doctors, nurses and allied health staff who have completed '
    . 'Foundations of Clinical Psychiatry or have equivalent experience.</p>'
    . '<p><strong>Duration:</strong> about 6 hours of self-paced study.</p>'
    . '<p><strong>How to complete:</strong> work through each section in order, take part in the discussion, '
    . 'submit the first-episode psychosis management plan and score at least 70% on both section quizzes.</p>'
    . '<p><em>Content is a teaching sample only and does not replace clinical judgement or local policy. Always check '
    . 'doses and monitoring requirements against your local formulary.</em></p>']);

// Section 1 content.
$gen->create_module('page', $common + ['section' => 1, 'name' => '1.1 Recognising Bipolar Disorder',
    'intro' => 'Mania, hypomania, bipolar depression and the differential diagnosis of elevated mood.',
    'contentformat' => FORMAT_HTML, 'content' => <<<HTML
<h3>Overview</h3>
<p>Bipolar disorder is a recurrent mood disorder with episodes of elevated mood (mania or hypomania) and, in most
people, episodes of depression. It usually begins in late adolescence or early adulthood. Depressive episodes are often
the first and most frequent presentation, so the diagnosis is commonly delayed.</p>
<h4>Mania and hypomania (DSM-5-TR summary)</h4>
<p>A distinct period of abnormally elevated, expansive or irritable mood <strong>and</strong> increased activity or
energy, with at least three of the following (four if the mood is only irritable):</p>
<ul>
<li>Inflated self-esteem or grandiosity</li>
<li>Decreased need for sleep</li>
<li>More talkative than usual, or pressured speech</li>
<li>Flight of ideas or racing thoughts</li>
<li>Distractibility</li>
<li>Increased goal-directed activity or psychomotor agitation</li>
<li>Excessive involvement in risky activities (e.g. spending sprees, sexual indiscretion, unwise investments)</li>
</ul>
<table class="table table-bordered">
<thead><tr><th></th><th>Mania</th><th>Hypomania</th></tr></thead>
<tbody>
<tr><td>Minimum duration</td><td>1 week (any duration if hospitalisation is needed)</td><td>4 consecutive days</td></tr>
<tr><td>Impairment</td><td>Marked impairment in functioning, or hospitalisation needed</td><td>A clear change in functioning, but not marked impairment</td></tr>
<tr><td>Psychotic features</td><td>May be present</td><td>Absent (if present, the episode is mania)</td></tr>
</tbody>
</table>
<h4>Bipolar I and bipolar II</h4>
<ul>
<li><strong>Bipolar I disorder:</strong> at least one manic episode. Depressive episodes are common but not required.</li>
<li><strong>Bipolar II disorder:</strong> at least one hypomanic episode and at least one major depressive episode, and
never a manic episode.</li>
</ul>
<h4>Differential diagnosis</h4>
<p>In a first presentation of elevated mood, exclude:</p>
<ul>
<li>Substances: stimulants (cocaine, amphetamines), intoxication or withdrawal</li>
<li>Medications: corticosteroids, levodopa, and antidepressants (which can trigger a switch into mania)</li>
<li>Medical causes: hyperthyroidism, neurological illness (e.g. frontal lobe lesions, multiple sclerosis), delirium</li>
<li>Other psychiatric conditions: schizoaffective disorder, ADHD, borderline personality disorder</li>
</ul>
<h4>Key point</h4>
<p>Before starting an antidepressant for depression, always ask about past episodes of elevated mood and any family
history of bipolar disorder.</p>
HTML]);

$gen->create_module('page', $common + ['section' => 1, 'name' => '1.2 Managing Bipolar Disorder',
    'intro' => 'Acute mania, bipolar depression, long-term treatment and lithium safety.',
    'contentformat' => FORMAT_HTML, 'content' => <<<HTML
<h3>Acute mania</h3>
<ul>
<li>Assess risk (to self, others and finances) and capacity. Consider admission if safety cannot be maintained.</li>
<li>Stop or taper any antidepressant.</li>
<li>First-line medication is usually an antipsychotic (e.g. haloperidol, olanzapine, quetiapine or risperidone).
Lithium or valproate are alternatives or additions.</li>
<li>Reduce stimulation, support sleep and involve family or carers where the patient agrees.</li>
</ul>
<h3>Bipolar depression</h3>
<ul>
<li>Avoid antidepressant monotherapy because of the risk of switching into mania or rapid cycling.</li>
<li>Options with evidence include quetiapine, lurasidone, and olanzapine combined with fluoxetine.</li>
<li>Offer a psychological intervention developed for bipolar depression alongside medication.</li>
</ul>
<h3>Long-term treatment</h3>
<p><strong>Lithium</strong> is the most effective long-term mood stabiliser for preventing relapse, and it reduces the
risk of suicide. Valproate, lamotrigine (mainly for preventing depression) and some antipsychotics are alternatives.
<strong>Valproate must be avoided in women and girls of childbearing potential</strong> unless strict pregnancy prevention
conditions are met, because of its high risk of birth defects and neurodevelopmental disorders.</p>
<h4>Lithium monitoring</h4>
<table class="table table-bordered">
<thead><tr><th>What</th><th>When</th></tr></thead>
<tbody>
<tr><td>Serum lithium level, taken 12 hours after the last dose</td><td>Weekly after starting and after each dose change until stable, then every 3 months in the first year, then every 3-6 months</td></tr>
<tr><td>Kidney function (eGFR) and thyroid function</td><td>Before starting, then every 6 months</td></tr>
<tr><td>Calcium, weight or BMI</td><td>Before starting, then at least yearly</td></tr>
<tr><td>ECG</td><td>Before starting if there is cardiovascular disease or risk</td></tr>
</tbody>
</table>
<p>A typical maintenance target is 0.6-0.8 mmol/L. Levels above about 1.5 mmol/L are associated with toxicity.</p>
<h4>Lithium toxicity: an emergency</h4>
<p>Signs include vomiting and diarrhoea, coarse tremor, ataxia, slurred speech, confusion, and in severe cases seizures
and reduced consciousness. Common triggers are dehydration, intercurrent illness, and interacting drugs (NSAIDs,
ACE inhibitors, angiotensin receptor blockers and thiazide diuretics). Stop lithium, check the level urgently and
seek medical advice.</p>
HTML]);

$gen->create_module('url', $common + ['section' => 1, 'name' => 'Resource: NICE Guideline CG185 - Bipolar Disorder',
    'intro' => 'The UK National Institute for Health and Care Excellence guideline on the assessment and management of '
        . 'bipolar disorder in adults, children and young people.',
    'externalurl' => 'https://www.nice.org.uk/guidance/cg185']);

$gen->create_module('forum', $common + ['section' => 1, 'name' => 'Discussion: Supporting Medication Adherence',
    'intro' => '<p>Many people with bipolar disorder stop their medication at some point. Describe one reason this '
        . 'might happen and one practical, collaborative way a clinician could respond. Reply to at least one '
        . 'colleague. Do not include any identifiable patient information.</p>', 'introformat' => FORMAT_HTML]);

// Section 2 content.
$gen->create_module('page', $common + ['section' => 2, 'name' => '2.1 Schizophrenia and the First Episode of Psychosis',
    'intro' => 'Symptoms, diagnostic criteria and the initial assessment of psychosis.',
    'contentformat' => FORMAT_HTML, 'content' => <<<HTML
<h3>What is psychosis?</h3>
<p>Psychosis describes a loss of contact with reality, most often with hallucinations, delusions or disorganised
thinking. It is a syndrome with many causes, not a diagnosis in itself.</p>
<h4>Symptom domains in schizophrenia</h4>
<table class="table table-bordered">
<thead><tr><th>Domain</th><th>Examples</th></tr></thead>
<tbody>
<tr><td>Positive symptoms</td><td>Hallucinations (most often auditory), delusions (e.g. persecutory, of reference), thought disorder, disorganised behaviour</td></tr>
<tr><td>Negative symptoms</td><td>Reduced emotional expression, avolition (lack of motivation), alogia (poverty of speech), anhedonia, social withdrawal</td></tr>
<tr><td>Cognitive symptoms</td><td>Problems with attention, working memory and executive function</td></tr>
</tbody>
</table>
<h4>Diagnosis by duration (DSM-5-TR summary)</h4>
<table class="table table-bordered">
<thead><tr><th>Diagnosis</th><th>Total duration of the disturbance</th></tr></thead>
<tbody>
<tr><td>Brief psychotic disorder</td><td>At least 1 day but less than 1 month, with full return to previous functioning</td></tr>
<tr><td>Schizophreniform disorder</td><td>At least 1 month but less than 6 months</td></tr>
<tr><td>Schizophrenia</td><td>At least 6 months, including at least 1 month of active symptoms, with a decline in functioning</td></tr>
</tbody>
</table>
<p>Schizoaffective disorder is considered when a major mood episode occurs together with psychotic symptoms, and
psychotic symptoms are also present for at least 2 weeks without a mood episode.</p>
<h4>Assessing a first episode of psychosis</h4>
<ul>
<li>Full psychiatric history, Mental Status Examination and risk assessment</li>
<li>Substance use history and a urine drug screen</li>
<li>Physical examination, including a neurological examination</li>
<li>Blood tests: full blood count, kidney, liver and thyroid function, glucose or HbA1c, lipids, and others guided
by the history (e.g. HIV and syphilis serology)</li>
<li>Brain imaging and EEG if there are neurological signs, atypical features or a suspected organic cause</li>
<li>Baseline weight, BMI, blood pressure and ECG before starting an antipsychotic</li>
</ul>
<h4>Key point</h4>
<p>Early intervention matters. Refer people with a first episode of psychosis promptly to an early intervention in
psychosis service, where one is available. A shorter duration of untreated psychosis is associated with better outcomes.</p>
HTML]);

$gen->create_module('page', $common + ['section' => 2, 'name' => '2.2 Antipsychotic Medication',
    'intro' => 'Choosing and monitoring antipsychotics, adverse effects and clozapine.',
    'contentformat' => FORMAT_HTML, 'content' => <<<HTML
<h3>Choosing an antipsychotic</h3>
<p>Antipsychotics are similarly effective for most people, apart from clozapine, so the choice is guided mainly by the
side-effect profile and the person's preferences. Start at a low dose, increase slowly and review the response after
an adequate trial (usually 4-6 weeks at a therapeutic dose).</p>
<h4>Important adverse effects</h4>
<table class="table table-bordered">
<thead><tr><th>Adverse effect</th><th>Notes</th></tr></thead>
<tbody>
<tr><td>Extrapyramidal side effects</td><td>Acute dystonia (hours to days), akathisia (inner restlessness), parkinsonism, and tardive dyskinesia (late, may be irreversible). More common with high-potency first-generation drugs such as haloperidol.</td></tr>
<tr><td>Metabolic effects</td><td>Weight gain, diabetes and dyslipidaemia, most marked with olanzapine and clozapine. Monitor weight, glucose and lipids.</td></tr>
<tr><td>Raised prolactin</td><td>Sexual dysfunction, menstrual disturbance, galactorrhoea. Common with risperidone and haloperidol.</td></tr>
<tr><td>Cardiac effects</td><td>QTc prolongation. Check an ECG when indicated and avoid combining QT-prolonging drugs.</td></tr>
<tr><td>Sedation and anticholinergic effects</td><td>Drowsiness, dry mouth, constipation, blurred vision.</td></tr>
</tbody>
</table>
<h4>Neuroleptic malignant syndrome (NMS): an emergency</h4>
<p>A rare but life-threatening reaction, most often soon after starting or increasing an antipsychotic. Features are
<strong>fever, muscle rigidity, altered consciousness and autonomic instability</strong> (fluctuating blood pressure,
tachycardia, sweating), with a raised creatine kinase. Stop the antipsychotic and arrange urgent medical care.</p>
<h4>Clozapine</h4>
<p>Clozapine is the only antipsychotic with proven extra benefit in <strong>treatment-resistant schizophrenia</strong>
(no adequate response to at least two different antipsychotics, each given at an adequate dose for an adequate time).
It needs registered blood monitoring because of the risk of neutropenia and agranulocytosis. Other important risks are
myocarditis (especially in the first weeks), severe constipation, seizures and metabolic effects.</p>
HTML]);

$gen->create_module('page', $common + ['section' => 2, 'name' => '2.3 Case Study: "They are watching me"',
    'intro' => 'Apply what you have learned to a first-episode psychosis vignette.',
    'contentformat' => FORMAT_HTML, 'content' => <<<HTML
<h3>Case vignette (fictional)</h3>
<p><strong>Mr B</strong>, a 21-year-old university student, is brought to the emergency department by his sister. Over
the past 4 months he has stopped attending lectures, stays in his room and has lost contact with friends. For the
last 3 weeks he has believed that his neighbours are watching him through the television and plan to harm him, and he
has covered his windows with foil. He hears two voices talking about him when no one is there. He has smoked cannabis
most days for two years. He has no past psychiatric history. His paternal uncle has schizophrenia.</p>
<h4>MSE summary</h4>
<p>Unkempt, guarded and suspicious; poor eye contact. Speech quiet with long pauses. Mood "fine", affect restricted.
Thought form: occasional loosening of associations. Thought content: persecutory delusions about his neighbours; no
current thoughts of harming himself, but says he would "defend himself" if they came to his flat. Perception: second-person
and third-person auditory hallucinations. Cognition: orientated. Insight: absent.</p>
<h4>Questions for reflection</h4>
<ol>
<li>What is your differential diagnosis? What further information would help you decide?</li>
<li>Which investigations would you request, and why?</li>
<li>How would you assess and manage the risks, including the risk to his neighbours?</li>
<li>What role might cannabis play, and how would you discuss it with him?</li>
<li>Which antipsychotic would you consider, and how would you involve him and his family in that decision?</li>
</ol>
<p>Use this case for the <strong>First-Episode Psychosis Management Plan</strong> assignment below.</p>
HTML]);

$gen->create_module('assign', ['course' => $course->id, 'section' => 2,
    'name' => 'Assignment: First-Episode Psychosis Management Plan',
    'intro' => '<p>Using the Mr B case study, write a management plan of no more than 800 words that includes:</p><ul>'
        . '<li>A working diagnosis with differential diagnoses, and the reasons for each</li>'
        . '<li>The investigations you would request</li>'
        . '<li>A risk assessment and risk management summary</li>'
        . '<li>Medication choice and monitoring, psychosocial interventions, and family involvement</li></ul>'
        . '<p>Marked out of 100: diagnosis 25, investigations 15, risk 25, management 25, clarity and structure 10.</p>',
    'introformat' => FORMAT_HTML,
    'assignsubmission_onlinetext_enabled' => 1,
    'assignsubmission_onlinetext_wordlimit' => 800,
    'assignsubmission_onlinetext_wordlimitenabled' => 1,
    'assignsubmission_file_enabled' => 1,
    'assignsubmission_file_maxfiles' => 1,
    'assignsubmission_file_maxsizebytes' => 5 * 1024 * 1024,
    'assignfeedback_comments_enabled' => 1,
    'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionsubmit' => 1,
]);

// Quizzes.
$quizzes = [
    1 => ['name' => 'Quiz: Section 1 Knowledge Check', 'questions' => [
        ['mc', 'Hypomania duration', 'The minimum duration of symptoms for a hypomanic episode (DSM-5-TR) is:',
            ['4 consecutive days', '1 week', '2 weeks', '1 day'],
            'Hypomania lasts at least 4 consecutive days; mania lasts at least 1 week, or any duration if hospitalisation is needed.'],
        ['mc', 'Bipolar II', 'Bipolar II disorder requires:',
            ['At least one hypomanic episode and at least one major depressive episode, with no manic episode',
             'At least one manic episode',
             'Recurrent depressive episodes only',
             'Psychotic symptoms during a depressive episode'],
            'Any manic episode makes the diagnosis bipolar I.'],
        ['mc', 'Lithium toxicity', 'Which of these is a typical sign of lithium toxicity?',
            ['Coarse tremor and ataxia', 'Fine tremor only', 'Weight loss', 'Hair growth'],
            'A coarse tremor, ataxia, vomiting, diarrhoea and confusion suggest toxicity. A fine tremor is a common side effect at therapeutic levels.'],
        ['mc', 'Lithium interactions', 'Which medicine commonly raises lithium levels?',
            ['Ibuprofen (an NSAID)', 'Paracetamol', 'Amoxicillin', 'Omeprazole'],
            'NSAIDs, ACE inhibitors, angiotensin receptor blockers and thiazide diuretics can all raise lithium levels.'],
        ['tf', 'Antidepressant monotherapy', 'Antidepressant monotherapy is the recommended first-line treatment for bipolar depression.', false,
            'False. Antidepressant monotherapy can trigger mania or rapid cycling. Options such as quetiapine or lurasidone are preferred.'],
    ]],
    2 => ['name' => 'Quiz: Section 2 Knowledge Check', 'questions' => [
        ['mc', 'Negative symptoms', 'Which of these is a <strong>negative</strong> symptom of schizophrenia?',
            ['Avolition (lack of motivation)', 'Persecutory delusions', 'Auditory hallucinations', 'Loosening of associations'],
            'Negative symptoms are a loss of normal functions, such as avolition, alogia and reduced emotional expression.'],
        ['mc', 'Schizophreniform duration', 'Psychotic symptoms lasting 3 months, with no mood disorder or organic cause, best fit:',
            ['Schizophreniform disorder', 'Brief psychotic disorder', 'Schizophrenia', 'Delusional disorder'],
            'Schizophreniform disorder lasts at least 1 month but less than 6 months.'],
        ['mc', 'NMS', 'Which combination is most typical of neuroleptic malignant syndrome?',
            ['Fever, muscle rigidity, altered consciousness and autonomic instability',
             'Low temperature, flaccid muscles and bradycardia',
             'Galactorrhoea and menstrual disturbance',
             'Inner restlessness and an urge to move'],
            'NMS is an emergency, usually with a raised creatine kinase. Restlessness describes akathisia; galactorrhoea reflects raised prolactin.'],
        ['mc', 'Clozapine monitoring', 'Regular blood monitoring is required with clozapine mainly because of the risk of:',
            ['Neutropenia and agranulocytosis', 'Hyponatraemia', 'Liver failure', 'Hypothyroidism'],
            'Clozapine can cause a dangerous fall in neutrophils, so blood counts are monitored throughout treatment.'],
        ['tf', 'Investigating psychosis', 'Physical causes and substance use should be excluded in a first episode of psychosis.', true,
            'True. A first episode needs a physical examination, blood tests and a drug screen, with imaging if there are atypical features.'],
    ]],
];

foreach ($quizzes as $sectionnum => $quizdef) {
    $quiz = $gen->create_module('quiz', ['course' => $course->id, 'section' => $sectionnum,
        'name' => $quizdef['name'],
        'intro' => '<p>' . count($quizdef['questions']) . ' questions. Pass mark: 70%. Unlimited attempts; the highest grade counts.</p>',
        'introformat' => FORMAT_HTML,
        'grade' => 10, 'gradepass' => 7, 'questionsperpage' => 1,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionusegrade' => 1, 'completionpassgrade' => 1,
    ]);
    $context = context_module::instance($quiz->cmid);
    $qcat = question_get_default_category($context->id, true);

    foreach ($quizdef['questions'] as $q) {
        [$type, $name, $text] = $q;
        if ($type === 'mc') {
            [, , , $options, $explain] = $q;
            $answers = $fractions = $feedbacks = [];
            foreach (array_pad($options, 5, '') as $i => $opt) {
                $answers[] = ['text' => $opt, 'format' => FORMAT_PLAIN];
                $fractions[] = $i === 0 ? '1.0' : '0.0';
                $feedbacks[] = ['text' => '', 'format' => FORMAT_HTML];
            }
            $question = sample_save_question('multichoice', $qcat->id, [
                'name' => $name, 'single' => 1, 'shownumcorrect' => 0, 'showstandardinstruction' => 0,
                'questiontext' => ['text' => "<p>$text</p>", 'format' => FORMAT_HTML],
                'generalfeedback' => ['text' => "<p>$explain</p>", 'format' => FORMAT_HTML],
                'answer' => $answers, 'fraction' => $fractions, 'feedback' => $feedbacks,
                'answernumbering' => 'abc', 'shuffleanswers' => 1,
                'hint' => [], 'hintclearwrong' => [], 'hintshownumcorrect' => [],
                'correctfeedback' => ['text' => 'Correct.', 'format' => FORMAT_HTML],
                'partiallycorrectfeedback' => ['text' => 'Partially correct.', 'format' => FORMAT_HTML],
                'incorrectfeedback' => ['text' => 'Not quite. Review the section content.', 'format' => FORMAT_HTML],
            ]);
        } else {
            [, , , $correct, $explain] = $q;
            $question = sample_save_question('truefalse', $qcat->id, [
                'name' => $name, 'penalty' => 1, 'showstandardinstruction' => 0,
                'questiontext' => ['text' => "<p>$text</p>", 'format' => FORMAT_HTML],
                'generalfeedback' => ['text' => "<p>$explain</p>", 'format' => FORMAT_HTML],
                'correctanswer' => $correct ? '1' : '0',
                'feedbacktrue' => ['text' => '', 'format' => FORMAT_HTML],
                'feedbackfalse' => ['text' => '', 'format' => FORMAT_HTML],
            ]);
        }
        quiz_add_quiz_question($question->id, $quiz, 0, 2);
    }
    \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
}

rebuild_course_cache($course->id, true);
mtrace("Created course '{$course->fullname}' (id {$course->id}) in category '{$category->name}'.");
mtrace("$CFG->wwwroot/course/view.php?id={$course->id}");
