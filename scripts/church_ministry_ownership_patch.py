from pathlib import Path
import re


def replace_once(text, old, new, label):
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 match, found {count}")
    return text.replace(old, new, 1)


def sub_once(text, pattern, repl, label, flags=0):
    updated, count = re.subn(pattern, repl, text, count=1, flags=flags)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 match, found {count}")
    return updated

# Contact Routing: recipients only; Turnstile remains owned by Integrations.
path = Path('includes/contact-management.php')
text = path.read_text()
old_save = "$existing=get_option('surfside_tools_contact_settings',array()); $existing=is_array($existing)?$existing:array();\n        $recipients=array(); foreach(surfside_tools_contact_categories() as $key=>$label){$recipients[$key]=sanitize_email(wp_unslash($_POST['recipient_'.$key]??''));}\n        $site_key=sanitize_text_field(wp_unslash($_POST['turnstile_site_key']??''));\n        $secret_input=sanitize_text_field(wp_unslash($_POST['turnstile_secret_key']??''));\n        $secret_key=$secret_input!==''?$secret_input:sanitize_text_field($existing['turnstile_secret_key']??'');\n        update_option('surfside_tools_contact_settings',array('recipients'=>$recipients,'turnstile_site_key'=>$site_key,'turnstile_secret_key'=>$secret_key)); $saved=true;"
new_save = "$existing=get_option('surfside_tools_contact_settings',array()); $existing=is_array($existing)?$existing:array();\n        $recipients=array(); foreach(surfside_tools_contact_categories() as $key=>$label){$recipients[$key]=sanitize_email(wp_unslash($_POST['recipient_'.$key]??''));}\n        $existing['recipients']=$recipients;\n        update_option('surfside_tools_contact_settings',$existing); $saved=true;"
text = replace_once(text, old_save, new_save, 'contact save ownership')
text = replace_once(
    text,
    "<div class=\"surfside-staff-back\"><a href=\"<?php echo esc_url(surfside_tools_staff_page_url('site-management')); ?>\">← Back to Site Management</a></div>",
    "<div class=\"surfside-staff-back\"><a href=\"<?php echo esc_url(surfside_tools_staff_page_url('site-settings')); ?>\">← Back to Church Settings</a></div>",
    'contact back link'
)
text = sub_once(
    text,
    r'\n        <section class="surfside-staff-panel"><h2>Cloudflare Turnstile</h2>.*?</section>',
    '',
    'contact Turnstile panel',
    re.S,
)
if 'name="turnstile_site_key"' in text or 'name="turnstile_secret_key"' in text:
    raise SystemExit('Contact Routing still renders Turnstile fields')
path.write_text(text)

# Church Settings support: remove post-render rewrite/filter; keep shared Integrations save/panel helpers.
path = Path('includes/church-settings-polish.php')
text = path.read_text()
text = sub_once(
    text,
    r"function surfside_tools_church_settings_back_link\(\$output\) \{.*?\n\}\n\n(?=function surfside_tools_church_settings_shared_save)",
    '',
    'church settings back-link helper',
    re.S,
)
text = sub_once(
    text,
    r"\nadd_filter\('do_shortcode_tag', function \(\$output, \$tag\) \{.*?\n\}, 40, 2\);\n?",
    '\n',
    'church settings shortcode filter',
    re.S,
)
if 'do_shortcode_tag' in text or 'surfside_tools_church_settings_back_link' in text:
    raise SystemExit('Church Settings post-render compatibility layer remains')
path.write_text(text)

