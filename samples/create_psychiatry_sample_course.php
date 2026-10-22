<?php
// CLI: creates a sample Psychiatry course with two sections of content.
// Usage: php create_psychiatry_sample_course.php [--replace] [--config=C:/xampp/htdocs/tipps_excel_lms/config.php]
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

const SHORTNAME = 'PSY-SAMPLE-101';
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
    'fullname' => 'Sample Course: Foundations of Clinical Psychiatry',
    'shortname' => SHORTNAME,
    'summary' => '<p>An introductory sample course for the Psychiatry department. It covers the scope of psychiatry, '
        . 'the psychiatric interview and Mental Status Examination, and two of the most common presentations in '
        . 'clinical practice: depression and anxiety.</p>'
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
        'name' => 'Section 1: Introduction to Psychiatry and the Psychiatric Assessment',
        'summary' => '<p>Understand what psychiatry is, how mental disorders are classified, and how to conduct a '
            . 'structured psychiatric interview including the Mental Status Examination (MSE).</p>'
            . '<p><strong>Learning outcomes</strong></p><ul>'
            . '<li>Describe the scope of psychiatry and the biopsychosocial model.</li>'
            . '<li>Compare the DSM-5-TR and ICD-11 classification systems.</li>'
            . '<li>List the components of a psychiatric history and the MSE.</li>'
            . '<li>Recognise when a risk assessment is required.</li></ul>',
    ],
    2 => [
        'name' => 'Section 2: Common Psychiatric Disorders - Depression and Anxiety',
        'summary' => '<p>Recognise, screen for and outline the management of major depressive disorder and the '
            . 'common anxiety disorders, then apply your knowledge to a clinical case.</p>'
            . '<p><strong>Learning outcomes</strong></p><ul>'
            . '<li>Identify the core diagnostic features of major depressive disorder and generalised anxiety disorder.</li>'
            . '<li>Use and interpret the PHQ-9 and GAD-7 screening tools.</li>'
            . '<li>Outline stepped-care management, including psychological and pharmacological options.</li>'
            . '<li>Write a biopsychosocial case formulation.</li></ul>',
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
    '<h4>Welcome to Foundations of Clinical Psychiatry</h4>'
    . '<p><strong>Audience:</strong> medical students, junior doctors, nurses and allied health staff new to mental health.</p>'
    . '<p><strong>Duration:</strong> about 6 hours of self-paced study.</p>'
    . '<p><strong>How to complete:</strong> work through each section in order, take part in the discussion, '
    . 'submit the case formulation and score at least 70% on both section quizzes.</p>'
    . '<p><em>Content is a teaching sample only and does not replace clinical judgement or local policy.</em></p>']);

