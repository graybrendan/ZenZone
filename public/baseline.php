<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/zenscore.php';

requireLogin();

$db = getDB();

$labels = zenzone_labels();
$flash = getFlashMessage();

$formValues = [];
foreach (array_keys($labels) as $field) {
    $formValues[$field] = 4;
}

if ((string) getOldInput('baseline_form', '') === 'baseline') {
    foreach (array_keys($labels) as $field) {
        $value = (int) getOldInput($field, '4');
        if ($value < 1 || $value > 7) {
            $value = 4;
        }
        $formValues[$field] = $value;
    }
    clearOldInput();
}

$stmt = $db->prepare("
    SELECT baseline_complete
    FROM users
    WHERE id = :user_id
    LIMIT 1
");
$stmt->execute([
    'user_id' => $_SESSION['user_id']
]);

$user = $stmt->fetch();

if ($user && (int) $user['baseline_complete'] === 1) {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baseline Assessment - ZenZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h1 class="h3 mb-3">Baseline Assessment</h1>
                    <p class="text-muted mb-4">Rate each area from 1 to 7.</p>

                    <form method="POST" action="../api/baseline/save.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                        <?php if ($flash): ?>
                            <div class="alert alert-<?= htmlspecialchars(($flash['type'] ?? 'info') === 'error' ? 'danger' : (string) ($flash['type'] ?? 'secondary')) ?>">
                                <?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($labels as $field => $label): ?>
                            <div class="mb-4">
                                <label for="<?= htmlspecialchars($field) ?>" class="form-label d-flex justify-content-between">
                                    <span><?= htmlspecialchars($label) ?></span>
                                    <span id="<?= htmlspecialchars($field) ?>_value"><?= htmlspecialchars((string) $formValues[$field]) ?></span>
                                </label>
                                <input
                                    type="range"
                                    class="form-range"
                                    min="1"
                                    max="7"
                                    step="1"
                                    id="<?= htmlspecialchars($field) ?>"
                                    name="<?= htmlspecialchars($field) ?>"
                                    value="<?= htmlspecialchars((string) $formValues[$field]) ?>"
                                    oninput="document.getElementById('<?= htmlspecialchars($field) ?>_value').textContent = this.value"
                                    required
                                >
                            </div>
                        <?php endforeach; ?>

                        <button type="submit" class="btn btn-dark">Save Baseline</button>
                    </form>

                    <div class="mt-3">
                        <a href="<?= htmlspecialchars(BASE_URL) ?>/dashboard.php">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