# Surfside Information: native Church Settings navigation and remove dead legacy Ministries editor generation.
path = Path('includes/site-information-manager.php')
text = path.read_text()
text = replace_once(
    text,
    "<div class=\"surfside-staff-back\"><a href=\"<?php echo esc_url(surfside_tools_staff_page_url('site-management')); ?>\">← Back to Site Management</a></div>",
    "<div class=\"surfside-staff-back\"><a href=\"<?php echo esc_url(surfside_tools_staff_page_url('site-settings')); ?>\">← Back to Church Settings</a></div>",
    'site information back link'
)
text = sub_once(
    text,
    r"\n    \$posted_ministries = isset\(\$_POST\['adult_ministries'\]\).*?\n    \$section = isset\(\$_POST\['surfside_information_section'\]\)",
    "\n    $section = isset($_POST['surfside_information_section'])",
    'legacy ministry POST parsing',
    re.S,
)
text = replace_once(
    text,
    "    } elseif ($section === 'ministries') {\n        $updated_information['adult_ministries'] = $adult_ministries;\n    }\n",
    "    }\n",
    'legacy ministry save branch'
)
text = replace_once(
    text,
    "        'ministries' => array('eyebrow' => 'Site Management', 'title' => 'Ministries', 'description' => 'Manage ministry content displayed by Surfside Tools.', 'button' => 'Save Ministries'),\n",
    '',
    'legacy ministry section config'
)
text = sub_once(
    text,
    r"\n    wp_add_inline_style\('surfside-tools-information-manager', '\n        \.surfside-information-ministries.*?\n    '\);\n",
    '\n',
    'legacy ministry manager CSS',
    re.S,
)
text = sub_once(
    text,
    r"\n    wp_add_inline_script\('surfside-tools-information-manager', '\n        document\.addEventListener\(\"DOMContentLoaded\", function \(\) \{\n            var list = document\.querySelector\(\"\[data-surfside-ministries\]\"\);.*?\n    '\);\n",
    '\n',
    'legacy ministry manager JS',
    re.S,
)
text = sub_once(
    text,
    r"\n            <\?php if \(\$section === 'ministries'\) : \?>.*?\n            <\?php endif; \?>",
    '',
    'legacy ministry editor markup',
    re.S,
)
if "section === 'ministries'" in text or 'adult_ministries' in text or 'data-surfside-ministries' in text:
    raise SystemExit('Legacy Surfside Information ministry editor remains')
path.write_text(text)

