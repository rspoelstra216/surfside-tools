/* Surfside Tools — local, capability-filtered navigation search (no network requests). */
(function () {
  'use strict';

  function start() {
    var root = document.querySelector('[data-surfside-workspace]');
    if (!root) return;
    var drawer = root.querySelector('#surfside-workspace-sidebar');
    var search = root.querySelector('[data-ss-search]');
    var openButton = root.querySelector('[data-ss-open]');
    var empty = root.querySelector('[data-ss-empty]');
    var groups = Array.prototype.slice.call(root.querySelectorAll('[data-ss-group]'));

    function setOpen(open, restoreFocus) {
      root.classList.toggle('is-open', open);
      if (openButton) openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
      var scrim = root.querySelector('.surfside-workspace-scrim');
      if (scrim) scrim.hidden = !open;
      if (drawer) drawer.setAttribute('aria-hidden', !open ? 'true' : 'false');
      if (open && search) search.focus();
      if (!open && restoreFocus && openButton) openButton.focus();
    }

    function normalize(value) {
      return String(value || '').toLocaleLowerCase().trim().replace(/\s+/g, ' ');
    }

    function filterTools() {
      var phrase = normalize(search ? search.value : '');
      var shown = 0;
      groups.forEach(function (group) {
        var count = 0;
        Array.prototype.forEach.call(group.querySelectorAll('[data-ss-link]'), function (link) {
          var matches = phrase === '' ||
            normalize(link.textContent + ' ' + (link.getAttribute('data-keywords') || '') + ' ' +
              group.querySelector('h2').textContent).indexOf(phrase) !== -1;
          if (link.parentElement) link.parentElement.hidden = !matches;
          if (matches) { count++; shown++; }
        });
        group.hidden = count === 0;
      });
      if (empty) empty.hidden = shown > 0;
    }

    if (search) search.addEventListener('input', filterTools);
    if (openButton) openButton.addEventListener('click', function () {
      setOpen(!root.classList.contains('is-open'), false);
    });
    Array.prototype.forEach.call(root.querySelectorAll('[data-ss-close]'), function (control) {
      control.addEventListener('click', function () { setOpen(false, true); });
    });

    document.addEventListener('keydown', function (event) {
      if ((event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setOpen(true, false);
        if (search) search.focus();
      } else if (event.key === 'Escape' && root.classList.contains('is-open')) {
        event.preventDefault();
        setOpen(false, true);
      } else if (event.key === 'Tab' && root.classList.contains('is-open') && drawer) {
        // Keep keyboard focus in the open navigation overlay on every screen size.
        var tabbables = Array.prototype.filter.call(
          drawer.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])'),
          function (element) {
            return element.getClientRects().length > 0 &&
              !element.closest('[hidden]') && element.getAttribute('aria-hidden') !== 'true';
          }
        );
        if (tabbables.length) {
          var firstItem = tabbables[0];
          var lastItem = tabbables[tabbables.length - 1];
          if (event.shiftKey && (document.activeElement === firstItem ||
              !drawer.contains(document.activeElement))) {
            event.preventDefault();
            lastItem.focus();
          } else if (!event.shiftKey && (document.activeElement === lastItem ||
              !drawer.contains(document.activeElement))) {
            event.preventDefault();
            firstItem.focus();
          }
        }
      } else if (event.key === 'ArrowDown' && event.target === search) {
        var first = root.querySelector('[data-ss-link]:not([hidden])');
        // Hidden parent <li> and groups are excluded from keyboard results.
        var visible = Array.prototype.find.call(root.querySelectorAll('[data-ss-link]'), function (link) {
          return !link.parentElement.hidden && !link.closest('[data-ss-group]').hidden;
        });
        if (visible || first) {
          event.preventDefault();
          (visible || first).focus();
        }
      }
    });

    // Deep links into existing Integrations accordions, without moving editors.
    var settingNames = {
      'google-maps': 'Google Maps',
      'calendar-defaults': 'Calendar Defaults',
      'saved-places': 'Saved Places',
      'youtube-sermon-archive': 'YouTube Sermon Archive',
      'twitch': 'Twitch',
      'youversion': 'YouVersion',
      'giving': 'Giving',
      'cloudflare-turnstile': 'Cloudflare Turnstile',
      'reveal-countdown-styling': 'Reveal & Countdown Styling'
    };
    function focusSetting() {
      if (window.location.hash.indexOf('#surfside-focus-') !== 0) return;
      var slug;
      try { slug = decodeURIComponent(window.location.hash.slice('#surfside-focus-'.length)); }
      catch (error) { return; }
      if (!settingNames[slug]) return;
      var label = settingNames[slug];
      var target = Array.prototype.find.call(document.querySelectorAll('.surfside-front-settings-card'), function (details) {
        var summary = details.querySelector('summary');
        return summary && normalize(summary.textContent).indexOf(normalize(label)) === 0;
      });
      if (!target) return;
      target.open = true;
      target.scrollIntoView({ block: 'start', behavior: 'auto' });
      // Selecting a settings result should update the highlighted link too.
      Array.prototype.forEach.call(root.querySelectorAll('a[aria-current="page"]'), function (a) {
        a.removeAttribute('aria-current');
        a.classList.remove('is-active');
      });
      var selected = Array.prototype.find.call(root.querySelectorAll('[data-ss-link]'), function (a) {
        return a.hash === window.location.hash;
      });
      if (selected) {
        selected.setAttribute('aria-current', 'page');
        selected.classList.add('is-active');
        var breadcrumb = root.querySelector('.surfside-workspace-breadcrumb [aria-current="page"]');
        if (breadcrumb) breadcrumb.textContent = selected.textContent;
      }
    }
    window.addEventListener('hashchange', focusSetting);
    focusSetting();
    setOpen(false, false);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
}());
