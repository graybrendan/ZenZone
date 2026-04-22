<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

$db = getDB();

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

$dimensions = [
    'mindfulness' => [
        'label' => 'Mindfulness',
        'helper' => 'How present and aware you feel right now?',
        'low' => 'Scattered',
        'high' => 'Present',
    ],
    'energy' => [
        'label' => 'Energy',
        'helper' => 'How has you physical and mental fuel tank been recently?',
        'low' => 'Depleted',
        'high' => 'Energized',
    ],
    'connectedness' => [
        'label' => 'Connectedness',
        'helper' => 'How close do you feel to teammates, friends, and other people around you?',
        'low' => 'Isolated',
        'high' => 'Connected',
    ],
    'motivation' => [
        'label' => 'Motivation',
        'helper' => 'How is your drive to pursue what matters to you?',
        'low' => 'Flat',
        'high' => 'Driven',
    ],
    'confidence' => [
        'label' => 'Confidence',
        'helper' => 'Your belief in yourself going into whatever comes next.',
        'low' => 'Shaky',
        'high' => 'Assured',
    ],
    'emotional_balance' => [
        'label' => 'Emotional Balance',
        'helper' => 'How steady or in control do you feel emotionally?',
        'low' => 'Off-center',
        'high' => 'Steady',
    ],
    'recovery' => [
        'label' => 'Recovery',
        'helper' => 'How recovered you feel from recent training, work, or stress.',
        'low' => 'Worn',
        'high' => 'Restored',
    ],
    'readiness' => [
        'label' => 'Readiness',
        'helper' => 'How prepared you feel to perform at your desired level today.',
        'low' => 'Unprepared',
        'high' => 'Primed',
    ],
];

$formValues = [];
$hasOldBaselineInput = (string) getOldInput('baseline_form', '') === 'baseline';

foreach (array_keys($dimensions) as $field) {
    $prefilledValue = 4;

    if ($hasOldBaselineInput) {
        $oldValue = filter_var(
            getOldInput($field, ''),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 7]]
        );

        if ($oldValue !== false) {
            $prefilledValue = (int) $oldValue;
        }
    }

    $formValues[$field] = $prefilledValue;
}

if ($hasOldBaselineInput) {
    clearOldInput();
}

$pageTitle = 'Your Baseline';
$pageEyebrow = 'Calibration';
$pageHelper = null;
$activeNav = 'home';
$showBackButton = false;
$hideBottomNav = false;
?>
<?php require_once __DIR__ . '/../includes/partials/header.php'; ?>

<section aria-labelledby="zz-baseline-intro-title">
    <article class="zz-card zz-intro-card">
        <p class="zz-section-title zz-intro-card__eyebrow">WHAT IS ZENSCORE?</p>
        <h2 id="zz-baseline-intro-title">A snapshot of how you're doing.</h2>
        <p>Your ZenScore blends eight areas that affect how you show up - mentally, physically, and emotionally. Today's ratings become your baseline, so every future check-in compares to the version of you who's here right now. It takes about a minute.</p>
        <div class="zz-intro-card__badges" aria-label="Baseline details">
            <span class="zz-badge zz-badge--sage zz-badge--sm">Takes about a minute</span>
            <span class="zz-badge zz-badge--sage zz-badge--sm">Private to you</span>
            <span class="zz-badge zz-badge--sage zz-badge--sm">Used in every check-in</span>
        </div>
    </article>

    <form method="post" action="<?= htmlspecialchars(BASE_URL . '/api/baseline/save.php', ENT_QUOTES, 'UTF-8') ?>" class="zz-baseline-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <?php foreach ($dimensions as $field => $dim): ?>
            <?php $prefilledValue = (int) ($formValues[$field] ?? 4); ?>
            <fieldset class="zz-scale" data-scale-name="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>" data-scale-min="1" data-scale-max="7">
                <legend class="zz-label zz-scale__legend"><?= htmlspecialchars((string) ($dim['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></legend>
                <p class="zz-help zz-scale__description"><?= htmlspecialchars((string) ($dim['helper'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

                <div class="zz-scale__track" role="radiogroup" aria-label="<?= htmlspecialchars((string) ($dim['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?> rating from 1 to 7">
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <?php $isSelected = $prefilledValue === $i; ?>
                        <label class="zz-scale__pill<?= $isSelected ? ' is-selected' : '' ?>">
                            <input type="radio" name="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $i, ENT_QUOTES, 'UTF-8') ?>" <?= $isSelected ? 'checked' : '' ?>>
                            <span class="zz-scale__num"><?= htmlspecialchars((string) $i, ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                    <?php endfor; ?>
                </div>

                <div class="zz-scale__endpoints">
                    <span class="zz-scale__endpoint-word"><?= htmlspecialchars((string) ($dim['low'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="zz-scale__endpoint-word"><?= htmlspecialchars((string) ($dim['high'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </fieldset>
        <?php endforeach; ?>

        <div class="zz-baseline-form__submit">
            <button type="submit" class="zz-btn zz-btn--primary zz-btn--lg zz-btn--block">Save My Baseline</button>
            <p class="zz-help zz-baseline-form__disclaimer">You can retake your baseline anytime from your profile.</p>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
