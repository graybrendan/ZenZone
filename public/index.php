<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$authVariant = 'landing';
$pageTitle = 'ZenZone - Mindfulness for Athletes';
$pageDescription = 'ZenZone is a mindfulness and performance support app built for daily check-ins, goals, and coaching moments.';

require_once __DIR__ . '/../includes/partials/auth_header.php';
?>
<section class="zz-landing__hero">
    <p class="zz-section-title zz-landing__eyebrow">FOR ATHLETES AND ANYONE WHO TRAINS</p>
    <h1 class="zz-landing__title">Check in with yourself, one rep at a time.</h1>
    <p class="zz-landing__lede">ZenZone is a quiet space to track how you feel, set goals that matter, and reset when you need to. Built for daily use, on or off the field.</p>
    <div class="zz-landing__cta-row">
        <a class="zz-btn zz-btn--primary" href="<?= htmlspecialchars(BASE_URL . '/signup.php', ENT_QUOTES, 'UTF-8') ?>">Create your account</a>
        <a class="zz-btn zz-btn--ghost" href="<?= htmlspecialchars(BASE_URL . '/login.php', ENT_QUOTES, 'UTF-8') ?>">Log in</a>
    </div>
</section>

<section class="zz-container">
    <div class="zz-landing__features">
        <article class="zz-card zz-feature">
            <span class="zz-feature__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="m8.5 12.5 2.5 2.5 4.5-5"></path>
                </svg>
            </span>
            <h3>Daily check-ins, not a chore.</h3>
            <p>A quick pulse on how you're doing across eight areas that shape performance and wellbeing. Takes under a minute.</p>
        </article>

        <article class="zz-card zz-feature">
            <span class="zz-feature__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="8"></circle>
                    <circle cx="12" cy="12" r="4"></circle>
                    <circle cx="12" cy="12" r="1"></circle>
                </svg>
            </span>
            <h3>Goals you'll actually keep.</h3>
            <p>Set priority goals across daily, weekly, and monthly rhythms. Check them off with one tap.</p>
        </article>

        <article class="zz-card zz-feature">
            <span class="zz-feature__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v7A2.5 2.5 0 0 1 17.5 16H9l-4 4V6.5z"></path>
                    <circle cx="15.5" cy="10" r="1"></circle>
                </svg>
            </span>
            <h3>A coach in your pocket.</h3>
            <p>When something's off - pre-game nerves, a rough practice, a low stretch - get one grounded next step.</p>
        </article>
    </div>
</section>

<section class="zz-landing__capstone">
    <p>Built as a senior capstone at TODO. &copy; <?= date('Y') ?></p>
</section>

<?php require_once __DIR__ . '/../includes/partials/auth_footer.php'; ?>