# Public Ministry Directory: fold presentation refinements into the authoritative renderer.
path = Path('includes/adult-ministries.php')
text = path.read_text()
text = replace_once(
    text,
    "    $dialog_id=wp_unique_id('surfside-ministry-dialog-');\n",
    "    $dialog_id=wp_unique_id('surfside-ministry-dialog-');\n    $contact_url=esc_url(home_url('/contact/'));\n",
    'ministry contact URL'
)
text = replace_once(
    text,
    "      <dialog class=\"surfside-ministry-dialog\" id=\"<?php echo esc_attr($dialog_id); ?>\" data-ministry-dialog aria-labelledby=\"<?php echo esc_attr($dialog_id); ?>-title\"><div class=\"surfside-ministry-dialog__panel\"><button type=\"button\" class=\"surfside-ministry-dialog__close\" data-ministry-dialog-close aria-label=\"Close ministry details\">×</button><h3 id=\"<?php echo esc_attr($dialog_id); ?>-title\" data-ministry-dialog-title></h3><p class=\"surfside-ministry-dialog__schedule\" data-ministry-dialog-schedule hidden></p><p class=\"surfside-ministry-dialog__location\" data-ministry-dialog-location hidden></p><p class=\"surfside-ministry-dialog__description\" data-ministry-dialog-description hidden></p><div class=\"surfside-ministry-dialog__contact\" data-ministry-dialog-contact hidden><p data-ministry-dialog-contact-label></p><div class=\"surfside-ministry-dialog__contact-actions\"><a data-ministry-dialog-email>Email</a><a data-ministry-dialog-phone>Call</a></div></div></div></dialog>\n    </div><style>",
    "      <dialog class=\"surfside-ministry-dialog\" id=\"<?php echo esc_attr($dialog_id); ?>\" data-ministry-dialog aria-labelledby=\"<?php echo esc_attr($dialog_id); ?>-title\"><div class=\"surfside-ministry-dialog__panel\"><button type=\"button\" class=\"surfside-ministry-dialog__close\" data-ministry-dialog-close aria-label=\"Close ministry details\">×</button><h3 id=\"<?php echo esc_attr($dialog_id); ?>-title\" data-ministry-dialog-title></h3><p class=\"surfside-ministry-dialog__schedule\" data-ministry-dialog-schedule hidden></p><p class=\"surfside-ministry-dialog__location\" data-ministry-dialog-location hidden></p><p class=\"surfside-ministry-dialog__description\" data-ministry-dialog-description hidden></p><div class=\"surfside-ministry-dialog__contact\" data-ministry-dialog-contact hidden><p data-ministry-dialog-contact-label></p><div class=\"surfside-ministry-dialog__contact-actions\"><a data-ministry-dialog-email>Email</a><a data-ministry-dialog-phone>Call</a></div></div></div></dialog>\n      <div class=\"surfside-all-ministries__closing\"><h3>Interested in Serving?</h3><p>There are too many opportunities to list here! We’d love to help you find a place to use your gifts and talents.</p><a class=\"surfside-button\" href=\"<?php echo esc_url($contact_url); ?>\">Contact Us About Serving</a></div>\n    </div><style>",
    'ministry closing CTA'
)
old_css_marker = ".surfside-ministry-dialog__contact-actions a[hidden]{display:none!important}@media(max-width:900px)"
new_css_marker = ".surfside-ministry-dialog__contact-actions a[hidden]{display:none!important}.surfside-all-ministries{position:relative;z-index:0;box-sizing:border-box;width:100%!important;max-width:none!important;margin:0!important;padding-block:clamp(2.5rem,4vw,3.25rem)}.surfside-all-ministries::before{position:absolute;z-index:-1;inset-block:0;left:50%;width:100vw;width:100dvw;background:var(--surfside-color-white,#fff);content:\"\";transform:translateX(-50%)}.surfside-all-ministries__inner{box-sizing:border-box!important;width:min(100% - 2rem,72rem)!important;max-width:none!important;margin-inline:auto!important}.surfside-all-ministries__intro{text-align:center}.surfside-all-ministries__filters{justify-content:center}.surfside-all-ministries__closing{text-align:center;max-width:54rem;margin:1.2rem auto 0;padding-top:.2rem}.surfside-all-ministries__closing h3{margin:0 0 .35rem;color:var(--surfside-color-ocean-950,#061b33);font-size:1.05rem}.surfside-all-ministries__closing p{margin:0 0 .9rem;color:var(--surfside-color-muted,#536579)}.surfside-all-ministries__closing .surfside-button{display:inline-flex;align-items:center;justify-content:center;text-decoration:none}.surfside-ministry-dialog__contact-actions a[data-ministry-dialog-email]{max-width:100%;overflow-wrap:anywhere}.surfside-ministry-dialog__contact-actions a[data-ministry-dialog-phone].surfside-phone-display{background:#eef4f7!important;color:#31566d!important;cursor:text;user-select:text}@media(max-width:900px)"
text = replace_once(text, old_css_marker, new_css_marker, 'ministry directory CSS')
text = replace_once(
    text,
    "@media(max-width:620px){.surfside-all-ministries__list{grid-template-columns:1fr}}",
    "@media(max-width:620px){.surfside-all-ministries__list{grid-template-columns:1fr}}@media(max-width:700px){.surfside-all-ministries__inner{width:min(100% - 1.25rem,72rem)!important}}",
    'ministry mobile width CSS'
)
old_contact_js = "if(email){if(contactEmail){email.href='mailto:'+contactEmail;email.hidden=false;}else{email.removeAttribute('href');email.hidden=true;}}if(phone){if(contactPhone){phone.href='tel:'+contactPhone.replace(/[^0-9+]/g,'');phone.hidden=false;}else{phone.removeAttribute('href');phone.hidden=true;}}"
new_contact_js = "if(email){if(contactEmail){email.href='mailto:'+contactEmail;email.textContent='Email: '+contactEmail;email.setAttribute('title','Email '+contactEmail);email.hidden=false;}else{email.removeAttribute('href');email.removeAttribute('title');email.textContent='Email';email.hidden=true;}}if(phone){if(contactPhone){var digits=contactPhone.replace(/\\D/g,'');if(digits.length===11&&digits.charAt(0)==='1')digits=digits.slice(1);var displayPhone=digits.length===10?'('+digits.slice(0,3)+') '+digits.slice(3,6)+'-'+digits.slice(6):contactPhone;phone.removeAttribute('href');phone.textContent='Phone: '+displayPhone;phone.classList.add('surfside-phone-display');phone.setAttribute('aria-label','Phone '+displayPhone);phone.hidden=false;}else{phone.removeAttribute('href');phone.removeAttribute('aria-label');phone.textContent='Call';phone.classList.remove('surfside-phone-display');phone.hidden=true;}}"
text = replace_once(text, old_contact_js, new_contact_js, 'ministry contact presentation JS')
path.write_text(text)

# Bootstrap: the public directory owner now contains the final presentation.
path = Path('surfside-tools.php')
text = path.read_text()
text = replace_once(
    text,
    "require_once SURFSIDE_TOOLS_PATH . 'includes/ministry-directory-polish.php';\n",
    '',
    'ministry polish bootstrap include'
)
path.write_text(text)

polish = Path('includes/ministry-directory-polish.php')
if not polish.exists():
    raise SystemExit('Expected ministry-directory-polish.php to exist before retirement')
polish.unlink()

# Final structural assertions.
for candidate in [Path('includes/contact-management.php'), Path('includes/church-settings-polish.php'), Path('includes/site-information-manager.php'), Path('includes/adult-ministries.php')]:
    if not candidate.exists():
        raise SystemExit(f'Missing expected production file: {candidate}')
if 'do_shortcode_tag' in Path('includes/church-settings-polish.php').read_text():
    raise SystemExit('Church Settings still uses do_shortcode_tag')
if 'ministry-directory-polish.php' in Path('surfside-tools.php').read_text():
    raise SystemExit('Retired ministry polish is still bootstrapped')
