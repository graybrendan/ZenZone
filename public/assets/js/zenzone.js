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

  function initZenZone() {
    initScales();
    initChipGroups();
    initFloatingFields();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initZenZone);
  } else {
    initZenZone();
  }
})();

