<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

requireGuest();

$authVariant = 'card';
$pageTitle = 'Sign Up';
$pageDescription = 'Create your ZenZone account to start check-ins, goals, and coach support.';

$authMessage = getAuthPageMessage('signup', $_GET);
$errorCode = trim((string) ($_GET['error'] ?? ''));

if ($authMessage !== '') {
    $flashType = $errorCode === 'invalid_input' ? 'warning' : 'danger';
    setFlashMessage($flashType, $authMessage);
}

$oldFirstName = trim((string) getOldInput('first_name', ''));
$oldFullName = trim((string) getOldInput('full_name', ''));
$oldEmail = trim((string) getOldInput('email', ''));
clearOldInput();
?>
<?php require_once __DIR__ . '/../includes/partials/auth_header.php'; ?>

<h1>Create your account</h1>
<p class="zz-auth__subtitle">One minute now, a calmer practice later.</p>

<form method="post" action="<?= htmlspecialchars(BASE_URL . '/api/auth/register.php', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="zz-field zz-float" data-zz-float>
        <input
            type="text"
            id="first_name"
            name="first_name"
            class="zz-float__control"
            placeholder=" "
            autocomplete="given-name"
            maxlength="60"
            required
            value="<?= htmlspecialchars($oldFirstName, ENT_QUOTES, 'UTF-8') ?>"
        >
        <label class="zz-float__label" for="first_name">First name</label>
    </div>

    <div class="zz-field zz-float" data-zz-float>
        <input
            type="text"
            id="full_name"
            name="full_name"
            class="zz-float__control"
            placeholder=" "
            autocomplete="name"
            required
            value="<?= htmlspecialchars($oldFullName, ENT_QUOTES, 'UTF-8') ?>"
        >
        <label class="zz-float__label" for="full_name">Full name</label>
    </div>

    <div class="zz-field zz-float" data-zz-float>
        <input
            type="email"
            id="email"
            name="email"
            class="zz-float__control"
            placeholder=" "
            autocomplete="email"
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
            autocomplete="new-password"
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
    <p class="zz-help">At least 8 characters.</p>

    <button type="submit" class="zz-btn zz-btn--primary zz-btn--block">Create Account</button>
</form>

<p class="zz-auth__footer-links">
    Already have an account? <a href="<?= htmlspecialchars(BASE_URL . '/login.php', ENT_QUOTES, 'UTF-8') ?>">Log in</a>
</p>

<?php require_once __DIR__ . '/../includes/partials/auth_footer.php'; ?>
