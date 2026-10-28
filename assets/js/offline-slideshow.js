(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var manager = document.querySelector('[data-surfside-offline-slideshow]');
    if (!manager) return;

    var list = manager.querySelector('[data-slideshow-list]');
    var serialized = manager.querySelector('[data-slideshow-serialized]');
    var empty = manager.querySelector('[data-slideshow-empty]');
    var defaultDuration = manager.querySelector('[data-slideshow-default]');
    var addButton = manager.querySelector('[data-slideshow-add]');
    var previewButton = manager.querySelector('[data-slideshow-preview-button]');
    var preview = manager.querySelector('[data-slideshow-preview]');
    var previewImage = manager.querySelector('[data-slideshow-preview-image]');
    var previewLabel = manager.querySelector('[data-slideshow-preview-label]');
    var slides = [];
    var frame = null;
    var timer = null;
    var playing = false;

    try {
      var initial = JSON.parse(manager.getAttribute('data-slides') || '[]');
      slides = Array.isArray(initial) ? initial : [];
    } catch (error) {
      slides = [];
    }

    function element(tag, className, label) {
      var node = document.createElement(tag);
      if (className) node.className = className;
      if (typeof label === 'string') node.textContent = label;
      return node;
    }

    function button(label, className, callback) {
      var node = element('button', className, label);
      node.type = 'button';
      node.addEventListener('click', callback);
      return node;
    }

    function sync() {
      serialized.value = JSON.stringify(slides.map(function (slide) {
        return {
          id: Number(slide.id),
          enabled: !!slide.enabled,
          duration_seconds: Number(slide.duration_seconds) || 0
        };
      }));
    }

    function move(from, to) {
      if (from < 0 || to < 0 || from >= slides.length || to >= slides.length || from === to) return;
      var picked = slides.splice(from, 1)[0];
      slides.splice(to, 0, picked);
      render();
    }

    function stopPreview() {
      playing = false;
      window.clearTimeout(timer);
      timer = null;
      preview.hidden = true;
      previewButton.textContent = 'Preview slideshow';
    }

    function render() {
      list.replaceChildren();
      empty.hidden = slides.length > 0;
      slides.forEach(function (slide, index) {
        var row = element('div', 'surfside-slideshow-item');
        row.draggable = true;
        row.setAttribute('aria-label', 'Slide ' + (index + 1) + ': ' + (slide.title || 'Announcement'));
        row.addEventListener('dragstart', function (event) {
          event.dataTransfer.setData('text/plain', String(index));
          event.dataTransfer.effectAllowed = 'move';
        });
        row.addEventListener('dragover', function (event) { event.preventDefault(); });
        row.addEventListener('drop', function (event) {
          event.preventDefault();
          var from = Number(event.dataTransfer.getData('text/plain'));
          if (Number.isInteger(from)) move(from, index);
        });

        var thumbnail = element('img', 'surfside-slideshow-thumb');
        thumbnail.src = slide.thumbnail_url || slide.url || '';
        thumbnail.alt = '';
        thumbnail.loading = 'lazy';
        row.appendChild(thumbnail);

        var info = element('div', 'surfside-slideshow-info');
        info.appendChild(element('strong', '', (index + 1) + '. ' + (slide.title || 'Announcement image')));
        info.appendChild(element('span', '', 'Drag to reorder, or use the arrow buttons.'));
        row.appendChild(info);

        var durationLabel = element('label', 'surfside-slideshow-duration', 'Seconds');
        var durationInput = element('input');
        durationInput.type = 'number';
        durationInput.min = '3';
        durationInput.max = '60';
        durationInput.step = '1';
        durationInput.placeholder = 'Default';
        durationInput.value = slide.duration_seconds || '';
        durationInput.setAttribute('aria-label', 'Slide ' + (index + 1) + ' duration; blank uses default');
        durationInput.addEventListener('change', function () {
          var value = Math.floor(Number(durationInput.value)) || 0;
          slide.duration_seconds = value ? Math.max(3, Math.min(60, value)) : 0;
          durationInput.value = slide.duration_seconds || '';
          sync();
        });
        durationLabel.appendChild(durationInput);
        row.appendChild(durationLabel);

        var enabledLabel = element('label', 'surfside-slideshow-enabled');
        var enabledInput = element('input');
        enabledInput.type = 'checkbox';
        enabledInput.checked = !!slide.enabled;
        enabledInput.addEventListener('change', function () {
          slide.enabled = enabledInput.checked;
          sync();
        });
        enabledLabel.appendChild(enabledInput);
        enabledLabel.appendChild(document.createTextNode(' Active'));
        row.appendChild(enabledLabel);

        var actions = element('div', 'surfside-slideshow-item-actions');
        var up = button('↑', 'surfside-slideshow-small', function () { move(index, index - 1); });
        up.disabled = index === 0;
        up.setAttribute('aria-label', 'Move slide ' + (index + 1) + ' up');
        var down = button('↓', 'surfside-slideshow-small', function () { move(index, index + 1); });
        down.disabled = index === slides.length - 1;
        down.setAttribute('aria-label', 'Move slide ' + (index + 1) + ' down');
        var remove = button('Remove', 'surfside-slideshow-small', function () {
          slides.splice(index, 1);
          stopPreview();
          render();
        });
        remove.setAttribute('aria-label', 'Remove slide ' + (index + 1));
        actions.appendChild(up);
        actions.appendChild(down);
        actions.appendChild(remove);
        row.appendChild(actions);
        list.appendChild(row);
      });
      previewButton.disabled = !slides.some(function (slide) { return slide.enabled; });
      sync();
    }

    addButton.addEventListener('click', function () {
      if (!window.wp || !wp.media) {
        window.alert('The WordPress Media Library is unavailable. Refresh and try again.');
        return;
      }
      if (!frame) {
        frame = wp.media({
          title: 'Choose announcement images',
          button: { text: 'Add selected images' },
          library: { type: 'image' },
          multiple: true
        });
        frame.on('select', function () {
          var selected = frame.state().get('selection').toJSON();
          var known = new Set(slides.map(function (slide) { return Number(slide.id); }));
          selected.forEach(function (image) {
            if (!image.id || known.has(Number(image.id)) || slides.length >= 50) return;
            known.add(Number(image.id));
            slides.push({
              id: Number(image.id),
              title: image.title || image.filename || 'Announcement image',
              url: image.url,
              thumbnail_url: (image.sizes && image.sizes.medium && image.sizes.medium.url) || image.url,
              enabled: true,
              duration_seconds: 0
            });
          });
          stopPreview();
          render();
          if (slides.length >= 50) {
            window.alert('The slideshow can contain up to 50 images.');
          }
        });
      }
      frame.open();
    });

    previewButton.addEventListener('click', function () {
      if (playing) {
        stopPreview();
        return;
      }
      var active = slides.filter(function (slide) { return !!slide.enabled; });
      if (!active.length) return;
      playing = true;
      preview.hidden = false;
      previewButton.textContent = 'Stop preview';
      var index = 0;
      function showNext() {
        if (!playing) return;
        var slide = active[index % active.length];
        index++;
        previewImage.src = slide.url || slide.thumbnail_url || '';
        previewLabel.textContent = (slide.title || 'Announcement') + ' (' + index % active.length + '/' + active.length + ')';
        var seconds = Number(slide.duration_seconds) || Number(defaultDuration.value) || 8;
        timer = window.setTimeout(showNext, Math.max(3, Math.min(60, seconds)) * 1000);
      }
      showNext();
    });

    manager.querySelector('form').addEventListener('submit', function () {
      stopPreview();
      sync();
    });

    render();
  });
}());