// Section 1 content.
$gen->create_module('page', $common + ['section' => 1, 'name' => '1.1 What is Psychiatry?',
    'intro' => 'Scope of psychiatry, the biopsychosocial model and classification systems.',
    'contentformat' => FORMAT_HTML, 'content' => <<<HTML
<h3>What is psychiatry?</h3>
<p>Psychiatry is the branch of medicine concerned with the assessment, diagnosis, treatment and prevention of mental,
emotional and behavioural disorders. Psychiatrists work alongside psychologists, mental health nurses, occupational
therapists, social workers and peer support workers as part of a multidisciplinary team (MDT).</p>
<h4>Sub-specialties</h4>
<ul>
<li>General adult psychiatry</li>
<li>Child and adolescent psychiatry</li>
<li>Old age (geriatric) psychiatry</li>
<li>Forensic psychiatry</li>
<li>Addiction psychiatry</li>
<li>Liaison (consultation-liaison) psychiatry: mental health care in general hospital settings</li>
<li>Psychiatry of intellectual disability</li>
</ul>
<h4>The biopsychosocial model</h4>
<p>Mental disorders rarely have a single cause. The biopsychosocial model considers three groups of factors, each of
which can be <strong>predisposing</strong> (why this person), <strong>precipitating</strong> (why now) or
<strong>perpetuating</strong> (why it continues). <strong>Protective</strong> factors are considered too.</p>
<table class="table table-bordered">
<thead><tr><th>Domain</th><th>Examples</th></tr></thead>
<tbody>
<tr><td>Biological</td><td>Genetics and family history, physical illness, substance use, medication effects, sleep</td></tr>
<tr><td>Psychological</td><td>Personality traits, coping style, early adverse experiences, thinking patterns</td></tr>
<tr><td>Social</td><td>Relationships, employment, housing, finances, culture, social isolation</td></tr>
</tbody>
</table>
<h4>Classification systems</h4>
<ul>
<li><strong>DSM-5-TR</strong> (Diagnostic and Statistical Manual of Mental Disorders, 5th edition, text revision,
American Psychiatric Association, 2022): widely used in clinical practice and research.</li>
<li><strong>ICD-11</strong> (International Classification of Diseases, 11th revision, World Health Organization): the
global standard for health reporting. Chapter 6 covers mental, behavioural and neurodevelopmental disorders.</li>
</ul>
<p>Both systems use descriptive criteria (symptoms, duration, and impact on functioning), because most psychiatric
disorders do not yet have a diagnostic biomarker.</p>
<h4>Key point</h4>
<p>A diagnosis is a starting point. Good psychiatric care always considers the whole person: their story, their goals
and their context.</p>
HTML]);

$gen->create_module('page', $common + ['section' => 1, 'name' => '1.2 The Psychiatric Interview and Mental Status Examination',
    'intro' => 'Structure of a psychiatric history, the MSE and an introduction to risk assessment.',
    'contentformat' => FORMAT_HTML, 'content' => <<<HTML
<h3>The psychiatric history</h3>
<p>Begin with open questions, build rapport and make sure the setting is private and safe. A complete history covers:</p>
<ol>
<li><strong>Presenting complaint</strong>, in the patient's own words</li>
<li><strong>History of presenting complaint</strong>: onset, duration, course, triggers and impact on functioning</li>
<li><strong>Past psychiatric history</strong>: previous diagnoses, admissions, treatments and self-harm</li>
<li><strong>Medical history and medications</strong>, including allergies</li>
<li><strong>Family history</strong> of mental illness or suicide</li>
<li><strong>Personal history</strong>: development, education, relationships, work</li>
<li><strong>Social history</strong>: housing, finances, support, alcohol, drugs, tobacco, forensic history</li>
<li><strong>Premorbid personality</strong></li>
</ol>
<h3>The Mental Status Examination (MSE)</h3>
<p>The MSE is a structured description of the patient's mental state <em>at the time of the interview</em>.</p>
<table class="table table-bordered">
<thead><tr><th>Component</th><th>What to observe or ask</th></tr></thead>
<tbody>
<tr><td>Appearance and behaviour</td><td>Self-care, clothing, eye contact, psychomotor activity, rapport</td></tr>
<tr><td>Speech</td><td>Rate, volume, tone, quantity (e.g. pressured or poverty of speech)</td></tr>
<tr><td>Mood and affect</td><td>Mood is what the patient reports (subjective). Affect is what you observe (objective), e.g. reactive, flat, labile</td></tr>
<tr><td>Thought form</td><td>How thoughts are linked, e.g. flight of ideas, loosening of associations, thought blocking</td></tr>
<tr><td>Thought content</td><td>Preoccupations, obsessions, delusions, and thoughts of self-harm, suicide or harm to others</td></tr>
<tr><td>Perception</td><td>Hallucinations (auditory, visual, etc.), illusions, depersonalisation</td></tr>
<tr><td>Cognition</td><td>Orientation, attention, memory; use a standard screening tool if indicated</td></tr>
<tr><td>Insight and judgement</td><td>Does the patient recognise the illness and the need for help?</td></tr>
</tbody>
</table>
<h3>Risk assessment</h3>
<p>Every psychiatric assessment includes an assessment of risk: to self (suicide, self-harm, self-neglect), to others,
and from others (vulnerability or exploitation). Asking directly about suicidal thoughts <strong>does not</strong>
increase the risk of suicide. Follow your organisation's risk-assessment and escalation policy.</p>
<h4>Practice tip</h4>
<p>Document the MSE in a consistent order, using the patient's own words in quotation marks where possible.</p>
HTML]);

