<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/zenscore.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$today = date('Y-m-d');

$dimensions = [
    'mindfulness' => [
        'label' => 'Mindfulness',
        'helper' => 'How present and aware do you feel right now?',
        'low' => 'Scattered',
        'high' => 'Present',
    ],
    'energy' => [
        'label' => 'Energy',
        'helper' => 'How much physical and mental fuel do you have right now?',
        'low' => 'Depleted',
        'high' => 'Energized',
    ],
    'connectedness' => [
        'label' => 'Connectedness',
        'helper' => 'How connected do you feel to people around you right now?',
        'low' => 'Isolated',
        'high' => 'Connected',
    ],
    'motivation' => [
        'label' => 'Motivation',
        'helper' => 'How much drive do you feel to do the next right thing?',
        'low' => 'Flat',
        'high' => 'Driven',
    ],
    'confidence' => [
        'label' => 'Confidence',
        'helper' => 'How steady is your belief in yourself right now?',
        'low' => 'Shaky',
        'high' => 'Assured',
    ],
    'emotional_balance' => [
        'label' => 'Emotional Balance',
        'helper' => 'How emotionally centered do you feel right now?',
        'low' => 'Off-center',
        'high' => 'Steady',
    ],
    'recovery' => [
        'label' => 'Recovery',
        'helper' => 'How recovered do you feel from recent effort and stress?',
        'low' => 'Worn',
        'high' => 'Restored',
    ],
    'readiness' => [
        'label' => 'Readiness',
        'helper' => 'How ready do you feel for what comes next today?',
        'low' => 'Unprepared',
        'high' => 'Primed',
    ],
];

$formValues = ['activity_text' => ''];
foreach (array_keys($dimensions) as $field) {
    $formValues[$field] = 4;
}

$checkinsTodayCount = 0;
$isDailyCheckin = true;
$loadErrorMessage = '';

try {
    $pdo = getDB();

    $checkinCountStmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM check_ins
        WHERE user_id = :user_id
          AND checkin_date = :checkin_date
    ');
    $checkinCountStmt->execute([
        'user_id' => $userId,
        'checkin_date' => $today,
    ]);

    $checkinsTodayCount = (int) $checkinCountStmt->fetchColumn();
    $isDailyCheckin = $checkinsTodayCount === 0;
} catch (Throwable $e) {
    error_log('Check-in page load failed: ' . $e->getMessage());
    $loadErrorMessage = 'We could not verify today\'s check-in count. You can still submit your check-in.';
}

if ((string) getOldInput('checkin_form', '') === 'checkin') {
    foreach (array_keys($dimensions) as $field) {
        $oldValue = filter_var(
            getOldInput($field, ''),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 7]]
        );

        if ($oldValue !== false) {
            $formValues[$field] = (int) $oldValue;
        }
    }

    $formValues['activity_text'] = (string) getOldInput('activity_text', getOldInput('activity_context', ''));
    clearOldInput();
}

$pageTitle = 'Daily Check-In';
$pageEyebrow = 'Today';
$pageHelper = 'A quick pulse on how you\'re doing right now.';
$activeNav = 'checkin';
$showBackButton = false;

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<?php require_once __DIR__ . '/../includes/partials/header.php'; ?>

