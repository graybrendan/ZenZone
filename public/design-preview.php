<?php
require_once __DIR__ . '/../includes/config.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '/ZenZone/public';
$zenzoneCssHref = rtrim($baseUrl, '/') . '/assets/css/zenzone.css';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZenZone Design Preview</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($zenzoneCssHref, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
<main class="zz-preview">
    <div class="zz-container">
        <header class="zz-preview-header">
            <p class="zz-section-title">ZenZone Design System</p>
            <h1>Phase 1 Visual Foundation</h1>
            <p class="zz-muted">Calm, premium, wellness-oriented components and tokens for upcoming page upgrades.</p>
        </header>

        <section class="zz-preview-section" aria-labelledby="color-swatches">
            <p class="zz-section-title" id="color-swatches">1. Color swatches</p>
            <div class="zz-swatch-grid">
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--sage"></div>
                    <p class="zz-swatch-name">--zz-sage</p>
                    <p class="zz-swatch-hex">#7A9B76</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--sage-deep"></div>
                    <p class="zz-swatch-name">--zz-sage-deep</p>
                    <p class="zz-swatch-hex">#5B7A59</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--sage-wash"></div>
                    <p class="zz-swatch-name">--zz-sage-wash</p>
                    <p class="zz-swatch-hex">#E8EDE4</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--gold"></div>
                    <p class="zz-swatch-name">--zz-gold</p>
                    <p class="zz-swatch-hex">#D4A574</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--gold-light"></div>
                    <p class="zz-swatch-name">--zz-gold-light</p>
                    <p class="zz-swatch-hex">#F4E4C8</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--bg"></div>
                    <p class="zz-swatch-name">--zz-bg</p>
                    <p class="zz-swatch-hex">#FAF8F3</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--surface"></div>
                    <p class="zz-swatch-name">--zz-surface</p>
                    <p class="zz-swatch-hex">#FFFFFF</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--ink"></div>
                    <p class="zz-swatch-name">--zz-ink</p>
                    <p class="zz-swatch-hex">#2A2E2B</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--ink-soft"></div>
                    <p class="zz-swatch-name">--zz-ink-soft</p>
                    <p class="zz-swatch-hex">#4A4F4B</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--muted"></div>
                    <p class="zz-swatch-name">--zz-muted</p>
                    <p class="zz-swatch-hex">#6B7268</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--border"></div>
                    <p class="zz-swatch-name">--zz-border</p>
                    <p class="zz-swatch-hex">#E5E1D6</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--border-strong"></div>
                    <p class="zz-swatch-name">--zz-border-strong</p>
                    <p class="zz-swatch-hex">#CEC9BA</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--success"></div>
                    <p class="zz-swatch-name">--zz-success</p>
                    <p class="zz-swatch-hex">#5B8A6B</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--warning"></div>
                    <p class="zz-swatch-name">--zz-warning</p>
                    <p class="zz-swatch-hex">#C88A3D</p>
                </article>
                <article class="zz-swatch">
                    <div class="zz-swatch-chip zz-swatch-chip--danger"></div>
                    <p class="zz-swatch-name">--zz-danger</p>
                    <p class="zz-swatch-hex">#B5553F</p>
                </article>
            </div>
        </section>

        <section class="zz-preview-section" aria-labelledby="type-scale">
            <p class="zz-section-title" id="type-scale">2. Typography scale</p>
            <div class="zz-card">
                <p class="zz-type-sample-display">ZenZone helps athletes check in with themselves.</p>
                <p class="zz-type-sample-body">ZenZone helps athletes check in with themselves.</p>
                <hr class="zz-divider">
                <h1>Heading 1 · Fraunces Display</h1>
                <h2>Heading 2 · Fraunces Display</h2>
                <h3>Heading 3 · Fraunces Display</h3>
                <h4>Heading 4 · Fraunces Display</h4>
                <h5>Heading 5 · Fraunces Display</h5>
                <h6>Heading 6 · Fraunces Display</h6>
                <p>Body text · Inter · ZenZone helps athletes check in with themselves.</p>
                <p><small>Small text · Inter · ZenZone helps athletes check in with themselves.</small></p>
            </div>
        </section>

        <section class="zz-preview-section" aria-labelledby="button-variants">
            <p class="zz-section-title" id="button-variants">3. Buttons</p>
            <div class="zz-card">
                <div class="zz-button-row">
                    <button class="zz-btn zz-btn--primary" type="button">Primary</button>
                    <button class="zz-btn zz-btn--secondary" type="button">Secondary</button>
                    <button class="zz-btn zz-btn--accent" type="button">Accent</button>
                    <button class="zz-btn zz-btn--ghost" type="button">Ghost</button>
                    <button class="zz-btn zz-btn--danger" type="button">Danger</button>
                </div>
                <div class="zz-button-row">
                    <button class="zz-btn zz-btn--primary zz-btn--sm" type="button">Primary Small</button>
                    <button class="zz-btn zz-btn--secondary zz-btn--sm" type="button">Secondary Small</button>
                    <button class="zz-btn zz-btn--accent zz-btn--lg" type="button">Accent Large</button>
                    <button class="zz-btn zz-btn--ghost zz-btn--lg" type="button">Ghost Large</button>
                </div>
                <div class="zz-button-row zz-button-row--column">
                    <button class="zz-btn zz-btn--primary zz-btn--block" type="button">Block Primary</button>
                    <button class="zz-btn zz-btn--secondary zz-btn--block" type="button">Block Secondary</button>
                </div>
                <div class="zz-button-row">
                    <button class="zz-btn zz-btn--primary" type="button" disabled>Primary Disabled</button>
                    <button class="zz-btn zz-btn--secondary" type="button" disabled>Secondary Disabled</button>
                    <button class="zz-btn zz-btn--accent" type="button" disabled>Accent Disabled</button>
                </div>
            </div>
        </section>

        <section class="zz-preview-section" aria-labelledby="form-elements">
            <p class="zz-section-title" id="form-elements">4. Form fields</p>
            <form class="zz-card" action="#" method="post">
                <div class="zz-form-layout zz-form-layout--2">
                    <div>
                        <div class="zz-field">
                            <label class="zz-label" for="preview-input">Training reflection title <span class="zz-optional-tag">Optional</span></label>
                            <input class="zz-input" id="preview-input" name="preview-input" type="text" placeholder="How did practice feel today?">
                            <p class="zz-help">Clear and short labels keep check-ins fast and focused.</p>
                        </div>

                        <div class="zz-field">
                            <label class="zz-label" for="preview-select">Session emphasis</label>
                            <select class="zz-select" id="preview-select" name="preview-select">
                                <option value="presence">Presence</option>
                                <option value="confidence">Confidence</option>
                                <option value="energy">Energy</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <div class="zz-field">
                            <label class="zz-label" for="preview-textarea">Coach notes</label>
                            <textarea class="zz-textarea" id="preview-textarea" name="preview-textarea" placeholder="Write a short reminder for your next session."></textarea>
                        </div>

                        <div class="zz-field">
                            <label class="zz-label" for="preview-range">Readiness score (1-7)</label>
                            <div class="zz-range-row">
                                <input class="zz-range js-zz-range" id="preview-range" name="preview-range" type="range" min="1" max="7" value="4">
                                <output class="zz-range-value" for="preview-range" data-range-output="preview-range">4</output>
                            </div>
                            <p class="zz-help">1 = low readiness, 7 = fully ready.</p>
                        </div>
                    </div>
                </div>

                <div class="zz-divider"></div>

                <div class="zz-form-layout zz-form-layout--2">
                    <div class="zz-field">
                        <span class="zz-label">Focus options</span>
                        <div class="zz-inline-options">
                            <label class="zz-choice" for="preview-checkbox-1">
                                <input class="zz-check" id="preview-checkbox-1" type="checkbox" name="focus-options[]" value="breathing">
                                Breathing drills
                            </label>
                            <label class="zz-choice" for="preview-checkbox-2">
                                <input class="zz-check" id="preview-checkbox-2" type="checkbox" name="focus-options[]" value="visualization">
                                Visualization
                            </label>
                        </div>
                    </div>

                    <div class="zz-field">
                        <span class="zz-label">Primary mood</span>
                        <div class="zz-inline-options">
                            <label class="zz-choice" for="preview-radio-1">
                                <input class="zz-radio" id="preview-radio-1" type="radio" name="primary-mood" value="calm" checked>
                                Calm
                            </label>
                            <label class="zz-choice" for="preview-radio-2">
                                <input class="zz-radio" id="preview-radio-2" type="radio" name="primary-mood" value="focused">
                                Focused
                            </label>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        <section class="zz-preview-section" aria-labelledby="cards-preview">
            <p class="zz-section-title" id="cards-preview">5. Cards</p>
            <div class="zz-preview-grid zz-preview-grid--2">
                <article class="zz-card">
                    <h4>Default Card</h4>
                    <p class="zz-muted">Use for primary content surfaces and low visual intensity sections.</p>
                </article>
                <article class="zz-card zz-card--elevated">
                    <h4>Elevated Card</h4>
                    <p class="zz-muted">Use sparingly when you need stronger visual hierarchy.</p>
                </article>
            </div>
        </section>

        <section class="zz-preview-section" aria-labelledby="badges-preview">
            <p class="zz-section-title" id="badges-preview">6. Badges</p>
            <div class="zz-card">
                <div class="zz-badge-row">
                    <span class="zz-badge zz-badge--sage">Sage</span>
                    <span class="zz-badge zz-badge--gold">Gold</span>
                    <span class="zz-badge zz-badge--neutral">Neutral</span>
                    <span class="zz-badge zz-badge--success">Success</span>
                    <span class="zz-badge zz-badge--warning">Warning</span>
                    <span class="zz-badge zz-badge--danger">Danger</span>
                </div>
            </div>
        </section>

        <section class="zz-preview-section" aria-labelledby="alerts-preview">
            <p class="zz-section-title" id="alerts-preview">7. Alerts / notices</p>
            <div class="zz-alert-stack">
                <div class="zz-alert zz-alert--info">Info: Your next check-in is available when you finish this reflection.</div>
                <div class="zz-alert zz-alert--success">Success: Baseline data saved and ready for trend tracking.</div>
                <div class="zz-alert zz-alert--warning">Warning: You are missing one dimension score for today.</div>
                <div class="zz-alert zz-alert--danger">Danger: Unable to save check-in. Please retry.</div>
            </div>
        </section>

        <section class="zz-preview-section" aria-labelledby="sample-composition">
            <p class="zz-section-title" id="sample-composition">8. Sample page composition</p>
            <article class="zz-card zz-card--elevated">
                <p class="zz-section-title">Baseline preview</p>
                <h3>How present do you feel before training today?</h3>
                <p class="zz-sample-meta">Choose a value that best represents your current state in this moment.</p>

                <div class="zz-field">
                    <label class="zz-label" for="sample-range">Presence score <span class="zz-optional-tag">1-7</span></label>
                    <div class="zz-range-row">
                        <input class="zz-range js-zz-range" id="sample-range" name="sample-range" type="range" min="1" max="7" value="5">
                        <output class="zz-range-value" for="sample-range" data-range-output="sample-range">5</output>
                    </div>
                    <p class="zz-help">Dimension note: Presence reflects how grounded, clear, and attentive you feel before activity.</p>
                </div>

                <hr class="zz-divider">

                <div class="zz-button-row">
                    <button class="zz-btn zz-btn--secondary" type="button">Save Draft</button>
                    <button class="zz-btn zz-btn--primary" type="button">Submit Check-In</button>
                </div>
            </article>
        </section>
    </div>
</main>

<script>
(function () {
    var ranges = document.querySelectorAll('.js-zz-range');

    ranges.forEach(function (rangeInput) {
        var output = document.querySelector('[data-range-output="' + rangeInput.id + '"]');

        var syncRangeState = function () {
            var minValue = Number(rangeInput.min || 0);
            var maxValue = Number(rangeInput.max || 100);
            var currentValue = Number(rangeInput.value || minValue);
            var span = maxValue - minValue;
            var percent = span > 0 ? ((currentValue - minValue) * 100) / span : 0;

            rangeInput.style.setProperty('--zz-range-percent', percent + '%');

            if (output) {
                output.textContent = String(currentValue);
            }
        };

        rangeInput.addEventListener('input', syncRangeState);
        syncRangeState();
    });
})();
</script>
</body>
</html>
