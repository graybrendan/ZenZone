<?php

function detectCoachCrisisLanguage(string $text): array
{
    $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', $text)));

    if ($normalized === '') {
        return [
            'crisis_detected' => false,
            'crisis_message' => null,
        ];
    }

    $patterns = [
        '/\bsuicid(?:e|al)\b/u',
        '/\bkill myself\b/u',
        '/\bend my life\b/u',
        '/\bself[- ]?harm\b/u',
        '/\bhurt myself\b/u',
        '/\bwant to die\b/u',
        '/\b(?:don\'t|dont|do not) want to be here\b/u',
        '/\bwant to disappear\b/u',
        '/\bcan\'t go on\b/u',
        '/\bcant go on\b/u',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $normalized) === 1) {
            return [
                'crisis_detected' => true,
                'crisis_message' => "I'm really glad you shared this. This needs immediate human support right now. If you might hurt yourself or are in immediate danger, call or text 988 now (US) or call 911, and reach out to a trusted person immediately.",
            ];
        }
    }

    return [
        'crisis_detected' => false,
        'crisis_message' => null,
    ];
}

function rankCoachRecommendations(array $input, array $lessonCatalog): array
{
    $lessonLookup = getCoachLessonLookup($lessonCatalog);
    if (empty($lessonLookup)) {
        return [];
    }

    $scores = array_fill_keys(array_keys($lessonLookup), 0);

    $situationType = (string) ($input['situation_type'] ?? 'other');
    $baseMap = getCoachSituationTypeRecommendationMap();
    $preferredSlugs = $baseMap[$situationType] ?? $baseMap['other'];
    $resolvedBaseSlugs = resolveCoachSlugList($preferredSlugs, $lessonLookup);

    foreach ($resolvedBaseSlugs as $index => $slug) {
        if (!isset($scores[$slug])) {
            continue;
        }

        $scores[$slug] += 360 - ($index * 40);
    }

    $combinedText = strtolower(
        trim(
            (string) ($input['situation_text'] ?? '') . ' ' . (string) ($input['upcoming_event'] ?? '')
        )
    );

    applyCoachKeywordWeighting($scores, $combinedText, $lessonLookup);
    applyCoachTimeWeighting($scores, $lessonLookup, (int) ($input['time_available'] ?? 3));
    applyCoachStressWeighting($scores, $lessonLookup, (int) ($input['stress_level'] ?? 3));
    applyCoachUpcomingEventWeighting($scores, $lessonLookup, (string) ($input['upcoming_event'] ?? ''));
    applyCoachGoalContextWeighting($scores, $lessonLookup, $input);

    $sortedSlugs = array_keys($scores);
    usort($sortedSlugs, static function (string $left, string $right) use ($scores, $lessonLookup): int {
        $scoreDiff = ((int) ($scores[$right] ?? 0)) <=> ((int) ($scores[$left] ?? 0));
        if ($scoreDiff !== 0) {
            return $scoreDiff;
        }

        $leftDuration = (int) ($lessonLookup[$left]['duration_minutes'] ?? 0);
        $rightDuration = (int) ($lessonLookup[$right]['duration_minutes'] ?? 0);
        $durationDiff = $leftDuration <=> $rightDuration;
        if ($durationDiff !== 0) {
            return $durationDiff;
        }

        return ((int) ($lessonLookup[$left]['sort_order'] ?? 0)) <=> ((int) ($lessonLookup[$right]['sort_order'] ?? 0));
    });

    $ranked = [];
    foreach ($sortedSlugs as $slug) {
        if (!isset($lessonLookup[$slug])) {
            continue;
        }

        $ranked[] = [
            'slug' => $slug,
            'score' => (int) ($scores[$slug] ?? 0),
            'lesson' => $lessonLookup[$slug],
        ];

        if (count($ranked) >= 3) {
            break;
        }
    }

    if (count($ranked) < 3) {
        foreach ($resolvedBaseSlugs as $slug) {
            if (!isset($lessonLookup[$slug])) {
                continue;
            }

            $alreadySelected = false;
            foreach ($ranked as $item) {
                if (($item['slug'] ?? '') === $slug) {
                    $alreadySelected = true;
                    break;
                }
            }
            if ($alreadySelected) {
                continue;
            }

            $ranked[] = [
                'slug' => $slug,
                'score' => (int) ($scores[$slug] ?? 0),
                'lesson' => $lessonLookup[$slug],
            ];

            if (count($ranked) >= 3) {
                break;
            }
        }
    }

    if (count($ranked) < 3) {
        $remainingLessons = array_values($lessonLookup);
        usort($remainingLessons, static function (array $a, array $b): int {
            return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
        });

        foreach ($remainingLessons as $lesson) {
            $slug = (string) ($lesson['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $alreadySelected = false;
            foreach ($ranked as $item) {
                if (($item['slug'] ?? '') === $slug) {
                    $alreadySelected = true;
                    break;
                }
            }
            if ($alreadySelected) {
                continue;
            }

            $ranked[] = [
                'slug' => $slug,
                'score' => (int) ($scores[$slug] ?? 0),
                'lesson' => $lesson,
            ];

            if (count($ranked) >= 3) {
                break;
            }
        }
    }

    return array_slice($ranked, 0, 3);
}

function buildCoachRecommendationFromLesson(array $lesson, array $input = []): array
{
    $upcomingEvent = trim((string) ($input['upcoming_event'] ?? ''));

    $steps = [];
    if (!empty($lesson['try_now_steps']) && is_array($lesson['try_now_steps'])) {
        foreach ($lesson['try_now_steps'] as $step) {
            $stepText = sanitizeCoachNarrativeLine((string) $step, 140);
            if ($stepText !== '') {
                $steps[] = $stepText;
            }
        }
    }

    if (empty($steps)) {
        $steps = [
            'Settle your breathing for one cycle.',
            'Pick one focus cue for the next action.',
            'Execute the next action at controlled pace.',
        ];
    }

    $whenToUse = sanitizeCoachNarrativeLine((string) ($lesson['when_to_use'] ?? ''), 220);
    if ($upcomingEvent !== '') {
        $whenToUse = sanitizeCoachNarrativeLine('Use this before ' . $upcomingEvent . '. ' . $whenToUse, 220);
    }

    return [
        'slug' => trim((string) ($lesson['slug'] ?? '')),
        'title' => sanitizeCoachNarrativeLine((string) ($lesson['title'] ?? ''), 90),
        'why_this_works' => sanitizeCoachNarrativeLine((string) ($lesson['why_this_works'] ?? ''), 220),
        'when_to_use' => $whenToUse,
        'steps' => array_slice($steps, 0, 5),
        'duration_minutes' => max(1, (int) ($lesson['duration_minutes'] ?? 1)),
        'evidence_note' => sanitizeCoachNarrativeLine((string) ($lesson['evidence_note'] ?? ''), 180),
    ];
}

function buildCoachNarrative(array $response): array
{
    if (!empty($response['crisis_detected'])) {
        $response['summary'] = sanitizeCoachNarrativeLine(
            (string) ($response['summary'] ?? 'This sounds like a high-distress moment that needs immediate human support.'),
            220
        );
        $response['coach_message'] = sanitizeCoachNarrativeLine(
            (string) ($response['coach_message'] ?? 'Pause performance or training work and contact emergency support or a trusted person now.'),
            220
        );

        return $response;
    }

    $input = is_array($response['input_context'] ?? null) ? $response['input_context'] : [];
    $top = is_array($response['top_recommendation'] ?? null) ? $response['top_recommendation'] : null;

    if ($top === null) {
        $response['summary'] = sanitizeCoachNarrativeLine(
            (string) ($response['summary'] ?? 'You shared a pressure moment. Best move is one short reset to settle your body and narrow attention.'),
            220
        );
        $response['coach_message'] = sanitizeCoachNarrativeLine(
            (string) ($response['coach_message'] ?? 'Take one reset action now, then log Better, Same, or Worse and move to the next action.'),
            220
        );

        return $response;
    }

    $situationType = (string) ($input['situation_type'] ?? 'other');
    $timeAvailable = (int) ($input['time_available'] ?? ($top['duration_minutes'] ?? 3));
    $upcomingEvent = trim((string) ($input['upcoming_event'] ?? ''));
    $title = (string) ($top['title'] ?? 'this reset tool');
    $duration = max(1, (int) ($top['duration_minutes'] ?? $timeAvailable));

    $situationLeadMap = [
        'pre-performance nerves' => 'You sound keyed up before a performance moment',
        'after mistake' => 'You are still carrying the last mistake',
        'low focus' => 'Your attention sounds scattered right now',
        'frustration / anger' => 'You sound frustrated and heated',
        'confidence dip' => 'Your confidence looks shaky in this moment',
        'post-practice reset' => 'You are in post-session decompression mode',
        'other' => 'You are in a pressure moment',
    ];

    $timePhrase = 'with enough time for a short reset';
    if ($timeAvailable <= 1) {
        $timePhrase = 'with about one minute available';
    } elseif ($timeAvailable <= 3) {
        $timePhrase = 'and short on time';
    } elseif ($timeAvailable >= 10) {
        $timePhrase = 'with enough time for a full reset';
    }

    $lead = $situationLeadMap[$situationType] ?? $situationLeadMap['other'];
    $benefit = getCoachBenefitPhraseForSlug((string) ($top['slug'] ?? ''));

    $summary = $lead . ' ' . $timePhrase . '. ';
    $summary .= 'The best move right now is ' . $title . ' to ' . $benefit . '.';

    if ($upcomingEvent !== '') {
        $summary .= ' Use it before ' . $upcomingEvent . ' so you can step into the next action with steadier focus.';
    }

    $coachMessage = 'Run ' . $title . ' now for about ' . $duration . ' minute(s). ';
    $coachMessage .= 'Follow the steps, then mark Better, Same, or Worse and take your next useful action.';

    $response['summary'] = sanitizeCoachNarrativeLine((string) ($response['summary'] ?? $summary), 260);
    if (trim((string) ($response['summary'] ?? '')) === '') {
        $response['summary'] = sanitizeCoachNarrativeLine($summary, 260);
    }

    $response['coach_message'] = sanitizeCoachNarrativeLine((string) ($response['coach_message'] ?? $coachMessage), 260);
    if (trim((string) ($response['coach_message'] ?? '')) === '') {
        $response['coach_message'] = sanitizeCoachNarrativeLine($coachMessage, 260);
    }

    return $response;
}

function getCoachSituationTypeRecommendationMap(): array
{
    return [
        'pre-performance nerves' => [
            'box-breathing-reset',
            'pre-performance-grounding',
            'confidence-cue-routine',
        ],
        'after mistake' => [
            'reset-after-a-mistake',
            'physiological-sigh-reset',
            'narrow-the-focus',
        ],
        'low focus' => [
            'narrow-the-focus',
            '60-second-body-scan',
            'box-breathing-reset',
        ],
        'frustration / anger' => [
            're-center-after-frustration',
            'physiological-sigh-reset',
            'post-practice-reflection',
        ],
        'confidence dip' => [
            'confidence-cue-routine',
            'visualization-for-the-next-rep',
            'pre-performance-grounding',
        ],
        'post-practice reset' => [
            'post-practice-reflection',
            '60-second-body-scan',
            'box-breathing-reset',
        ],
        'other' => [
            'box-breathing-reset',
            'narrow-the-focus',
            'post-practice-reflection',
            'minimum-viable-rep',
        ],
    ];
}

function getCoachLessonLookup(array $lessonCatalog): array
{
    $lookup = [];

    foreach ($lessonCatalog as $lesson) {
        $slug = trim((string) ($lesson['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $lookup[$slug] = $lesson;
    }

    return $lookup;
}

function applyCoachKeywordWeighting(array &$scores, string $text, array $lessonLookup): void
{
    $groups = [
        [
            'terms' => ['game', 'match', 'race', 'meet', 'kickoff', 'tipoff', 'start', 'pregame', 'event', 'presentation', 'audition', 'workout', 'session'],
            'boosts' => [
                'pre-performance-grounding' => 90,
                'box-breathing-reset' => 80,
                'confidence-cue-routine' => 70,
            ],
        ],
        [
            'terms' => ['mistake', 'turnover', 'drop', 'missed', 'messed up', 'bad rep', 'error'],
            'boosts' => [
                'reset-after-a-mistake' => 95,
                'narrow-the-focus' => 70,
            ],
        ],
        [
            'terms' => ['angry', 'pissed', 'frustrated', 'mad', 'heated'],
            'boosts' => [
                're-center-after-frustration' => 95,
                'physiological-sigh-reset' => 75,
            ],
        ],
        [
            'terms' => ['focus', 'locked in', 'distracted', 'spacing out'],
            'boosts' => [
                'narrow-the-focus' => 95,
                '60-second-body-scan' => 70,
            ],
        ],
        [
            'terms' => ['confidence', 'hesitating', 'self-doubt'],
            'boosts' => [
                'confidence-cue-routine' => 90,
                'visualization-for-the-next-rep' => 70,
            ],
        ],
        [
            'terms' => ['cool down', 'after practice', 'after training', 'after work', 'after session', 'postgame'],
            'boosts' => [
                'post-practice-reflection' => 90,
                '60-second-body-scan' => 70,
            ],
        ],
    ];

    foreach ($groups as $group) {
        if (!coachTextContainsAny($text, $group['terms'])) {
            continue;
        }

        applyCoachBoostMap($scores, $group['boosts'], $lessonLookup);
    }
}

function applyCoachTimeWeighting(array &$scores, array $lessonLookup, int $timeAvailable): void
{
    $timeAvailable = in_array($timeAvailable, [1, 3, 5, 10], true) ? $timeAvailable : 3;

    foreach ($lessonLookup as $slug => $lesson) {
        $duration = max(1, (int) ($lesson['duration_minutes'] ?? 1));
        $adjustment = 0;

        if ($timeAvailable === 1) {
            if ($duration <= 1) {
                $adjustment = 130;
            } elseif ($duration <= 2) {
                $adjustment = 80;
            } elseif ($duration <= 3) {
                $adjustment = 30;
            } else {
                $adjustment = -130;
            }
        } elseif ($timeAvailable === 3) {
            if ($duration <= 2) {
                $adjustment = 80;
            } elseif ($duration <= 3) {
                $adjustment = 55;
            } elseif ($duration <= 5) {
                $adjustment = 20;
            } else {
                $adjustment = -55;
            }
        } elseif ($timeAvailable === 5) {
            if ($duration <= 3) {
                $adjustment = 55;
            } elseif ($duration <= 5) {
                $adjustment = 45;
            } elseif ($duration <= 7) {
                $adjustment = 20;
            } else {
                $adjustment = -35;
            }
        } elseif ($timeAvailable === 10) {
            if ($duration <= 5) {
                $adjustment = 28;
            } elseif ($duration <= 10) {
                $adjustment = 22;
            } else {
                $adjustment = -10;
            }
        }

        $scores[$slug] += $adjustment;
    }

    if ($timeAvailable === 10) {
        applyCoachBoostMap($scores, [
            'post-practice-reflection' => 30,
            'visualization-for-the-next-rep' => 25,
        ], $lessonLookup);
    }
}

function applyCoachStressWeighting(array &$scores, array $lessonLookup, int $stressLevel): void
{
    if ($stressLevel >= 4) {
        applyCoachBoostMap($scores, [
            'physiological-sigh-reset' => 95,
            'box-breathing-reset' => 85,
            '60-second-body-scan' => 70,
            're-center-after-frustration' => 70,
        ], $lessonLookup);

        applyCoachBoostMap($scores, [
            'post-practice-reflection' => -35,
            'visualization-for-the-next-rep' => -20,
        ], $lessonLookup);
        return;
    }

    if ($stressLevel <= 2) {
        applyCoachBoostMap($scores, [
            'post-practice-reflection' => 40,
            'visualization-for-the-next-rep' => 35,
            'confidence-cue-routine' => 30,
            'narrow-the-focus' => 25,
        ], $lessonLookup);
    }
}

function applyCoachUpcomingEventWeighting(array &$scores, array $lessonLookup, string $upcomingEvent): void
{
    if (trim($upcomingEvent) === '') {
        return;
    }

    applyCoachBoostMap($scores, [
        'pre-performance-grounding' => 90,
        'confidence-cue-routine' => 80,
        'box-breathing-reset' => 60,
        'visualization-for-the-next-rep' => 55,
    ], $lessonLookup);
}

function applyCoachGoalContextWeighting(array &$scores, array $lessonLookup, array $input): void
{
    $goalTitle = strtolower(trim((string) ($input['goal_title'] ?? '')));
    $goalStatus = strtolower(trim((string) ($input['goal_status'] ?? '')));
    $goalCadenceUnit = strtolower(trim((string) ($input['goal_cadence_unit'] ?? '')));
    $goalCadenceNumber = max(1, (int) ($input['goal_cadence_number'] ?? 1));
    $goalCheckinsUsed = max(0, (int) ($input['goal_checkins_used'] ?? 0));
    $goalCheckinsTarget = max(1, (int) ($input['goal_checkins_target'] ?? 1));
    $goalCategoriesRaw = $input['goal_categories'] ?? [];

    $goalCategories = [];
    if (is_string($goalCategoriesRaw) && $goalCategoriesRaw !== '') {
        $goalCategoriesRaw = explode(',', $goalCategoriesRaw);
    }
    if (is_array($goalCategoriesRaw)) {
        foreach ($goalCategoriesRaw as $rawCategory) {
            $category = strtolower(trim((string) $rawCategory));
            if (!in_array($category, ['body', 'mind', 'soul'], true)) {
                continue;
            }
            if (in_array($category, $goalCategories, true)) {
                continue;
            }
            $goalCategories[] = $category;
        }
    }

    if (in_array('mind', $goalCategories, true)) {
        applyCoachBoostMap($scores, [
            'narrow-the-focus' => 85,
            'confidence-cue-routine' => 75,
            'visualization-for-the-next-rep' => 65,
            'if-then-trigger-plan' => 40,
        ], $lessonLookup);
    }

    if (in_array('body', $goalCategories, true)) {
        applyCoachBoostMap($scores, [
            '60-second-body-scan' => 85,
            'physiological-sigh-reset' => 75,
            'pre-performance-grounding' => 55,
            'minimum-viable-rep' => 45,
        ], $lessonLookup);
    }

    if (in_array('soul', $goalCategories, true)) {
        applyCoachBoostMap($scores, [
            'post-practice-reflection' => 85,
            'weekly-review-reset' => 75,
            'friction-audit-adjustment' => 45,
        ], $lessonLookup);
    }

    if ($goalStatus === 'paused') {
        applyCoachBoostMap($scores, [
            'minimum-viable-rep' => 95,
            'if-then-trigger-plan' => 80,
            'friction-audit-adjustment' => 70,
        ], $lessonLookup);
    }

    $remaining = max(0, $goalCheckinsTarget - $goalCheckinsUsed);
    if ($remaining > 0 && $goalCheckinsUsed === 0) {
        applyCoachBoostMap($scores, [
            'minimum-viable-rep' => 90,
            'if-then-trigger-plan' => 80,
        ], $lessonLookup);
    } elseif ($remaining === 0) {
        applyCoachBoostMap($scores, [
            'weekly-review-reset' => 70,
            'post-practice-reflection' => 55,
        ], $lessonLookup);
    }

    if ($goalCadenceUnit === 'day' && $goalCadenceNumber === 1) {
        applyCoachBoostMap($scores, [
            'if-then-trigger-plan' => 60,
            'minimum-viable-rep' => 55,
        ], $lessonLookup);
    } elseif ($goalCadenceUnit === 'week' || $goalCadenceUnit === 'month') {
        applyCoachBoostMap($scores, [
            'weekly-review-reset' => 65,
            'friction-audit-adjustment' => 60,
        ], $lessonLookup);
    }

    if ($goalTitle !== '') {
        applyCoachGoalTitleKeywordWeighting($scores, $lessonLookup, $goalTitle);
    }
}

function applyCoachGoalTitleKeywordWeighting(array &$scores, array $lessonLookup, string $goalTitle): void
{
    $groups = [
        [
            'terms' => ['sleep', 'recovery', 'rest', 'bedtime'],
            'boosts' => [
                '60-second-body-scan' => 75,
                'post-practice-reflection' => 60,
                'minimum-viable-rep' => 40,
            ],
        ],
        [
            'terms' => ['train', 'practice', 'drill', 'lift', 'run', 'workout', 'conditioning', 'stretch'],
            'boosts' => [
                'pre-performance-grounding' => 80,
                'minimum-viable-rep' => 70,
                'if-then-trigger-plan' => 60,
            ],
        ],
        [
            'terms' => ['journal', 'gratitude', 'reflect', 'mindset', 'purpose'],
            'boosts' => [
                'post-practice-reflection' => 85,
                'weekly-review-reset' => 70,
            ],
        ],
        [
            'terms' => ['focus', 'study', 'class', 'read', 'attention', 'concentration'],
            'boosts' => [
                'narrow-the-focus' => 90,
                'if-then-trigger-plan' => 65,
            ],
        ],
        [
            'terms' => ['confidence', 'calm', 'anxiety', 'nerves'],
            'boosts' => [
                'confidence-cue-routine' => 85,
                'box-breathing-reset' => 70,
                'physiological-sigh-reset' => 60,
            ],
        ],
        [
            'terms' => ['habit', 'consistency', 'daily', 'routine'],
            'boosts' => [
                'minimum-viable-rep' => 90,
                'if-then-trigger-plan' => 80,
                'friction-audit-adjustment' => 60,
            ],
        ],
    ];

    foreach ($groups as $group) {
        if (!coachTextContainsAny($goalTitle, $group['terms'])) {
            continue;
        }

        applyCoachBoostMap($scores, $group['boosts'], $lessonLookup);
    }
}

function applyCoachBoostMap(array &$scores, array $boostMap, array $lessonLookup): void
{
    foreach ($boostMap as $preferredSlug => $boost) {
        $resolvedSlug = resolveCoachLessonSlug((string) $preferredSlug, $lessonLookup);
        if ($resolvedSlug === null || !isset($scores[$resolvedSlug])) {
            continue;
        }

        $scores[$resolvedSlug] += (int) $boost;
    }
}

function resolveCoachSlugList(array $slugs, array $lessonLookup): array
{
    $resolved = [];

    foreach ($slugs as $slug) {
        $resolvedSlug = resolveCoachLessonSlug((string) $slug, $lessonLookup);
        if ($resolvedSlug === null || in_array($resolvedSlug, $resolved, true)) {
            continue;
        }

        $resolved[] = $resolvedSlug;
    }

    return $resolved;
}

function resolveCoachLessonSlug(string $slug, array $lessonLookup): ?string
{
    if ($slug === '') {
        return null;
    }

    if (isset($lessonLookup[$slug])) {
        return $slug;
    }

    $fallbackMap = getCoachSlugFallbackMap();
    $fallbackSlugs = $fallbackMap[$slug] ?? [];

    foreach ($fallbackSlugs as $fallbackSlug) {
        if (isset($lessonLookup[$fallbackSlug])) {
            return $fallbackSlug;
        }
    }

    return null;
}

function getCoachSlugFallbackMap(): array
{
    return [
        'box-breathing-reset' => ['physiological-sigh-reset', '60-second-body-scan', 'narrow-the-focus'],
        'pre-performance-grounding' => ['confidence-cue-routine', 'box-breathing-reset', 'visualization-for-the-next-rep'],
        'confidence-cue-routine' => ['pre-performance-grounding', 'visualization-for-the-next-rep', 'narrow-the-focus'],
        'reset-after-a-mistake' => ['physiological-sigh-reset', 'narrow-the-focus', 're-center-after-frustration'],
        'physiological-sigh-reset' => ['box-breathing-reset', '60-second-body-scan', 're-center-after-frustration'],
        'narrow-the-focus' => ['60-second-body-scan', 'box-breathing-reset', 'confidence-cue-routine'],
        '60-second-body-scan' => ['narrow-the-focus', 'box-breathing-reset', 'physiological-sigh-reset'],
        're-center-after-frustration' => ['physiological-sigh-reset', 'box-breathing-reset', 'narrow-the-focus'],
        'visualization-for-the-next-rep' => ['confidence-cue-routine', 'pre-performance-grounding', 'narrow-the-focus'],
        'post-practice-reflection' => ['60-second-body-scan', 'box-breathing-reset', 'narrow-the-focus'],
        'if-then-trigger-plan' => ['minimum-viable-rep', 'friction-audit-adjustment', 'narrow-the-focus'],
        'minimum-viable-rep' => ['if-then-trigger-plan', 'friction-audit-adjustment', 'post-practice-reflection'],
        'friction-audit-adjustment' => ['if-then-trigger-plan', 'weekly-review-reset', 'minimum-viable-rep'],
        'weekly-review-reset' => ['post-practice-reflection', 'friction-audit-adjustment', 'if-then-trigger-plan'],
    ];
}

function coachTextContainsAny(string $text, array $terms): bool
{
    foreach ($terms as $term) {
        if ($term !== '' && strpos($text, $term) !== false) {
            return true;
        }
    }

    return false;
}

function getCoachBenefitPhraseForSlug(string $slug): string
{
    $map = [
        'box-breathing-reset' => 'settle your body and narrow your attention',
        'physiological-sigh-reset' => 'downshift fast and regain control of your breathing',
        '60-second-body-scan' => 're-center quickly and release tension before the next action',
        'reset-after-a-mistake' => 'stop replaying the mistake and return to the next action',
        'pre-performance-grounding' => 're-center before the moment starts',
        'visualization-for-the-next-rep' => 'lock into the next action with a clear execution picture',
        're-center-after-frustration' => 'settle emotional heat and regain focus',
        'post-practice-reflection' => 'close the session cleanly without rumination',
        'confidence-cue-routine' => 'stabilize confidence around a simple cue',
        'narrow-the-focus' => 'regain focus on one controllable cue',
        'if-then-trigger-plan' => 'turn intention into a clear action trigger',
        'minimum-viable-rep' => 'restart consistency with a version you can complete today',
        'friction-audit-adjustment' => 'identify the blocker and remove resistance before the next attempt',
        'weekly-review-reset' => 'translate last period into one clear adjustment for the next one',
    ];

    return $map[$slug] ?? 'reset and regain focus for the next action';
}

function sanitizeCoachNarrativeLine(string $text, int $maxLength = 220): string
{
    $clean = trim((string) preg_replace('/\s+/', ' ', $text));
    $clean = str_replace('!', '.', $clean);
    $clean = trim((string) preg_replace('/\.+$/', '.', $clean));

    if ($clean === '.') {
        $clean = '';
    }

    if ($maxLength < 40) {
        $maxLength = 40;
    }

    if ($clean !== '' && strlen($clean) > $maxLength) {
        $clean = rtrim(substr($clean, 0, $maxLength - 3)) . '...';
    }

    return $clean;
}