$gen->create_module('url', $common + ['section' => 1, 'name' => 'Resource: WHO ICD-11 Browser',
    'intro' => 'Explore Chapter 6 (Mental, behavioural or neurodevelopmental disorders) of ICD-11.',
    'externalurl' => 'https://icd.who.int/browse/en']);

$gen->create_module('forum', $common + ['section' => 1, 'name' => 'Discussion: Stigma in Mental Health',
    'intro' => '<p>Describe one way stigma can stop people from seeking mental health care, and suggest one '
        . 'practical step clinicians or services can take to reduce it. Reply to at least one colleague. '
        . 'Do not include any identifiable patient information.</p>', 'introformat' => FORMAT_HTML]);

// Section 2 content.
$gen->create_module('page', $common + ['section' => 2, 'name' => '2.1 Major Depressive Disorder',
    'intro' => 'Recognition, screening and stepped-care management of depression.',
    'contentformat' => FORMAT_HTML, 'content' => <<<HTML
<h3>Overview</h3>
<p>Depression is one of the leading causes of disability worldwide. Major depressive disorder (MDD) is characterised by
persistent low mood and/or loss of interest or pleasure, together with other symptoms that cause significant distress
or impairment.</p>
<h4>Diagnostic features (DSM-5-TR summary)</h4>
<p>Five or more of the following, present for <strong>at least 2 weeks</strong>, nearly every day, with at least one
being (1) depressed mood or (2) loss of interest or pleasure (anhedonia):</p>
<ol>
<li>Depressed mood</li>
<li>Markedly diminished interest or pleasure</li>
<li>Significant weight or appetite change</li>
<li>Insomnia or hypersomnia</li>
<li>Psychomotor agitation or retardation</li>
<li>Fatigue or loss of energy</li>
<li>Feelings of worthlessness or excessive guilt</li>
<li>Reduced ability to think, concentrate or make decisions</li>
<li>Recurrent thoughts of death, suicidal ideation or a suicide attempt</li>
</ol>
<p>Always exclude other causes, such as hypothyroidism, substance or medication effects, or bipolar disorder (ask about
past episodes of elevated mood).</p>
<h4>Screening: PHQ-9</h4>
<p>The Patient Health Questionnaire-9 scores each of the nine symptoms from 0 to 3 (total 0-27).</p>
<table class="table table-bordered">
<thead><tr><th>Score</th><th>Severity</th></tr></thead>
<tbody>
<tr><td>0-4</td><td>Minimal</td></tr>
<tr><td>5-9</td><td>Mild</td></tr>
<tr><td>10-14</td><td>Moderate</td></tr>
<tr><td>15-19</td><td>Moderately severe</td></tr>
<tr><td>20-27</td><td>Severe</td></tr>
</tbody>
</table>
<p>Any positive response to item 9 (thoughts of self-harm) needs a direct risk assessment, whatever the total score.</p>
<h4>Management: stepped care</h4>
<ul>
<li><strong>Mild:</strong> psychoeducation, active monitoring, guided self-help, physical activity, sleep hygiene</li>
<li><strong>Moderate to severe:</strong> psychological therapy (e.g. CBT, behavioural activation, interpersonal therapy)
and/or antidepressant medication (an SSRI is commonly first line)</li>
<li><strong>Severe, complex or high risk:</strong> specialist mental health team referral; combined treatment; inpatient
care if safety cannot be maintained</li>
</ul>
<p>Antidepressants typically take 2-4 weeks for initial benefit. Review patients regularly, especially young people
early in treatment, and continue treatment after remission to reduce the risk of relapse.</p>
HTML]);

