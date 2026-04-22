<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

requireGuest();

$authVariant = 'card';
$pageTitle = 'Log In';
$pageDescription = 'Log in to ZenZone and continue your daily check-ins and goals.';

$authMessage = getAuthPageMessage('login', $_GET);
$errorCode = trim((string) ($_GET['error'] ?? ''));
$statusCode = trim((string) ($_GET['status'] ?? ''));

if ($authMessage !== '') {
    $flashType = 'danger';
    if ($statusCode === 'logged_out') {
        $flashType = 'info';
    } elseif ($errorCode === 'too_many_attempts') {
        $flashType = 'warning';
    }

    setFlashMessage($flashType, $authMessage);
}

$oldEmail = trim((string) getOldInput('email', ''));
clearOldInput();
$hasLoginError = $errorCode !== '';
$csrfToken = getGuestCsrfToken();
?>
<?php require_once __DIR__ . '/../includes/partials/auth_header.php'; ?>

<h1>Welcome back</h1>
<p class="zz-auth__subtitle">Log in to keep your streak going.</p>

<form method="post" action="<?= htmlspecialchars(BASE_URL . '/api/auth/login.php', ENT_QUOTES, 'UTF-8') ?>" data-zz-login-form data-zz-login-error="<?= $hasLoginError ? 'true' : 'false' ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="zz-field zz-float" data-zz-float>
        <input
            type="email"
            id="email"
            name="email"
            class="zz-float__control"
            placeholder=" "
            autocomplete="email"
            data-zz-login-email
            required
            value="<?= htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') ?>"
        >
        <label class="zz-float__label" for="email">Email</label>
    </div>

    <div class="zz-field zz-float zz-password-field" data-zz-float>
        <input
            type="password"
            id="password"
            name="password"
            class="zz-float__control"
            placeholder=" "
            autocomplete="current-password"
            required
        >
        <label class="zz-float__label" for="password">Password</label>
        <button type="button" class="zz-password-field__toggle" aria-label="Show password" aria-pressed="false" data-zz-password-toggle>
            <svg class="zz-password-field__icon zz-password-field__icon--eye" aria-hidden="true">
                <use xlink:href="#icon-eye"></use>
            </svg>
            <svg class="zz-password-field__icon zz-password-field__icon--eye-off" aria-hidden="true">
                <use xlink:href="#icon-eye-off"></use>
            </svg>
            <span class="zz-sr-only">Toggle password visibility</span>
        </button>
    </div>

    <button type="submit" class="zz-btn zz-btn--primary zz-btn--block">Log In</button>
</form>

<p class="zz-auth__footer-links">
    New here? <a href="<?= htmlspecialchars(BASE_URL . '/signup.php', ENT_QUOTES, 'UTF-8') ?>">Create an account</a>
</p>

<?php require_once __DIR__ . '/../includes/partials/auth_footer.php'; ?>
