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
    <p class="zz-section-title zz-landing__eyebrow">NEW HERE? START HERE</p>
    <h1 class="zz-landing__title">What ZenZone is, and exactly how it helps.</h1>
    <p class="zz-landing__lede">ZenZone is a daily mental performance app. You log quick check-ins, get a ZenScore, track trends over time, and get one clear next action from Coach or Lessons when you need a reset.</p>
    <div class="zz-landing__cta-row">
        <a class="zz-btn zz-btn--primary" href="<?= htmlspecialchars(BASE_URL . '/signup.php', ENT_QUOTES, 'UTF-8') ?>">Create your account</a>
        <a class="zz-btn zz-btn--ghost" href="<?= htmlspecialchars(BASE_URL . '/login.php', ENT_QUOTES, 'UTF-8') ?>">Log in</a>
    </div>
</section>

<section class="zz-container zz-landing__section zz-landing__section--install-banner" aria-label="Install ZenZone">
    <div class="zz-alert zz-alert--info zz-install-banner" data-zz-install-banner hidden>
        <div class="zz-install-banner__content">
            <p class="zz-install-banner__title">Save ZenZone to your home screen</p>
            <p class="zz-install-banner__text" data-zz-install-copy>Use your browser menu and choose Add to Home Screen for one-tap daily access.</p>
        </div>
        <div class="zz-install-banner__actions">
            <button type="button" class="zz-btn zz-btn--primary zz-btn--sm" data-zz-install-action hidden>Install</button>
            <button type="button" class="zz-btn zz-btn--ghost zz-btn--sm" data-zz-install-dismiss>Dismiss</button>
        </div>
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

<section class="zz-container zz-landing__section" aria-labelledby="zz-landing-about-title">
    <article class="zz-card zz-landing__info-card">
        <p class="zz-section-title zz-landing__eyebrow">START HERE</p>
        <h2 id="zz-landing-about-title">What is ZenZone?</h2>
        <p>ZenZone is a simple daily system for mental performance. You check in, track patterns, and take one clear next step so you can stay consistent during training and competition.</p>

        <div class="zz-grid zz-grid--3">
            <section>
                <h3>After each check-in</h3>
                <p>You get a ZenScore plus confidence, focus, recovery, and stress signals you can compare over time.</p>
            </section>
            <section>
                <h3>On your dashboard</h3>
                <p>You see today's status, your active goals, and one-tap actions for Coach, Lessons, and Trends.</p>
            </section>
            <section>
                <h3>In Trends</h3>
                <p>You get line charts and recent data points, so your patterns are visible instead of guesswork.</p>
            </section>
        </div>

        <div class="zz-grid zz-grid--2">
            <div>
                <h3>What you do</h3>
                <ul class="zz-landing__list">
                    <li>Complete your baseline once.</li>
                    <li>Log a daily check-in in under a minute.</li>
                    <li>Create daily, weekly, and monthly goals.</li>
                    <li>Use Coach and Lessons when you need a reset.</li>
                </ul>
            </div>
            <div>
                <h3>What you get</h3>
                <ul class="zz-landing__list">
                    <li>A daily ZenScore and trend view over time.</li>
                    <li>A clearer picture of confidence, focus, recovery, and stress.</li>
                    <li>Practical next actions from Coach and Lessons instead of guesswork.</li>
                    <li>A routine you can actually stick with.</li>
                </ul>
            </div>
        </div>
    </article>
</section>

<section class="zz-container zz-landing__section" aria-labelledby="zz-landing-walkthrough-title">
    <article class="zz-card zz-landing__info-card">
        <p class="zz-section-title zz-landing__eyebrow">FIRST SESSION WALKTHROUGH</p>
        <h2 id="zz-landing-walkthrough-title">Your first 5 minutes in ZenZone</h2>
        <ol class="zz-landing__steps">
            <li>Create your account and complete your baseline.</li>
            <li>Run your first check-in to generate your first ZenScore.</li>
            <li>Create one priority goal you can complete this week.</li>
            <li>Open Trends and confirm your first data point appears.</li>
            <li>Open Coach and save one recommendation for later.</li>
        </ol>
    </article>
</section>

<section class="zz-container zz-landing__section" aria-labelledby="zz-landing-home-screen-title">
    <article class="zz-card zz-landing__info-card">
        <p class="zz-section-title zz-landing__eyebrow">SAVE TO HOME SCREEN</p>
        <h2 id="zz-landing-home-screen-title">Add ZenZone like an app</h2>
        <p>Use this address for your saved shortcut and bookmark: <a href="https://zenzone.up.railway.app/" target="_blank" rel="noopener noreferrer">https://zenzone.up.railway.app/</a></p>
        <p>Tip: install/save from your phone browser first so ZenZone is always one tap away from your home screen.</p>

        <div class="zz-grid zz-grid--3">
            <section>
                <h3>iPhone (Safari)</h3>
                <ol class="zz-landing__steps zz-landing__steps--compact">
                    <li>Open ZenZone in Safari.</li>
                    <li>Tap the Share button.</li>
                    <li>Tap <strong>Add to Home Screen</strong>.</li>
                    <li>Tap <strong>Add</strong> in the top-right.</li>
                </ol>
            </section>
            <section>
                <h3>Android (Chrome)</h3>
                <ol class="zz-landing__steps zz-landing__steps--compact">
                    <li>Open ZenZone in Chrome.</li>
                    <li>Tap the 3-dot menu.</li>
                    <li>Tap <strong>Add to Home screen</strong> or <strong>Install app</strong>.</li>
                    <li>Confirm by tapping <strong>Add</strong> or <strong>Install</strong>.</li>
                </ol>
            </section>
            <section>
                <h3>Desktop (Chrome/Edge)</h3>
                <ol class="zz-landing__steps zz-landing__steps--compact">
                    <li>Open ZenZone in Chrome or Edge.</li>
                    <li>Click the install icon in the address bar (or open the browser menu).</li>
                    <li>Choose <strong>Install ZenZone</strong> / <strong>Apps &gt; Install this site as an app</strong>.</li>
                    <li>Pin it to taskbar/dock for fast daily access.</li>
                </ol>
            </section>
        </div>
    </article>
</section>

<section class="zz-landing__capstone">
    <p>Built as a senior capstone project. &copy; <?= date('Y') ?></p>
</section>

<?php require_once __DIR__ . '/../includes/partials/auth_footer.php'; ?>