$gen->create_module('page', $common + ['section' => 2, 'name' => '2.2 Anxiety Disorders',
    'intro' => 'Generalised anxiety disorder, panic disorder and their management.',
    'contentformat' => FORMAT_HTML, 'content' => <<<HTML
<h3>Overview</h3>
<p>Anxiety is a normal response to threat. It becomes a disorder when it is excessive, persistent, hard to control,
and impairs daily life. Anxiety disorders often occur alongside depression.</p>
<h4>Generalised anxiety disorder (GAD)</h4>
<p>Excessive worry about a number of everyday events, occurring more days than not for <strong>at least 6
months</strong>, that is difficult to control and is associated with at least three of:</p>
<ul>
<li>Restlessness or feeling on edge</li>
<li>Being easily fatigued</li>
<li>Difficulty concentrating</li>
<li>Irritability</li>
<li>Muscle tension</li>
<li>Sleep disturbance</li>
</ul>
<h4>Panic disorder</h4>
<p>Recurrent, unexpected panic attacks (sudden surges of intense fear peaking within minutes, with palpitations,
breathlessness, chest discomfort, dizziness, or fear of dying or losing control), followed by persistent concern about
further attacks or avoidance behaviour. Rule out physical causes, such as arrhythmias, hyperthyroidism and stimulant use.</p>
<h4>Other anxiety-related conditions</h4>
<p>Social anxiety disorder, specific phobias and agoraphobia. Obsessive-compulsive disorder and post-traumatic stress
disorder are classified separately in DSM-5-TR but are often managed by the same services.</p>
<h4>Screening: GAD-7</h4>
<p>Seven items scored 0-3 (total 0-21). Scores of 5, 10 and 15 represent mild, moderate and severe anxiety. A score of
10 or more warrants further assessment.</p>
<h4>Management</h4>
<ul>
<li>Psychoeducation and guided self-help</li>
<li>Cognitive behavioural therapy (CBT), the psychological treatment with the strongest evidence base</li>
<li>SSRIs or SNRIs where indicated</li>
<li>Benzodiazepines are generally avoided except for short-term crisis use, because of dependence risk</li>
</ul>
HTML]);

$gen->create_module('page', $common + ['section' => 2, 'name' => '2.3 Case Study: "I just can\'t switch off"',
    'intro' => 'Apply what you have learned to a clinical vignette.',
    'contentformat' => FORMAT_HTML, 'content' => <<<HTML
<h3>Case vignette (fictional)</h3>
<p><strong>Ms A</strong>, a 34-year-old schoolteacher, sees her GP. For the past 8 months she has worried constantly
about her work, her children's health and money. She says, "I just can't switch off." She reports muscle tension,
poor sleep and irritability. Over the last 3 weeks her mood has been low most days, she has stopped going to her
weekly choir, and she feels tired and guilty about "letting everyone down." She denies any plans to end her life but
says she sometimes wishes she "could just disappear for a while." She drinks 2-3 glasses of wine most evenings to help
her sleep. There is a family history of depression (her mother).</p>
<h4>MSE summary</h4>
<p>Casually dressed and well kempt; restless; speech of normal rate but quiet; mood "worn out", affect anxious and
tearful at times; no formal thought disorder; worries about work and family, passive death wishes without intent or
plan; no perceptual abnormalities; cognition grossly intact; good insight.</p>
<h4>Questions for reflection</h4>
<ol>
<li>Which diagnoses would you consider? Which criteria does she meet?</li>
<li>Which screening tools would you use, and what would you do with the results?</li>
<li>How would you explore and document risk? Is there any further information you need?</li>
<li>Identify predisposing, precipitating, perpetuating and protective factors.</li>
<li>Outline an initial management plan, including how you would address her alcohol use.</li>
</ol>
<p>Use this case for the <strong>Case Formulation</strong> assignment below.</p>
HTML]);

