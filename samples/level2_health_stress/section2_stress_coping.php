<?php
// Level 2 Psychiatry, Section 2: Stress, Stress Responses and Coping Strategies.
// Content for create_level2_health_stress_content.php (page, applied assessment and quiz).

defined('MOODLE_INTERNAL') || die();

return [
    'section' => 2,
    'category' => 'Stress and Coping',

    // Page.
    'page' => [
        'idnumber' => 'L2-S2-PAGE',
        'name' => 'Stress, Stress Responses, and Coping Strategies',
        'intro' => 'What stress is, the types of stressors and stress, how we appraise and respond to stress, and how to '
            . 'cope with it effectively.',
        'content' => <<<'HTML'
<p class="lead">Stress affects both mental and physical health and is a common factor in many presentations seen in
psychiatric practice. This page explains what stress is, the different kinds of stressors, how our appraisal of a
situation shapes the stress we feel, and how we respond to stress emotionally, physiologically and behaviourally.
It ends with healthy, constructive ways of coping.</p>

<div class="alert alert-secondary">
<h4 class="alert-heading">Learning outcomes</h4>
<p>By the end of this page you should be able to:</p>
<ul class="mb-0">
<li>Define stress and explain the role of perceived threat and perceived ability to cope.</li>
<li>Classify stressors by severity and duration.</li>
<li>Explain primary and secondary cognitive appraisal and common appraisal errors.</li>
<li>Describe frustration, change, conflict and pressure, and the three types of conflict.</li>
<li>Describe the emotional, physiological and behavioural responses to stress, including the fight-or-flight
response and the HPA axis.</li>
<li>Compare problem-focused and emotion-focused coping and recommend constructive coping strategies.</li>
</ul>
</div>

<h3>1. What is stress?</h3>
<div class="alert alert-info">
<p class="mb-0"><strong>Stress</strong> occurs when a person perceives a situation as threatening to their well-being
<em>and</em> perceives that they lack the skills or resources to cope with it. The event that triggers stress is
called a <strong>stressor</strong>.</p>
</div>
<p>It is not the event itself that triggers the stress response, but our <strong>appraisal</strong> of it. A job
interview, sitting in traffic, getting married or escaping a house fire can all be stressful, but only if the person
believes the event is threatening and that their skills are not enough to handle it. Two people can face the same
event and experience very different levels of stress.</p>

<h3>2. Types of stressors</h3>
<h4>By severity</h4>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Type of stressor</th><th>Description</th><th>Examples</th></tr></thead>
<tbody>
<tr><td><strong>Catastrophic events</strong></td><td>Sudden, unexpected negative events that affect a large group of
people. Extremely stressful.</td><td>War, hurricanes, earthquakes, floods</td></tr>
<tr><td><strong>Major negative life events</strong></td><td>Serious negative events that happen to an individual.</td>
<td>Death of a loved one, being the victim of a crime, divorce, losing a job</td></tr>
<tr><td><strong>Daily hassles</strong><br><small>(micro-stressors)</small></td><td>Minor, frequent irritations of everyday
life. Repeated exposure is also associated with poorer health.</td><td>Traffic jams, queues, workplace
irritations</td></tr>
</tbody>
</table>
</div>

<h4>By duration</h4>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th></th><th>Acute stressor</th><th>Chronic stressor</th></tr></thead>
<tbody>
<tr><td><strong>Duration</strong></td><td>Relatively brief, with a clear end in sight</td><td>Ongoing, with no clear end in
sight</td></tr>
<tr><td><strong>Examples</strong></td><td>A difficult examination, a traffic jam, a job interview</td><td>Long-term
conflict with a neighbour or co-workers, caring for a chronically ill relative, persistent financial
difficulty</td></tr>
<tr><td><strong>Health impact</strong></td><td>Stress response usually subsides once the event ends</td><td>Prolonged
activation of the stress response, which can harm physical and mental health</td></tr>
</tbody>
</table>
</div>

<h3>3. Cognitive appraisal</h3>
<p>How stressful an event feels depends largely on how we <strong>cognitively appraise</strong> it. We appraise two
things:</p>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Appraisal</th><th>Question we ask</th><th>Job interview example</th></tr></thead>
<tbody>
<tr><td><strong>Primary appraisal</strong><br><small>demands of the situation</small></td><td>"What does this situation
demand of me? Is it a threat?"</td><td>"What will I have to do? What questions will they ask?"</td></tr>
<tr><td><strong>Secondary appraisal</strong><br><small>resources to cope</small></td><td>"Do I have what I need to
handle it?"</td><td>"Do I have the knowledge, skills and preparation to answer well?"</td></tr>
</tbody>
</table>
</div>
<p>Stress is greatest when the demands appear to outweigh the resources.</p>

<h4>Appraisal errors</h4>
<ul>
<li><strong>Overestimating the demands:</strong> the person feels intense stress in the lead-up to the event and
over-prepares. Once in the situation, the stress decreases because it is easier than expected.</li>
<li><strong>Underestimating the demands:</strong> the person feels little stress beforehand and prepares too little.
Once they realise they are out of their depth, their stress rises sharply.</li>
</ul>

<h4>Everyday examples</h4>
<ul>
<li><strong>Examinations:</strong> a student who appraises an exam as manageable and feels well prepared experiences
less stress than one who believes the content is impossible.</li>
<li><strong>Workplace challenges:</strong> a new task can feel exciting if a person believes they have the skills, or
threatening if they doubt their abilities.</li>
<li><strong>Family difficulties:</strong> a family argument feels less overwhelming when a person believes they have
the communication skills and support to resolve it.</li>
</ul>
<div class="alert alert-secondary"><strong>Reflect:</strong> Think of an upcoming challenge. How would you appraise its
demands, and what resources do you have to meet them?</div>

<h3>4. The four major types of stress</h3>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Type</th><th>Definition</th><th>Examples</th></tr></thead>
<tbody>
<tr><td><strong>Frustration</strong></td><td>Being blocked from achieving a goal. Usually temporary.</td><td>Failing an
examination, sitting in traffic when late</td></tr>
<tr><td><strong>Change</strong></td><td>Having to adjust to something new, whether the change is positive or
negative.</td><td>Marriage, moving house, changing schools or jobs</td></tr>
<tr><td><strong>Conflict</strong></td><td>Two incompatible motivations or goals compete with each other.</td><td>Choosing
between two options (see section 5)</td></tr>
<tr><td><strong>Pressure</strong></td><td>Feeling expected to behave in a certain way: to perform to a standard or to
conform to others' expectations.</td><td>Expectations to excel at school or work; pressure to keep up appearances or
follow social rules</td></tr>
</tbody>
</table>
</div>

<div class="alert alert-warning">
<h4 class="alert-heading">The Social Readjustment Rating Scale (SRRS)</h4>
<p>The SRRS, developed by Holmes and Rahe (1967), assesses the <strong>cumulative impact of major life
changes</strong>. It lists 43 life events, each with a score in "life change units" reflecting how much readjustment
it requires. For example, marriage scores 50, a change in schools 20 and Christmas 12. A person adds up the scores for
the events they have experienced over a set period (typically the past year).</p>
<p>Higher totals indicate more life change and are associated with a greater risk of mental and physical health
problems. Both positive and negative events count, because any change requires adjustment.</p>
<p class="mb-0"><strong>Important:</strong> the SRRS is a research and screening measure of life-change stress. It is
<strong>not a diagnostic instrument</strong> and cannot diagnose any physical or mental disorder. It also does not
capture how each person appraises an event or the coping resources they have.</p>
</div>

<h3>5. Types of conflict</h3>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Type of conflict</th><th>Definition</th><th>Example</th><th>Typical stress level</th></tr></thead>
<tbody>
<tr><td><strong>Approach–approach</strong></td><td>A choice between two attractive goals.</td><td>Choosing between
cheesecake and chocolate cake, or between two good job offers</td><td>Least stressful</td></tr>
<tr><td><strong>Avoidance–avoidance</strong></td><td>A choice between two unattractive options; we dislike both.</td>
<td>Having a root canal treatment or continuing to live with toothache</td><td>Most stressful</td></tr>
<tr><td><strong>Approach–avoidance</strong></td><td>A single goal with both positive and negative aspects, leaving us
unsure whether to proceed.</td><td>Travelling to Europe this summer, but being unable to work and save money</td>
<td>Moderate; often causes wavering</td></tr>
</tbody>
</table>
</div>
<div class="alert alert-secondary"><strong>Reflect:</strong> Which type of conflict best describes a difficult decision
you have faced recently, and why did it feel stressful?</div>

<h3>6. Emotional responses to stress</h3>
<p>Stressful events commonly trigger negative emotions such as <strong>fear</strong>, <strong>anger</strong> and
<strong>sadness</strong>. These reactions are not always immediate: a person escaping a house fire may feel almost
nothing until they reach safety, and only then experience the emotional impact. After the event, positive emotions
such as <strong>relief</strong> and <strong>gratitude</strong> for having come through it may also appear.</p>

<h3>7. Physiological responses to stress</h3>
<p>Stress triggers the <strong>fight-or-flight response</strong>, which prepares the body to defend itself or escape.
This response was useful for our ancestors facing physical dangers such as predators. Today it is often triggered by
situations such as a difficult examination or waiting in a queue, where physical arousal does little to help.</p>
<p>Two pathways run from the <strong>hypothalamus</strong> to the body:</p>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th></th><th>Pathway 1: sympathetic–adrenal-medullary (SAM)</th><th>Pathway 2: hypothalamic–pituitary–adrenal
(HPA) axis</th></tr></thead>
<tbody>
<tr><td><strong>Route</strong></td><td>Hypothalamus → sympathetic nervous system → adrenal medulla</td><td>Hypothalamus
(releases CRH) → pituitary gland (releases ACTH) → adrenal cortex</td></tr>
<tr><td><strong>Hormones released</strong></td><td>Catecholamines (adrenaline and noradrenaline)</td><td>Corticosteroids
(mainly cortisol)</td></tr>
<tr><td><strong>Effects</strong></td><td>Rapid energy: faster heart rate and breathing, raised blood pressure, more
glucose available</td><td>Increased energy (glucose release) and reduced tissue inflammation in case of injury</td></tr>
<tr><td><strong>Speed</strong></td><td>Activated very quickly and subsides quickly once the threat has passed</td>
<td>Activated more slowly and lasts longer</td></tr>
</tbody>
</table>
</div>

<h4>Effects of prolonged stress</h4>
<p>If the HPA axis stays activated for too long, as with chronic stressors, persistently raised corticosteroids
<strong>suppress immune functioning</strong>. This is one way in which stress takes a toll on health. Prolonged stress
is associated with:</p>
<ul>
<li>Greater vulnerability to infections and slower healing.</li>
<li>Raised blood pressure and increased cardiovascular strain.</li>
<li>Sleep disturbance, fatigue and difficulty concentrating.</li>
<li>Greater risk of anxiety and depressive symptoms.</li>
</ul>

<h3>8. Behavioural responses and coping mechanisms</h3>
<p>Behavioural reactions to stress are called <strong>coping mechanisms</strong>. Some are unhelpful and may make
things worse in the long run.</p>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Unhelpful response</th><th>Description</th><th>Why it is unhelpful</th></tr></thead>
<tbody>
<tr><td><strong>Learned helplessness</strong></td><td>A passive, giving-up response that develops after repeated
exposure to uncontrollable stressful events.</td><td>The person stops trying even when the situation could be
improved, and distress persists.</td></tr>
<tr><td><strong>Aggression</strong></td><td>Lashing out at others to release a surge of negative emotion.</td><td>It
gives temporary relief but damages relationships and increases distress over time.</td></tr>
<tr><td><strong>Defence mechanisms</strong></td><td>Unconscious strategies, described by Freud, that distort reality
to protect us from anxiety, such as <em>denial</em> (refusing to accept that an email to a manager was insulting) or
<em>rationalisation</em> (justifying it as something that "needed saying").</td><td>They avoid the real problem;
in effect, we deceive ourselves.</td></tr>
<tr><td><strong>Impulsive or unhealthy behaviours</strong></td><td>Eating, drinking, smoking, gambling or shopping
more when stressed.</td><td>They bring brief relief, but the stress remains and guilt is added.</td></tr>
</tbody>
</table>
</div>

<h3>9. Healthy coping strategies</h3>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th></th><th>Problem-focused coping</th><th>Emotion-focused coping</th></tr></thead>
<tbody>
<tr><td><strong>Focus</strong></td><td>Addresses the <strong>source</strong> of the stress directly</td><td>Manages the
<strong>emotional response</strong> to the stress</td></tr>
<tr><td><strong>Approach</strong></td><td>Assess the demands of the situation, evaluate your resources and act to
change the situation</td><td>Change how you respond when the situation itself cannot be changed</td></tr>
<tr><td><strong>Best used when</strong></td><td>The situation can be changed or controlled</td><td>The situation is
outside your control</td></tr>
<tr><td><strong>Example</strong></td><td>Opening a discussion with a manager to resolve a workplace problem
together</td><td>Staying calm in unavoidable traffic instead of getting angry, for example by listening to music</td></tr>
</tbody>
</table>
</div>
<p>The two approaches work well together: try to change what can be changed, and manage your reactions to what
cannot.</p>

<h4>Constructive coping and stress management</h4>
<ul>
<li><strong>Planning and time management:</strong> break large tasks into steps and prioritise.</li>
<li><strong>Relaxation techniques:</strong> slow breathing, progressive muscle relaxation and mindfulness.</li>
<li><strong>Physical activity and sleep:</strong> regular exercise and good sleep reduce the impact of stress.</li>
<li><strong>Seeking support:</strong> talking to friends, family, colleagues or a professional.</li>
<li><strong>Reappraisal:</strong> reconsider whether the demands are really as great as they seem and recognise the
resources you have.</li>
<li><strong>Developing adaptive responses:</strong> replace unhelpful habits, such as drinking or lashing out, with
healthier alternatives, and seek professional help when stress feels unmanageable.</li>
</ul>

<h3>Summary</h3>
<p>Stress arises when we perceive a situation as threatening and doubt our ability to cope. Stressors range from
catastrophic events to daily hassles, and from acute to chronic. Our cognitive appraisal of demands and resources
shapes how stressful an event feels. Frustration, change, conflict and pressure are four major types of stress, and
the SRRS measures the cumulative impact of life change without diagnosing illness. Stress produces emotional,
physiological and behavioural responses; prolonged activation of the HPA axis can suppress immune functioning.
Unhelpful coping such as learned helplessness, aggression, defence mechanisms and impulsive behaviours tends to worsen
distress, whereas problem-focused and emotion-focused coping help people manage stress constructively.</p>

<div class="alert alert-success">
<h4 class="alert-heading">Key learning points</h4>
<ul class="mb-0">
<li>Stress depends on appraisal: perceived threat plus perceived inability to cope.</li>
<li>Stressors vary in severity (catastrophic events, major life events, daily hassles) and duration (acute,
chronic).</li>
<li>Primary appraisal assesses demands; secondary appraisal assesses coping resources.</li>
<li>Avoidance–avoidance conflict is usually the most stressful, approach–approach the least.</li>
<li>The SAM pathway acts fast through catecholamines; the HPA axis acts more slowly through corticosteroids.</li>
<li>Prolonged stress can suppress immune functioning and harm health.</li>
<li>Problem-focused coping changes the situation; emotion-focused coping changes the response to it.</li>
</ul>
</div>

<div class="alert alert-secondary">
<h4 class="alert-heading">Reflective questions</h4>
<ol class="mb-0">
<li>A patient describes constant worry about a family member's illness. Is this an acute or a chronic stressor, and
what might it mean for their physical health?</li>
<li>How could you help a patient who appears to show learned helplessness begin to regain a sense of control?</li>
<li>When would you recommend emotion-focused coping rather than problem-focused coping?</li>
</ol>
</div>
HTML,
    ],

    // Applied assessment (assignment with a marking guide).
    'assessment' => [
        'idnumber' => 'L2-S2-ASSESS',
        'name' => 'Stress and Coping – Applied Assessment',
        'topic' => 'stress, stressors, cognitive appraisal, stress responses and coping',
        'shortanswers' => [
            [
                'question' => 'Define stress and explain the two perceptions that must be present for an event to cause stress.',
                'marks' => 2,
                'guide' => 'Model answer: Stress occurs when a person perceives a situation as threatening to their '
                    . 'well-being and perceives that they lack the skills or resources to cope with it.' . "\n"
                    . 'Award 1 mark for perceived threat and 1 mark for perceived inability to cope.',
            ],
            [
                'question' => 'Distinguish between catastrophic events, major negative life events and daily hassles, '
                    . 'giving an example of each.',
                'marks' => 2,
                'guide' => 'Model answer: Catastrophic events are sudden negative events affecting many people (war, '
                    . 'hurricane); major negative life events happen to an individual (bereavement, being a crime victim); '
                    . 'daily hassles are minor, frequent irritations (traffic jams, queues).' . "\n"
                    . 'Award 1 mark for correct distinctions and 1 mark for appropriate examples of all three.',
            ],
            [
                'question' => 'Explain the difference between an acute and a chronic stressor, with one example of each.',
                'marks' => 2,
                'guide' => 'Model answer: An acute stressor is brief with a clear end in sight (a difficult exam, a traffic '
                    . 'jam); a chronic stressor is ongoing with no end in sight (conflict with a neighbour or co-workers).' . "\n"
                    . 'Award 1 mark for the distinction and 1 mark for correct examples.',
            ],
            [
                'question' => 'Explain primary and secondary appraisal, using a job interview as an example.',
                'marks' => 2,
                'guide' => 'Model answer: Primary appraisal assesses the demands of the situation ("What will they ask me?"); '
                    . 'secondary appraisal assesses the resources available to cope ("Do I have the knowledge and skills?").' . "\n"
                    . 'Award 1 mark for each appraisal correctly explained with the example.',
            ],
            [
                'question' => 'Name and briefly define the four major types of stress.',
                'marks' => 2,
                'guide' => 'Model answer: Frustration (being blocked from a goal); change (having to adjust to something new); '
                    . 'conflict (two incompatible motivations compete); pressure (feeling expected to behave or perform in a '
                    . 'certain way).' . "\n"
                    . 'Award 0.5 mark for each type correctly named and defined.',
            ],
            [
                'question' => 'What does the Social Readjustment Rating Scale (SRRS) measure, and why should it not be used as '
                    . 'a diagnostic tool?',
                'marks' => 2,
                'guide' => 'Model answer: It measures the cumulative stress of major life changes by adding life change units '
                    . 'for events experienced (e.g. marriage 50, Christmas 12); higher totals are associated with greater '
                    . 'health risk. It is not diagnostic because it only shows an association with risk, cannot identify any '
                    . 'disorder and ignores individual appraisal and coping resources.' . "\n"
                    . 'Award 1 mark for what it measures and 1 mark for why it is not diagnostic.',
            ],
            [
                'question' => 'Describe the three types of conflict and state which is usually the most stressful.',
                'marks' => 2,
                'guide' => 'Model answer: Approach–approach (choice between two attractive goals); avoidance–avoidance '
                    . '(choice between two unattractive options); approach–avoidance (one goal with positive and negative '
                    . 'aspects). Avoidance–avoidance is usually the most stressful.' . "\n"
                    . 'Award 1.5 marks for the three descriptions (0.5 each) and 0.5 mark for identifying avoidance–avoidance.',
            ],
            [
                'question' => 'Compare the sympathetic–adrenal-medullary pathway and the hypothalamic–pituitary–adrenal (HPA) '
                    . 'axis in the stress response.',
                'marks' => 2,
                'guide' => 'Model answer: SAM: hypothalamus → sympathetic nervous system → adrenal medulla → catecholamines; '
                    . 'fast onset and fast recovery, energises the body. HPA: hypothalamus → pituitary → adrenal cortex → '
                    . 'corticosteroids (cortisol); slower and longer lasting, increases energy and reduces inflammation.' . "\n"
                    . 'Award 1 mark for each pathway correctly described (route, hormone and speed).',
            ],
            [
                'question' => 'Explain how prolonged stress may harm physical health.',
                'marks' => 2,
                'guide' => 'Model answer: Prolonged activation of the HPA axis keeps corticosteroids raised, which suppresses '
                    . 'immune functioning, increasing vulnerability to infection; prolonged stress is also linked to raised '
                    . 'blood pressure, sleep problems and unhealthy coping behaviours.' . "\n"
                    . 'Award 1 mark for the HPA/immune mechanism and 1 mark for a relevant health consequence.',
            ],
            [
                'question' => 'Define learned helplessness and explain how it develops.',
                'marks' => 2,
                'guide' => 'Model answer: Learned helplessness is a passive, giving-up response to stress. It develops after '
                    . 'repeated exposure to uncontrollable stressful events, so the person learns that their actions make no '
                    . 'difference and stops trying, even when change is possible.' . "\n"
                    . 'Award 1 mark for the definition and 1 mark for how it develops.',
            ],
        ],
        'scenarios' => [
            [
                'question' => '<p><strong>Scenario: examination stress.</strong> Priya has her final examinations in three '
                    . 'weeks. She believes the syllabus is "impossible to cover", has a racing heart and poor sleep, and has '
                    . 'started avoiding her revision.</p><ol type="a">'
                    . '<li>Explain Priya\'s stress using primary and secondary appraisal. (2 marks)</li>'
                    . '<li>Describe the physiological responses she is likely to be experiencing. (2 marks)</li>'
                    . '<li>Recommend one problem-focused and one emotion-focused coping strategy for her. (2 marks)</li></ol>',
                'marks' => 6,
                'guide' => '(a) Primary appraisal: she judges the demands as overwhelming; secondary appraisal: she believes '
                    . 'her time and skills are insufficient, so demands outweigh resources. 1 mark each.' . "\n"
                    . '(b) Fight-or-flight via the sympathetic nervous system and catecholamines (racing heart, arousal); '
                    . 'with ongoing worry, HPA activation and cortisol release, contributing to poor sleep. 1 mark for each '
                    . 'pathway or two relevant responses.' . "\n"
                    . '(c) Problem-focused: a realistic revision timetable, breaking the syllabus into sections, asking tutors '
                    . 'for help. Emotion-focused: relaxation or breathing exercises, exercise, talking to friends. 1 mark each.',
            ],
            [
                'question' => '<p><strong>Scenario: workplace conflict.</strong> Daniel, a staff nurse, is repeatedly criticised '
                    . 'by his supervisor. He has begun snapping at colleagues, insists "there is no problem at all", and is '
                    . 'drinking more alcohol in the evenings.</p><ol type="a">'
                    . '<li>Identify three unhelpful responses to stress shown by Daniel. (3 marks)</li>'
                    . '<li>Describe a problem-focused approach he could take. (2 marks)</li>'
                    . '<li>Suggest one emotion-focused strategy. (1 mark)</li></ol>',
                'marks' => 6,
                'guide' => '(a) Aggression (lashing out at colleagues); denial, a defence mechanism ("there is no problem"); '
                    . 'impulsive or unhealthy behaviour (increased drinking). 1 mark each.' . "\n"
                    . '(b) Address the source: arrange a calm discussion with the supervisor to clarify expectations and agree '
                    . 'solutions, seek feedback or involve a manager or HR if needed. 2 marks for a clear, appropriate plan.' . "\n"
                    . '(c) Relaxation, exercise, talking to a trusted colleague or counsellor, reducing alcohol. 1 mark.',
            ],
            [
                'question' => '<p><strong>Scenario: traffic congestion.</strong> Kiran commutes for 90 minutes each way through '
                    . 'heavy traffic. He arrives at work angry and tense, with a pounding heart, almost every day.</p><ol type="a">'
                    . '<li>Classify this stressor by severity and identify the type of stress involved. (2 marks)</li>'
                    . '<li>Explain why the fight-or-flight response is poorly suited to this situation. (2 marks)</li>'
                    . '<li>Recommend coping strategies, justifying whether they are problem-focused or emotion-focused. (2 marks)</li></ol>',
                'marks' => 6,
                'guide' => '(a) A daily hassle (micro-stressor); frustration, because he is blocked from his goal of getting '
                    . 'to work. Because it recurs every day, accept discussion of its ongoing (chronic) nature. 1 mark each.' . "\n"
                    . '(b) Fight-or-flight evolved to deal with physical threats; in traffic, arousal (raised heart rate, '
                    . 'catecholamines) cannot be used to fight or flee, so it adds tension without helping. 2 marks.' . "\n"
                    . '(c) Emotion-focused (the traffic cannot be changed): staying calm, music, breathing techniques. '
                    . 'Problem-focused alternatives: changing route or travel time, public transport, flexible hours. '
                    . '1 mark for appropriate strategies, 1 mark for correct justification.',
            ],
            [
                'question' => '<p><strong>Scenario: a difficult decision.</strong> Leena has been offered a one-year fellowship '
                    . 'abroad that would advance her career, but she would have to leave her family and her current job.</p>'
                    . '<ol type="a">'
                    . '<li>Identify the type of conflict and justify your answer. (2 marks)</li>'
                    . '<li>Contrast this with the other two types of conflict, giving an example of each. (2 marks)</li>'
                    . '<li>Suggest two strategies Leena could use to manage the stress of the decision. (2 marks)</li></ol>',
                'marks' => 6,
                'guide' => '(a) Approach–avoidance conflict: one goal (the fellowship) with both positive (career) and '
                    . 'negative (leaving family and job) aspects, causing uncertainty. 2 marks.' . "\n"
                    . '(b) Approach–approach: two attractive options (two good job offers), least stressful; '
                    . 'avoidance–avoidance: two unattractive options (root canal or toothache), most stressful. 1 mark each.' . "\n"
                    . '(c) Problem-focused: list pros and cons, gather information, discuss options with family or a mentor, '
                    . 'set a decision deadline. Emotion-focused: relaxation, talking to supportive people. 1 mark each.',
            ],
            [
                'question' => '<p><strong>Scenario: family caregiving.</strong> Mrs Kapoor has cared for her mother, who has '
                    . 'dementia, for two years. Several attempts to get help have failed. She now says, "Nothing I do makes '
                    . 'any difference", has stopped asking for support and keeps catching colds.</p><ol type="a">'
                    . '<li>Explain her situation using the concepts of chronic stress and learned helplessness. (2 marks)</li>'
                    . '<li>Explain the likely link between her stress and her frequent colds. (2 marks)</li>'
                    . '<li>Suggest two constructive coping strategies or supports. (2 marks)</li></ol>',
                'marks' => 6,
                'guide' => '(a) Caregiving is a chronic stressor with no end in sight; repeated uncontrollable experiences '
                    . '(failed attempts at help) have led to learned helplessness, a passive giving-up response. 1 mark each.' . "\n"
                    . '(b) Prolonged HPA axis activation keeps corticosteroids raised, which suppresses immune functioning and '
                    . 'increases vulnerability to infections. 2 marks.' . "\n"
                    . '(c) Problem-focused: respite care, carer support services, sharing tasks with family, practical help '
                    . 'from social services. Emotion-focused: carer support groups, relaxation, counselling; small achievable '
                    . 'goals to rebuild a sense of control. 1 mark each.',
            ],
        ],
    ],

    // Quiz: 10 multiple choice, 2 true/false, 3 matching (15 marks).
    'quiz' => [
        'idnumber' => 'L2-S2-QUIZ',
        'name' => 'Stress and Coping – Knowledge Assessment',
        'multichoice' => [
            ['SC-MC01 Definition of stress', 'According to the appraisal view, when does stress occur?',
                ['When a situation is perceived as threatening and one\'s coping skills are perceived as insufficient',
                 'Whenever any change occurs in a person\'s life', 'Only when a person is physically injured',
                 'Only during catastrophic events'],
                'Stress depends on appraisal: a perceived threat combined with a perceived lack of coping resources.'],
            ['SC-MC02 Catastrophic event', 'A hurricane that suddenly destroys homes across a whole city is an example of a:',
                ['Catastrophic event', 'Daily hassle', 'Approach–avoidance conflict', 'Chronic micro-stressor'],
                'Catastrophic events are sudden, unexpected negative events that affect large numbers of people.'],
            ['SC-MC03 Daily hassles', 'Repeated traffic jams, long queues and minor workplace irritations are best described as:',
                ['Daily hassles', 'Catastrophic events', 'Major negative life events', 'Defence mechanisms'],
                'Daily hassles are frequent micro-stressors; repeated exposure is also associated with poorer health.'],
            ['SC-MC04 Chronic stressor', 'Years of ongoing conflict with a neighbour, with no end in sight, is an example of:',
                ['A chronic stressor', 'An acute stressor', 'An approach–approach conflict', 'Primary appraisal'],
                'A chronic stressor is ongoing with no clear end, unlike an acute stressor such as a single examination.'],
            ['SC-MC05 Primary appraisal', 'Before a job interview, Ali asks himself, "What questions will they ask me?" This is:',
                ['Primary appraisal of the demands of the situation', 'Secondary appraisal of his coping resources',
                 'Emotion-focused coping', 'Learned helplessness'],
                'Primary appraisal assesses the demands of a situation; secondary appraisal assesses the resources to cope.'],
            ['SC-MC06 Underestimating demands', 'A student underestimates how difficult an examination will be. What is the most '
                . 'likely pattern of stress?',
                ['Little stress beforehand, then a sharp rise once they realise they are unprepared',
                 'Intense stress beforehand, then relief during the examination',
                 'No stress at any point', 'Constant, unchanging stress throughout'],
                'Underestimating demands leads to little preparation and stress that rises sharply during the event.'],
            ['SC-MC07 Frustration', 'Failing an examination that blocks progress towards a career goal is an example of which type of stress?',
                ['Frustration', 'Pressure', 'Change', 'Approach–approach conflict'],
                'Frustration occurs when we are blocked from achieving a goal; it is usually temporary.'],
            ['SC-MC08 Avoidance–avoidance conflict', 'Having to choose between a root canal treatment and living with a toothache is:',
                ['An avoidance–avoidance conflict', 'An approach–approach conflict', 'An approach–avoidance conflict',
                 'A catastrophic event'],
                'Both options are unattractive, which makes avoidance–avoidance usually the most stressful type of conflict.'],
            ['SC-MC09 Fast stress pathway', 'Which sequence describes the rapid fight-or-flight pathway?',
                ['Hypothalamus → sympathetic nervous system → adrenal medulla → catecholamines',
                 'Hypothalamus → pituitary gland → adrenal cortex → corticosteroids',
                 'Pituitary gland → thyroid gland → thyroxine',
                 'Adrenal cortex → hypothalamus → insulin'],
                'The sympathetic–adrenal-medullary pathway acts quickly through catecholamines; the HPA axis is the slower '
                    . 'pathway releasing corticosteroids.'],
            ['SC-MC10 Emotion-focused coping', 'Stuck in unavoidable traffic, a driver focuses on staying calm and listens to '
                . 'music instead of getting angry. This is:',
                ['Emotion-focused coping', 'Problem-focused coping', 'Learned helplessness', 'A defence mechanism'],
                'When the situation cannot be changed, emotion-focused coping manages the emotional response to it.'],
        ],
        'truefalse' => [
            ['SC-TF01 SRRS includes positive events', 'On the Social Readjustment Rating Scale, only negative life events '
                . 'contribute to a person\'s score.', false,
                'False. Positive events such as marriage and Christmas also score, because any change requires readjustment.'],
            ['SC-TF02 Prolonged HPA activation', 'Prolonged activation of the hypothalamic–pituitary–adrenal axis can suppress '
                . 'immune functioning.', true,
                'True. Persistently raised corticosteroids reduce immune functioning, which is one way chronic stress harms health.'],
        ],
        'matching' => [
            ['SC-MA01 Four types of stress', 'Match each type of stress with its definition.', [
                'Frustration' => 'Being blocked from achieving a goal',
                'Change' => 'Having to adjust to something new',
                'Conflict' => 'Two incompatible motivations competing with each other',
                'Pressure' => 'Feeling expected to behave or perform in a certain way',
            ], 'Frustration, change, conflict and pressure are the four major types of stress.'],
            ['SC-MA02 Types of conflict', 'Match each type of conflict with the example that illustrates it.', [
                'Approach–approach' => 'Choosing between two attractive job offers',
                'Avoidance–avoidance' => 'Choosing between a root canal treatment and living with toothache',
                'Approach–avoidance' => 'Wanting to travel abroad for the summer but losing the chance to earn money',
            ], 'Approach–approach involves two attractive goals, avoidance–avoidance two unattractive ones, and '
                . 'approach–avoidance one goal with both positive and negative aspects.'],
            ['SC-MA03 Responses to stress', 'Match each response to stress with its description.', [
                'Learned helplessness' => 'A passive, giving-up response after repeated uncontrollable stressors',
                'Defence mechanism' => 'An unconscious distortion of reality, such as denial or rationalisation',
                'Problem-focused coping' => 'Directly addressing the source of the stress',
                'Emotion-focused coping' => 'Managing one\'s emotional reaction when the situation cannot be changed',
            ], 'Learned helplessness and defence mechanisms are unhelpful responses; problem-focused and emotion-focused '
                . 'coping are constructive strategies.'],
        ],
    ],
];
