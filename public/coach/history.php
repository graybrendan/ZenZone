<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/coach_engine.php';
require_once __DIR__ . '/../../includes/date_helpers.php';

requireLogin();

const COACH_HISTORY_PAGE_SIZE = 10;

$db = getDB();
$userId = (int) $_SESSION['user_id'];

if (!isCoachStorageReady($db)) {
    setFlashMessage('error', 'Coach setup is incomplete. Run the latest Coach migrations first.');
    authRedirect('coach/index.php');
}

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM coach_threads
    WHERE user_id = :user_id
      AND archived = 0
");
$countStmt->execute(['user_id' => $userId]);
$totalRows = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalRows / COACH_HISTORY_PAGE_SIZE));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * COACH_HISTORY_PAGE_SIZE;

$rows = [];
if ($totalRows > 0) {
    $listStmt = $db->prepare("
        SELECT
            id,
            COALESCE(NULLIF(summary, ''), thread_title) AS summary,
            created_at,
            updated_at,
            last_message_at
        FROM coach_threads
        WHERE user_id = :user_id
          AND archived = 0
        ORDER BY COALESCE(last_message_at, updated_at, created_at) DESC
        LIMIT " . COACH_HISTORY_PAGE_SIZE . " OFFSET " . (int) $offset . "
    ");
    $listStmt->execute(['user_id' => $userId]);
    $rows = $listStmt->fetchAll();
}

$pageTitle = 'Coach History';
$pageEyebrow = 'Coach';
$pageHelper = 'Review past situations and revisit recommendations.';
$activeNav = 'coach';
$showBackButton = true;
$backHref = BASE_URL . '/coach/index.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<?php require_once __DIR__ . '/../../includes/partials/header.php'; ?>

<section class="zz-coach-page zz-coach-history" aria-labelledby="zz-coach-history-title">
    <h2 id="zz-coach-history-title" class="zz-visually-hidden">Coach history</h2>

    <article class="zz-card zz-coach-history-head">
        <h3 class="zz-coach-card-title">Saved Situations</h3>
        <p class="zz-help">Page <?= h((string) $page) ?> of <?= h((string) $totalPages) ?></p>
    </article>

    <?php if (empty($rows)): ?>
        <article class="zz-card zz-coach-empty">
            <svg class="zz-coach-empty__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H10l-5 4V7a2 2 0 0 1 2-2z"></path>
                <path d="M9 10h6"></path>
                <path d="M9 13h4"></path>
            </svg>
            <h3>No coach situations yet</h3>
            <p>Start your first one to build a history of recommendations.</p>
            <a class="zz-btn zz-btn--primary" href="<?= h(BASE_URL . '/coach/index.php') ?>">Start New Situation</a>
        </article>
    <?php else: ?>
        <div class="zz-coach-list">
            <?php foreach ($rows as $row): ?>
                <?php
                $threadId = (int) ($row['id'] ?? 0);
                $summary = createCoachSituationSummary((string) ($row['summary'] ?? ''), 170);
                $createdAt = (string) ($row['created_at'] ?? '');
                $updatedAt = (string) ($row['last_message_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? '');
                ?>
                <article class="zz-coach-item" aria-labelledby="zz-coach-history-thread-<?= h((string) $threadId) ?>">
                    <h3 id="zz-coach-history-thread-<?= h((string) $threadId) ?>" class="zz-coach-item__title"><?= h($summary) ?></h3>
                    <p class="zz-coach-item__meta">
                        Created <?= h(zz_format_datetime($createdAt !== '' ? $createdAt : null)) ?>
                        <span aria-hidden="true">&middot;</span>
                        Updated <?= h(zz_format_datetime($updatedAt !== '' ? $updatedAt : null)) ?>
                    </p>

                    <div class="zz-coach-item__actions">
                        <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/coach/view.php?id=' . $threadId) ?>">View</a>
                        <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/coach/edit.php?id=' . $threadId) ?>">Edit</a>
                        <form method="POST" action="../../api/coach/delete.php" class="zz-inline-form" data-coach-delete-form data-confirm-message="Delete this coach situation? This cannot be undone.">
                            <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                            <input type="hidden" name="thread_id" value="<?= h((string) $threadId) ?>">
                            <button type="submit" class="zz-btn zz-btn--danger zz-btn--sm">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="zz-coach-pagination" aria-label="Coach history pagination">
            <?php if ($page > 1): ?>
                <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/coach/history.php?page=' . ($page - 1)) ?>">Previous</a>
            <?php else: ?>
                <button type="button" class="zz-btn zz-btn--secondary zz-btn--sm" disabled aria-disabled="true">Previous</button>
            <?php endif; ?>

            <span class="zz-coach-pagination__status">Page <?= h((string) $page) ?> of <?= h((string) $totalPages) ?></span>

            <?php if ($page < $totalPages): ?>
                <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/coach/history.php?page=' . ($page + 1)) ?>">Next</a>
            <?php else: ?>
                <button type="button" class="zz-btn zz-btn--secondary zz-btn--sm" disabled aria-disabled="true">Next</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/partials/footer.php'; ?>
