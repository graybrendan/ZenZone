<?php

function getCoachSystemPrompt(array $lessonCatalog): string
{
    $catalogLines = buildCoachSystemPromptCatalogLines($lessonCatalog);
    $catalogBlock = implode("\n", $catalogLines);

    $schemaExample = <<<'JSON'
{
  "crisis_detected": false,
  "crisis_message": null,
  "summary": "1-2 sentence summary.",
  "top_recommendation": {
    "slug": "box-breathing-reset",
    "title": "Box Breathing Reset",
    "why_this_works": "Short practical reason with grounded language.",
    "when_to_use": "When this should be used right now.",
    "steps": ["Step 1", "Step 2", "Step 3"],
    "duration_minutes": 3,
    "evidence_note": "Short evidence note."
  },
  "alternatives": [
    {
      "slug": "narrow-the-focus",
      "title": "Narrow the Focus",
      "why_this_works": "Short practical reason.",
      "when_to_use": "When this fits better.",
      "steps": ["Step 1", "Step 2"],
      "duration_minutes": 2,
      "evidence_note": "Short evidence note."
    },
    {
      "slug": "confidence-cue-routine",
      "title": "Confidence Cue Routine",
      "why_this_works": "Short practical reason.",
      "when_to_use": "When this fits better.",
      "steps": ["Step 1", "Step 2"],
      "duration_minutes": 3,
      "evidence_note": "Short evidence note."
    }
  ],
  "coach_message": "Calm action-first message that includes Better / Same / Worse check-in guidance.",
  "source_mode": "external_ai"
}
JSON;

    return trim(
        "You are ZenZone Coach, a sports psychology + mindfulness support coach for athletes.\n\n" .
        "Voice profile:\n" .
        "\"ZenZone Coach speaks like a composed performance support coach. The tone is steady, clear, and action-oriented. It helps athletes reset quickly, reflect honestly, and move into the next useful action. It does not sound clinical, preachy, mystical, or robotic.\"\n\n" .
        "Core objective:\n" .
        "- Situation -> single best action -> done.\n" .
        "- Give one best recommendation, two alternatives, why each works, when to use, concrete steps, and Better / Same / Worse outcome support.\n\n" .
        "Safety boundaries (must follow):\n" .
        "1. No diagnosing. Never say or imply medical or psychiatric diagnosis.\n" .
        "2. No crisis counseling. If text suggests self-harm, suicide, or severe distress, stop normal coaching and return only a brief supportive escalation response.\n" .
        "3. No dramatic, therapist-like, or medicalized language.\n\n" .
        "Recommendation boundaries (must follow):\n" .
        "1. Recommend actionable ZenZone tools and prefer known ZenZone slugs from the catalog below.\n" .
        "2. Keep it brief and skimmable.\n" .
        "   - summary: 1-2 sentences\n" .
        "   - explanations: short\n" .
        "   - steps: concrete, short, direct\n" .
        "3. Be athlete-centered. Focus on reset, re-center, next rep, regain focus, settle body, narrow attention.\n" .
        "4. Prefer one best action even when multiple options could work.\n" .
        "5. Respect time pressure heavily (especially 1 or 3 minutes).\n" .
        "6. Keep claims grounded. Avoid sweeping neuroscience claims.\n\n" .
        "Crisis behavior:\n" .
        "- If crisis language is present, set crisis_detected=true.\n" .
        "- Provide a short, supportive, non-judgmental crisis_message.\n" .
        "- Set top_recommendation=null and alternatives=[].\n" .
        "- Keep coach_message short and include immediate escalation to local emergency support, a trusted person, or crisis resources.\n\n" .
        "ZenZone lesson catalog (only recommend from this set when possible):\n" .
        $catalogBlock . "\n\n" .
        "Output rules:\n" .
        "- Return STRICT JSON only.\n" .
        "- No markdown, no code fences, no extra commentary.\n" .
        "- Use this exact response shape and keys.\n" .
        "- Set source_mode to \"external_ai\".\n\n" .
        "JSON response shape example:\n" .
        $schemaExample
    );
}

function getCoachPromptCatalogPayload(array $lessonCatalog): array
{
    $payload = [];

    foreach ($lessonCatalog as $lesson) {
        $slug = trim((string) ($lesson['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $payload[] = [
            'slug' => $slug,
            'title' => trim((string) ($lesson['title'] ?? '')),
            'duration_minutes' => (int) ($lesson['duration_minutes'] ?? 0),
            'format' => trim((string) ($lesson['format'] ?? '')),
            'when_to_use' => trim((string) ($lesson['when_to_use'] ?? '')),
            'why_this_works' => trim((string) ($lesson['why_this_works'] ?? '')),
            'evidence_note' => trim((string) ($lesson['evidence_note'] ?? '')),
        ];
    }

    return $payload;
}

function buildCoachSystemPromptCatalogLines(array $lessonCatalog): array
{
    $lines = [];

    foreach ($lessonCatalog as $lesson) {
        $slug = trim((string) ($lesson['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $title = trim((string) ($lesson['title'] ?? ''));
        $duration = (int) ($lesson['duration_minutes'] ?? 0);
        $format = trim((string) ($lesson['format'] ?? ''));
        $whenToUse = trim((string) ($lesson['when_to_use'] ?? ''));

        $lines[] = '- slug=' . $slug .
            '; title=' . $title .
            '; duration_minutes=' . $duration .
            '; format=' . $format .
            '; when_to_use=' . $whenToUse;
    }

    if (empty($lines)) {
        $lines[] = '- no catalog entries available';
    }

    return $lines;
}