<section class="zz-checkin-layout" aria-labelledby="zz-checkin-form-title">
    <div class="zz-check-in-type-banner zz-alert zz-alert--info" role="status">
        <?= $isDailyCheckin
            ? 'This will be logged as today\'s daily check-in.'
            : 'This will be logged as an additional check-in for today.' ?>
    </div>

    <?php if ($checkinsTodayCount > 0): ?>
        <p class="zz-help zz-checkin-context">This will be check-in #<?= h((string) ($checkinsTodayCount + 1)) ?> for today.</p>
    <?php endif; ?>

    <?php if ($loadErrorMessage !== ''): ?>
        <div class="zz-alert zz-alert--warning">
            <p><?= h($loadErrorMessage) ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="../api/checkin/submit.php" class="zz-baseline-form zz-checkin-form" novalidate>
        <h2 id="zz-checkin-form-title" class="zz-visually-hidden">Daily check-in form</h2>
        <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">

        <?php foreach ($dimensions as $field => $dim): ?>
            <?php $prefilledValue = (int) ($formValues[$field] ?? 4); ?>
            <fieldset class="zz-scale" data-scale-name="<?= h($field) ?>" data-scale-min="1" data-scale-max="7">
                <legend class="zz-label zz-scale__legend"><?= h((string) ($dim['label'] ?? '')) ?></legend>
                <p class="zz-help zz-scale__description"><?= h((string) ($dim['helper'] ?? '')) ?></p>

                <div class="zz-scale__track" role="radiogroup" aria-label="<?= h((string) ($dim['label'] ?? '')) ?> rating from 1 to 7">
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <?php $isSelected = $prefilledValue === $i; ?>
                        <label class="zz-scale__pill<?= $isSelected ? ' is-selected' : '' ?>">
                            <input type="radio" name="<?= h($field) ?>" value="<?= h((string) $i) ?>" <?= $isSelected ? 'checked' : '' ?>>
                            <span class="zz-scale__num"><?= h((string) $i) ?></span>
                        </label>
                    <?php endfor; ?>
                </div>

                <div class="zz-scale__endpoints">
                    <span class="zz-scale__endpoint-word"><?= h((string) ($dim['low'] ?? '')) ?></span>
                    <span class="zz-scale__endpoint-word"><?= h((string) ($dim['high'] ?? '')) ?></span>
                </div>
            </fieldset>
        <?php endforeach; ?>

        <div class="zz-field">
            <div class="zz-field__header">
                <label for="activity_text" class="zz-label">What's going on right now?</label>
                <span class="zz-optional-tag">Optional</span>
            </div>
            <p class="zz-help">No pressure - a few words help your future check-ins make sense.</p>

            <div class="zz-chip-group zz-chips" data-chip-target="#activity_text">
                <button type="button" class="zz-chip" data-value="Pre-practice" aria-pressed="false">Pre-practice</button>
                <button type="button" class="zz-chip" data-value="Between classes" aria-pressed="false">Between classes</button>
                <button type="button" class="zz-chip" data-value="After a tough day" aria-pressed="false">After a tough day</button>
                <button type="button" class="zz-chip" data-value="Winding down" aria-pressed="false">Winding down</button>
                <button type="button" class="zz-chip" data-value="Just reflecting" aria-pressed="false">Just reflecting</button>
            </div>

            <textarea
                id="activity_text"
                name="activity_text"
                class="zz-textarea zz-textarea--journal"
                rows="4"
                maxlength="1000"
                placeholder="Pre-practice, between classes, winding down..."
            ><?= h($formValues['activity_text']) ?></textarea>
        </div>

        <div class="zz-baseline-form__submit zz-checkin-form__submit">
            <button type="submit" class="zz-btn zz-btn--primary zz-btn--lg zz-btn--block">Log Check-In</button>
            <p class="zz-help zz-checkin-form__hint">You can check in more than once a day. Extra check-ins are logged separately.</p>
        </div>
    </form>
</section>

<script>
(function () {
    function splitValues(value) {
        return value
            .split(',')
            .map(function (item) {
                return item.trim();
            })
            .filter(function (item) {
                return item !== '';
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var groups = document.querySelectorAll('[data-chip-target]');

        groups.forEach(function (group) {
            var targetSelector = group.getAttribute('data-chip-target') || '';
            if (targetSelector === '') {
                return;
            }

            var target = document.querySelector(targetSelector);
            if (!target) {
                return;
            }

            var chips = Array.prototype.slice.call(group.querySelectorAll('.zz-chip'));
            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    var value = (chip.getAttribute('data-value') || chip.textContent || '').trim();
                    if (value === '') {
                        return;
                    }

                    var values = splitValues(target.value);
                    if (values.indexOf(value) === -1) {
                        values.push(value);
                    }

                    target.value = values.join(', ');
                    target.dispatchEvent(new Event('input', { bubbles: true }));

                    chips.forEach(function (item) {
                        item.classList.remove('is-selected');
                        item.setAttribute('aria-pressed', 'false');
                    });

                    chip.classList.add('is-selected');
                    chip.setAttribute('aria-pressed', 'true');
                    target.focus();
                });
            });
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>