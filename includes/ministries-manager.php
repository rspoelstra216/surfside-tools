<?php
/** Dedicated staff manager for ongoing ministries. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_staff_ministries_manager_shortcode() {
    if (function_exists('surfside_tools_prevent_cache')) surfside_tools_prevent_cache();
    if (function_exists('surfside_tools_staff_enqueue_styles')) surfside_tools_staff_enqueue_styles();
    if (function_exists('surfside_tools_site_information_manager_assets')) surfside_tools_site_information_manager_assets();
    if (!is_user_logged_in()) return function_exists('surfside_tools_staff_login_box') ? surfside_tools_staff_login_box('Please log in to manage ministries.') : '<p>Please log in.</p>';
    if (!current_user_can('manage_options')) return '<div class="surfside-staff-shell"><p>You do not have permission to manage ministries.</p></div>';

    $notice = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['surfside_ministries_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_ministries_nonce'])), 'surfside_ministries_save')) {
        $posted = isset($_POST['ministries']) && is_array($_POST['ministries']) ? wp_unslash($_POST['ministries']) : array();
        surfside_tools_update_ministries($posted);
        if (function_exists('surfside_tools_update_ministry_default_email')) {
            $default_email = isset($_POST['ministry_default_email']) ? sanitize_email(wp_unslash($_POST['ministry_default_email'])) : '';
            surfside_tools_update_ministry_default_email($default_email);
        }
        $notice = '<div class="surfside-mobile-notice">Ministries saved.</div>';
    }

    $ministries = surfside_tools_get_ministries();
    $default_email = function_exists('surfside_tools_get_ministry_default_email') ? surfside_tools_get_ministry_default_email() : '';
    $audiences = surfside_tools_ministry_audience_choices();
    $icon_choices = array(
        '🙏'=>'Prayer', '✝️'=>'Faith', '📖'=>'Bible', '❤️'=>'Care', '🤝'=>'Serving', '👨‍👩‍👧‍👦'=>'Families',
        '🧒'=>'Kids', '🧑‍🤝‍🧑'=>'Community', '☕'=>'Fellowship', '🎵'=>'Music', '🎤'=>'Worship', '🌊'=>'Ocean',
        '🏄'=>'Surfing', '🥋'=>'Jiu Jitsu', '🏃'=>'Fitness', '🍽️'=>'Meals', '🧰'=>'Projects', '🌱'=>'Growth',
        '🕊️'=>'Peace', '🎨'=>'Creative', '🎓'=>'Students', '🔥'=>'Youth', '🚌'=>'Outreach', '🏠'=>'Home Group'
    );
    ob_start(); ?>
    <div class="surfside-staff-shell surfside-information-manager">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('site-settings')); ?>">← Back to Site Settings</a></div>
      <section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Website &amp; App</p><h1>Ministries</h1><p class="surfside-staff-muted">Manage ongoing ministries shared by the website and mobile app. Choose every audience each ministry serves. Bible studies are managed separately through Calendar Manager.</p></section>
      <?php echo $notice; ?>
      <form method="post" class="surfside-information-form">
        <?php wp_nonce_field('surfside_ministries_save', 'surfside_ministries_nonce'); ?>
        <section class="surfside-ministry-default-contact">
          <h2>Default ministry contact</h2>
          <p>If a ministry does not have its own email address, messages will use this address.</p>
          <label class="surfside-information-field"><span>Default contact email</span><input type="email" name="ministry_default_email" value="<?php echo esc_attr($default_email); ?>" placeholder="office@example.org"></label>
        </section>
        <div class="surfside-information-ministries" data-surfside-ministries>
          <?php foreach ($ministries as $index => $ministry):
            $featured = array_key_exists('featured', $ministry) ? !empty($ministry['featured']) : true;
            $published = array_key_exists('published', $ministry) ? !empty($ministry['published']) : true;
          ?>
          <section class="surfside-information-card surfside-information-ministry">
            <div class="surfside-ministry-card-heading"><small>Ministry <?php echo esc_html($index + 1); ?></small><span><?php echo esc_html(trim(($ministry['icon'] ?? '') . ' ' . ($ministry['name'] ?? ''))); ?></span></div>
            <input type="hidden" name="ministries[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($ministry['key'] ?? ''); ?>">
            <label class="surfside-information-field surfside-ministry-icon-field"><span>Icon</span><span class="surfside-ministry-icon-control"><input type="text" name="ministries[<?php echo esc_attr($index); ?>][icon]" value="<?php echo esc_attr($ministry['icon'] ?? ''); ?>" maxlength="12" placeholder="🙏" data-ministry-icon-input readonly aria-label="Choose ministry emoji" title="Choose ministry emoji"><button type="button" class="surfside-information-remove surfside-ministry-icon-button" data-ministry-icon-open>Choose</button></span></label>
            <label class="surfside-information-field"><span>Ministry name</span><input type="text" name="ministries[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($ministry['name'] ?? ''); ?>" required></label>
            <label class="surfside-information-field"><span>Usual schedule</span><input type="text" name="ministries[<?php echo esc_attr($index); ?>][schedule]" value="<?php echo esc_attr($ministry['schedule'] ?? ''); ?>"></label>
            <label class="surfside-information-field"><span>Usual location</span><input type="text" name="ministries[<?php echo esc_attr($index); ?>][location]" value="<?php echo esc_attr($ministry['location'] ?? ''); ?>"></label>
            <fieldset class="surfside-ministry-audiences"><legend>Who is this ministry for?</legend><?php foreach ($audiences as $key => $label): ?><label class="surfside-information-checkbox"><input type="checkbox" name="ministries[<?php echo esc_attr($index); ?>][audiences][]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, (array) ($ministry['audiences'] ?? array('adults')), true)); ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></fieldset>
            <div class="surfside-ministry-publish-row" data-ministry-publish-row>
              <input type="hidden" name="ministries[<?php echo esc_attr($index); ?>][published]" value="0">
              <label><input type="checkbox" name="ministries[<?php echo esc_attr($index); ?>][published]" value="1" <?php checked($published); ?>> <span>Published</span></label>
              <span class="surfside-ministry-status <?php echo $published ? 'surfside-ministry-status--published' : 'surfside-ministry-status--draft'; ?>" data-ministry-publish-status><?php echo $published ? 'Published' : 'Draft'; ?></span>
            </div>
            <div class="surfside-ministry-featured">
              <input type="hidden" name="ministries[<?php echo esc_attr($index); ?>][featured]" value="0">
              <label class="surfside-information-checkbox"><input type="checkbox" name="ministries[<?php echo esc_attr($index); ?>][featured]" value="1" <?php checked($featured); ?>> <strong>Featured Ministry</strong></label>
              <span class="surfside-staff-muted">Show this ministry in the featured Serve &amp; Get Involved block.</span>
            </div>
            <div class="surfside-ministry-contact-fields">
              <label class="surfside-information-field"><span>Contact name</span><input type="text" name="ministries[<?php echo esc_attr($index); ?>][contact_name]" value="<?php echo esc_attr($ministry['contact_name'] ?? ''); ?>"></label>
              <label class="surfside-information-field"><span>Contact email</span><input type="email" name="ministries[<?php echo esc_attr($index); ?>][contact_email]" value="<?php echo esc_attr($ministry['contact_email'] ?? ''); ?>"></label>
              <label class="surfside-information-field"><span>Contact phone</span><input type="tel" name="ministries[<?php echo esc_attr($index); ?>][contact_phone]" value="<?php echo esc_attr($ministry['contact_phone'] ?? ''); ?>" inputmode="tel" placeholder="(321) 555-1234"></label>
              <p class="surfside-ministry-contact-note">Email may be left blank to use the default ministry contact. Phone is optional; if blank, no Call button will be shown.</p>
            </div>
            <label class="surfside-information-field surfside-information-ministry-description"><span>Description</span><textarea name="ministries[<?php echo esc_attr($index); ?>][description]" rows="3"><?php echo esc_textarea($ministry['description'] ?? ''); ?></textarea></label>
            <div class="surfside-information-ministry-actions"><button type="button" class="surfside-information-remove" data-ministry-up>↑</button><button type="button" class="surfside-information-remove" data-ministry-down>↓</button><button type="button" class="surfside-information-remove" data-ministry-remove>Remove</button></div>
          </section>
          <?php endforeach; ?>
        </div>
        <button type="button" class="surfside-information-add" data-ministry-add>+ Add ministry</button>
        <template data-ministry-template><section class="surfside-information-card surfside-information-ministry"><div class="surfside-ministry-card-heading"><small>New ministry</small><span>New ministry</span></div><input type="hidden" name="ministries[__INDEX__][key]" value=""><label class="surfside-information-field surfside-ministry-icon-field"><span>Icon</span><span class="surfside-ministry-icon-control"><input type="text" name="ministries[__INDEX__][icon]" maxlength="12" placeholder="🙏" data-ministry-icon-input readonly aria-label="Choose ministry emoji" title="Choose ministry emoji"><button type="button" class="surfside-information-remove surfside-ministry-icon-button" data-ministry-icon-open>Choose</button></span></label><label class="surfside-information-field"><span>Ministry name</span><input type="text" name="ministries[__INDEX__][name]" required></label><label class="surfside-information-field"><span>Usual schedule</span><input type="text" name="ministries[__INDEX__][schedule]"></label><label class="surfside-information-field"><span>Usual location</span><input type="text" name="ministries[__INDEX__][location]"></label><fieldset class="surfside-ministry-audiences"><legend>Who is this ministry for?</legend><?php foreach ($audiences as $key => $label): ?><label class="surfside-information-checkbox"><input type="checkbox" name="ministries[__INDEX__][audiences][]" value="<?php echo esc_attr($key); ?>" <?php checked($key, 'adults'); ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></fieldset><div class="surfside-ministry-publish-row" data-ministry-publish-row><input type="hidden" name="ministries[__INDEX__][published]" value="0"><label><input type="checkbox" name="ministries[__INDEX__][published]" value="1"> <span>Published</span></label><span class="surfside-ministry-status surfside-ministry-status--draft" data-ministry-publish-status>Draft</span></div><div class="surfside-ministry-featured"><input type="hidden" name="ministries[__INDEX__][featured]" value="0"><label class="surfside-information-checkbox"><input type="checkbox" name="ministries[__INDEX__][featured]" value="1"> <strong>Featured Ministry</strong></label><span class="surfside-staff-muted">Show this ministry in the featured Serve &amp; Get Involved block.</span></div><div class="surfside-ministry-contact-fields"><label class="surfside-information-field"><span>Contact name</span><input type="text" name="ministries[__INDEX__][contact_name]"></label><label class="surfside-information-field"><span>Contact email</span><input type="email" name="ministries[__INDEX__][contact_email]"></label><label class="surfside-information-field"><span>Contact phone</span><input type="tel" name="ministries[__INDEX__][contact_phone]" inputmode="tel" placeholder="(321) 555-1234"></label><p class="surfside-ministry-contact-note">Email may be left blank to use the default ministry contact. Phone is optional; if blank, no Call button will be shown.</p></div><label class="surfside-information-field surfside-information-ministry-description"><span>Description</span><textarea name="ministries[__INDEX__][description]" rows="3"></textarea></label><div class="surfside-information-ministry-actions"><button type="button" class="surfside-information-remove" data-ministry-up>↑</button><button type="button" class="surfside-information-remove" data-ministry-down>↓</button><button type="button" class="surfside-information-remove" data-ministry-remove>Remove</button></div></section></template>
        <div class="surfside-information-actions"><button type="submit" class="surfside-information-save">Save Ministries</button></div>
      </form>
      <div class="surfside-ministry-icon-picker" data-ministry-icon-picker hidden>
        <div class="surfside-ministry-icon-picker-backdrop" data-ministry-icon-close></div>
        <section class="surfside-ministry-icon-picker-panel" role="dialog" aria-modal="true" aria-labelledby="surfside-ministry-icon-title">
          <div class="surfside-ministry-icon-picker-head"><div><p class="surfside-staff-eyebrow">Ministry Icon</p><h2 id="surfside-ministry-icon-title">Choose an emoji</h2><p class="surfside-staff-muted">Pick a common icon below, or close this window and paste any emoji directly into the Icon field.</p></div><button type="button" class="surfside-ministry-icon-close" data-ministry-icon-close aria-label="Close icon picker">×</button></div>
          <div class="surfside-ministry-icon-grid"><?php foreach ($icon_choices as $emoji => $label): ?><button type="button" class="surfside-ministry-icon-choice" data-ministry-icon-value="<?php echo esc_attr($emoji); ?>" title="<?php echo esc_attr($label); ?>"><span aria-hidden="true"><?php echo esc_html($emoji); ?></span><small><?php echo esc_html($label); ?></small></button><?php endforeach; ?></div>
        </section>
      </div>
    </div>
    <style>
      .surfside-ministry-default-contact{margin:0 0 18px;padding:18px;border:1px solid #d7e0e8;border-radius:14px;background:#f8fafc}.surfside-ministry-default-contact h2{margin:0 0 5px;color:#061b33;font-size:1.15rem}.surfside-ministry-default-contact p{margin:0 0 12px;color:#60708a}.surfside-ministry-default-contact .surfside-information-field{max-width:520px}
      .surfside-information-ministries{display:grid;gap:18px}.surfside-information-ministry{position:relative;display:grid;grid-template-columns:72px repeat(3,minmax(0,1fr));column-gap:12px;row-gap:13px;align-items:end;padding:16px 18px 18px!important;border:1px solid #d7e0e8!important;border-left:5px solid #0b5fa5!important;border-radius:14px!important;background:#fff!important}.surfside-information-ministry:nth-child(even){background:#f5f8fb!important;border-left-color:#6b8ca4!important}
      .surfside-ministry-card-heading{grid-column:1/-1;display:flex;align-items:center;gap:8px;margin:-2px 0 3px;padding-bottom:8px;border-bottom:1px solid rgba(96,112,138,.18);color:#061b33;font-size:1.02rem;font-weight:900;line-height:1.25}.surfside-ministry-card-heading small{color:#60708a;font-size:.76rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
      .surfside-ministry-audiences{grid-column:1/-1;display:flex;gap:10px;flex-wrap:wrap;border:0;padding:7px 0 5px;margin:2px 0 0}.surfside-ministry-audiences legend{width:100%;margin-bottom:5px;font-size:.9rem;font-weight:800;color:#26323d}.surfside-ministry-publish-row{grid-column:1/-1;display:flex;align-items:center;gap:10px;padding:8px 0 2px}.surfside-ministry-publish-row label{display:inline-flex;align-items:center;gap:8px;font-weight:800;color:#26323d}.surfside-ministry-status{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:.04em}.surfside-ministry-status--published{background:#eaf7ef;color:#126b36}.surfside-ministry-status--draft{background:#fff4d6;color:#7a5200}.surfside-ministry-featured{grid-column:1/-1;display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:2px 0;padding:5px 0}.surfside-ministry-featured .surfside-staff-muted{font-size:.86rem}
      .surfside-ministry-contact-fields{grid-column:1/-1;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px 12px;margin-top:2px;padding:12px;border:1px solid #d7e0e8;border-radius:12px;background:rgba(255,255,255,.58)}.surfside-information-ministry:nth-child(even) .surfside-ministry-contact-fields{background:rgba(255,255,255,.82)}.surfside-ministry-contact-fields .surfside-information-field{margin:0}.surfside-ministry-contact-note{grid-column:1/-1;margin:3px 0 0;color:#60708a;font-size:.78rem;line-height:1.35}.surfside-information-ministry-description,.surfside-information-ministry-actions{grid-column:1/-1}.surfside-information-ministry-description{margin-top:1px!important}.surfside-information-ministry-description textarea{box-sizing:border-box;width:100%;min-height:64px;height:64px;padding:10px 12px;border:1px solid #aeb9c4;border-radius:9px;font:inherit;resize:vertical}.surfside-information-ministry-actions{display:flex;gap:8px;margin-top:1px!important;padding-top:4px!important}.surfside-information-ministry .surfside-information-field>span{display:block;margin-bottom:4px;font-size:.86rem}
      .surfside-ministry-icon-control{display:block}.surfside-ministry-icon-control input[data-ministry-icon-input]{box-sizing:border-box;width:58px;min-width:58px;max-width:58px;height:48px;padding:6px;border-radius:10px;text-align:center;font-size:1.45rem;line-height:1;cursor:pointer;caret-color:transparent}.surfside-ministry-icon-control input[data-ministry-icon-input]:hover,.surfside-ministry-icon-control input[data-ministry-icon-input]:focus-visible{border-color:#0b5fa5;box-shadow:0 0 0 3px rgba(11,95,165,.12);outline:0}.surfside-ministry-icon-button{display:none!important}
      .surfside-ministry-icon-picker[hidden]{display:none}.surfside-ministry-icon-picker{position:fixed;inset:0;z-index:99999;display:grid;place-items:center;padding:20px}.surfside-ministry-icon-picker-backdrop{position:absolute;inset:0;background:rgba(6,27,51,.55)}.surfside-ministry-icon-picker-panel{position:relative;z-index:1;width:min(720px,100%);max-height:min(760px,90vh);overflow:auto;padding:24px;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(6,27,51,.28)}.surfside-ministry-icon-picker-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:20px}.surfside-ministry-icon-picker-head h2{margin:0;color:#061b33}.surfside-ministry-icon-close{border:0;background:transparent;color:#52606d;font-size:2rem;line-height:1;cursor:pointer}.surfside-ministry-icon-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.surfside-ministry-icon-choice{display:grid;place-items:center;gap:5px;min-height:84px;padding:9px 6px;border:1px solid #d7e0e8;border-radius:12px;background:#f8fafc;color:#26323d;cursor:pointer}.surfside-ministry-icon-choice:hover,.surfside-ministry-icon-choice:focus-visible{border-color:#0b5fa5;background:#eaf3fb;outline:0}.surfside-ministry-icon-choice span{font-size:1.8rem;line-height:1}.surfside-ministry-icon-choice small{font-size:.73rem;line-height:1.15;text-align:center}
      @media(max-width:900px){.surfside-information-ministry{grid-template-columns:72px repeat(2,minmax(0,1fr))}.surfside-ministry-contact-fields{grid-template-columns:1fr 1fr}}@media(max-width:700px){.surfside-information-ministry,.surfside-ministry-contact-fields{grid-template-columns:1fr}.surfside-information-ministry{padding:14px!important;row-gap:12px!important}.surfside-ministry-icon-field{max-width:72px}}@media(max-width:480px){.surfside-ministry-icon-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
    </style>
    <script>
    (function(){
      var list=document.querySelector('[data-surfside-ministries]'),add=document.querySelector('[data-ministry-add]'),template=document.querySelector('[data-ministry-template]'),picker=document.querySelector('[data-ministry-icon-picker]'),activeInput=null;
      if(!list||!add||!template)return;
      var next=1000;
      function formatPhone(value){var digits=(value||'').replace(/\D/g,'');if(digits.length===11&&digits.charAt(0)==='1')digits=digits.slice(1);return digits.length===10?'('+digits.slice(0,3)+') '+digits.slice(3,6)+'-'+digits.slice(6):value;}
      function updateCard(card,index){
        var heading=card.querySelector('.surfside-ministry-card-heading'),nameInput=card.querySelector('input[name$="[name]"]'),iconInput=card.querySelector('input[name$="[icon]"]'),phoneInput=card.querySelector('input[name$="[contact_phone]"]'),publishedInput=card.querySelector('input[name$="[published]"][type="checkbox"]'),status=card.querySelector('[data-ministry-publish-status]');
        if(heading){var small=heading.querySelector('small'),label=heading.querySelector('span'),name=nameInput&&nameInput.value?nameInput.value:'New ministry',icon=iconInput&&iconInput.value?iconInput.value:'';if(small)small.textContent='Ministry '+(index+1);if(label)label.textContent=(icon?icon+' ':'')+name;}
        if(phoneInput)phoneInput.value=formatPhone(phoneInput.value);
        if(publishedInput&&status){status.textContent=publishedInput.checked?'Published':'Draft';status.className='surfside-ministry-status '+(publishedInput.checked?'surfside-ministry-status--published':'surfside-ministry-status--draft');}
      }
      function decorate(){Array.from(list.children).forEach(updateCard);}
      function renumber(){Array.from(list.children).forEach(function(item,i){item.querySelectorAll('[name]').forEach(function(el){el.name=el.name.replace(/ministries\[[^\]]+\]/,'ministries['+i+']');});updateCard(item,i);});}
      function closePicker(){if(!picker)return;picker.hidden=true;activeInput=null;}
      add.addEventListener('click',function(){var wrap=document.createElement('div');wrap.innerHTML=template.innerHTML.replaceAll('__INDEX__','new-'+next++).trim();list.appendChild(wrap.firstElementChild);renumber();});
      list.addEventListener('input',function(e){if(e.target.matches('input[name$="[name]"]'))decorate();});
      list.addEventListener('change',function(e){if(e.target.matches('input[name$="[published]"][type="checkbox"]'))decorate();});
      list.addEventListener('blur',function(e){if(e.target.matches('input[name$="[contact_phone]"]'))e.target.value=formatPhone(e.target.value);},true);
      list.addEventListener('click',function(e){
        var item=e.target.closest('.surfside-information-ministry');if(!item)return;
        if(e.target.closest('[data-ministry-remove]'))item.remove();
        else if(e.target.closest('[data-ministry-up]')&&item.previousElementSibling)list.insertBefore(item,item.previousElementSibling);
        else if(e.target.closest('[data-ministry-down]')&&item.nextElementSibling)list.insertBefore(item.nextElementSibling,item);
        else if(e.target.matches('input[data-ministry-icon-input]')||e.target.closest('[data-ministry-icon-open]')){activeInput=item.querySelector('[data-ministry-icon-input]');if(picker)picker.hidden=false;}
        renumber();
      });
      list.addEventListener('keydown',function(e){if((e.key==='Enter'||e.key===' ')&&e.target.matches('input[data-ministry-icon-input]')){e.preventDefault();var item=e.target.closest('.surfside-information-ministry');activeInput=item&&item.querySelector('[data-ministry-icon-input]');if(picker)picker.hidden=false;}});
      if(picker){picker.addEventListener('click',function(e){var choice=e.target.closest('[data-ministry-icon-value]');if(choice&&activeInput){activeInput.value=choice.getAttribute('data-ministry-icon-value')||'';activeInput.dispatchEvent(new Event('change',{bubbles:true}));renumber();closePicker();return;}if(e.target.closest('[data-ministry-icon-close]'))closePicker();});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!picker.hidden)closePicker();});}
      decorate();renumber();
    })();
    </script>
    <?php return ob_get_clean();
}
add_shortcode('surfside_staff_ministries_manager', 'surfside_tools_staff_ministries_manager_shortcode');
