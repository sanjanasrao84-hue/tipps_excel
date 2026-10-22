<?php
// Level 2 Psychiatry, Section 1: Health Psychology and Health-Related Behaviour.
// Content for create_level2_health_stress_content.php (page, applied assessment and quiz).

defined('MOODLE_INTERNAL') || die();

return [
    'section' => 1,
    'category' => 'Health Psychology',

    // Page.
    'page' => [
        'idnumber' => 'L2-S1-PAGE',
        'name' => 'Health Psychology and Health-Related Behaviour',
        'intro' => 'How psychological and social factors shape physical health, health behaviours, behaviour change and '
            . 'coping with serious illness.',
        'content' => <<<'HTML'
<p class="lead">Physical health is shaped not only by genes, infections and injuries, but also by how we think, feel,
behave and relate to other people. This page introduces <strong>health psychology</strong>: why everyday behaviour
matters so much for health, why changing behaviour is difficult, how lasting change can be achieved, and how people
cope when they face a serious health condition.</p>

<div class="alert alert-secondary">
<h4 class="alert-heading">Learning outcomes</h4>
<p>By the end of this page you should be able to:</p>
<ul class="mb-0">
<li>Define health psychology and explain why it is important in modern healthcare.</li>
<li>Describe how stress, personality and social relationships influence health outcomes.</li>
<li>Distinguish health-promoting behaviours from health-risk behaviours and link lifestyle to chronic illness.</li>
<li>Explain the process of behaviour change, including relapse and how to manage it.</li>
<li>Describe help-seeking, patient–professional communication and the factors that affect treatment adherence.</li>
</ul>
</div>

<h3>1. What is health psychology?</h3>
<div class="alert alert-info">
<p class="mb-0"><strong>Health psychology</strong> is the branch of psychology that studies how
<strong>psychosocial factors</strong>, meaning psychological, behavioural and social influences, affect physical
health, illness and recovery. It also applies this knowledge to promote health and prevent illness.</p>
</div>
<p>Health psychology is based on the <strong>biopsychosocial model</strong>: health and illness result from the
interaction of biological factors (such as genetics), psychological factors (such as thoughts, emotions and coping)
and social factors (such as relationships, culture and living conditions).</p>

<h4>Psychosocial factors that influence health</h4>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Psychosocial factor</th><th>How it can influence health</th><th>Example</th></tr></thead>
<tbody>
<tr><td><strong>Stress</strong></td><td>Prolonged stress keeps the body's stress systems activated, which can weaken immune
functioning and encourage unhealthy coping such as smoking or overeating.</td><td>A person under long-term work stress
catches colds more often and sleeps poorly.</td></tr>
<tr><td><strong>Personality</strong></td><td>Personality traits influence how people appraise stress, the health
behaviours they choose and how they respond to illness.</td><td>A highly hostile, competitive person may react to
minor frustrations with strong anger.</td></tr>
<tr><td><strong>Social relationships</strong></td><td>Supportive relationships buffer stress and encourage healthy
behaviour; conflict and isolation increase risk.</td><td>A supportive partner helps someone keep to an exercise
plan.</td></tr>
</tbody>
</table>
</div>

<h4>Why health psychology matters today</h4>
<p>Many of today's leading causes of death, including <strong>heart disease</strong> and <strong>cancer</strong>, are
strongly influenced by what people do every day. This means that a large part of illness and early death is linked
to behaviours that can be changed.</p>
<p>A well-known longitudinal study followed nearly 7,000 adults and recorded how many of seven good health practices
each person followed regularly:</p>
<ol>
<li>Sleeping 7–8 hours a night</li>
<li>Eating breakfast</li>
<li>Not smoking</li>
<li>Rarely snacking between meals</li>
<li>Maintaining a healthy weight</li>
<li>Exercising regularly</li>
<li>Drinking little or no alcohol</li>
</ol>
<p>People who followed fewer of these practices were considerably more likely to die over the following years than
people who followed more of them. The findings show a direct link between everyday behaviour and life expectancy.</p>
<div class="alert alert-secondary"><strong>Reflect:</strong> How many of the seven health practices do you follow
regularly? Which one would be the most realistic to improve?</div>

<h3>2. Health-promoting and health-risk behaviours</h3>
<div class="alert alert-info">
<p class="mb-1"><strong>Health-promoting behaviours</strong> are actions that protect or improve health.</p>
<p class="mb-0"><strong>Health-risk behaviours</strong> are actions that increase the likelihood of illness, injury or
early death.</p>
</div>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Health-promoting behaviour</th><th>Why it helps</th><th>Related health-risk behaviour</th></tr></thead>
<tbody>
<tr><td>Regular physical exercise</td><td>Strengthens the heart and muscles, helps control weight, blood pressure and
blood glucose, and improves mood and sleep.</td><td>Physical inactivity and prolonged sitting</td></tr>
<tr><td>Balanced nutrition</td><td>Provides essential nutrients and supports a healthy weight and healthy blood
lipids.</td><td>Diets high in sugar, salt and saturated fat; frequent snacking</td></tr>
<tr><td>Adequate sleep (about 7–8 hours)</td><td>Supports immune functioning, concentration, mood and metabolic
health.</td><td>Persistent sleep deprivation</td></tr>
<tr><td>Maintaining a healthy weight</td><td>Reduces strain on the heart and joints and lowers the risk of type 2
diabetes.</td><td>Obesity</td></tr>
<tr><td>Not smoking</td><td>Avoids a major cause of cancer, lung disease and cardiovascular disease.</td><td>Smoking or
other tobacco use</td></tr>
<tr><td>Limiting alcohol</td><td>Reduces the risk of liver disease, several cancers, injuries and dependence.</td>
<td>Excessive or binge drinking</td></tr>
</tbody>
</table>
</div>

<h4>Lifestyle and chronic illness</h4>
<p>Health-risk behaviours rarely cause illness overnight. Their effects build up over years and contribute to
<strong>chronic illnesses</strong> such as cardiovascular disease, type 2 diabetes, chronic lung disease and some
cancers. <strong>Cardiovascular disease</strong> is a clear example:</p>
<ul>
<li>Smoking damages blood vessel walls and promotes the build-up of fatty plaques (atherosclerosis).</li>
<li>A diet high in salt and saturated fat, together with inactivity, raises blood pressure and cholesterol.</li>
<li>Excess weight and inactivity increase the risk of high blood pressure and type 2 diabetes, which further damage
the heart and blood vessels.</li>
<li>Excessive alcohol raises blood pressure and can damage the heart muscle.</li>
</ul>
<p>Because several risk behaviours often occur together, improving even one or two of them can meaningfully lower a
person's overall risk.</p>
<div class="alert alert-secondary"><strong>Reflect:</strong> Why might a person continue a health-risk behaviour even
when they know it is harmful?</div>

<h3>3. Behaviour change</h3>
<p>Most people know that they should exercise, eat well, manage stress and limit alcohol. Knowing is not enough:
behaviour change happens in steps, and people need different kinds of help at each step. The steps below follow the
widely used <strong>stages of change</strong> (transtheoretical) model.</p>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Step</th><th>What happens</th><th>Most helpful support</th></tr></thead>
<tbody>
<tr><td><strong>Recognising the need for change</strong><br><small>(precontemplation to contemplation)</small></td><td>The
person accepts that a behaviour is causing, or will cause, a problem. Without this recognition there is no reason to
change.</td><td>Understanding the consequences of unhealthy habits; non-judgemental feedback</td></tr>
<tr><td><strong>Weighing up change</strong><br><small>(contemplation)</small></td><td>The person considers whether to
change and what it would involve.</td><td>Reliable, accurate information about the problem and the available
treatments</td></tr>
<tr><td><strong>Planning</strong><br><small>(preparation)</small></td><td>The person decides to change and develops a
practical action plan with clear, realistic goals.</td><td>Professional guidance from doctors, nurses, dietitians or
psychologists to build the plan</td></tr>
<tr><td><strong>Taking action</strong><br><small>(action)</small></td><td>The plan is put into practice. This is the
most demanding step and takes the most energy and commitment.</td><td>Strong social support; maintaining
motivation</td></tr>
<tr><td><strong>Maintaining the change</strong><br><small>(maintenance)</small></td><td>The new behaviour is
sustained. It becomes easier over time, and after about six months it tends to feel natural and much less
effortful.</td><td>Ongoing encouragement; planning for high-risk situations</td></tr>
</tbody>
</table>
</div>

<h4>Example: changing an unhealthy routine</h4>
<p>Sara, a 38-year-old administrator, rarely exercises and eats fast food most days. After her blood pressure is
found to be raised, she <em>recognises the need for change</em>. Her nurse gives her <em>reliable information</em>
about blood pressure and lifestyle, and together they write a <em>practical plan</em>: three brisk 30-minute walks a
week and home-cooked lunches. Her sister joins her on walks, providing <em>social support</em>. After six months the
walks have become part of her routine.</p>

<h4>Managing relapse</h4>
<div class="alert alert-info">
<p class="mb-0"><strong>Relapse</strong> is a return to the old behaviour after a period of change. It is a common and
expected part of behaviour change, not proof of failure.</p>
</div>
<ul>
<li>Recognise a lapse for what it is: a temporary setback, not a reason to abandon the goal.</li>
<li>Treat it as a signal that the situation exceeded your coping skills at that moment.</li>
<li>Identify the trigger (for example stress, tiredness, bad weather or a social event) and plan how to handle it
next time.</li>
<li>Restart the plan as soon as possible, building new skills each time.</li>
</ul>
<p>Long-term goals are achieved by people who return to the plan after setbacks, not by people who never slip.</p>
<div class="alert alert-secondary"><strong>Reflect:</strong> Think of a habit you tried to change. What triggered a
relapse, and how could you prepare for that situation in the future?</div>

<h3>4. Coping with serious health conditions</h3>
<p>When people face a serious illness, they differ in how quickly they seek help, how they communicate with health
professionals and how closely they follow treatment.</p>

<h4>Help-seeking behaviour</h4>
<p>Some people tolerate symptoms for a long time before consulting a doctor, while others seek help immediately.
Delays can be caused by fear of the diagnosis, minimising symptoms, embarrassment, cost, or difficulty getting an
appointment. Delayed help-seeking can allow a treatable condition to progress.</p>

<h4>Communication between patients and healthcare professionals</h4>
<p>Many patients leave a consultation without fully understanding what they are supposed to do. Common reasons
include:</p>
<ul>
<li>Short appointment times.</li>
<li>Patients being too passive to ask questions because they see the doctor as an authority figure.</li>
<li>Medical jargon and too much information given at once.</li>
<li>Anxiety, which reduces how much information a person can absorb and remember.</li>
</ul>
<p>Communication improves when professionals use plain language, check understanding (for example by asking the
patient to explain the plan back in their own words), give written instructions, and invite questions. Patients can
help by preparing questions and asking for clarification.</p>

<h4>Treatment adherence</h4>
<div class="alert alert-info">
<p class="mb-0"><strong>Treatment adherence</strong> is the extent to which a patient follows the treatment that has
been agreed with their healthcare professional, such as taking medicines as prescribed or following lifestyle
advice.</p>
</div>
<p>Some estimates suggest that as many as half of patients do not follow their treatment exactly as recommended. They
may never start it, stop early, change the dose or take it irregularly.</p>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Adherence is more likely when…</th><th>Barriers to adherence</th></tr></thead>
<tbody>
<tr><td>The patient fully understands the treatment and why it is needed</td><td>Confusing or poorly explained
instructions</td></tr>
<tr><td>The treatment is not unpleasant or aversive</td><td>Unpleasant side effects or uncomfortable procedures</td></tr>
<tr><td>The patient trusts their healthcare professional</td><td>Low trust or a poor relationship with the
professional</td></tr>
<tr><td>The regimen is simple and fits daily life</td><td>Complex regimens, forgetfulness, cost or practical
difficulties</td></tr>
<tr><td>The patient believes the treatment will help</td><td>Feeling better and assuming treatment is no longer
needed</td></tr>
</tbody>
</table>
</div>
<div class="alert alert-secondary"><strong>Reflect:</strong> As a healthcare professional, what could you do in a
short consultation to make it more likely that a patient understands and follows their treatment?</div>

<h3>Summary</h3>
<p>Health psychology shows that physical health depends on psychological, behavioural and social factors as well as
biology. Everyday behaviours such as exercise, diet, sleep, smoking and alcohol use strongly influence the risk of
chronic illnesses such as cardiovascular disease. Changing behaviour is a gradual process that needs recognition,
information, planning, support and persistence, and relapse is a normal part of it. When illness occurs, timely
help-seeking, clear communication, trust and adherence to treatment all improve outcomes.</p>

<div class="alert alert-success">
<h4 class="alert-heading">Key learning points</h4>
<ul class="mb-0">
<li>Health psychology studies how psychosocial factors such as stress, personality and relationships affect
health.</li>
<li>Leading causes of death, including heart disease and cancer, are strongly influenced by behaviour.</li>
<li>Following more good health practices is associated with lower mortality.</li>
<li>Behaviour change moves through recognition, information, planning, action and maintenance.</li>
<li>Relapse is common and should be treated as a learning opportunity, not a reason to give up.</li>
<li>Adherence improves when patients understand their treatment, find it acceptable and trust their healthcare
professional.</li>
</ul>
</div>
HTML,
    ],

    // Applied assessment (assignment with a marking guide).
    'assessment' => [
        'idnumber' => 'L2-S1-ASSESS',
        'name' => 'Health Psychology – Applied Assessment',
        'topic' => 'health psychology, health-related behaviour, behaviour change and treatment adherence',
        'shortanswers' => [
            [
                'question' => 'Define health psychology and name three psychosocial factors that can influence physical health.',
                'marks' => 2,
                'guide' => 'Model answer: Health psychology is the branch of psychology that studies how psychological, '
                    . 'behavioural and social (psychosocial) factors influence physical health, illness and recovery, and '
                    . 'applies this to promote health. Factors: stress, personality, quality of social relationships '
                    . '(accept social support, coping style, emotions).' . "\n"
                    . 'Award 1 mark for an accurate definition and 1 mark for three relevant factors.',
            ],
            [
                'question' => 'Explain why health psychology is particularly important in modern healthcare.',
                'marks' => 2,
                'guide' => 'Model answer: Many leading causes of death today, such as heart disease and cancer, are strongly '
                    . 'influenced by behaviour (smoking, diet, inactivity, alcohol). Understanding and changing these '
                    . 'behaviours can prevent illness and early death.' . "\n"
                    . 'Award 1 mark for linking major causes of death to behaviour and 1 mark for the preventive value.',
            ],
            [
                'question' => 'List four health-promoting behaviours and explain how one of them protects health.',
                'marks' => 2,
                'guide' => 'Model answer: Regular exercise, balanced nutrition, 7–8 hours of sleep, maintaining a healthy '
                    . 'weight, not smoking, limiting alcohol. Example explanation: exercise strengthens the heart and '
                    . 'helps control weight, blood pressure and blood glucose.' . "\n"
                    . 'Award 1 mark for four correct behaviours (0.5 for two or three) and 1 mark for an accurate explanation.',
            ],
            [
                'question' => 'Distinguish between a health-promoting behaviour and a health-risk behaviour, giving one example of each.',
                'marks' => 2,
                'guide' => 'Model answer: A health-promoting behaviour protects or improves health (e.g. regular exercise); '
                    . 'a health-risk behaviour increases the likelihood of illness, injury or early death (e.g. smoking).' . "\n"
                    . 'Award 1 mark for the distinction and 1 mark for one correct example of each.',
            ],
            [
                'question' => 'Describe two ways in which lifestyle choices can contribute to cardiovascular disease.',
                'marks' => 2,
                'guide' => 'Model answer (any two): smoking damages blood vessel walls and promotes atherosclerosis; diets '
                    . 'high in salt and saturated fat raise blood pressure and cholesterol; inactivity and excess weight '
                    . 'increase the risk of hypertension and type 2 diabetes; excessive alcohol raises blood pressure.' . "\n"
                    . 'Award 1 mark for each mechanism correctly linked to cardiovascular disease.',
            ],
            [
                'question' => 'A longitudinal study of nearly 7,000 adults recorded how many of seven good health practices '
                    . 'each person followed. Name two of these practices and state the main finding.',
                'marks' => 2,
                'guide' => 'Model answer: Practices: sleeping 7–8 hours, eating breakfast, not smoking, rarely snacking, '
                    . 'maintaining a healthy weight, regular exercise, little or no alcohol. Finding: people who followed '
                    . 'fewer of the practices were more likely to die over the following years than those who followed more.' . "\n"
                    . 'Award 1 mark for two correct practices and 1 mark for the finding.',
            ],
            [
                'question' => 'Why is recognising the need for change the essential first step in behaviour change?',
                'marks' => 2,
                'guide' => 'Model answer: If a person does not see a behaviour as a problem (e.g. does not believe smoking is '
                    . 'harmful), there is no motivation to change and no point discussing plans. Recognising the '
                    . 'consequences creates the reason to change and moves the person towards considering change.' . "\n"
                    . 'Award 1 mark for explaining that change does not start without it and 1 mark for linking it to '
                    . 'understanding consequences or motivation.',
            ],
            [
                'question' => 'Explain the roles of reliable information and professional guidance in behaviour change.',
                'marks' => 2,
                'guide' => 'Model answer: Reliable information helps a person who is weighing up change to judge whether there '
                    . 'is a problem and what treatments exist. Professional guidance helps the person build a practical, '
                    . 'realistic action plan once they have decided to change.' . "\n"
                    . 'Award 1 mark for each role correctly explained.',
            ],
            [
                'question' => 'Define relapse and explain how it should be interpreted by someone trying to change a behaviour.',
                'marks' => 2,
                'guide' => 'Model answer: Relapse is a return to the old behaviour after a period of change. It is common and '
                    . 'should be seen as a temporary setback that shows the situation exceeded the person\'s coping skills '
                    . 'at that moment; they should identify the trigger, prepare for it and resume the plan rather than give up.' . "\n"
                    . 'Award 1 mark for the definition and 1 mark for the constructive interpretation.',
            ],
            [
                'question' => 'Give two reasons why patients may leave a consultation without understanding their treatment '
                    . 'instructions, and suggest one way to improve communication.',
                'marks' => 2,
                'guide' => 'Model answer: Reasons: short appointment times; patients being too passive to ask questions '
                    . 'because they see the doctor as an authority figure; jargon; anxiety. Improvement: plain language, '
                    . 'asking the patient to explain the plan back (teach-back), written instructions, inviting questions.' . "\n"
                    . 'Award 0.5 mark for each of two reasons and 1 mark for a suitable strategy.',
            ],
        ],
        'scenarios' => [
            [
                'question' => '<p><strong>Scenario: an unhealthy lifestyle.</strong> Ravi is a 45-year-old office worker. He '
                    . 'sits for most of the day, eats fast food most evenings, smokes 10 cigarettes a day and sleeps about '
                    . 'five hours a night. At a routine check-up he is told that his blood pressure is high.</p><ol type="a">'
                    . '<li>Identify Ravi\'s health-risk behaviours and explain how they increase his cardiovascular risk. (2 marks)</li>'
                    . '<li>Outline how Ravi could move through the steps of behaviour change, from recognising the need for '
                    . 'change to maintaining it. (3 marks)</li>'
                    . '<li>Explain how social support could help Ravi. (1 mark)</li></ol>',
                'marks' => 6,
                'guide' => '(a) Inactivity, poor diet, smoking and short sleep; they raise blood pressure and cholesterol, '
                    . 'promote atherosclerosis and weight gain. 1 mark for identifying at least three behaviours, 1 mark '
                    . 'for linking them to cardiovascular risk.' . "\n"
                    . '(b) Recognise the problem (raised blood pressure as a warning); obtain reliable information about '
                    . 'risks and treatment; make a practical plan with a health professional (e.g. smoking cessation support, '
                    . 'walking, diet changes, sleep routine); act with support; maintain for at least six months and plan '
                    . 'for relapse. 1 mark per well-applied stage, up to 3.' . "\n"
                    . '(c) Family, friends or colleagues encourage him, join activities, reduce temptations and help him stay '
                    . 'committed during the demanding action stage. 1 mark.',
            ],
            [
                'question' => '<p><strong>Scenario: examination stress and relapse.</strong> Meera, a medical student, started '
                    . 'a regular exercise programme six weeks ago. During a stressful examination week she stopped '
                    . 'exercising completely and began snacking late at night. She now says, "I have failed, so there is '
                    . 'no point continuing."</p><ol type="a">'
                    . '<li>Identify what Meera is experiencing and explain why her interpretation is unhelpful. (3 marks)</li>'
                    . '<li>Suggest three practical strategies to help her resume her plan and prevent future relapse. (3 marks)</li></ol>',
                'marks' => 6,
                'guide' => '(a) A relapse: a temporary return to old behaviours, which is common in behaviour change. Her '
                    . 'all-or-nothing view turns a lapse into a reason to give up; instead it signals that the examination '
                    . 'stress exceeded her coping skills at that time. 1 mark for identifying relapse, 2 marks for the '
                    . 'explanation.' . "\n"
                    . '(b) Any three: restart as soon as possible; identify the trigger (exam stress) and plan for it (e.g. '
                    . 'shorter workouts during exams); use social support (an exercise partner); set realistic goals; '
                    . 'prepare healthy snacks; use stress management. 1 mark each.',
            ],
            [
                'question' => '<p><strong>Scenario: a patient misunderstanding medical instructions.</strong> Mr Thomas, aged '
                    . '68, is prescribed a daily tablet for high blood pressure during a short appointment. He feels '
                    . 'unable to ask questions because "the doctor is very busy and knows best". At home he takes the tablet '
                    . 'only on days when he feels dizzy.</p><ol type="a">'
                    . '<li>Identify the adherence problem. (2 marks)</li>'
                    . '<li>Explain two factors that contributed to it. (2 marks)</li>'
                    . '<li>Suggest two communication strategies that could prevent this situation. (2 marks)</li></ol>',
                'marks' => 6,
                'guide' => '(a) Non-adherence: he takes the medicine irregularly instead of daily as prescribed, so his '
                    . 'blood pressure is not controlled. 2 marks for identifying non-adherence and its form.' . "\n"
                    . '(b) Short appointment time; patient passivity because he sees the doctor as an authority figure; '
                    . 'not fully understanding why the medicine is taken daily. 1 mark each, up to 2.' . "\n"
                    . '(c) Plain-language explanation of purpose and dose; teach-back (asking him to explain the plan); '
                    . 'written instructions; inviting and encouraging questions; follow-up check. 1 mark each, up to 2.',
            ],
            [
                'question' => '<p><strong>Scenario: delayed help-seeking.</strong> Anil, aged 52, has had chest discomfort when '
                    . 'climbing stairs for three months. He has not seen a doctor because he "does not want to make a fuss" '
                    . 'and is worried about what he might be told.</p><ol type="a">'
                    . '<li>Explain how people differ in help-seeking behaviour, and identify the factors delaying Anil. (3 marks)</li>'
                    . '<li>Describe how healthcare professionals and services could encourage earlier help-seeking and '
                    . 'build trust. (3 marks)</li></ol>',
                'marks' => 6,
                'guide' => '(a) Some people tolerate symptoms for a long time while others seek help immediately. Anil\'s '
                    . 'delay reflects minimising symptoms ("not making a fuss") and fear of the diagnosis; delay risks '
                    . 'progression of a possibly serious heart condition. 1 mark for variation, 2 marks for factors and risk.' . "\n"
                    . '(b) Public education about warning signs; accessible, non-judgemental services; clear, '
                    . 'reassuring communication; taking concerns seriously; involving family; continuity of care to build '
                    . 'trust. 1 mark each, up to 3.',
            ],
            [
                'question' => '<p><strong>Scenario: treatment adherence and trust.</strong> Sunita has type 2 diabetes and has '
                    . 'started a new medicine that gives her stomach upset. She has just moved to a new clinic, does not yet '
                    . 'trust her doctor and has secretly halved her dose.</p><ol type="a">'
                    . '<li>Identify three factors that are reducing Sunita\'s adherence. (3 marks)</li>'
                    . '<li>Recommend three strategies the healthcare team could use to improve her adherence. (3 marks)</li></ol>',
                'marks' => 6,
                'guide' => '(a) The treatment is aversive (side effects); low trust in a new doctor; possibly incomplete '
                    . 'understanding of why the full dose matters; changing the dose without discussion. 1 mark each, up to 3.' . "\n"
                    . '(b) Build trust through respectful, unhurried communication; explain the purpose and importance of '
                    . 'the full dose; review side effects and adjust or change the medicine; simplify the regimen; invite '
                    . 'her to raise concerns; arrange follow-up. 1 mark each, up to 3.',
            ],
        ],
    ],

    // Quiz: 10 multiple choice, 3 true/false, 2 matching (15 marks).
    'quiz' => [
        'idnumber' => 'L2-S1-QUIZ',
        'name' => 'Health Psychology – Knowledge Assessment',
        'multichoice' => [
            ['HP-MC01 Definition of health psychology', 'Which statement best defines health psychology?',
                ['The study of how psychological, behavioural and social factors influence physical health and illness',
                 'The treatment of severe mental illness with medication',
                 'The study of the anatomy and structure of the brain',
                 'The diagnosis and treatment of infectious diseases'],
                'Health psychology focuses on how psychosocial factors affect physical health, illness and recovery.'],
            ['HP-MC02 Psychosocial factors', 'Which of the following is a psychosocial factor that can influence physical health?',
                ['The quality of a person\'s relationships with friends and family', 'Blood group',
                 'Exposure to a virus', 'Bone density'],
                'Relationships are a social factor. Blood group, viral exposure and bone density are biological factors.'],
            ['HP-MC03 Behaviour and leading causes of death', 'Which two leading causes of death are strongly influenced by everyday behaviour?',
                ['Heart disease and cancer', 'Influenza and measles', 'Genetic disorders and birth defects',
                 'Accidental falls and drowning'],
                'Heart disease and cancer are strongly linked to modifiable behaviours such as smoking, diet and inactivity.'],
            ['HP-MC04 Health-promoting behaviour', 'Which of these is a health-promoting behaviour?',
                ['Sleeping 7–8 hours a night', 'Snacking frequently between meals', 'Smoking a few cigarettes a day to relax',
                 'Drinking heavily only at weekends'],
                'Adequate sleep supports immune, metabolic and mental health. The other options are health-risk behaviours.'],
            ['HP-MC05 Health practices and mortality', 'A longitudinal study recorded how many of seven good health practices '
                . 'adults followed. What did it find?',
                ['People who followed fewer practices were more likely to die over the following years',
                 'The number of practices followed made no difference to mortality',
                 'Only not smoking was linked to mortality',
                 'People who followed more practices lived longer only if they were under 30'],
                'Following fewer good health practices was associated with a higher risk of death, showing how behaviour '
                    . 'affects life expectancy.'],
            ['HP-MC06 Recognising the need for change', 'A patient says, "My smoking does not affect my health." Which step of '
                . 'behaviour change must happen before planning can begin?',
                ['Recognising that the behaviour is a problem and change is needed', 'Seeking social support',
                 'Maintaining the new behaviour for six months', 'Managing relapse'],
                'Without recognising that a behaviour is a problem, there is no reason to plan a change.'],
            ['HP-MC07 Help while weighing up change', 'Someone is unsure whether their drinking is a problem. What is the most '
                . 'useful help at this point?',
                ['Reliable information about the problem and the available treatments', 'A strict plan written for them immediately',
                 'Being told they have already failed', 'Avoiding the topic until they raise it again'],
                'When a person is weighing up change, accurate information helps them judge whether a problem exists and what can be done.'],
            ['HP-MC08 Most demanding step', 'Which step of behaviour change usually requires the most energy and the strongest '
                . 'social support?',
                ['Putting the action plan into practice', 'Recognising that a problem exists', 'Collecting information',
                 'Reflecting on the behaviour after it has become a habit'],
                'Carrying out the plan is the most challenging step; social support helps people stay committed.'],
            ['HP-MC09 Maintenance', 'After roughly how long does a new health behaviour usually start to feel natural and much less effortful?',
                ['About six months', 'About one week', 'About two days', 'It never becomes easier'],
                'Change gets easier over time, and after about six months the behaviour tends to feel natural.'],
            ['HP-MC10 Improving adherence', 'Which situation makes a patient MOST likely to follow their treatment?',
                ['They fully understand the treatment and trust their healthcare professional',
                 'The appointment is very short and there is no time for questions',
                 'The treatment is unpleasant and causes discomfort',
                 'They see the doctor as an authority figure and avoid asking questions'],
                'Adherence improves with understanding, an acceptable treatment and trust in the professional.'],
        ],
        'truefalse' => [
            ['HP-TF01 Relapse', 'A relapse shows that a person is incapable of changing their behaviour.', false,
                'False. Relapse is a common, expected part of behaviour change and a signal to prepare better, not a reason to give up.'],
            ['HP-TF02 Extent of non-adherence', 'Some estimates suggest that as many as half of patients do not follow their '
                . 'treatment exactly as recommended.', true,
                'True. Patients may not start treatment, stop early, change the dose or take it irregularly.'],
            ['HP-TF03 Social support', 'Social support is useful only before a behaviour change plan begins, not while it is being carried out.', false,
                'False. Social support is most important while the plan is being carried out, which is the most demanding step.'],
        ],
        'matching' => [
            ['HP-MA01 Steps of behaviour change', 'Match each step of behaviour change with its description.', [
                'Recognising the need for change' => 'Accepting that a behaviour is causing, or will cause, a problem',
                'Obtaining reliable information' => 'Learning about the risks and the available treatment options',
                'Planning' => 'Developing a practical action plan, ideally with professional guidance',
                'Maintenance' => 'Sustaining the new behaviour for at least six months until it becomes habitual',
            ], 'Behaviour change moves from recognising the problem, through information and planning, to action and maintenance.'],
            ['HP-MA02 Health behaviour concepts', 'Match each concept with the example that best illustrates it.', [
                'Health-promoting behaviour' => 'Walking briskly for 30 minutes on most days',
                'Health-risk behaviour' => 'Smoking a packet of cigarettes a day',
                'Non-adherence' => 'Stopping a course of medicine early because the symptoms have improved',
                'Delayed help-seeking' => 'Ignoring chest pain for weeks before seeing a doctor',
            ], 'Health psychology covers healthy and unhealthy behaviours, adherence to treatment and decisions about seeking help.'],
        ],
    ],
];
