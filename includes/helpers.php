<?php

function getLessonCatalog(): array
{
    static $lessons = null;

    if ($lessons !== null) {
        return $lessons;
    }

    $lessons = [
        [
            'id' => 1,
            'slug' => 'box-breathing-reset',
            'title' => 'Box Breathing Reset',
            'topic' => 'breath work',
            'duration_minutes' => 3,
            'format' => 'exercise',
            'short_description' => 'Use a steady 4-4-4-4 breathing rhythm to settle nerves before practice or competition.',
            'why_this_works' => 'Slow, paced breathing can lower stress arousal, improve emotional control, and make attention shifts easier.',
            'when_to_use' => 'In the locker room, before warm-up, or when intensity spikes.',
            'try_now_steps' => [
                'Inhale through your nose for 4 seconds.',
                'Hold for 4 seconds.',
                'Exhale slowly for 4 seconds.',
                'Hold for 4 seconds, then repeat for 4 rounds.',
            ],
            'external_video_url' => 'https://www.youtube.com/results?search_query=box+breathing+for+athletes',
            'evidence_note' => 'Paced breathing supports arousal regulation and attention reset.',
            'is_featured' => 1,
            'sort_order' => 10,
        ],
        [
            'id' => 2,
            'slug' => 'physiological-sigh-reset',
            'title' => 'Physiological Sigh Reset',
            'topic' => 'breath work',
            'duration_minutes' => 2,
            'format' => 'exercise',
            'short_description' => 'Use two quick inhales and one long exhale to downshift stress fast.',
            'why_this_works' => 'A longer exhale helps shift the body out of high-alert mode and reduces emotional overload quickly.',
            'when_to_use' => 'After a mistake, whistle break, or before re-entry.',
            'try_now_steps' => [
                'Take a full inhale through your nose.',
                'Add a second short inhale on top.',
                'Exhale slowly through your mouth until empty.',
                'Repeat for 3 to 5 cycles.',
            ],
            'external_video_url' => 'https://www.youtube.com/results?search_query=physiological+sigh+breathing',
            'evidence_note' => 'Double inhale plus long exhale can reduce acute stress and rumination.',
            'is_featured' => 1,
            'sort_order' => 20,
        ],
        [
            'id' => 3,
            'slug' => '60-second-body-scan',
            'title' => '60-Second Body Scan',
            'topic' => 'mindfulness',
            'duration_minutes' => 1,
            'format' => 'script',
            'short_description' => 'Quickly check jaw, shoulders, hands, and breath to notice tension before it hijacks focus.',
            'why_this_works' => 'Body awareness increases early detection of tension and supports intentional self-regulation.',
            'when_to_use' => 'Before a rep, at halftime, or between sets.',
            'try_now_steps' => [
                'Notice your jaw, shoulders, and hands without judging.',
                'Name one tension spot silently.',
                'Soften that area on one long exhale.',
                'Set one simple cue: loose jaw, low shoulders, steady breath.',
            ],
            'external_video_url' => null,
            'evidence_note' => 'Body scans improve awareness and emotional stability under pressure.',
            'is_featured' => 1,
            'sort_order' => 30,
        ],
        [
            'id' => 4,
            'slug' => 'reset-after-a-mistake',
            'title' => 'Reset After a Mistake',
            'topic' => 'reset routines',
            'duration_minutes' => 2,
            'format' => 'session',
            'short_description' => 'Use a three-part reset routine to interrupt frustration and return to the next play.',
            'why_this_works' => 'A repeatable post-error routine reduces rumination and improves emotional recovery speed.',
            'when_to_use' => 'Immediately after a turnover, miss, or tactical error.',
            'try_now_steps' => [
                'Exhale once and drop your shoulders.',
                'Say your cue word: next, reset, or present.',
                'Lock eyes on one external target for 2 seconds.',
                'Choose your next controllable action and execute it.',
            ],
            'external_video_url' => null,
            'evidence_note' => 'Post-error routines support resilience and reduce attention drift.',
            'is_featured' => 1,
            'sort_order' => 40,
        ],
        [
            'id' => 5,
            'slug' => 'pre-performance-grounding',
            'title' => 'Pre-Performance Grounding',
            'topic' => 'emotional regulation',
            'duration_minutes' => 4,
            'format' => 'session',
            'short_description' => 'Ground your body and attention before performance with a short sensory anchor routine.',
            'why_this_works' => 'Grounding shifts attention from worry loops toward present-moment cues and body control.',
            'when_to_use' => 'Pre-game, pre-heat, or before stepping into a high-pressure drill.',
            'try_now_steps' => [
                'Feel your feet press into the ground.',
                'Take 3 slow breaths and count each exhale.',
                'Name one sound, one visual cue, and one body cue.',
                'Repeat your intention: calm body, clear focus, next action.',
            ],
            'external_video_url' => 'https://www.youtube.com/results?search_query=grounding+exercise+athletes',
            'evidence_note' => 'Grounding can reduce stress reactivity and improve attentional control.',
            'is_featured' => 0,
            'sort_order' => 50,
        ],
        [
            'id' => 6,
            'slug' => 'visualization-for-the-next-rep',
            'title' => 'Visualization for the Next Rep',
            'topic' => 'visualization / imagery',
            'duration_minutes' => 3,
            'format' => 'exercise',
            'short_description' => 'Rehearse one upcoming action with clear sensory detail and a calm execution pace.',
            'why_this_works' => 'Short imagery can strengthen attention cues and composure when returning to action.',
            'when_to_use' => 'Before a set play, attempt, or restart.',
            'try_now_steps' => [
                'Pick one specific action you will do next.',
                'Close your eyes and imagine the first 3 seconds clearly.',
                'Include one body cue and one breathing cue.',
                'Open your eyes and execute with that same tempo.',
            ],
            'external_video_url' => 'https://www.youtube.com/results?search_query=sport+imagery+mental+rehearsal',
            'evidence_note' => 'Imagery can support focus, confidence, and response consistency.',
            'is_featured' => 0,
            'sort_order' => 60,
        ],
        [
            'id' => 7,
            'slug' => 're-center-after-frustration',
            'title' => 'Re-center After Frustration',
            'topic' => 'emotional regulation',
            'duration_minutes' => 3,
            'format' => 'script',
            'short_description' => 'Use label, release, refocus to move through frustration without carrying it to the next rep.',
            'why_this_works' => 'Emotion labeling and controlled breathing can reduce the intensity of unhelpful emotional spirals.',
            'when_to_use' => 'When irritation, anger, or self-criticism starts to rise.',
            'try_now_steps' => [
                'Label the state: frustrated, tight, or rushed.',
                'Take one long exhale and unclench your hands.',
                'Refocus on one external cue like ball, breath, or target.',
                'Start the next action within 5 seconds.',
            ],
            'external_video_url' => null,
            'evidence_note' => 'Naming emotions helps reduce reactivity and supports self-regulation.',
            'is_featured' => 0,
            'sort_order' => 70,
        ],
        [
            'id' => 8,
            'slug' => 'post-practice-reflection',
            'title' => 'Post-Practice Reflection',
            'topic' => 'recovery / reflection',
            'duration_minutes' => 5,
            'format' => 'session',
            'short_description' => 'Close the day with a short nonjudgmental reflection that separates learning from self-criticism.',
            'why_this_works' => 'Brief reflection lowers rumination, reinforces learning, and supports emotional recovery.',
            'when_to_use' => 'Within 30 minutes after training or competition.',
            'try_now_steps' => [
                'Write one thing that went well.',
                'Write one moment you want to improve.',
                'Write one adjustment for the next session.',
                'End with one neutral statement: still learning, still building.',
            ],
            'external_video_url' => null,
            'evidence_note' => 'Structured reflection supports learning while protecting emotional balance.',
            'is_featured' => 1,
            'sort_order' => 80,
        ],
        [
            'id' => 9,
            'slug' => 'confidence-cue-routine',
            'title' => 'Confidence Cue Routine',
            'topic' => 'confidence / composure',
            'duration_minutes' => 3,
            'format' => 'script',
            'short_description' => 'Build a short confidence cue routine tied to process, not hype.',
            'why_this_works' => 'Process-based cues stabilize confidence and reduce outcome-driven pressure swings.',
            'when_to_use' => 'Before pressure moments or when self-doubt appears.',
            'try_now_steps' => [
                'Pick one cue phrase: tall and smooth, calm and direct, or own this rep.',
                'Pair the cue with one deep breath and posture check.',
                'Repeat cue twice at your normal speaking pace.',
                'Shift to one immediate action target.',
            ],
            'external_video_url' => null,
            'evidence_note' => 'Consistent cue routines can improve composure and attentional control.',
            'is_featured' => 0,
            'sort_order' => 90,
        ],
        [
            'id' => 10,
            'slug' => 'narrow-the-focus',
            'title' => 'Narrow the Focus',
            'topic' => 'attention/focus',
            'duration_minutes' => 2,
            'format' => 'exercise',
            'short_description' => 'Use a one-cue focus drill to block noise and commit attention to the present task.',
            'why_this_works' => 'Narrowing to one controllable cue can reduce distraction and improve execution quality.',
            'when_to_use' => 'When attention feels scattered or the environment feels loud.',
            'try_now_steps' => [
                'Choose one cue: breath, target, or body position.',
                'Say the cue silently before the action.',
                'If distracted, return to the same cue without judgment.',
                'Rate your focus from 1 to 5 after one rep.',
            ],
            'external_video_url' => 'https://www.youtube.com/results?search_query=attention+control+training+athletes',
            'evidence_note' => 'Single-cue attention routines can reduce cognitive overload.',
            'is_featured' => 0,
            'sort_order' => 100,
        ],
        [
            'id' => 11,
            'slug' => 'if-then-trigger-plan',
            'title' => 'If-Then Trigger Plan',
            'topic' => 'habit design',
            'duration_minutes' => 3,
            'format' => 'exercise',
            'short_description' => 'Convert a goal into a concrete trigger-action plan so follow-through is easier under pressure.',
            'why_this_works' => 'Implementation intentions improve follow-through by linking behavior to a clear cue.',
            'when_to_use' => 'When you know what to do but keep missing the moment to do it.',
            'try_now_steps' => [
                'Pick one specific cue: time, place, or event.',
                'Write one line: If [cue], then I will [first action].',
                'Shrink the action to a 2-minute version.',
                'Run the first rep now to lock in the cue.',
            ],
            'external_video_url' => null,
            'evidence_note' => 'If-then planning is linked to stronger execution consistency.',
            'is_featured' => 1,
            'sort_order' => 110,
        ],
        [
            'id' => 12,
            'slug' => 'minimum-viable-rep',
            'title' => 'Minimum Viable Rep',
            'topic' => 'consistency',
            'duration_minutes' => 2,
            'format' => 'exercise',
            'short_description' => 'Restart momentum with the smallest completed version of your goal today.',
            'why_this_works' => 'Small, completed reps lower resistance and rebuild behavioral momentum.',
            'when_to_use' => 'When a goal feels heavy, stalled, or easy to avoid.',
            'try_now_steps' => [
                'Define the smallest meaningful rep you can finish today.',
                'Set a 2-minute timer and begin immediately.',
                'Mark it complete before scaling anything up.',
                'Choose tomorrow\'s first tiny rep now.',
            ],
            'external_video_url' => null,
            'evidence_note' => 'Tiny action strategies support habit restart and adherence.',
            'is_featured' => 1,
            'sort_order' => 120,
        ],
        [
            'id' => 13,
            'slug' => 'friction-audit-adjustment',
            'title' => 'Friction Audit and Adjustment',
            'topic' => 'habit troubleshooting',
            'duration_minutes' => 5,
            'format' => 'session',
            'short_description' => 'Spot the obstacle blocking your goal and adjust environment, timing, or scope.',
            'why_this_works' => 'Behavior improves when you reduce friction and pre-decide the next setup.',
            'when_to_use' => 'After missed check-ins, skipped reps, or repeated stop-start cycles.',
            'try_now_steps' => [
                'Name the blocker from your last missed rep.',
                'Pick one change: reduce scope, move timing, or prep materials.',
                'Write your next start cue in one sentence.',
                'Schedule the next attempt and protect that slot.',
            ],
            'external_video_url' => null,
            'evidence_note' => 'Reducing environmental and cognitive friction improves consistency.',
            'is_featured' => 0,
            'sort_order' => 130,
        ],
        [
            'id' => 14,
            'slug' => 'weekly-review-reset',
            'title' => 'Weekly Review Reset',
            'topic' => 'reflection/replanning',
            'duration_minutes' => 5,
            'format' => 'session',
            'short_description' => 'Use a short review to convert this week\'s outcomes into one practical adjustment.',
            'why_this_works' => 'Brief structured reviews improve learning transfer and planning quality.',
            'when_to_use' => 'At the end of your week or cadence window before setting the next rep.',
            'try_now_steps' => [
                'List one win and one miss from this period.',
                'Identify the main reason the miss happened.',
                'Choose one concrete adjustment for next period.',
                'Set your first check-in cue for the next window.',
            ],
            'external_video_url' => null,
            'evidence_note' => 'Regular reflection loops support adaptation and sustained progress.',
            'is_featured' => 0,
            'sort_order' => 140,
        ],
    ];

    usort($lessons, static function (array $a, array $b): int {
        return (int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0);
    });

    return $lessons;
}

function getLessonBySlug(string $slug): ?array
{
    foreach (getLessonCatalog() as $lesson) {
        if (($lesson['slug'] ?? '') === $slug) {
            return $lesson;
        }
    }

    return null;
}

function isValidLessonSlug(string $slug): bool
{
    return getLessonBySlug($slug) !== null;
}

function getLessonTopics(): array
{
    $topics = [];

    foreach (getLessonCatalog() as $lesson) {
        $topic = (string) ($lesson['topic'] ?? '');
        if ($topic !== '') {
            $topics[$topic] = $topic;
        }
    }

    $topicList = array_values($topics);
    sort($topicList);

    return $topicList;
}

function getLessonDurationOptions(): array
{
    $durations = [];

    foreach (getLessonCatalog() as $lesson) {
        $minutes = (int) ($lesson['duration_minutes'] ?? 0);
        if ($minutes > 0) {
            $durations[$minutes] = $minutes;
        }
    }

    $durationList = array_values($durations);
    sort($durationList);

    return $durationList;
}
