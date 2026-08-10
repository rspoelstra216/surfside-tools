<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_site_information_capability() {
    return apply_filters('surfside_tools_site_information_capability', 'manage_options');
}

function surfside_tools_site_information_manager_notice($message, $type = 'success') {
    return '<div class="surfside-information-notice surfside-information-notice-' . esc_attr($type) . '" role="status">' . esc_html($message) . '</div>';
}

function surfside_tools_site_information_manager_handle_post() {
    $defaults = surfside_tools_site_information_defaults();

    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
        empty($_POST['surfside_information_action'])
    ) {
        return '';
    }

    if (!current_user_can(surfside_tools_site_information_capability())) {
        return surfside_tools_site_information_manager_notice('You do not have permission to update Surfside Information.', 'error');
    }

    $nonce = isset($_POST['surfside_information_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['surfside_information_nonce']))
        : '';
    if (!wp_verify_nonce($nonce, 'surfside_information_update')) {
        return surfside_tools_site_information_manager_notice('Security check failed. Please refresh and try again.', 'error');
    }

    $posted_services = isset($_POST['services']) && is_array($_POST['services'])
        ? wp_unslash($_POST['services'])
        : array();
    $services = array();

    $weekday_names = array(
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    );
    foreach ($posted_services as $service) {
        if (!is_array($service)) {
            continue;
        }

        $weekday = absint($service['weekday'] ?? 0);
        $services[] = array(
            'key' => $service['key'] ?? '',
            'weekday' => $weekday,
            'day' => $weekday_names[$weekday] ?? '',
            'label' => $service['label'] ?? '',
            'time' => $service['time'] ?? '',
            'livestream' => !empty($service['livestream']),
        );
    }

    $posted_navigation = isset($_POST['navigation']) && is_array($_POST['navigation'])
        ? wp_unslash($_POST['navigation'])
        : array();
    $navigation = array();
    foreach ($posted_navigation as $index => $link) {
        if (!is_array($link)) {
            continue;
        }
        $destination = sanitize_text_field($link['destination'] ?? 'custom');
        $type = strpos($destination, 'page:') === 0 ? 'page' : 'custom';
        $navigation[] = array(
            'key' => $link['key'] ?? '',
            'label' => $link['label'] ?? '',
            'type' => $type,
            'page_id' => $type === 'page' ? absint(substr($destination, 5)) : 0,
            'url' => $type === 'custom' ? ($link['url'] ?? '') : '',
            'new_tab' => $type === 'custom' && !empty($link['new_tab']),
        );
    }

    $social = array();
    foreach ($defaults['social'] as $key => $link) {
        $social[$key] = array(
            'label' => $link['label'],
            'url' => isset($_POST['social'][$key])
                ? wp_unslash($_POST['social'][$key])
                : $link['url'],
        );
    }

    $posted_ministries = isset($_POST['adult_ministries']) && is_array($_POST['adult_ministries'])
        ? wp_unslash($_POST['adult_ministries'])
        : array();
    $adult_ministries = array();
    foreach ($posted_ministries as $ministry) {
        if (!is_array($ministry)) {
            continue;
        }
        $adult_ministries[] = array(
            'key' => $ministry['key'] ?? '',
            'icon' => $ministry['icon'] ?? '',
            'name' => $ministry['name'] ?? '',
            'schedule' => $ministry['schedule'] ?? '',
            'location' => $ministry['location'] ?? '',
            'description' => $ministry['description'] ?? '',
        );
    }

    $section = isset($_POST['surfside_information_section'])
        ? sanitize_key(wp_unslash($_POST['surfside_information_section']))
        : 'information';
    $updated_information = surfside_tools_get_site_information();

    if ($section === 'information') {
        $updated_information['identity'] = array(
            'name' => isset($_POST['church_name']) ? wp_unslash($_POST['church_name']) : '',
            'logo_id' => isset($_POST['logo_id']) ? absint($_POST['logo_id']) : 0,
            'tagline' => isset($_POST['tagline']) ? wp_unslash($_POST['tagline']) : '',
            'phone' => isset($_POST['phone']) ? wp_unslash($_POST['phone']) : '',
            'email' => isset($_POST['email']) ? wp_unslash($_POST['email']) : '',
            'contact_url' => isset($_POST['contact_url']) ? wp_unslash($_POST['contact_url']) : '',
        );
        $updated_information['location'] = array(
            'venue' => isset($_POST['venue']) ? wp_unslash($_POST['venue']) : '',
            'street' => isset($_POST['street']) ? wp_unslash($_POST['street']) : '',
            'city' => isset($_POST['city']) ? wp_unslash($_POST['city']) : '',
            'state' => isset($_POST['state']) ? wp_unslash($_POST['state']) : '',
            'postal_code' => isset($_POST['postal_code']) ? wp_unslash($_POST['postal_code']) : '',
        );
        $updated_information['services'] = $services;
        $updated_information['social'] = $social;
    } elseif ($section === 'streaming') {
        $updated_information['streaming'] = array(
            'twitch_channel' => isset($_POST['twitch_channel']) ? wp_unslash($_POST['twitch_channel']) : '',
            'announcement_video_id' => isset($_POST['announcement_video_id']) ? absint($_POST['announcement_video_id']) : 0,
            'youtube_url' => isset($_POST['stream_youtube_url']) ? wp_unslash($_POST['stream_youtube_url']) : '',
            'facebook_url' => isset($_POST['stream_facebook_url']) ? wp_unslash($_POST['stream_facebook_url']) : '',
        );
    } elseif ($section === 'navigation') {
        $updated_information['navigation'] = $navigation;
    } elseif ($section === 'ministries') {
        $updated_information['adult_ministries'] = $adult_ministries;
    }

    surfside_tools_update_site_information($updated_information);

    return surfside_tools_site_information_manager_notice('Changes saved.');
}

function surfside_tools_site_information_manager_media_assets() {
    if (!is_user_logged_in() || !current_user_can(surfside_tools_site_information_capability())) {
        return;
    }

    $post = get_queried_object();
    if (!($post instanceof WP_Post) || !has_shortcode($post->post_content, 'surfside_staff_site_information')) {
        return;
    }

    wp_enqueue_media();
}
add_action('wp_enqueue_scripts', 'surfside_tools_site_information_manager_media_assets', 20);

function surfside_tools_site_information_manager_assets() {
    $version = defined('SURFSIDE_TOOLS_VERSION') ? SURFSIDE_TOOLS_VERSION : '2.3.1';

    wp_register_style(
        'surfside-tools-information-manager',
        false,
        array('surfside-tools-staff-dashboard'),
        $version
    );
    wp_enqueue_style('surfside-tools-information-manager');
    wp_add_inline_style('surfside-tools-information-manager', '
        .surfside-information-form{display:grid;gap:22px}.surfside-information-card{padding:clamp(20px,3vw,30px);border:1px solid rgba(6,27,51,.13);border-radius:18px;background:#fff;box-shadow:0 8px 24px rgba(6,27,51,.06)}.surfside-information-card h2{margin:0 0 6px;color:#061b33}.surfside-information-card>p{margin:0 0 20px;color:#56616d}.surfside-information-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.surfside-information-field{display:grid;gap:7px}.surfside-information-field-wide{grid-column:1/-1}.surfside-information-field span,.surfside-information-services legend{color:#26323d;font-weight:800}.surfside-information-field input,.surfside-information-field select{width:100%;min-height:46px;padding:10px 12px;border:1px solid #aeb9c4;border-radius:9px;background:#fff;color:#26323d;font:inherit}.surfside-information-field input:focus,.surfside-information-field select:focus{border-color:#0b5fa5;outline:3px solid rgba(11,95,165,.18);outline-offset:1px}.surfside-information-help{margin:0;color:#687480;font-size:.88rem;line-height:1.45}.surfside-information-services{display:grid;gap:16px;margin:0;padding:0;border:0}.surfside-information-service{display:grid;grid-template-columns:minmax(120px,.7fr) minmax(180px,1.15fr) minmax(120px,.65fr) auto;align-items:end;gap:14px;padding:18px;border:1px solid rgba(6,27,51,.12);border-radius:13px;background:#f7f9fb}.surfside-information-service-actions{display:flex;align-items:center;gap:14px;min-height:46px}.surfside-information-checkbox{display:inline-flex;align-items:center;gap:8px;color:#26323d;font-weight:800;white-space:nowrap}.surfside-information-checkbox input{width:20px;height:20px;margin:0;accent-color:#0b5fa5}.surfside-information-remove,.surfside-information-add{min-height:42px;padding:9px 14px;border:1px solid #0b5fa5;border-radius:9px;background:#fff;color:#0b5fa5;font:inherit;font-weight:800;cursor:pointer}.surfside-information-remove:hover,.surfside-information-remove:focus-visible,.surfside-information-add:hover,.surfside-information-add:focus-visible{background:#eaf3fb;outline:0}.surfside-information-remove:disabled{cursor:not-allowed;opacity:.45}.surfside-information-service-controls{display:flex;justify-content:flex-start;margin-top:2px}.surfside-information-link-list{display:grid;gap:13px}.surfside-information-link{display:grid;grid-template-columns:minmax(130px,.35fr) minmax(0,1fr);align-items:center;gap:14px}.surfside-information-link strong{color:#26323d}.surfside-information-actions{position:sticky;bottom:14px;z-index:3;display:flex;justify-content:flex-end;padding:14px;border:1px solid rgba(6,27,51,.12);border-radius:14px;background:rgba(255,255,255,.94);box-shadow:0 10px 28px rgba(6,27,51,.12);backdrop-filter:blur(8px)}.surfside-information-save{min-height:48px;padding:11px 22px;border:0;border-radius:9px;background:#0b5fa5;color:#fff;font:inherit;font-weight:900;cursor:pointer}.surfside-information-save:hover,.surfside-information-save:focus-visible{background:#061b33}.surfside-information-save:focus-visible{outline:3px solid rgba(11,95,165,.28);outline-offset:3px}.surfside-information-notice{margin:0 0 20px;padding:14px 16px;border-radius:10px;font-weight:800}.surfside-information-notice-success{border:1px solid #9bd2a6;background:#edf9f0;color:#17682e}.surfside-information-notice-error{border:1px solid #e7aaaa;background:#fff0f0;color:#9b2020}@media(max-width:900px){.surfside-information-service{grid-template-columns:repeat(2,minmax(0,1fr))}.surfside-information-service-actions{align-self:end}}@media(max-width:720px){.surfside-information-grid,.surfside-information-service,.surfside-information-link{grid-template-columns:1fr}.surfside-information-field-wide{grid-column:auto}.surfside-information-actions{bottom:8px}.surfside-information-save{width:100%}.surfside-information-service-actions{justify-content:space-between}}
    ');

    wp_add_inline_style('surfside-tools-information-manager', '
        .surfside-information-ministries{display:grid;gap:14px;margin-bottom:14px}.surfside-information-ministry{display:grid;grid-template-columns:64px minmax(180px,.8fr) minmax(180px,1fr) minmax(180px,1fr) auto;align-items:end;gap:12px;padding:16px;border:1px solid rgba(6,27,51,.12);border-radius:13px;background:#f7f9fb}.surfside-information-ministry textarea{box-sizing:border-box;width:100%;padding:10px 12px;border:1px solid #aeb9c4;border-radius:9px;background:#fff;color:#26323d;font:inherit;line-height:1.5;resize:vertical}.surfside-information-ministry textarea:focus{border-color:#0b5fa5;outline:3px solid rgba(11,95,165,.18);outline-offset:1px}.surfside-information-ministry-description{grid-column:1/-1}.surfside-information-ministry-actions{display:flex;gap:6px}.surfside-information-ministry-actions button:disabled{cursor:not-allowed;opacity:.45}@media(max-width:1050px){.surfside-information-ministry{grid-template-columns:64px repeat(2,minmax(0,1fr))}.surfside-information-ministry-actions{grid-column:2/-1}}@media(max-width:720px){.surfside-information-ministry{grid-template-columns:1fr}.surfside-information-ministry>*{grid-column:auto}.surfside-information-ministry-actions{flex-wrap:wrap}}
    ');

    wp_add_inline_style('surfside-tools-information-manager', '
        .surfside-information-logo{grid-column:1/-1;display:grid;grid-template-columns:minmax(180px,280px) minmax(0,1fr);align-items:center;gap:22px;padding:18px;border:1px solid rgba(6,27,51,.12);border-radius:13px;background:#f7f9fb}.surfside-information-logo-preview{display:grid;min-height:130px;place-items:center;padding:16px;border:1px solid #d8e1e9;border-radius:10px;background:#fff}.surfside-information-logo-preview img{display:block;width:auto;max-width:100%;max-height:120px}.surfside-information-logo-copy{display:grid;gap:8px}.surfside-information-logo-copy>strong{color:#26323d;font-weight:800}.surfside-information-logo-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:4px}.surfside-information-logo-status{margin:0;color:#687480;font-size:.88rem;line-height:1.45}@media(max-width:720px){.surfside-information-logo{grid-template-columns:1fr}.surfside-information-logo-actions>*{width:100%}}
    ');

    wp_add_inline_style('surfside-tools-information-manager', '
        .surfside-information-navigation{display:grid;gap:14px;margin-bottom:14px}.surfside-information-nav-item{display:grid;grid-template-columns:34px minmax(150px,.8fr) minmax(180px,1fr) minmax(220px,1.2fr) auto;align-items:end;gap:12px;padding:16px;border:1px solid rgba(6,27,51,.12);border-radius:13px;background:#f7f9fb}.surfside-information-nav-item.is-dragging{opacity:.45}.surfside-information-drag{align-self:center;color:#687480;font-size:1.3rem;cursor:grab}.surfside-information-nav-custom{display:grid;grid-template-columns:minmax(150px,1fr) auto;align-items:end;gap:12px}.surfside-information-nav-custom[hidden]{display:none}.surfside-information-nav-actions{display:flex;gap:6px}.surfside-information-nav-actions .surfside-information-remove{min-width:42px;padding-inline:10px}@media(max-width:1050px){.surfside-information-nav-item{grid-template-columns:28px repeat(2,minmax(0,1fr))}.surfside-information-nav-custom{grid-column:2/-1}.surfside-information-nav-actions{grid-column:2/-1}}@media(max-width:720px){.surfside-information-nav-item{grid-template-columns:24px minmax(0,1fr)}.surfside-information-nav-item>.surfside-information-field,.surfside-information-nav-custom,.surfside-information-nav-actions{grid-column:2}.surfside-information-nav-custom{grid-template-columns:1fr}.surfside-information-nav-actions{flex-wrap:wrap}}
    ');

    wp_register_script('surfside-tools-information-manager', false, array(), $version, true);
    wp_enqueue_script('surfside-tools-information-manager');
    wp_add_inline_script('surfside-tools-information-manager', '
        document.addEventListener("DOMContentLoaded", function () {
            var fieldset = document.querySelector("[data-surfside-services]");
            var template = document.querySelector("[data-surfside-service-template]");
            var addButton = document.querySelector("[data-surfside-add-service]");
            var status = document.querySelector("[data-surfside-service-status]");
            if (!fieldset || !template || !addButton) {
                return;
            }

            var nextIndex = fieldset.querySelectorAll(".surfside-information-service").length;

            function rows() {
                return fieldset.querySelectorAll(".surfside-information-service");
            }

            function updateRemoveButtons() {
                var buttons = fieldset.querySelectorAll("[data-surfside-remove-service]");
                buttons.forEach(function (button) {
                    button.disabled = buttons.length === 1;
                });
            }

            addButton.addEventListener("click", function () {
                var index = "new-" + nextIndex++;
                var wrapper = document.createElement("div");
                wrapper.innerHTML = template.innerHTML.replaceAll("__INDEX__", index).trim();
                var row = wrapper.firstElementChild;
                fieldset.appendChild(row);
                updateRemoveButtons();
                var day = row.querySelector("select");
                if (day) {
                    day.focus();
                }
                if (status) {
                    status.textContent = "Service added.";
                }
            });

            fieldset.addEventListener("click", function (event) {
                var button = event.target.closest("[data-surfside-remove-service]");
                if (!button || rows().length === 1) {
                    return;
                }
                button.closest(".surfside-information-service").remove();
                updateRemoveButtons();
                if (status) {
                    status.textContent = "Service removed. Save to confirm the change.";
                }
            });

            updateRemoveButtons();
        });
    ');

    wp_add_inline_script('surfside-tools-information-manager', '
        document.addEventListener("DOMContentLoaded", function () {
            var list = document.querySelector("[data-surfside-ministries]");
            var template = document.querySelector("[data-surfside-ministry-template]");
            var add = document.querySelector("[data-surfside-ministry-add]");
            var status = document.querySelector("[data-surfside-ministry-status]");
            if (!list || !template || !add) return;
            var nextIndex = list.children.length;
            function items() { return Array.from(list.querySelectorAll(".surfside-information-ministry")); }
            function announce(message) { if (status) status.textContent = message; }
            function sync() {
                items().forEach(function (item, index, all) {
                    var up = item.querySelector("[data-surfside-ministry-up]");
                    var down = item.querySelector("[data-surfside-ministry-down]");
                    if (up) up.disabled = index === 0;
                    if (down) down.disabled = index === all.length - 1;
                });
            }
            add.addEventListener("click", function () {
                var wrapper = document.createElement("div");
                wrapper.innerHTML = template.innerHTML.replaceAll("__INDEX__", "new-" + nextIndex++).trim();
                var item = wrapper.firstElementChild;
                list.appendChild(item);
                sync();
                var name = item.querySelector("[data-surfside-ministry-name]");
                if (name) name.focus();
                announce("Adult ministry added. Save to publish it.");
            });
            list.addEventListener("click", function (event) {
                var item = event.target.closest(".surfside-information-ministry");
                if (!item) return;
                if (event.target.closest("[data-surfside-ministry-remove]")) {
                    item.remove(); sync(); announce("Adult ministry removed. Save to confirm."); return;
                }
                var direction = event.target.closest("[data-surfside-ministry-up]") ? -1 : event.target.closest("[data-surfside-ministry-down]") ? 1 : 0;
                if (!direction) return;
                var sibling = direction < 0 ? item.previousElementSibling : item.nextElementSibling;
                if (!sibling) return;
                if (direction < 0) list.insertBefore(item, sibling); else list.insertBefore(sibling, item);
                sync(); announce("Adult ministry order changed. Save to publish.");
            });
            sync();
        });
    ');

    wp_add_inline_script('surfside-tools-information-manager', '
        document.addEventListener("DOMContentLoaded", function () {
            var list = document.querySelector("[data-surfside-navigation]");
            var template = document.querySelector("[data-surfside-nav-template]");
            var add = document.querySelector("[data-surfside-nav-add]");
            var status = document.querySelector("[data-surfside-nav-status]");
            if (!list || !template || !add) return;
            var nextIndex = list.children.length;
            var dragged = null;

            function items() { return Array.from(list.querySelectorAll(".surfside-information-nav-item")); }
            function announce(message) { if (status) status.textContent = message; }
            function sync() {
                items().forEach(function (item, index, all) {
                    var up = item.querySelector("[data-surfside-nav-up]");
                    var down = item.querySelector("[data-surfside-nav-down]");
                    if (up) up.disabled = index === 0;
                    if (down) down.disabled = index === all.length - 1;
                    var select = item.querySelector("[data-surfside-nav-destination]");
                    var custom = item.querySelector("[data-surfside-nav-custom]");
                    var url = item.querySelector("[data-surfside-nav-url]");
                    var isCustom = select && select.value === "custom";
                    if (custom) custom.hidden = !isCustom;
                    if (url) url.required = isCustom;
                });
            }
            function move(item, direction) {
                var sibling = direction < 0 ? item.previousElementSibling : item.nextElementSibling;
                if (!sibling) return;
                if (direction < 0) list.insertBefore(item, sibling);
                else list.insertBefore(sibling, item);
                sync();
                announce("Navigation order changed. Save to publish.");
            }

            add.addEventListener("click", function () {
                var index = "new-" + nextIndex++;
                var wrapper = document.createElement("div");
                wrapper.innerHTML = template.innerHTML.replaceAll("__INDEX__", index).trim();
                var item = wrapper.firstElementChild;
                list.appendChild(item);
                sync();
                var label = item.querySelector("input[type=text]");
                if (label) label.focus();
                announce("Navigation link added.");
            });
            list.addEventListener("change", function (event) {
                if (event.target.matches("[data-surfside-nav-destination]")) sync();
            });
            list.addEventListener("click", function (event) {
                var item = event.target.closest(".surfside-information-nav-item");
                if (!item) return;
                if (event.target.closest("[data-surfside-nav-remove]")) {
                    item.remove(); sync(); announce("Navigation link removed. Save to publish.");
                } else if (event.target.closest("[data-surfside-nav-up]")) move(item, -1);
                else if (event.target.closest("[data-surfside-nav-down]")) move(item, 1);
            });
            list.addEventListener("dragstart", function (event) {
                dragged = event.target.closest(".surfside-information-nav-item");
                if (!dragged) return;
                dragged.classList.add("is-dragging");
                event.dataTransfer.effectAllowed = "move";
            });
            list.addEventListener("dragover", function (event) {
                if (!dragged) return;
                event.preventDefault();
                var target = event.target.closest(".surfside-information-nav-item");
                if (!target || target === dragged) return;
                var box = target.getBoundingClientRect();
                list.insertBefore(dragged, event.clientY < box.top + box.height / 2 ? target : target.nextSibling);
            });
            list.addEventListener("dragend", function () {
                if (dragged) dragged.classList.remove("is-dragging");
                dragged = null; sync(); announce("Navigation order changed. Save to publish.");
            });
            sync();
        });
    ');

    wp_add_inline_script('surfside-tools-information-manager', '
        document.addEventListener("DOMContentLoaded", function () {
            var control = document.querySelector("[data-surfside-logo]");
            if (!control || !window.wp || !wp.media) {
                return;
            }

            var input = control.querySelector("[data-surfside-logo-id]");
            var preview = control.querySelector("[data-surfside-logo-preview]");
            var selectButton = control.querySelector("[data-surfside-logo-select]");
            var defaultButton = control.querySelector("[data-surfside-logo-default]");
            var status = control.querySelector("[data-surfside-logo-status]");
            var defaultUrl = control.getAttribute("data-default-logo");
            var frame;

            if (!input || !preview || !selectButton || !defaultButton) {
                return;
            }

            selectButton.addEventListener("click", function () {
                if (!frame) {
                    frame = wp.media({
                        title: "Select the Surfside site logo",
                        button: { text: "Use this logo" },
                        library: { type: "image" },
                        multiple: false
                    });

                    frame.on("select", function () {
                        var attachment = frame.state().get("selection").first().toJSON();
                        var displayUrl = attachment.url;
                        if (attachment.sizes && attachment.sizes.medium) {
                            displayUrl = attachment.sizes.medium.url;
                        }

                        input.value = attachment.id;
                        preview.src = displayUrl;
                        defaultButton.disabled = false;
                        selectButton.textContent = "Replace logo";
                        if (status) {
                            status.textContent = "Custom Media Library logo selected. Save to publish the change.";
                        }
                    });
                }

                frame.open();
            });

            defaultButton.addEventListener("click", function () {
                input.value = "0";
                preview.src = defaultUrl;
                defaultButton.disabled = true;
                selectButton.textContent = "Select logo";
                if (status) {
                    status.textContent = "Restored plugin logo selected. Save to publish the change.";
                }
            });
        });
    ');

    wp_add_inline_script('surfside-tools-information-manager', '
        document.addEventListener("DOMContentLoaded", function () {
            var control = document.querySelector("[data-surfside-stream-video]");
            var input = document.querySelector("[data-surfside-stream-video-id]");
            if (!control || !input || !window.wp || !wp.media) return;

            var selectButton = control.querySelector("[data-surfside-stream-video-select]");
            var removeButton = control.querySelector("[data-surfside-stream-video-remove]");
            var status = document.querySelector("[data-surfside-stream-video-status]");
            var frame;

            selectButton.addEventListener("click", function () {
                if (!frame) {
                    frame = wp.media({
                        title: "Select the offline announcement video",
                        button: { text: "Use this video" },
                        library: { type: "video" },
                        multiple: false
                    });
                    frame.on("select", function () {
                        var attachment = frame.state().get("selection").first().toJSON();
                        input.value = attachment.id;
                        selectButton.textContent = "Replace video";
                        removeButton.disabled = false;
                        if (status) status.textContent = "Selected: " + attachment.filename + ". Save to publish the change.";
                    });
                }
                frame.open();
            });

            removeButton.addEventListener("click", function () {
                input.value = "0";
                selectButton.textContent = "Select video";
                removeButton.disabled = true;
                if (status) status.textContent = "No fallback video selected. Save to publish the change.";
            });
        });
    ');

}

function surfside_tools_staff_site_information_shortcode($attributes = array()) {
    $attributes = shortcode_atts(array('section' => 'information'), $attributes, 'surfside_staff_site_information');
    $section = sanitize_key($attributes['section']);
    $sections = array(
        'information' => array('eyebrow' => 'Core Site Details', 'title' => 'Surfside Information', 'description' => 'Manage the church identity, location, service schedule, and social links.', 'button' => 'Save Surfside Information'),
        'streaming' => array('eyebrow' => 'Site Management', 'title' => 'Streaming', 'description' => 'Manage Twitch and the destinations and media used by Watch Live.', 'button' => 'Save Streaming'),
        'navigation' => array('eyebrow' => 'Site Management', 'title' => 'Navigation', 'description' => 'Manage the links shared by the website header and footer.', 'button' => 'Save Navigation'),
        'ministries' => array('eyebrow' => 'Site Management', 'title' => 'Ministries', 'description' => 'Manage ministry content displayed by Surfside Tools.', 'button' => 'Save Ministries'),
    );
    if (!isset($sections[$section])) {
        $section = 'information';
    }
    $section_config = $sections[$section];
    if (function_exists('surfside_tools_prevent_cache')) {
        surfside_tools_prevent_cache();
    }
    if (function_exists('surfside_tools_staff_enqueue_styles')) {
        surfside_tools_staff_enqueue_styles();
    }

    if (!is_user_logged_in()) {
        return function_exists('surfside_tools_staff_login_box')
            ? surfside_tools_staff_login_box('Please log in to manage Surfside Information.')
            : '<p>Please log in.</p>';
    }

    if (!current_user_can(surfside_tools_site_information_capability())) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to manage Surfside Information.</p></div>';
    }

    surfside_tools_site_information_manager_assets();
    $notice = surfside_tools_site_information_manager_handle_post();
    $information = surfside_tools_get_site_information();
    $identity = $information['identity'];
    $location = $information['location'];
    $streaming = isset($information['streaming']) && is_array($information['streaming']) ? $information['streaming'] : array();
    $announcement_video_id = absint($streaming['announcement_video_id'] ?? 0);
    $announcement_video_url = $announcement_video_id ? wp_get_attachment_url($announcement_video_id) : '';
    $announcement_video_name = $announcement_video_id ? wp_basename((string) get_attached_file($announcement_video_id)) : '';
    $logo_id = absint($identity['logo_id'] ?? 0);
    $logo_url = surfside_tools_site_information_logo_url($information, 'medium_large');
    $default_logo_url = SURFSIDE_TOOLS_URL . 'assets/images/surfside-logo-restored.png';
    $weekdays = array(
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    );
    $published_pages = get_pages(array(
        'post_status' => 'publish',
        'sort_column' => 'post_title',
        'sort_order' => 'ASC',
    ));

    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-information-manager">
        <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('site-management')); ?>">← Back to Site Management</a></div>
        <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow"><?php echo esc_html($section_config['eyebrow']); ?></p>
            <h1><?php echo esc_html($section_config['title']); ?></h1>
            <p class="surfside-staff-muted"><?php echo esc_html($section_config['description']); ?></p>
        </section>

        <?php echo $notice; ?>

        <form method="post" class="surfside-information-form">
            <?php wp_nonce_field('surfside_information_update', 'surfside_information_nonce'); ?>
            <input type="hidden" name="surfside_information_action" value="save">
            <input type="hidden" name="surfside_information_section" value="<?php echo esc_attr($section); ?>">

            <?php if ($section === 'information') : ?>
            <section class="surfside-information-card">
                <h2>Church Identity</h2>
                <p>The public name, tagline, phone number, and Contact destination.</p>
                <div class="surfside-information-grid">
                    <div class="surfside-information-logo" data-surfside-logo data-default-logo="<?php echo esc_url($default_logo_url); ?>">
                        <input type="hidden" name="logo_id" value="<?php echo esc_attr($logo_id); ?>" data-surfside-logo-id>
                        <div class="surfside-information-logo-preview">
                            <img src="<?php echo esc_url($logo_url); ?>" alt="" data-surfside-logo-preview>
                        </div>
                        <div class="surfside-information-logo-copy">
                            <strong>Site logo</strong>
                            <p class="surfside-information-help">Select an image from the Media Library. The restored plugin logo remains the safe default.</p>
                            <div class="surfside-information-logo-actions">
                                <button type="button" class="surfside-information-add" data-surfside-logo-select><?php echo $logo_id > 0 ? 'Replace logo' : 'Select logo'; ?></button>
                                <button type="button" class="surfside-information-remove" data-surfside-logo-default <?php disabled($logo_id, 0); ?>>Use default logo</button>
                            </div>
                            <p class="surfside-information-logo-status" data-surfside-logo-status><?php echo $logo_id > 0 ? 'Using a custom Media Library logo.' : 'Using the restored plugin logo.'; ?></p>
                        </div>
                    </div>
                    <label class="surfside-information-field"><span>Church name</span><input type="text" name="church_name" value="<?php echo esc_attr($identity['name']); ?>" required></label>
                    <label class="surfside-information-field"><span>Phone</span><input type="tel" name="phone" value="<?php echo esc_attr($identity['phone']); ?>" required></label>
                    <label class="surfside-information-field"><span>Email</span><input type="email" name="email" value="<?php echo esc_attr($identity['email']); ?>" required></label>
                    <label class="surfside-information-field surfside-information-field-wide"><span>Tagline</span><input type="text" name="tagline" value="<?php echo esc_attr($identity['tagline']); ?>" required></label>
                    <label class="surfside-information-field surfside-information-field-wide"><span>Contact destination</span><input type="text" name="contact_url" value="<?php echo esc_attr($identity['contact_url']); ?>" required><small class="surfside-information-help">Use a site path such as /contact/#Contact or a complete URL.</small></label>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($section === 'information') : ?>
            <section class="surfside-information-card">
                <h2>Current Meeting Location</h2>
                <p>This address will generate the public Google Maps destination automatically.</p>
                <div class="surfside-information-grid">
                    <label class="surfside-information-field surfside-information-field-wide"><span>Venue</span><input type="text" name="venue" value="<?php echo esc_attr($location['venue']); ?>" required></label>
                    <label class="surfside-information-field surfside-information-field-wide"><span>Street address</span><input type="text" name="street" value="<?php echo esc_attr($location['street']); ?>" required></label>
                    <label class="surfside-information-field"><span>City</span><input type="text" name="city" value="<?php echo esc_attr($location['city']); ?>" required></label>
                    <label class="surfside-information-field"><span>State</span><input type="text" name="state" value="<?php echo esc_attr($location['state']); ?>" maxlength="2" required></label>
                    <label class="surfside-information-field"><span>ZIP code</span><input type="text" name="postal_code" value="<?php echo esc_attr($location['postal_code']); ?>" required></label>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($section === 'streaming') : ?>
            <section class="surfside-information-card">
                <h2>Watch Live Streaming</h2>
                <p>Control the live Twitch channel and the local announcement video shown whenever Twitch is offline.</p>
                <div class="surfside-information-grid">
                    <label class="surfside-information-field"><span>Twitch channel</span><input type="text" name="twitch_channel" value="<?php echo esc_attr($streaming['twitch_channel'] ?? 'surfsidecf'); ?>" required><small class="surfside-information-help">Channel name only, such as surfsidecf.</small></label>
                    <div class="surfside-information-field">
                        <span>Offline announcement video</span>
                        <input type="hidden" name="announcement_video_id" value="<?php echo esc_attr($announcement_video_id); ?>" data-surfside-stream-video-id>
                        <div class="surfside-information-logo-actions" data-surfside-stream-video>
                            <button type="button" class="surfside-information-add" data-surfside-stream-video-select><?php echo $announcement_video_id ? 'Replace video' : 'Select video'; ?></button>
                            <button type="button" class="surfside-information-remove" data-surfside-stream-video-remove <?php disabled($announcement_video_id, 0); ?>>Remove video</button>
                        </div>
                        <small class="surfside-information-help" data-surfside-stream-video-status><?php echo $announcement_video_name ? esc_html('Selected: ' . $announcement_video_name) : 'No fallback video selected. The next-service panel will appear while offline.'; ?></small>
                    </div>
                    <label class="surfside-information-field surfside-information-field-wide"><span>YouTube destination</span><input type="url" name="stream_youtube_url" value="<?php echo esc_attr($streaming['youtube_url'] ?? ''); ?>"></label>
                    <label class="surfside-information-field surfside-information-field-wide"><span>Facebook destination</span><input type="url" name="stream_facebook_url" value="<?php echo esc_attr($streaming['facebook_url'] ?? ''); ?>"></label>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($section === 'information') : ?>
            <section class="surfside-information-card">
                <h2>Weekly Service Schedule</h2>
                <p>Add every recurring weekly service here. Use Calendar Manager for one-time special services.</p>
                <fieldset class="surfside-information-services" data-surfside-services>
                    <legend class="screen-reader-text">Weekly services</legend>
                    <?php foreach ($information['services'] as $index => $service) : ?>
                        <div class="surfside-information-service">
                            <input type="hidden" name="services[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($service['key']); ?>">
                            <label class="surfside-information-field"><span>Day</span><select name="services[<?php echo esc_attr($index); ?>][weekday]"><?php foreach ($weekdays as $number => $day) : ?><option value="<?php echo esc_attr($number); ?>" <?php selected((int) $service['weekday'], $number); ?>><?php echo esc_html($day); ?></option><?php endforeach; ?></select></label>
                            <label class="surfside-information-field"><span>Public label</span><input type="text" name="services[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($service['label']); ?>" required></label>
                            <label class="surfside-information-field"><span>Start time</span><input type="time" name="services[<?php echo esc_attr($index); ?>][time]" value="<?php echo esc_attr($service['time']); ?>" required></label>
                            <div class="surfside-information-service-actions">
                                <label class="surfside-information-checkbox"><input type="checkbox" name="services[<?php echo esc_attr($index); ?>][livestream]" value="1" <?php checked(!empty($service['livestream'])); ?>> Livestream</label>
                                <button type="button" class="surfside-information-remove" data-surfside-remove-service>Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
                <div class="surfside-information-service-controls">
                    <button type="button" class="surfside-information-add" data-surfside-add-service>+ Add another service</button>
                </div>
                <p class="screen-reader-text" aria-live="polite" data-surfside-service-status></p>
                <template data-surfside-service-template>
                    <div class="surfside-information-service">
                        <input type="hidden" name="services[__INDEX__][key]" value="">
                        <label class="surfside-information-field"><span>Day</span><select name="services[__INDEX__][weekday]"><?php foreach ($weekdays as $number => $day) : ?><option value="<?php echo esc_attr($number); ?>"><?php echo esc_html($day); ?></option><?php endforeach; ?></select></label>
                        <label class="surfside-information-field"><span>Public label</span><input type="text" name="services[__INDEX__][label]" value="Worship Service" required></label>
                        <label class="surfside-information-field"><span>Start time</span><input type="time" name="services[__INDEX__][time]" required></label>
                        <div class="surfside-information-service-actions">
                            <label class="surfside-information-checkbox"><input type="checkbox" name="services[__INDEX__][livestream]" value="1"> Livestream</label>
                            <button type="button" class="surfside-information-remove" data-surfside-remove-service>Remove</button>
                        </div>
                    </div>
                </template>
            </section>
            <?php endif; ?>

            <?php if ($section === 'navigation') : ?>
            <section class="surfside-information-card">
                <h2>Main Navigation</h2>
                <p>Build the ordered menu shared by the footer and upcoming site header. Choose a published page to keep the link working if its title or slug changes.</p>
                <div class="surfside-information-navigation" data-surfside-navigation>
                    <?php foreach ($information['navigation'] as $index => $link) :
                        $destination = ($link['type'] ?? '') === 'page' && !empty($link['page_id'])
                            ? 'page:' . absint($link['page_id'])
                            : 'custom';
                        ?>
                        <div class="surfside-information-nav-item" draggable="true">
                            <span class="surfside-information-drag" aria-hidden="true" title="Drag to reorder">⋮⋮</span>
                            <input type="hidden" name="navigation[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($link['key'] ?? ''); ?>">
                            <label class="surfside-information-field"><span>Menu text</span><input type="text" name="navigation[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($link['label'] ?? ''); ?>" required></label>
                            <label class="surfside-information-field"><span>Destination</span><select name="navigation[<?php echo esc_attr($index); ?>][destination]" data-surfside-nav-destination>
                                <?php foreach ($published_pages as $page) : ?><option value="page:<?php echo esc_attr($page->ID); ?>" <?php selected($destination, 'page:' . $page->ID); ?>><?php echo esc_html($page->post_title); ?></option><?php endforeach; ?>
                                <option value="custom" <?php selected($destination, 'custom'); ?>>Custom URL</option>
                            </select></label>
                            <div class="surfside-information-nav-custom" data-surfside-nav-custom>
                                <label class="surfside-information-field"><span>Custom URL</span><input type="text" name="navigation[<?php echo esc_attr($index); ?>][url]" value="<?php echo esc_attr($link['url'] ?? ''); ?>" data-surfside-nav-url></label>
                                <label class="surfside-information-checkbox"><input type="checkbox" name="navigation[<?php echo esc_attr($index); ?>][new_tab]" value="1" <?php checked(!empty($link['new_tab'])); ?>> Open in new tab</label>
                            </div>
                            <div class="surfside-information-nav-actions">
                                <button type="button" class="surfside-information-remove" data-surfside-nav-up aria-label="Move <?php echo esc_attr($link['label'] ?? 'link'); ?> up">↑</button>
                                <button type="button" class="surfside-information-remove" data-surfside-nav-down aria-label="Move <?php echo esc_attr($link['label'] ?? 'link'); ?> down">↓</button>
                                <button type="button" class="surfside-information-remove" data-surfside-nav-remove>Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="surfside-information-add" data-surfside-nav-add>+ Add navigation link</button>
                <p class="screen-reader-text" aria-live="polite" data-surfside-nav-status></p>
                <template data-surfside-nav-template>
                    <div class="surfside-information-nav-item" draggable="true">
                        <span class="surfside-information-drag" aria-hidden="true" title="Drag to reorder">⋮⋮</span>
                        <input type="hidden" name="navigation[__INDEX__][key]" value="">
                        <label class="surfside-information-field"><span>Menu text</span><input type="text" name="navigation[__INDEX__][label]" required></label>
                        <label class="surfside-information-field"><span>Destination</span><select name="navigation[__INDEX__][destination]" data-surfside-nav-destination>
                            <?php foreach ($published_pages as $page) : ?><option value="page:<?php echo esc_attr($page->ID); ?>"><?php echo esc_html($page->post_title); ?></option><?php endforeach; ?>
                            <option value="custom">Custom URL</option>
                        </select></label>
                        <div class="surfside-information-nav-custom" data-surfside-nav-custom hidden>
                            <label class="surfside-information-field"><span>Custom URL</span><input type="text" name="navigation[__INDEX__][url]" data-surfside-nav-url></label>
                            <label class="surfside-information-checkbox"><input type="checkbox" name="navigation[__INDEX__][new_tab]" value="1"> Open in new tab</label>
                        </div>
                        <div class="surfside-information-nav-actions">
                            <button type="button" class="surfside-information-remove" data-surfside-nav-up aria-label="Move link up">↑</button>
                            <button type="button" class="surfside-information-remove" data-surfside-nav-down aria-label="Move link down">↓</button>
                            <button type="button" class="surfside-information-remove" data-surfside-nav-remove>Remove</button>
                        </div>
                    </div>
                </template>
            </section>
            <?php endif; ?>

            <?php if ($section === 'ministries') : ?>
            <section class="surfside-information-card">
                <h2>Adult Ministries</h2>
                <p>Manage the ministries displayed by <code>[surfside_adult_ministries]</code>. The order here is the public card order.</p>
                <div class="surfside-information-ministries" data-surfside-ministries>
                    <?php foreach ((array) ($information['adult_ministries'] ?? array()) as $index => $ministry) : ?>
                        <div class="surfside-information-ministry">
                            <input type="hidden" name="adult_ministries[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($ministry['key'] ?? ''); ?>">
                            <label class="surfside-information-field"><span>Icon</span><input type="text" name="adult_ministries[<?php echo esc_attr($index); ?>][icon]" value="<?php echo esc_attr($ministry['icon'] ?? ''); ?>" maxlength="12" placeholder="🙏"></label>
                            <label class="surfside-information-field"><span>Ministry name</span><input type="text" name="adult_ministries[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($ministry['name'] ?? ''); ?>" required data-surfside-ministry-name></label>
                            <label class="surfside-information-field"><span>Usual schedule</span><input type="text" name="adult_ministries[<?php echo esc_attr($index); ?>][schedule]" value="<?php echo esc_attr($ministry['schedule'] ?? ''); ?>"></label>
                            <label class="surfside-information-field"><span>Usual location</span><input type="text" name="adult_ministries[<?php echo esc_attr($index); ?>][location]" value="<?php echo esc_attr($ministry['location'] ?? ''); ?>"></label>
                            <div class="surfside-information-ministry-actions">
                                <button type="button" class="surfside-information-remove" data-surfside-ministry-up aria-label="Move <?php echo esc_attr($ministry['name'] ?? 'ministry'); ?> up">↑</button>
                                <button type="button" class="surfside-information-remove" data-surfside-ministry-down aria-label="Move <?php echo esc_attr($ministry['name'] ?? 'ministry'); ?> down">↓</button>
                                <button type="button" class="surfside-information-remove" data-surfside-ministry-remove>Remove</button>
                            </div>
                            <label class="surfside-information-field surfside-information-ministry-description"><span>Description</span><textarea name="adult_ministries[<?php echo esc_attr($index); ?>][description]" rows="3"><?php echo esc_textarea($ministry['description'] ?? ''); ?></textarea></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="surfside-information-add" data-surfside-ministry-add>+ Add adult ministry</button>
                <p class="screen-reader-text" aria-live="polite" data-surfside-ministry-status></p>
                <template data-surfside-ministry-template>
                    <div class="surfside-information-ministry">
                        <input type="hidden" name="adult_ministries[__INDEX__][key]" value="">
                        <label class="surfside-information-field"><span>Icon</span><input type="text" name="adult_ministries[__INDEX__][icon]" maxlength="12" placeholder="🙏"></label>
                        <label class="surfside-information-field"><span>Ministry name</span><input type="text" name="adult_ministries[__INDEX__][name]" required data-surfside-ministry-name></label>
                        <label class="surfside-information-field"><span>Usual schedule</span><input type="text" name="adult_ministries[__INDEX__][schedule]"></label>
                        <label class="surfside-information-field"><span>Usual location</span><input type="text" name="adult_ministries[__INDEX__][location]"></label>
                        <div class="surfside-information-ministry-actions"><button type="button" class="surfside-information-remove" data-surfside-ministry-up aria-label="Move ministry up">↑</button><button type="button" class="surfside-information-remove" data-surfside-ministry-down aria-label="Move ministry down">↓</button><button type="button" class="surfside-information-remove" data-surfside-ministry-remove>Remove</button></div>
                        <label class="surfside-information-field surfside-information-ministry-description"><span>Description</span><textarea name="adult_ministries[__INDEX__][description]" rows="3"></textarea></label>
                    </div>
                </template>
            </section>
            <?php endif; ?>

            <?php if ($section === 'information') : ?>
            <section class="surfside-information-card">
                <h2>Social Destinations</h2>
                <p>The footer will present these as accessible social icons.</p>
                <div class="surfside-information-link-list">
                    <?php foreach ($information['social'] as $key => $link) : ?>
                        <label class="surfside-information-link"><strong><?php echo esc_html($link['label']); ?></strong><input type="url" name="social[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($link['url']); ?>" required></label>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <div class="surfside-information-actions">
                <button type="submit" class="surfside-information-save"><?php echo esc_html($section_config['button']); ?></button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_staff_site_information', 'surfside_tools_staff_site_information_shortcode');

function surfside_tools_repair_site_information_staff_page() {
    if (!function_exists('surfside_tools_ensure_staff_page')) {
        return;
    }

    $dashboard = get_page_by_path('dashboard');
    if (!$dashboard) {
        return;
    }

    $existing = get_page_by_path('dashboard/surfside-information');
    if ($existing && $existing->post_status === 'publish') {
        return;
    }

    $page_id = surfside_tools_ensure_staff_page(
        'Surfside Information',
        'surfside-information',
        '[surfside_staff_site_information]',
        (int) $dashboard->ID
    );

    if ($page_id) {
        flush_rewrite_rules(false);
    }
}
add_action('init', 'surfside_tools_repair_site_information_staff_page', 70);
