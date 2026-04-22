<?php
$zzFlashItems = [];

if (function_exists('getFlashMessage')) {
    $flashPayload = getFlashMessage();

    if (is_array($flashPayload)) {
        if (array_key_exists('message', $flashPayload)) {
            $zzFlashItems[] = $flashPayload;
        } else {
            foreach ($flashPayload as $flashRow) {
                if (is_array($flashRow) && array_key_exists('message', $flashRow)) {
                    $zzFlashItems[] = $flashRow;
                }
            }
        }
    }
}

if (empty($zzFlashItems)) {
    return;
}

$zzFlashVariantMap = [
    'success' => 'success',
    'info' => 'info',
    'warning' => 'warning',
    'danger' => 'danger',
    'error' => 'danger',
];
?>
<?php foreach ($zzFlashItems as $zzFlashItem): ?>
    <?php
    $rawType = strtolower(trim((string) ($zzFlashItem['type'] ?? 'info')));
    $variant = $zzFlashVariantMap[$rawType] ?? 'info';
    $message = trim((string) ($zzFlashItem['message'] ?? ''));
    $role = in_array($variant, ['warning', 'danger'], true) ? 'alert' : 'status';

    if ($message === '') {
        continue;
    }
    ?>
    <div class="zz-toast zz-toast--<?= htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') ?>" role="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">
        <span class="zz-toast__icon" aria-hidden="true">
            <svg class="zz-toast__icon-svg">
                <use xlink:href="#icon-check"></use>
            </svg>
        </span>
        <p class="zz-toast__text"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <button type="button" class="zz-toast__close" aria-label="Dismiss">
            <svg class="zz-toast__close-icon" aria-hidden="true">
                <use xlink:href="#icon-close"></use>
            </svg>
        </button>
    </div>
<?php endforeach; ?>
