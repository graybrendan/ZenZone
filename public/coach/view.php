<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/coach_engine.php';
require_once __DIR__ . '/../../includes/date_helpers.php';
require_once __DIR__ . '/../../includes/coach_view_helpers.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];

if (!isCoachStorageReady($db)) {
    setFlashMessage('error', 'Coach setup is incomplete. Run the latest Coach migrations first.');
    authRedirect('coach/index.php');
}

$threadId = (int) ($_GET['id'] ?? 0);
if ($threadId <= 0) {
    setFlashMessage('error', 'Invalid coach situation selected.');
    authRedirect('coach/index.php');
}

$threadStmt = $db->prepare("
    SELECT
        id,
        thread_title,
        summary,
        situation_text,
        situation_type,
        time_available,
        stress_level,
        upcoming_event,
        created_at,
        updated_at,
        last_message_at
    FROM coach_threads
    WHERE id = :thread_id
      AND user_id = :user_id
      AND archived = 0
    LIMIT 1
");
$threadStmt->execute([
    'thread_id' => $threadId,
    'user_id' => $userId,
]);
$thread = $threadStmt->fetch();

if (!$thread) {
    setFlashMessage('error', 'Coach situation not found.');
    authRedirect('coach/index.php');
}

$aiMessageStmt = $db->prepare("
    SELECT content, metadata_json, created_at
    FROM coach_messages
    WHERE thread_id = :thread_id
      AND sender = 'ai'
    ORDER BY id DESC
    LIMIT 1
");
$aiMessageStmt->execute(['thread_id' => $threadId]);
$latestAiMessage = $aiMessageStmt->fetch();

$coachResponse = null;
if ($latestAiMessage && !empty($latestAiMessage['metadata_json'])) {
    $decoded = json_decode((string) $latestAiMessage['metadata_json'], true);
    if (is_array($decoded)) {
        $coachResponse = $decoded;
    }
}

if ($coachResponse === null) {
    $coachResponse = generateCoachResponse([
        'situation_text' => (string) ($thread['situation_text'] ?? ''),
        'situation_type' => (string) ($thread['situation_type'] ?? 'other'),
        'time_available' => (int) ($thread['time_available'] ?? 3),
        'stress_level' => (int) ($thread['stress_level'] ?? 3),
        'upcoming_event' => (string) ($thread['upcoming_event'] ?? ''),
    ]);
}

$threadSummary = trim((string) ($thread['summary'] ?? ''));
if ($threadSummary === '') {
    $threadSummary = createCoachSituationSummary((string) ($thread['situation_text'] ?? ''), 180);
}

$topRecommendation = is_array($coachResponse['top_recommendation'] ?? null)
    ? $coachResponse['top_recommendation']
    : null;
$alternatives = is_array($coachResponse['alternatives'] ?? null)
    ? $coachResponse['alternatives']
    : [];

$topSlug = '';
$topLessonExists = false;
if ($topRecommendation !== null) {
    $topSlug = trim((string) ($topRecommendation['slug'] ?? ''));
    if ($topSlug !== '') {
        $topLessonExists = getLessonBySlug($topSlug) !== null;
    }
}

$createdAt = (string) ($thread['created_at'] ?? '');
$updatedAt = (string) ($thread['last_message_at'] ?? $thread['updated_at'] ?? $thread['created_at'] ?? '');
$createdLabel = zz_format_datetime($createdAt !== '' ? $createdAt : null);
$updatedLabel = zz_format_datetime($updatedAt !== '' ? $updatedAt : null);

$pageTitle = 'Your Situation';
$pageEyebrow = 'Coach';
$pageHelper = 'Review your recommendation and take the next useful action.';
$activeNav = 'coach';
$showBackButton = true;
$backHref = BASE_URL . '/coach/index.php';

function coachTypeLabel(string $type): string
{
    $normalized = strtolower(trim($type));
    $normalized = str_replace(['_', '-', '/'], ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $normalized = is_string($normalized) ? trim($normalized) : '';

    if ($normalized === '') {
        return 'Other';
    }

    return ucwords($normalized);
}

function coachCleanRecommendationText(string $text): string
{
    $clean = trim($text);
    if ($clean === '') {
        return '';
    }

    $patterns = [
        '/\s*then\s+(?:log|mark)\s+better\s*,?\s*same\s*,?\s*or\s*worse[^.]*\.?/i',
        '/\s*(?:log|mark)\s+better\s*,?\s*same\s*,?\s*or\s*worse[^.]*\.?/i',
        '/\s*better\s*\/\s*same\s*\/\s*worse[^.]*\.?/i',
        '/\s*follow\s+the\s+instructions(?:\s+above)?[^.]*\.?/i',
    ];

    $clean = preg_replace($patterns, '', $clean);
    $clean = is_string($clean) ? $clean : '';
    $clean = preg_replace('/\s{2,}/', ' ', $clean);
    $clean = preg_replace('/\s+([,.!?;:])/', '$1', $clean);
    $clean = is_string($clean) ? trim($clean) : '';

    return $clean;
}

function coachNormalizeCitationUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    return $url;
}

function coachNormalizeCitationsForView($rawCitations): array
{
    if (!is_array($rawCitations)) {
        return [];
    }

    $normalized = [];
    $seen = [];

    foreach ($rawCitations as $rawCitation) {
        if (!is_array($rawCitation)) {
            continue;
        }

        $title = trim((string) ($rawCitation['title'] ?? ''));
        $filename = trim((string) ($rawCitation['filename'] ?? ''));
        $fileId = trim((string) ($rawCitation['file_id'] ?? ''));
        $url = coachNormalizeCitationUrl((string) ($rawCitation['url'] ?? ''));
        $excerpt = trim((string) ($rawCitation['excerpt'] ?? ''));
        $evidenceTier = trim((string) ($rawCitation['evidence_tier'] ?? ''));

        $score = null;
        $rawScore = $rawCitation['score'] ?? null;
        if (is_numeric($rawScore)) {
            $score = (float) $rawScore;
            if ($score < 0) {
                $score = 0.0;
            }
            if ($score > 1) {
                $score = 1.0;
            }
        }

        if ($title === '' && $filename === '' && $url === '' && $excerpt === '') {
            continue;
        }

        $dedupeKey = strtolower($fileId . '|' . $filename . '|' . $url . '|' . $title);
        if ($dedupeKey === '') {
            $dedupeKey = md5(json_encode($rawCitation) ?: serialize($rawCitation));
        }
        if (isset($seen[$dedupeKey])) {
            continue;
        }
        $seen[$dedupeKey] = true;

        $normalized[] = [
            'title' => $title,
            'filename' => $filename,
            'file_id' => $fileId,
            'url' => $url,
            'excerpt' => $excerpt,
            'evidence_tier' => $evidenceTier,
            'score' => $score,
        ];

        if (count($normalized) >= 10) {
            break;
        }
    }

    return $normalized;
}

$sourceMode = strtolower(trim((string) ($coachResponse['source_mode'] ?? 'rule_based')));
$knowledgeMode = strtolower(trim((string) ($coachResponse['knowledge_mode'] ?? 'evidence')));
if (!in_array($knowledgeMode, ['evidence', 'reflection'], true)) {
    $knowledgeMode = 'evidence';
}

$citations = coachNormalizeCitationsForView($coachResponse['citations'] ?? []);
$retrievalMetadata = is_array($coachResponse['retrieval_metadata'] ?? null)
    ? $coachResponse['retrieval_metadata']
    : [];
$retrievalProvider = trim((string) ($retrievalMetadata['provider'] ?? ''));
$retrievalResultCount = (int) ($retrievalMetadata['result_count'] ?? count($citations));
if ($retrievalResultCount < 0) {
    $retrievalResultCount = 0;
}
?>
<?php require_once __DIR__ . '/../../includes/partials/header.php'; ?>

<section class="zz-coach-page zz-coach-view" aria-labelledby="zz-coach-view-title">
    <h2 id="zz-coach-view-title" class="zz-visually-hidden">Coach situation details</h2>

    <article class="zz-card zz-coach-thread" aria-labelledby="zz-coach-thread-title">
        <h3 id="zz-coach-thread-title" class="zz-coach-thread__title"><?= h($threadSummary) ?></h3>
        <div class="zz-coach-thread__meta">
            <span class="zz-badge zz-badge--neutral zz-badge--sm"><?= h(coachTypeLabel((string) ($thread['situation_type'] ?? 'other'))) ?></span>
            <span class="zz-badge zz-badge--sage zz-badge--sm"><?= h((string) ((int) ($thread['time_available'] ?? 0))) ?> min</span>
            <span class="zz-badge zz-badge--gold zz-badge--sm">Emotion intensity <?= h((string) ((int) ($thread['stress_level'] ?? 0))) ?>/5</span>
        </div>
        <p class="zz-coach-thread__dates zz-date-time">
            Created <?= h($createdLabel) ?>
            <span aria-hidden="true">&middot;</span>
            Updated <?= h($updatedLabel) ?>
        </p>
    </article>

    <article class="zz-card zz-coach-recommendation" aria-labelledby="zz-coach-recommendation-title">
        <h3 id="zz-coach-recommendation-title" class="zz-coach-card-title">Recommendation</h3>
        <p class="zz-coach-recommendation__meta">
            Source mode: <?= h($sourceMode === 'external_ai' ? 'External AI' : 'Rule-based') ?>
            <span aria-hidden="true">&middot;</span>
            Knowledge mode: <?= h($knowledgeMode === 'reflection' ? 'Reflection' : 'Evidence') ?>
        </p>

        <?php if (!empty($coachResponse['crisis_detected'])): ?>
            <div class="zz-alert zz-alert--danger zz-coach-crisis" role="alert">
                <p><strong>Immediate support recommended</strong></p>
                <?php if (trim((string) ($coachResponse['crisis_message'] ?? '')) !== ''): ?>
                    <?php $crisisMessage = coachCleanRecommendationText((string) $coachResponse['crisis_message']); ?>
                    <?php if ($crisisMessage !== ''): ?>
                        <p><?= h($crisisMessage) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (trim((string) ($coachResponse['coach_message'] ?? '')) !== ''): ?>
                    <?php $coachCrisisMessage = coachCleanRecommendationText((string) $coachResponse['coach_message']); ?>
                    <?php if ($coachCrisisMessage !== ''): ?>
                        <p><?= h($coachCrisisMessage) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if (!empty($coachResponse['summary'])): ?>
                <?php $coachSummary = coachCleanRecommendationText((string) $coachResponse['summary']); ?>
                <?php if ($coachSummary !== ''): ?>
                    <p class="zz-coach-recommendation__summary"><?= h($coachSummary) ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($topRecommendation !== null): ?>
                <article class="zz-coach-rec-primary">
                    <div class="zz-coach-rec-primary__head">
                        <h4><?= h((string) ($topRecommendation['title'] ?? 'Top recommendation')) ?></h4>
                        <span class="zz-badge zz-badge--gold zz-badge--sm"><?= h((string) ((int) ($topRecommendation['duration_minutes'] ?? 0))) ?> min</span>
                    </div>
                    <?php $topWhy = coachCleanRecommendationText((string) ($topRecommendation['why_this_works'] ?? '')); ?>
                    <?php $topWhen = coachCleanRecommendationText((string) ($topRecommendation['when_to_use'] ?? '')); ?>
                    <?php if ($topWhy !== ''): ?>
                        <p class="zz-help"><span class="zz-coach-rec-label">Why this works:</span> <?= h($topWhy) ?></p>
                    <?php endif; ?>
                    <?php if ($topWhen !== ''): ?>
                        <p class="zz-help"><span class="zz-coach-rec-label">When to use:</span> <?= h($topWhen) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($topRecommendation['steps']) && is_array($topRecommendation['steps'])): ?>
                        <?php
                        $topSteps = [];
                        foreach ($topRecommendation['steps'] as $step) {
                            $cleanStep = coachCleanRecommendationText((string) $step);
                            if ($cleanStep !== '') {
                                $topSteps[] = $cleanStep;
                            }
                        }
                        ?>
                        <?php if (!empty($topSteps)): ?>
                            <ol class="zz-coach-rec-steps">
                                <?php foreach ($topSteps as $step): ?>
                                    <li><?= h($step) ?></li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($topSlug !== '' && $topLessonExists): ?>
                        <a class="zz-btn zz-btn--primary zz-btn--sm" href="<?= h(BASE_URL . '/content/view.php?slug=' . urlencode($topSlug)) ?>">Start This Tool</a>
                    <?php endif; ?>
                </article>
            <?php else: ?>
                <p class="zz-muted">No top recommendation was available for this situation.</p>
            <?php endif; ?>

            <?php if (!empty($coachResponse['coach_message'])): ?>
                <?php $coachMessage = coachCleanRecommendationText((string) $coachResponse['coach_message']); ?>
                <?php if ($coachMessage !== ''): ?>
                    <p class="zz-coach-recommendation__message"><?= h($coachMessage) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </article>

    <?php if ($sourceMode === 'external_ai'): ?>
        <details class="zz-card zz-coach-details zz-coach-sources">
            <summary class="zz-section-title">Sources (<?= h((string) count($citations)) ?>)</summary>
            <p class="zz-help zz-coach-sources__meta">
                Provider: <?= h($retrievalProvider !== '' ? $retrievalProvider : 'external_ai') ?>
                <span aria-hidden="true">&middot;</span>
                Retrieved chunks: <?= h((string) $retrievalResultCount) ?>
            </p>

            <?php if (!empty($citations)): ?>
                <ul class="zz-coach-sources-list">
                    <?php foreach ($citations as $citation): ?>
                        <?php
                        $citationTitle = trim((string) ($citation['title'] ?? ''));
                        $citationFilename = trim((string) ($citation['filename'] ?? ''));
                        $citationLabel = $citationTitle !== '' ? $citationTitle : ($citationFilename !== '' ? $citationFilename : 'Untitled source');
                        $citationUrl = trim((string) ($citation['url'] ?? ''));
                        $citationExcerpt = trim((string) ($citation['excerpt'] ?? ''));
                        $citationTier = trim((string) ($citation['evidence_tier'] ?? ''));
                        $citationScore = $citation['score'] ?? null;
                        ?>
                        <li class="zz-coach-source-item">
                            <p class="zz-coach-source-item__title"><?= h($citationLabel) ?></p>
                            <?php if ($citationTier !== '' || is_numeric($citationScore)): ?>
                                <p class="zz-help zz-coach-source-item__meta">
                                    <?php if ($citationTier !== ''): ?>
                                        Tier: <?= h($citationTier) ?>
                                    <?php endif; ?>
                                    <?php if ($citationTier !== '' && is_numeric($citationScore)): ?>
                                        <span aria-hidden="true">&middot;</span>
                                    <?php endif; ?>
                                    <?php if (is_numeric($citationScore)): ?>
                                        Relevance: <?= h((string) round(((float) $citationScore) * 100)) ?>%
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($citationExcerpt !== ''): ?>
                                <p class="zz-help zz-coach-source-item__excerpt"><?= h($citationExcerpt) ?></p>
                            <?php endif; ?>
                            <?php if ($citationUrl !== ''): ?>
                                <a class="zz-btn zz-btn--ghost zz-btn--sm" href="<?= h($citationUrl) ?>" target="_blank" rel="noopener noreferrer">Open Source</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="zz-muted">No source citations were returned for this response.</p>
            <?php endif; ?>
        </details>
    <?php endif; ?>

    <?php if (!empty($alternatives)): ?>
        <details class="zz-card zz-coach-details zz-coach-alternatives">
            <summary class="zz-section-title">Alternatives (<?= h((string) count($alternatives)) ?>)</summary>
            <div class="zz-coach-alt-list">
                <?php foreach ($alternatives as $alternative): ?>
                    <?php if (!is_array($alternative)): continue; endif; ?>
                    <?php
                    $altSlug = trim((string) ($alternative['slug'] ?? ''));
                    $altLessonExists = ($altSlug !== '' && getLessonBySlug($altSlug) !== null);
                    ?>
                    <article class="zz-coach-alt-item">
                        <h4><?= h((string) ($alternative['title'] ?? 'Alternative tool')) ?></h4>
                        <?php $altWhy = coachCleanRecommendationText((string) ($alternative['why_this_works'] ?? '')); ?>
                        <?php $altWhen = coachCleanRecommendationText((string) ($alternative['when_to_use'] ?? '')); ?>
                        <?php if ($altWhy !== ''): ?>
                            <p class="zz-help"><span class="zz-coach-rec-label">Why this works:</span> <?= h($altWhy) ?></p>
                        <?php endif; ?>
                        <?php if ($altWhen !== ''): ?>
                            <p class="zz-help"><span class="zz-coach-rec-label">When to use:</span> <?= h($altWhen) ?></p>
                        <?php endif; ?>
                        <p class="zz-help"><strong>Estimated duration:</strong> <?= h((string) ((int) ($alternative['duration_minutes'] ?? 0))) ?> min</p>

                        <?php if (!empty($alternative['steps']) && is_array($alternative['steps'])): ?>
                            <?php
                            $altSteps = [];
                            foreach ($alternative['steps'] as $step) {
                                $cleanStep = coachCleanRecommendationText((string) $step);
                                if ($cleanStep !== '') {
                                    $altSteps[] = $cleanStep;
                                }
                            }
                            ?>
                            <?php if (!empty($altSteps)): ?>
                                <ol class="zz-coach-alt-item__steps">
                                    <?php foreach ($altSteps as $step): ?>
                                        <li><?= h($step) ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($altLessonExists): ?>
                            <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/content/view.php?slug=' . urlencode($altSlug)) ?>">Start This Tool</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

    <div class="zz-coach-actions" aria-label="Coach situation actions">
        <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/coach/edit.php?id=' . $threadId) ?>">Edit Situation</a>
        <a class="zz-btn zz-btn--ghost zz-btn--sm" href="<?= h(BASE_URL . '/coach/index.php') ?>">New Situation</a>
        <a class="zz-btn zz-btn--ghost zz-btn--sm" href="<?= h(BASE_URL . '/dashboard.php') ?>">Dashboard</a>
    </div>

    <details class="zz-card zz-coach-details zz-coach-situation">
        <summary class="zz-section-title">Situation Details</summary>
        <dl class="zz-detail-list zz-coach-meta-list">
            <dt>Situation type</dt>
            <dd><?= h(coachTypeLabel((string) ($thread['situation_type'] ?? 'other'))) ?></dd>
            <dt>Time available</dt>
            <dd><?= h((string) ((int) ($thread['time_available'] ?? 0))) ?> min</dd>
            <dt>Emotion intensity</dt>
            <dd><?= h((string) ((int) ($thread['stress_level'] ?? 0))) ?> / 5</dd>
            <dt>Upcoming event</dt>
            <dd><?= h((string) (($thread['upcoming_event'] ?? '') !== '' ? $thread['upcoming_event'] : 'None')) ?></dd>
            <dt>What happened</dt>
            <dd><?= nl2br(h((string) ($thread['situation_text'] ?? ''))) ?></dd>
        </dl>
    </details>
</section>

<?php require_once __DIR__ . '/../../includes/partials/footer.php'; ?>