$gen->create_module('assign', ['course' => $course->id, 'section' => 2,
    'name' => 'Assignment: Biopsychosocial Case Formulation',
    'intro' => '<p>Using the Ms A case study, write a case formulation of no more than 800 words that includes:</p><ul>'
        . '<li>A summary of the presentation and a working diagnosis, with differential diagnoses</li>'
        . '<li>A biopsychosocial formulation using the 4 Ps (predisposing, precipitating, perpetuating, protective)</li>'
        . '<li>A risk assessment summary</li>'
        . '<li>An initial management plan with follow-up arrangements</li></ul>'
        . '<p>Marked out of 100: formulation 40, risk 20, management 30, clarity and structure 10.</p>',
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
        ['mc', 'Mood vs affect', 'In the Mental Status Examination, "affect" refers to:',
            ['The clinician\'s observation of the patient\'s emotional expression',
             'The patient\'s own description of how they feel',
             'The patient\'s level of insight',
             'The speed of the patient\'s speech'],
            'Mood is subjective (reported by the patient); affect is objective (observed by the clinician).'],
        ['mc', 'Biopsychosocial model', 'Which of these is a <strong>social</strong> factor in the biopsychosocial model?',
            ['Recent loss of employment', 'Family history of bipolar disorder', 'Hypothyroidism', 'Perfectionistic personality traits'],
            'Employment is a social factor; family history and hypothyroidism are biological; personality is psychological.'],
        ['mc', 'Classification', 'Which organisation publishes the ICD-11?',
            ['World Health Organization', 'American Psychiatric Association', 'Royal College of Psychiatrists', 'National Institute of Mental Health'],
            'ICD-11 is published by the WHO; DSM-5-TR is published by the American Psychiatric Association.'],
        ['tf', 'Asking about suicide', 'Asking a patient directly about suicidal thoughts increases their risk of suicide.', false,
            'False. Asking directly does not increase risk and is an essential part of every psychiatric assessment.'],
        ['mc', 'Thought form', 'Rapidly jumping between loosely connected ideas is an abnormality of:',
            ['Thought form', 'Thought content', 'Perception', 'Cognition'],
            'Flight of ideas is a disorder of thought form (how thoughts are linked).'],
    ]],
    2 => ['name' => 'Quiz: Section 2 Knowledge Check', 'questions' => [
        ['mc', 'MDD duration', 'For a diagnosis of major depressive disorder (DSM-5-TR), symptoms must be present for at least:',
            ['2 weeks', '1 week', '1 month', '6 months'],
            'Five or more symptoms for at least 2 weeks, including depressed mood or anhedonia.'],
        ['mc', 'PHQ-9 interpretation', 'A PHQ-9 score of 12 indicates:',
            ['Moderate depression', 'Mild depression', 'Moderately severe depression', 'Severe depression'],
            'PHQ-9 bands: 5-9 mild, 10-14 moderate, 15-19 moderately severe, 20-27 severe.'],
        ['mc', 'GAD duration', 'The minimum duration of symptoms for generalised anxiety disorder (DSM-5-TR) is:',
            ['6 months', '2 weeks', '1 month', '12 months'],
            'GAD requires excessive worry on more days than not for at least 6 months.'],
        ['mc', 'Anxiety psychological treatment', 'Which psychological therapy has the strongest evidence base for anxiety disorders?',
            ['Cognitive behavioural therapy (CBT)', 'Psychoanalysis', 'Hypnotherapy', 'Art therapy'],
            'CBT is the first-line psychological treatment for most anxiety disorders.'],
        ['tf', 'Benzodiazepines', 'Benzodiazepines are the recommended first-line long-term treatment for generalised anxiety disorder.', false,
            'False. Because of dependence risk, they are generally limited to short-term crisis use. SSRIs/SNRIs and CBT are preferred.'],
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
