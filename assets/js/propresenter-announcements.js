/* Staff-only ProPresenter slide editor. No data or API key is exposed publicly. */
(function () {
    'use strict';
    const root = document.getElementById('surfside-propresenter');
    if (!root) return;

    const status = root.querySelector('#surfside-propresenter-status');
    const slideContainer = root.querySelector('#surfside-propresenter-slides');
    const actions = root.querySelector('.surfside-propresenter-actions');

    function announce(message, error) {
        status.textContent = message;
        status.classList.toggle('is-error', Boolean(error));
    }

    function slidesFromEditor() {
        return Array.from(root.querySelectorAll('[data-propresenter-slide]')).map(function (slide) {
            const title = slide.querySelector('[data-propresenter-title]').value.trim();
            const lines = slide.querySelector('[data-propresenter-lines]').value
                .split(/\r?\n/).map(function (line) { return line.trim(); })
                .filter(Boolean);
            return { title: title, lines: lines };
        });
    }

    function slideText(slide) {
        return [slide.title].concat(slide.lines).join('\n');
    }

    async function copyText(value) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(value);
            return;
        }
        const field = document.createElement('textarea');
        field.value = value;
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        const copied = document.execCommand('copy');
        field.remove();
        if (!copied) throw new Error('Copy is not supported by this browser.');
    }

    async function request(type, values) {
        const form = new FormData();
        form.append('action', 'surfside_propresenter_' + type);
        form.append('nonce', root.dataset.nonce);
        form.append('source_hash', root.dataset.sourceHash || '');
        Object.keys(values || {}).forEach(function (key) {
            form.append(key, values[key]);
        });
        const response = await fetch(root.dataset.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: form
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.data && data.data.message
                ? data.data.message : 'The request did not complete.');
        }
        return data.data || {};
    }

    function createSlide(index, slide) {
        const article = document.createElement('article');
        article.className = 'surfside-propresenter-slide';
        article.setAttribute('data-propresenter-slide', '');

        const heading = document.createElement('div');
        heading.className = 'surfside-propresenter-slide-heading';
        const label = document.createElement('strong');
        label.textContent = 'Slide ' + (index + 1);
        const copyButton = document.createElement('button');
        copyButton.type = 'button';
        copyButton.className = 'surfside-staff-button-secondary';
        copyButton.dataset.propresenterAction = 'copy-one';
        copyButton.textContent = 'Copy slide';
        heading.append(label, copyButton);

        const titleLabel = document.createElement('label');
        titleLabel.appendChild(document.createTextNode('Title'));
        const titleInput = document.createElement('input');
        titleInput.type = 'text';
        titleInput.setAttribute('data-propresenter-title', '');
        titleInput.value = slide.title;
        titleLabel.appendChild(titleInput);

        const bodyLabel = document.createElement('label');
        bodyLabel.appendChild(document.createTextNode('Lines (one per line)'));
        const bodyInput = document.createElement('textarea');
        bodyInput.rows = 3;
        bodyInput.setAttribute('data-propresenter-lines', '');
        bodyInput.value = slide.lines.join('\n');
        bodyLabel.appendChild(bodyInput);

        article.append(heading, titleLabel, bodyLabel);
        return article;
    }

    function refreshSlides(slides) {
        slideContainer.replaceChildren();
        slides.forEach(function (slide, index) {
            slideContainer.appendChild(createSlide(index, slide));
        });
        const empty = root.querySelector('[data-propresenter-empty]');
        if (empty) empty.hidden = slides.length > 0;
        const previousError = root.querySelector('[data-propresenter-generation-error]');
        if (previousError && slides.length) previousError.hidden = true;
        actions.querySelectorAll('[data-propresenter-action="copy-all"],' +
            '[data-propresenter-action="save"],[data-propresenter-action="email"]')
            .forEach(function (button) {
                button.disabled = !slides.length;
            });
    }

    root.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-propresenter-action]');
        if (!button || !root.contains(button) || button.disabled) return;
        const action = button.dataset.propresenterAction;
        const slides = slidesFromEditor();
        if (action === 'regenerate' && slides.length &&
            !window.confirm('Regenerate AI slides? This will replace any unsaved or saved slide edits.')) return;

        const buttons = root.querySelectorAll('[data-propresenter-action]');
        buttons.forEach(function (other) { other.disabled = true; });
        announce('');
        try {
            if (action === 'copy-one') {
                const article = button.closest('[data-propresenter-slide]');
                const index = Array.from(slideContainer.children).indexOf(article);
                if (index < 0 || !slides[index]) throw new Error('Slide unavailable. Refresh the page.');
                await copyText(slideText(slides[index]));
                announce('Slide copied.');
            } else if (action === 'copy-all') {
                // Keep exactly one newline between each announcement. In Reflow,
                // staff can add slide breaks wherever needed.
                await copyText(slides.map(slideText).join('\n'));
                announce('All announcements copied as plain text. Add slide breaks in ProPresenter Reflow.');
            } else if (action === 'save') {
                const result = await request('save', { slides: JSON.stringify(slides) });
                announce(result.message || 'Slide edits saved.');
            } else if (action === 'regenerate') {
                const result = await request('regenerate');
                refreshSlides(result.slides || []);
                announce(result.message || 'Slides regenerated.');
            } else if (action === 'email') {
                // Save the edited text first, so the email matches the visible preview.
                await request('save', { slides: JSON.stringify(slides) });
                const result = await request('email');
                announce(result.message || 'Email sent.');
            } else if (action === 'settings') {
                const recipients = root.querySelector('[data-propresenter-recipients]').value;
                const enabled = root.querySelector('[data-propresenter-enabled]').checked ? '1' : '0';
                const result = await request('settings', { recipients: recipients, enabled: enabled });
                announce(result.message || 'Email settings saved.');
            }
        } catch (error) {
            announce(error.message || 'An unexpected error occurred.', true);
        } finally {
            buttons.forEach(function (other) {
                const needsSlides = ['copy-all', 'save', 'email', 'copy-one'].includes(other.dataset.propresenterAction);
                other.disabled = needsSlides && !root.querySelector('[data-propresenter-slide]');
            });
        }
    });
})();
