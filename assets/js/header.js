document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-surfside-header]').forEach(function (header) {
        var toggle = header.querySelector('[data-surfside-menu-toggle]');
        var menu = header.querySelector('[data-surfside-menu]');

        function normalizedInternalPath(url) {
            try {
                var parsed = new URL(url, window.location.href);
                if (parsed.origin !== window.location.origin) return null;
                var path = decodeURIComponent(parsed.pathname || '/').replace(/\/+$/, '');
                return path || '/';
            } catch (error) {
                return null;
            }
        }

        function updateCurrentLink() {
            var currentPath = normalizedInternalPath(window.location.href);

            header.querySelectorAll('.surfside-site-header__link').forEach(function (link) {
                var isLive = link.classList.contains('surfside-site-header__link--live');
                link.classList.remove('surfside-site-header__link--current');
                link.removeAttribute('aria-current');
                if (!isLive) link.classList.remove('surfside-site-header__link--primary');

                var linkPath = normalizedInternalPath(link.href);
                if (currentPath !== null && linkPath === currentPath) {
                    link.classList.add('surfside-site-header__link--current');
                    link.setAttribute('aria-current', 'page');
                }
            });
        }

        function setOpen(open) {
            header.classList.toggle('menu-open', open);
            if (toggle) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                var label = toggle.querySelector('.screen-reader-text');
                if (label) label.textContent = open ? 'Close navigation menu' : 'Open navigation menu';
            }
        }

        function updateScrolled() {
            header.classList.toggle('is-scrolled', window.scrollY > 24);
        }

        if (toggle && menu) {
            toggle.addEventListener('click', function () {
                setOpen(toggle.getAttribute('aria-expanded') !== 'true');
            });
            menu.addEventListener('click', function (event) {
                if (event.target.closest('a')) setOpen(false);
            });
            document.addEventListener('click', function (event) {
                if (!header.contains(event.target)) setOpen(false);
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && header.classList.contains('menu-open')) {
                    setOpen(false);
                    toggle.focus();
                }
            });
            window.addEventListener('resize', function () {
                if (window.innerWidth > 1080) setOpen(false);
            });
        }

        var liveUntil = Number(header.getAttribute('data-surfside-live-until') || 0);
        if (liveUntil && Date.now() >= liveUntil) {
            header.classList.remove('surfside-site-header--live');
            var visitItem = header.querySelector('[data-surfside-nav-role="visit"] a');
            var watchItem = header.querySelector('[data-surfside-nav-role="watch"] a');
            if (visitItem) visitItem.classList.remove('surfside-site-header__link--primary');
            if (watchItem) {
                watchItem.classList.remove('surfside-site-header__link--primary', 'surfside-site-header__link--live');
                var dot = watchItem.querySelector('.surfside-site-header__live-dot');
                if (dot) dot.remove();
                var label = watchItem.querySelector('[data-surfside-link-label]');
                if (label) label.textContent = label.getAttribute('data-default-label') || 'Watch Live';
            }
        }

        updateCurrentLink();
        updateScrolled();
        window.addEventListener('scroll', updateScrolled, { passive: true });
    });
});
