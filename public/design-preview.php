<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ZenZone Design Preview</title>
  <link rel="stylesheet" href="assets/css/zenzone.css">
</head>
<body>
  <main class="zz-preview">
    <div class="zz-container">
      <header class="zz-card zz-card--elevated zz-preview-header">
        <p class="zz-section-title">ZenZone Design System</p>
        <h1>Phase 1 Visual Foundation</h1>
        <p class="zz-muted">Calm, premium, wellness-oriented design tokens and reusable interface components for upcoming page integration.</p>
        <div class="zz-inline">
          <span class="zz-badge zz-badge--sage">Sage-led palette</span>
          <span class="zz-badge zz-badge--gold">Warm accent</span>
          <span class="zz-badge zz-badge--neutral">Editorial tone</span>
        </div>
      </header>

      <section class="zz-preview-section" aria-labelledby="zz-color-title">
        <p class="zz-section-title" id="zz-color-title">Design Tokens</p>
        <div class="zz-card">
          <h3>Color System</h3>
          <p class="zz-muted">Core, utility, and status colors are defined as CSS variables in <code>:root</code>.</p>
          <div class="zz-token-grid">
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-sage);"></div><p class="zz-token__name">--zz-sage</p><p class="zz-token__value">#7A9B76</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-sage-deep);"></div><p class="zz-token__name">--zz-sage-deep</p><p class="zz-token__value">#5B7A59</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-sage-wash);"></div><p class="zz-token__name">--zz-sage-wash</p><p class="zz-token__value">#E8EDE4</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-sage-wash-strong);"></div><p class="zz-token__name">--zz-sage-wash-strong</p><p class="zz-token__value">#D4DECB</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-gold);"></div><p class="zz-token__name">--zz-gold</p><p class="zz-token__value">#D4A574</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-gold-light);"></div><p class="zz-token__name">--zz-gold-light</p><p class="zz-token__value">#F4E4C8</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-bg);"></div><p class="zz-token__name">--zz-bg</p><p class="zz-token__value">#FAF8F3</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-surface);"></div><p class="zz-token__name">--zz-surface</p><p class="zz-token__value">#FFFFFF</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-surface-soft);"></div><p class="zz-token__name">--zz-surface-soft</p><p class="zz-token__value">#F5F2E9</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-ink);"></div><p class="zz-token__name">--zz-ink</p><p class="zz-token__value">#2A2E2B</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-ink-soft);"></div><p class="zz-token__name">--zz-ink-soft</p><p class="zz-token__value">#4A4F4B</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-muted);"></div><p class="zz-token__name">--zz-muted</p><p class="zz-token__value">#6B7268</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-border);"></div><p class="zz-token__name">--zz-border</p><p class="zz-token__value">#E5E1D6</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-border-strong);"></div><p class="zz-token__name">--zz-border-strong</p><p class="zz-token__value">#CEC9BA</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-success);"></div><p class="zz-token__name">--zz-success</p><p class="zz-token__value">#5B8A6B</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-warning);"></div><p class="zz-token__name">--zz-warning</p><p class="zz-token__value">#C88A3D</p></article>
            <article class="zz-token"><div class="zz-token__swatch" style="background: var(--zz-danger);"></div><p class="zz-token__name">--zz-danger</p><p class="zz-token__value">#B5553F</p></article>
          </div>
        </div>
      </section>

      <section class="zz-preview-section" aria-labelledby="zz-type-title">
        <p class="zz-section-title" id="zz-type-title">Typography</p>
        <div class="zz-grid zz-grid--2">
          <article class="zz-card">
            <h3>Display and Body Pairing</h3>
            <p style="font-family: var(--zz-font-display); font-size: var(--zz-text-xl);">Presence starts with calm attention.</p>
            <p class="zz-muted" style="margin-bottom: 0;">Inter body text keeps forms and analytics clean and readable for daily use.</p>
          </article>
          <article class="zz-card">
            <h3>Type Scale</h3>
            <div class="zz-type-list">
              <div class="zz-type-row"><p class="zz-type-label">H1</p><p class="zz-type-example" style="font-family: var(--zz-font-display); font-size: var(--zz-text-4xl); line-height: 1.25;">Focus Before Performance</p></div>
              <div class="zz-type-row"><p class="zz-type-label">H2</p><p class="zz-type-example" style="font-family: var(--zz-font-display); font-size: var(--zz-text-3xl); line-height: 1.25;">Daily Check-In</p></div>
              <div class="zz-type-row"><p class="zz-type-label">H3</p><p class="zz-type-example" style="font-family: var(--zz-font-display); font-size: var(--zz-text-2xl); line-height: 1.25;">Reflect and Reset</p></div>
              <div class="zz-type-row"><p class="zz-type-label">Body</p><p class="zz-type-example" style="font-size: var(--zz-text-base);">Steady routines build confidence over time.</p></div>
              <div class="zz-type-row"><p class="zz-type-label">Small</p><p class="zz-type-example" style="font-size: var(--zz-text-sm);">Helper text guides actions without noise.</p></div>
            </div>
          </article>
        </div>
      </section>

      <section class="zz-preview-section" aria-labelledby="zz-cards-buttons-title">
        <p class="zz-section-title" id="zz-cards-buttons-title">Cards and Buttons</p>
        <div class="zz-grid zz-grid--2">
          <article class="zz-card">
            <h3>Card</h3>
            <p class="zz-muted">Default surface for forms, trend modules, and summaries.</p>
            <hr class="zz-divider">
            <div class="zz-button-stack">
              <div class="zz-row">
                <button type="button" class="zz-btn zz-btn--primary">Primary</button>
                <button type="button" class="zz-btn zz-btn--secondary">Secondary</button>
                <button type="button" class="zz-btn zz-btn--accent">Accent</button>
                <button type="button" class="zz-btn zz-btn--ghost">Ghost</button>
                <button type="button" class="zz-btn zz-btn--danger">Danger</button>
              </div>
              <div class="zz-row">
                <button type="button" class="zz-btn zz-btn--primary zz-btn--sm">Primary Small</button>
                <button type="button" class="zz-btn zz-btn--secondary zz-btn--sm">Secondary Small</button>
                <button type="button" class="zz-btn zz-btn--accent zz-btn--lg">Accent Large</button>
              </div>
              <button type="button" class="zz-btn zz-btn--primary zz-btn--block">Block Primary</button>
            </div>
          </article>

          <article class="zz-card zz-card--elevated">
            <h3>Elevated Card</h3>
            <p class="zz-muted">Use this variant for hero cards, key summaries, or onboarding prompts.</p>
            <div class="zz-inline" style="margin-top: var(--zz-space-4);">
              <span class="zz-badge zz-badge--sage">Sage</span>
              <span class="zz-badge zz-badge--gold">Gold</span>
              <span class="zz-badge zz-badge--neutral">Neutral</span>
              <span class="zz-badge zz-badge--success">Success</span>
              <span class="zz-badge zz-badge--warning">Warning</span>
              <span class="zz-badge zz-badge--danger">Danger</span>
            </div>
          </article>
        </div>
      </section>

      <section class="zz-preview-section" aria-labelledby="zz-forms-title">
        <p class="zz-section-title" id="zz-forms-title">Standard Form Elements</p>
        <form class="zz-card" action="#" method="post" novalidate>
          <div class="zz-form-grid">
            <div>
              <div class="zz-field">
                <label class="zz-label" for="zz-standard-title">Reflection Title <span class="zz-optional-tag">Optional</span></label>
                <input class="zz-input" id="zz-standard-title" name="zz_standard_title" type="text" placeholder="How did training feel today?">
                <p class="zz-help">Use concise prompts for quicker check-ins.</p>
              </div>
              <div class="zz-field">
                <label class="zz-label" for="zz-standard-select">Session Emphasis</label>
                <select class="zz-select" id="zz-standard-select" name="zz_standard_select">
                  <option value="presence">Presence</option>
                  <option value="confidence">Confidence</option>
                  <option value="recovery">Recovery</option>
                </select>
              </div>
            </div>
            <div>
              <div class="zz-field">
                <label class="zz-label" for="zz-standard-notes">Journal Notes</label>
                <textarea class="zz-textarea" id="zz-standard-notes" name="zz_standard_notes" placeholder="Capture what helped, what distracted you, and what to repeat next time."></textarea>
              </div>
              <p class="zz-muted" style="margin-bottom: 0;">Muted utility text example for low-emphasis metadata.</p>
            </div>
          </div>
        </form>
      </section>

      <section class="zz-preview-section" aria-labelledby="zz-scale-title">
        <p class="zz-section-title" id="zz-scale-title">Distinctive Component 1: Segmented Scale</p>
        <div class="zz-card zz-stack">
          <div class="zz-field" style="margin-bottom: 0;">
            <span class="zz-label" id="zz-readiness-label">How ready do you feel right now? <span class="zz-optional-tag">1-7</span></span>
            <div class="zz-scale" data-zz-scale data-output="zz-readiness-value" role="radiogroup" aria-labelledby="zz-readiness-label">
              <input type="hidden" name="zz_readiness" value="4" data-zz-scale-input>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="1">1</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="2">2</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="3">3</button>
              <button type="button" class="zz-scale__segment is-active" role="radio" aria-checked="true" data-value="4">4</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="5">5</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="6">6</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="7">7</button>
            </div>
            <div class="zz-scale__meta">
              <span>Low readiness</span>
              <span>Selected: <strong class="zz-scale__value" id="zz-readiness-value">4</strong></span>
              <span>High readiness</span>
            </div>
            <p class="zz-help">Keyboard support: arrow keys, Home, and End.</p>
          </div>

          <div class="zz-field" style="margin-bottom: 0;">
            <span class="zz-label" id="zz-stress-label">Perceived stress level <span class="zz-optional-tag">1-10</span></span>
            <div class="zz-scale" data-zz-scale data-output="zz-stress-value" role="radiogroup" aria-labelledby="zz-stress-label">
              <input type="hidden" name="zz_stress" value="3" data-zz-scale-input>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="1">1</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="2">2</button>
              <button type="button" class="zz-scale__segment is-active" role="radio" aria-checked="true" data-value="3">3</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="4">4</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="5">5</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="6">6</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="7">7</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="8">8</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="9">9</button>
              <button type="button" class="zz-scale__segment" role="radio" aria-checked="false" data-value="10">10</button>
            </div>
            <div class="zz-scale__meta">
              <span>Calm</span>
              <span>Selected: <strong class="zz-scale__value" id="zz-stress-value">3</strong></span>
              <span>Overloaded</span>
            </div>
          </div>
        </div>
      </section>

      <section class="zz-preview-section" aria-labelledby="zz-chip-title">
        <p class="zz-section-title" id="zz-chip-title">Distinctive Component 2: Interactive Chips</p>
        <div class="zz-card zz-stack">
          <div>
            <label class="zz-label" for="zz-focus-chip-input">Focus Areas</label>
            <div class="zz-chip-group" id="zz-focus-chips" data-zz-chips data-multiple="true">
              <input id="zz-focus-chip-input" type="hidden" name="zz_focus_chips" data-zz-chip-input>
              <button type="button" class="zz-chip is-selected" aria-pressed="true" data-value="Breathwork">Breathwork</button>
              <button type="button" class="zz-chip is-selected" aria-pressed="true" data-value="Visualization">Visualization</button>
              <button type="button" class="zz-chip" aria-pressed="false" data-value="Recovery">Recovery</button>
              <button type="button" class="zz-chip" aria-pressed="false" data-value="Confidence">Confidence</button>
              <button type="button" class="zz-chip" aria-pressed="false" data-value="Composure">Composure</button>
            </div>
            <p class="zz-chip-summary">Selected tags: <span data-zz-chip-value-for="zz-focus-chips">Breathwork, Visualization</span></p>
          </div>

          <div>
            <label class="zz-label" for="zz-cadence-chip-input">Preferred Check-In Cadence</label>
            <div class="zz-chip-group" id="zz-cadence-chips" data-zz-chips data-multiple="false">
              <input id="zz-cadence-chip-input" type="hidden" name="zz_cadence_chip" data-zz-chip-input>
              <button type="button" class="zz-chip" aria-pressed="false" data-value="Daily">Daily</button>
              <button type="button" class="zz-chip is-selected" aria-pressed="true" data-value="Every Other Day">Every Other Day</button>
              <button type="button" class="zz-chip" aria-pressed="false" data-value="Twice Weekly">Twice Weekly</button>
            </div>
            <p class="zz-chip-summary">Selected cadence: <span data-zz-chip-value-for="zz-cadence-chips">Every Other Day</span></p>
          </div>
        </div>
      </section>

      <section class="zz-preview-section" aria-labelledby="zz-float-title">
        <p class="zz-section-title" id="zz-float-title">Distinctive Component 3: Floating Labels</p>
        <form class="zz-card" action="#" method="post" novalidate>
          <div class="zz-form-grid">
            <div>
              <div class="zz-field">
                <div class="zz-float" data-zz-float>
                  <input class="zz-float__control" id="zz-float-title-input" name="zz_float_title" type="text" placeholder=" " value="">
                  <label class="zz-float__label" for="zz-float-title-input">Reflection title</label>
                </div>
                <p class="zz-help">Label lifts on focus or when a value exists.</p>
              </div>

              <div class="zz-field" style="margin-bottom: 0;">
                <div class="zz-float" data-zz-float>
                  <select class="zz-float__control" id="zz-float-select" name="zz_float_select">
                    <option value=""> </option>
                    <option value="presence">Presence</option>
                    <option value="confidence">Confidence</option>
                    <option value="recovery">Recovery</option>
                  </select>
                  <label class="zz-float__label" for="zz-float-select">Session emphasis</label>
                </div>
              </div>
            </div>

            <div>
              <div class="zz-field" style="margin-bottom: 0;">
                <div class="zz-float zz-float--textarea" data-zz-float>
                  <textarea class="zz-float__control zz-float__control--textarea" id="zz-float-textarea" name="zz_float_textarea" placeholder=" "></textarea>
                  <label class="zz-float__label" for="zz-float-textarea">Journal entry</label>
                </div>
              </div>
            </div>
          </div>
        </form>
      </section>

      <section class="zz-preview-section" aria-labelledby="zz-alert-title" style="margin-bottom: 0;">
        <p class="zz-section-title" id="zz-alert-title">Alerts and Notices</p>
        <div class="zz-alert-stack">
          <div class="zz-alert zz-alert--info">Info: Baseline profile is complete and ready for your next check-in.</div>
          <div class="zz-alert zz-alert--success">Success: Your reflection was saved.</div>
          <div class="zz-alert zz-alert--warning">Warning: One score is missing for today.</div>
          <div class="zz-alert zz-alert--danger">Danger: Save failed. Please retry.</div>
        </div>
      </section>
    </div>
  </main>

  <script src="assets/js/zenzone.js"></script>
</body>
</html>

