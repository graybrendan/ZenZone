(function () {
  'use strict';

  function clampIndex(index, length) {
    if (length <= 0) {
      return 0;
    }

    if (index < 0) {
      return 0;
    }

    if (index >= length) {
      return length - 1;
    }

    return index;
  }

  function initScale(scale) {
    var segments = Array.prototype.slice.call(
      scale.querySelectorAll('.zz-scale__segment[data-value]')
    );
    var hiddenInput = scale.querySelector('[data-zz-scale-input]');
    var outputId = scale.getAttribute('data-output');
    var output = outputId ? document.getElementById(outputId) : null;

    if (!segments.length) {
      return;
    }

    scale.style.setProperty('--zz-scale-columns', String(segments.length));

    if (!scale.hasAttribute('role')) {
      scale.setAttribute('role', 'radiogroup');
    }

    function setActiveByIndex(nextIndex, shouldFocus) {
      var activeIndex = clampIndex(nextIndex, segments.length);
      var activeSegment = segments[activeIndex];
      var activeValue = activeSegment.getAttribute('data-value') || '';

      segments.forEach(function (segment, index) {
        var isActive = index === activeIndex;
        segment.classList.toggle('is-active', isActive);
        segment.setAttribute('aria-checked', isActive ? 'true' : 'false');
        segment.tabIndex = isActive ? 0 : -1;
      });

      if (hiddenInput) {
        hiddenInput.value = activeValue;
      }

      if (output) {
        output.textContent = activeValue;
      }

      if (shouldFocus) {
        activeSegment.focus();
      }
    }

    var initialIndex = segments.findIndex(function (segment) {
      if (hiddenInput && hiddenInput.value !== '') {
        return segment.getAttribute('data-value') === hiddenInput.value;
      }

      return (
        segment.classList.contains('is-active') ||
        segment.getAttribute('aria-checked') === 'true'
      );
    });

    if (initialIndex < 0) {
      initialIndex = 0;
    }

    setActiveByIndex(initialIndex, false);

    segments.forEach(function (segment, index) {
      segment.addEventListener('click', function () {
        setActiveByIndex(index, false);
      });

      segment.addEventListener('keydown', function (event) {
        var activeIndex = segments.findIndex(function (item) {
          return item.getAttribute('aria-checked') === 'true';
        });

        if (activeIndex < 0) {
          activeIndex = 0;
        }

        if (event.key === 'ArrowRight' || event.key === 'ArrowUp') {
          event.preventDefault();
          setActiveByIndex(activeIndex + 1, true);
          return;
        }

        if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
          event.preventDefault();
          setActiveByIndex(activeIndex - 1, true);
          return;
        }

        if (event.key === 'Home') {
          event.preventDefault();
          setActiveByIndex(0, true);
          return;
        }

        if (event.key === 'End') {
          event.preventDefault();
          setActiveByIndex(segments.length - 1, true);
        }
      });
    });
  }

  function initScales() {
    var scales = document.querySelectorAll('[data-zz-scale]');
    scales.forEach(initScale);
  }

  function syncRadioScaleSelection(radios) {
    if (!radios.length) {
      return;
    }

    var selectedIndex = radios.findIndex(function (radio) {
      return radio.checked;
    });

    if (selectedIndex < 0) {
      selectedIndex = 0;
      radios[0].checked = true;
    }

    radios.forEach(function (radio, index) {
      var isSelected = index === selectedIndex;
      var pill = radio.closest('.zz-scale__pill');

      if (pill) {
        pill.classList.toggle('is-selected', isSelected);
      }
    });
  }

  function setRadioScaleByIndex(radios, nextIndex, shouldFocus) {
    if (!radios.length) {
      return;
    }

    var selectedIndex = clampIndex(nextIndex, radios.length);
    var nextRadio = radios[selectedIndex];

    if (!nextRadio) {
      return;
    }

    nextRadio.checked = true;
    nextRadio.dispatchEvent(new Event('change', { bubbles: true }));

    if (shouldFocus) {
      nextRadio.focus();
    }
  }

  function initRadioScale(scale) {
    var radios = Array.prototype.slice.call(
      scale.querySelectorAll('.zz-scale__pill input[type="radio"]')
    );
    var track = scale.querySelector('.zz-scale__track');

    if (!radios.length) {
      return;
    }

    if (track && !track.hasAttribute('role')) {
      track.setAttribute('role', 'radiogroup');
    }

    syncRadioScaleSelection(radios);

    radios.forEach(function (radio, index) {
      radio.addEventListener('change', function () {
        syncRadioScaleSelection(radios);
      });

      radio.addEventListener('keydown', function (event) {
        if (
          event.key !== 'ArrowRight' &&
          event.key !== 'ArrowUp' &&
          event.key !== 'ArrowLeft' &&
          event.key !== 'ArrowDown' &&
          event.key !== 'Home' &&
          event.key !== 'End'
        ) {
          return;
        }

        event.preventDefault();

        if (event.key === 'ArrowRight' || event.key === 'ArrowUp') {
          setRadioScaleByIndex(radios, index + 1, true);
          return;
        }

        if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
          setRadioScaleByIndex(radios, index - 1, true);
          return;
        }

        if (event.key === 'Home') {
          setRadioScaleByIndex(radios, 0, true);
          return;
        }

        if (event.key === 'End') {
          setRadioScaleByIndex(radios, radios.length - 1, true);
        }
      });
    });
  }

  function initRadioScales() {
    var scales = document.querySelectorAll('.zz-scale');
    scales.forEach(initRadioScale);
  }

  function initChipGroup(group) {
    var chips = Array.prototype.slice.call(
      group.querySelectorAll('.zz-chip[data-value]')
    );

    if (!chips.length) {
      return;
    }

    var isMultiple = group.getAttribute('data-multiple') !== 'false';
    var output = group.querySelector('[data-zz-chip-input]');
    var summary = null;

    if (group.id) {
      summary = document.querySelector('[data-zz-chip-value-for="' + group.id + '"]');
    }

    function setChipState(chip, isSelected) {
      chip.classList.toggle('is-selected', isSelected);
      chip.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    }

    function selectedValues() {
      return chips
        .filter(function (chip) {
          return chip.getAttribute('aria-pressed') === 'true';
        })
        .map(function (chip) {
          return chip.getAttribute('data-value') || '';
        })
        .filter(Boolean);
    }

    function syncOutput() {
      var values = selectedValues();

      if (output) {
        output.value = values.join(',');
      }

      if (summary) {
        summary.textContent = values.length ? values.join(', ') : 'None';
      }
    }

    chips.forEach(function (chip) {
      var isSelected =
        chip.classList.contains('is-selected') ||
        chip.getAttribute('aria-pressed') === 'true';

      setChipState(chip, isSelected);

      chip.addEventListener('click', function () {
        if (isMultiple) {
          setChipState(chip, chip.getAttribute('aria-pressed') !== 'true');
        } else {
          chips.forEach(function (item) {
            setChipState(item, item === chip);
          });
        }

        syncOutput();
      });
    });

    if (!isMultiple && !chips.some(function (chip) {
      return chip.getAttribute('aria-pressed') === 'true';
    })) {
      setChipState(chips[0], true);
    }

    syncOutput();
  }

  function initChipGroups() {
    var chipGroups = document.querySelectorAll('[data-zz-chips]');
    chipGroups.forEach(initChipGroup);
  }

  function isControlFilled(control) {
    if (!control) {
      return false;
    }

    if (control.tagName === 'SELECT') {
      return control.value !== '';
    }

    return control.value.trim() !== '';
  }

  function initFloatingField(wrapper) {
    var control = wrapper.querySelector('.zz-float__control');

    if (!control) {
      return;
    }

    function syncFilledState() {
      wrapper.classList.toggle('is-filled', isControlFilled(control));
    }

    control.addEventListener('input', syncFilledState);
    control.addEventListener('change', syncFilledState);
    control.addEventListener('blur', syncFilledState);

    syncFilledState();
    window.setTimeout(syncFilledState, 120);
  }

  function initFloatingFields() {
    var fields = document.querySelectorAll('[data-zz-float]');
    fields.forEach(initFloatingField);
  }

  /* ============ App shell behaviors ============ */
  function initAppbarShadow() {
    var appbar = document.querySelector('.zz-appbar');
    if (!appbar) {
      return;
    }

    var isTicking = false;

    function syncAppbarState() {
      appbar.classList.toggle('is-scrolled', window.scrollY > 4);
      isTicking = false;
    }

    function onScroll() {
      if (isTicking) {
        return;
      }

      isTicking = true;
      window.requestAnimationFrame(syncAppbarState);
    }

    syncAppbarState();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  function closeAppbarMenu(wrap) {
    var toggle = wrap.querySelector('[data-zz-menu-toggle]');
    var panel = wrap.querySelector('[data-zz-menu-panel]');

    if (!toggle || !panel) {
      return;
    }

    toggle.setAttribute('aria-expanded', 'false');
    panel.hidden = true;
  }

  function initAppbarMenu() {
    var menuWraps = Array.prototype.slice.call(
      document.querySelectorAll('[data-zz-menu-wrap]')
    );

    if (!menuWraps.length) {
      return;
    }

    menuWraps.forEach(function (wrap) {
      var toggle = wrap.querySelector('[data-zz-menu-toggle]');
      var panel = wrap.querySelector('[data-zz-menu-panel]');

      if (!toggle || !panel) {
        return;
      }

      toggle.addEventListener('click', function () {
        var isOpen = toggle.getAttribute('aria-expanded') === 'true';

        menuWraps.forEach(function (item) {
          if (item !== wrap) {
            closeAppbarMenu(item);
          }
        });

        toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        panel.hidden = isOpen;
      });
    });

    document.addEventListener('click', function (event) {
      menuWraps.forEach(function (wrap) {
        if (!wrap.contains(event.target)) {
          closeAppbarMenu(wrap);
        }
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') {
        return;
      }

      menuWraps.forEach(closeAppbarMenu);
    });
  }

  function normalizeToastVariant(rawType) {
    var type = String(rawType || 'info').toLowerCase();

    if (type === 'error') {
      return 'danger';
    }

    if (['success', 'info', 'warning', 'danger'].indexOf(type) === -1) {
      return 'info';
    }

    return type;
  }

  function dismissToast(toast) {
    if (!toast || toast.classList.contains('is-dismissing')) {
      return;
    }

    toast.classList.add('is-dismissing');

    window.setTimeout(function () {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 230);
  }

  function scheduleToastDismiss(toast) {
    if (!toast || toast.getAttribute('data-zz-auto-dismiss') === 'true') {
      return;
    }

    toast.setAttribute('data-zz-auto-dismiss', 'true');

    window.setTimeout(function () {
      dismissToast(toast);
    }, 4500);
  }

  function createIconUse(symbolId, svgClass) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', svgClass);
    svg.setAttribute('aria-hidden', 'true');

    var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', symbolId);
    use.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', symbolId);

    svg.appendChild(use);
    return svg;
  }

  function createToastElement(rawType, message) {
    var variant = normalizeToastVariant(rawType);
    var toast = document.createElement('div');
    toast.className = 'zz-toast zz-toast--' + variant;
    toast.setAttribute(
      'role',
      variant === 'warning' || variant === 'danger' ? 'alert' : 'status'
    );

    var iconWrap = document.createElement('span');
    iconWrap.className = 'zz-toast__icon';
    iconWrap.appendChild(createIconUse('#icon-check', 'zz-toast__icon-svg'));

    var text = document.createElement('p');
    text.className = 'zz-toast__text';
    text.textContent = String(message || '').trim();

    var closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'zz-toast__close';
    closeButton.setAttribute('aria-label', 'Dismiss');
    closeButton.appendChild(createIconUse('#icon-close', 'zz-toast__close-icon'));

    toast.appendChild(iconWrap);
    toast.appendChild(text);
    toast.appendChild(closeButton);

    return toast;
  }

  function showToast(rawType, message) {
    var flashRegion = document.querySelector('.zz-flash-region .zz-container');
    var normalizedMessage = String(message || '').trim();

    if (!flashRegion || normalizedMessage === '') {
      return;
    }

    var toast = createToastElement(rawType, normalizedMessage);
    flashRegion.appendChild(toast);
    scheduleToastDismiss(toast);
  }

  function initFlashToasts() {
    var toasts = document.querySelectorAll('.zz-toast');
    toasts.forEach(scheduleToastDismiss);

    document.addEventListener('click', function (event) {
      var closeButton = event.target.closest('.zz-toast__close');
      if (!closeButton) {
        return;
      }

      dismissToast(closeButton.closest('.zz-toast'));
    });
  }

  function initPreviewToastTriggers() {
    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-zz-toast-trigger]');
      if (!trigger) {
        return;
      }

      event.preventDefault();
      showToast(
        trigger.getAttribute('data-zz-toast-type') || 'info',
        trigger.getAttribute('data-zz-toast-message') || 'Notification'
      );
    });
  }

  function inferNavKeyFromPathname(pathname) {
    var path = String(pathname || '').toLowerCase();

    if (path.indexOf('/checkin.php') !== -1) {
      return 'checkin';
    }

    if (path.indexOf('/goals/') !== -1) {
      return 'goals';
    }

    if (path.indexOf('/coach/') !== -1) {
      return 'coach';
    }

    if (path.indexOf('/content/') !== -1) {
      return 'lessons';
    }

    return 'home';
  }

  function applyFallbackActiveState(key, selector) {
    var activeItem = document.querySelector(
      selector + '[data-zz-nav-key="' + key + '"]'
    );

    if (!activeItem) {
      return;
    }

    activeItem.classList.add('is-active');
    activeItem.setAttribute('aria-current', 'page');
  }

  function initNavFallback() {
    var bottomNavItems = Array.prototype.slice.call(
      document.querySelectorAll('.zz-bottomnav__item[data-zz-nav-key]')
    );
    var desktopNavItems = Array.prototype.slice.call(
      document.querySelectorAll('.zz-appbar__nav-link[data-zz-nav-key]')
    );
    var mobileNavItems = Array.prototype.slice.call(
      document.querySelectorAll('.zz-appbar__mobile-nav-link[data-zz-nav-key]')
    );

    if (!bottomNavItems.length && !desktopNavItems.length && !mobileNavItems.length) {
      return;
    }

    var hasActive = bottomNavItems.concat(desktopNavItems, mobileNavItems).some(function (item) {
      return (
        item.classList.contains('is-active') ||
        item.getAttribute('aria-current') === 'page'
      );
    });

    if (hasActive) {
      return;
    }

    var fallbackKey = inferNavKeyFromPathname(window.location.pathname);
    applyFallbackActiveState(fallbackKey, '.zz-bottomnav__item');
    applyFallbackActiveState(fallbackKey, '.zz-appbar__nav-link');
    applyFallbackActiveState(fallbackKey, '.zz-appbar__mobile-nav-link');
  }

  function isIOSDevice() {
    var userAgent = String(window.navigator.userAgent || '');
    var platform = String(window.navigator.platform || '');
    var maxTouchPoints = Number(window.navigator.maxTouchPoints || 0);

    return /iP(hone|ad|od)/.test(userAgent) || (platform === 'MacIntel' && maxTouchPoints > 1);
  }

  function isStandaloneDisplayMode() {
    var legacyStandalone = Boolean(window.navigator.standalone);
    var mediaStandalone =
      typeof window.matchMedia === 'function' &&
      window.matchMedia('(display-mode: standalone)').matches;

    return legacyStandalone || mediaStandalone;
  }

  function initPullToRefresh() {
    if (!isIOSDevice() || !isStandaloneDisplayMode()) {
      return;
    }

    var scrollRoot = document.scrollingElement || document.documentElement;
    if (!scrollRoot || !document.body) {
      return;
    }

    var indicator = document.createElement('div');
    indicator.className = 'zz-ptr';
    indicator.setAttribute('role', 'status');
    indicator.setAttribute('aria-live', 'polite');
    indicator.setAttribute('aria-atomic', 'true');
    indicator.innerHTML =
      '<span class="zz-ptr__icon" aria-hidden="true"></span>' +
      '<span class="zz-ptr__text">Pull to refresh</span>';
    document.body.appendChild(indicator);

    var textNode = indicator.querySelector('.zz-ptr__text');
    var maxPullDistance = 116;
    var triggerDistance = 72;
    var startY = 0;
    var pullDistance = 0;
    var active = false;
    var refreshing = false;

    function setMessage(message) {
      if (textNode) {
        textNode.textContent = String(message || '');
      }
    }

    function setOffset(distance) {
      indicator.style.setProperty('--zz-ptr-offset', String(Math.max(0, distance)) + 'px');
    }

    function resetIndicator() {
      pullDistance = 0;
      setOffset(0);
      setMessage('Pull to refresh');
      indicator.classList.remove('is-release', 'is-refreshing');

      window.setTimeout(function () {
        if (!refreshing) {
          indicator.classList.remove('is-visible');
        }
      }, 140);
    }

    function shouldIgnoreTarget(target) {
      if (!target || !target.closest) {
        return false;
      }

      return Boolean(
        target.closest(
          'input, textarea, select, button, [contenteditable="true"], .zz-appbar, .zz-appbar__dropdown, [data-zz-menu-panel]'
        )
      );
    }

    document.addEventListener(
      'touchstart',
      function (event) {
        if (refreshing || active) {
          return;
        }

        if (!event.touches || event.touches.length !== 1) {
          return;
        }

        if (scrollRoot.scrollTop > 0) {
          return;
        }

        if (shouldIgnoreTarget(event.target)) {
          return;
        }

        startY = event.touches[0].clientY;
        pullDistance = 0;
        active = true;
        indicator.classList.add('is-visible');
        indicator.classList.remove('is-release');
        setMessage('Pull to refresh');
        setOffset(0);
      },
      { passive: true }
    );

    document.addEventListener(
      'touchmove',
      function (event) {
        if (!active || refreshing) {
          return;
        }

        if (!event.touches || event.touches.length !== 1) {
          return;
        }

        if (scrollRoot.scrollTop > 0) {
          active = false;
          resetIndicator();
          return;
        }

        var delta = event.touches[0].clientY - startY;
        if (delta <= 0) {
          pullDistance = 0;
          setOffset(0);
          indicator.classList.remove('is-release');
          setMessage('Pull to refresh');
          return;
        }

        pullDistance = Math.min(maxPullDistance, delta * 0.48);
        setOffset(pullDistance);

        if (pullDistance >= triggerDistance) {
          indicator.classList.add('is-release');
          setMessage('Release to refresh');
        } else {
          indicator.classList.remove('is-release');
          setMessage('Pull to refresh');
        }

        event.preventDefault();
      },
      { passive: false }
    );

    function handleTouchEnd() {
      if (!active || refreshing) {
        return;
      }

      active = false;

      if (pullDistance >= triggerDistance) {
        refreshing = true;
        indicator.classList.remove('is-release');
        indicator.classList.add('is-refreshing', 'is-visible');
        setOffset(triggerDistance);
        setMessage('Refreshing...');

        window.setTimeout(function () {
          window.location.reload();
        }, 160);
        return;
      }

      resetIndicator();
    }

    document.addEventListener('touchend', handleTouchEnd, { passive: true });
    document.addEventListener('touchcancel', handleTouchEnd, { passive: true });
  }

  /* ============ Auth page behaviors ============ */
  function initAuthPasswordFields() {
    var passwordFields = document.querySelectorAll('.zz-password-field');
    if (!passwordFields.length) {
      return;
    }

    passwordFields.forEach(function (field) {
      var input = field.querySelector('input');
      var toggleButton = field.querySelector('[data-zz-password-toggle]');

      if (!input || !toggleButton) {
        return;
      }

      var currentType = String(input.getAttribute('type') || '').toLowerCase();
      if (currentType !== 'password' && currentType !== 'text') {
        return;
      }

      var isVisible = currentType === 'text';

      function syncToggleState() {
        input.setAttribute('type', isVisible ? 'text' : 'password');
        toggleButton.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
        toggleButton.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
      }

      toggleButton.addEventListener('click', function (event) {
        event.preventDefault();
        isVisible = !isVisible;
        syncToggleState();
      });

      syncToggleState();
    });
  }

  function initAuthLoginEmailMemory() {
    var loginForm = document.querySelector('[data-zz-login-form]');
    var emailInput = document.querySelector('[data-zz-login-email]');

    if (!loginForm || !emailInput) {
      return;
    }

    var storageKey = 'zz_login_email';
    var hasLoginError = loginForm.getAttribute('data-zz-login-error') === 'true';
    var storage = null;

    try {
      storage = window.sessionStorage;
    } catch (error) {
      storage = null;
    }

    if (!storage) {
      return;
    }

    if (!hasLoginError) {
      storage.removeItem(storageKey);
    } else if (emailInput.value.trim() === '') {
      var storedEmail = String(storage.getItem(storageKey) || '').trim();
      if (storedEmail !== '') {
        emailInput.value = storedEmail;
        emailInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }

    loginForm.addEventListener('submit', function () {
      var emailValue = emailInput.value.trim();
      if (emailValue === '') {
        storage.removeItem(storageKey);
        return;
      }

      storage.setItem(storageKey, emailValue);
    });
  }

  /* ============ Goals module behaviors ============ */
  function syncGoalCategoryOption(option) {
    if (!option) {
      return;
    }

    var checkbox = option.querySelector('input[type="checkbox"]');
    if (!checkbox) {
      return;
    }

    option.classList.toggle('is-selected', checkbox.checked);
  }

  function initGoalCategoryPicker() {
    var categoryOptions = document.querySelectorAll('[data-goal-category-option]');
    if (!categoryOptions.length) {
      return;
    }

    categoryOptions.forEach(function (option) {
      var checkbox = option.querySelector('input[type="checkbox"]');
      if (!checkbox) {
        return;
      }

      syncGoalCategoryOption(option);
      checkbox.addEventListener('change', function () {
        syncGoalCategoryOption(option);
      });
    });
  }

  function readInt(value, fallback) {
    var parsed = parseInt(String(value || ''), 10);
    if (Number.isNaN(parsed)) {
      return fallback;
    }

    return parsed;
  }

  function formatGoalPriorityNote(note, cadenceUnit, cadenceNumber) {
    if (!note) {
      return;
    }

    if (cadenceNumber !== 1) {
      note.textContent =
        'Custom cadence is not priority-eligible. Use 1 per day, week, or month for priority slots.';
      note.classList.remove('is-full');
      return;
    }

    var keyMap = {
      day: 'daily',
      week: 'weekly',
      month: 'monthly',
    };

    var cadenceType = keyMap[cadenceUnit] || 'daily';
    var used = readInt(note.getAttribute('data-' + cadenceType + '-used'), 0);
    var limit = readInt(note.getAttribute('data-' + cadenceType + '-limit'), 0);
    var available = Math.max(0, limit - used);

    if (available === 0) {
      note.textContent = 'All ' + limit + ' ' + cadenceType + ' priority slots are in use.';
      note.classList.add('is-full');
      return;
    }

    note.textContent =
      'You have ' +
      available +
      ' of ' +
      limit +
      ' ' +
      cadenceType +
      ' priority slots available.';
    note.classList.remove('is-full');
  }

  function initGoalPriorityAvailability() {
    var forms = document.querySelectorAll('[data-goal-priority]');
    if (!forms.length) {
      return;
    }

    forms.forEach(function (form) {
      var numberInput = form.querySelector('#cadence_number');
      var unitSelect = form.querySelector('#cadence_unit');
      var note = form.querySelector('[data-goal-priority-note]');

      if (!numberInput || !unitSelect || !note) {
        return;
      }

      function syncPriorityAvailability() {
        var cadenceNumber = Math.max(1, readInt(numberInput.value, 1));
        var cadenceUnit = String(unitSelect.value || 'day').toLowerCase();
        formatGoalPriorityNote(note, cadenceUnit, cadenceNumber);
      }

      numberInput.addEventListener('input', syncPriorityAvailability);
      unitSelect.addEventListener('change', syncPriorityAvailability);
      syncPriorityAvailability();
    });
  }

  function initGoalDeleteConfirm() {
    var deleteForms = document.querySelectorAll('[data-goal-delete-form]');
    if (!deleteForms.length) {
      return;
    }

    deleteForms.forEach(function (form) {
      form.addEventListener('submit', function (event) {
        var message =
          form.getAttribute('data-confirm-message') ||
          'Delete this item? This cannot be undone.';

        if (!window.confirm(message)) {
          event.preventDefault();
        }
      });
    });
  }

  /* ============ Coach module behaviors ============ */
  function initCoachDeleteConfirm() {
    var deleteForms = document.querySelectorAll('[data-coach-delete-form]');
    if (!deleteForms.length) {
      return;
    }

    deleteForms.forEach(function (form) {
      form.addEventListener('submit', function (event) {
        var message =
          form.getAttribute('data-confirm-message') ||
          'Delete this coach situation? This cannot be undone.';

        if (!window.confirm(message)) {
          event.preventDefault();
        }
      });
    });
  }

  function splitChipValues(value) {
    return String(value || '')
      .split(',')
      .map(function (item) {
        return item.trim();
      })
      .filter(function (item) {
        return item !== '';
      });
  }

  function initChipTargetInsertion() {
    var groups = document.querySelectorAll('[data-chip-target]');
    if (!groups.length) {
      return;
    }

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
          var value = (
            chip.getAttribute('data-value') ||
            chip.textContent ||
            ''
          ).trim();
          if (value === '') {
            return;
          }

          var values = splitChipValues(target.value);
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
  }

  /* ============ Card radio + time pill selection ============ */
  function initCoachSelectionFallbacks() {
    document.addEventListener('change', function (e) {
      if (!e.target.matches('.zz-card-radio input[type="radio"]')) {
        return;
      }

      var group = e.target.closest('.zz-card-radio-group');
      if (!group) {
        return;
      }

      var cards = group.querySelectorAll('.zz-card-radio');
      cards.forEach(function (card) {
        card.classList.remove('is-selected');
      });

      var parent = e.target.closest('.zz-card-radio');
      if (parent) {
        parent.classList.add('is-selected');
      }
    });

    document.addEventListener('change', function (e) {
      if (!e.target.matches('.zz-time-pill input[type="radio"]')) {
        return;
      }

      var group = e.target.closest('.zz-field') || e.target.closest('.zz-time-pills');
      if (!group) {
        return;
      }

      var pills = group.querySelectorAll('.zz-time-pill');
      pills.forEach(function (pill) {
        pill.classList.remove('is-selected');
      });

      var parent = e.target.closest('.zz-time-pill');
      if (parent) {
        parent.classList.add('is-selected');
      }
    });

    document
      .querySelectorAll('.zz-card-radio input[type="radio"]:checked')
      .forEach(function (input) {
        var card = input.closest('.zz-card-radio');
        if (card) {
          card.classList.add('is-selected');
        }
      });

    document
      .querySelectorAll('.zz-time-pill input[type="radio"]:checked')
      .forEach(function (input) {
        var pill = input.closest('.zz-time-pill');
        if (pill) {
          pill.classList.add('is-selected');
        }
      });
  }

  function initZenZone() {
    initScales();
    initRadioScales();
    initChipGroups();
    initChipTargetInsertion();
    initFloatingFields();
    initAppbarShadow();
    initAppbarMenu();
    initFlashToasts();
    initPreviewToastTriggers();
    initNavFallback();
    initPullToRefresh();
    initAuthPasswordFields();
    initAuthLoginEmailMemory();
    initGoalCategoryPicker();
    initGoalPriorityAvailability();
    initGoalDeleteConfirm();
    initCoachDeleteConfirm();
    initCoachSelectionFallbacks();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initZenZone);
  } else {
    initZenZone();
  }
})();

