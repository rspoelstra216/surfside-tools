<?php
/** Contact details for canonical Ministry Manager records. */
if (!defined('ABSPATH')) { exit; }

/** Save the fallback email alongside the existing Ministry Manager form. */
function surfside_tools_ministry_contacts_save_default() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !current_user_can('manage_options')) return;
    if (!isset($_POST['surfside_ministries_nonce'])) return;
    $nonce = sanitize_text_field(wp_unslash($_POST['surfside_ministries_nonce']));
    if (!wp_verify_nonce($nonce, 'surfside_ministries_save')) return;

    $email = isset($_POST['ministry_default_email']) ? sanitize_email(wp_unslash($_POST['ministry_default_email'])) : '';
    update_option(SURFSIDE_TOOLS_MINISTRY_DEFAULT_EMAIL_OPTION, $email, false);
}
add_action('wp_loaded', 'surfside_tools_ministry_contacts_save_default');

/** Add contact controls to the staff Ministry Manager without duplicating its renderer. */
function surfside_tools_ministry_contacts_manager_fields() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return;

    $default_email = function_exists('surfside_tools_get_ministry_default_email') ? surfside_tools_get_ministry_default_email() : '';
    $saved = array();
    foreach ((array) surfside_tools_get_ministries() as $ministry) {
        $saved[(string) ($ministry['key'] ?? '')] = array(
            'contact_name' => (string) ($ministry['contact_name'] ?? ''),
            'contact_email' => (string) ($ministry['contact_email'] ?? ''),
            'contact_phone' => (string) ($ministry['contact_phone'] ?? ''),
        );
    }
    ?>
    <style>
      .surfside-ministry-default-contact{margin:0 0 18px;padding:18px;border:1px solid #d7e0e8;border-radius:14px;background:#f8fafc}
      .surfside-ministry-default-contact h2{margin:0 0 5px;color:#061b33;font-size:1.15rem}.surfside-ministry-default-contact p{margin:0 0 12px;color:#60708a}
      .surfside-ministry-default-contact label{display:grid;gap:6px;font-weight:800;color:#26323d}.surfside-ministry-default-contact input{box-sizing:border-box;width:100%;max-width:520px;padding:10px 12px;border:1px solid #aeb9c4;border-radius:9px;font:inherit}
      .surfside-ministry-contact-fields{grid-column:1/-1;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:14px;border:1px solid #d7e0e8;border-radius:12px;background:#fbfcfd}
      .surfside-ministry-contact-fields .surfside-information-field{margin:0}.surfside-ministry-contact-note{grid-column:1/-1;margin:0;color:#60708a;font-size:.85rem}
      @media(max-width:800px){.surfside-ministry-contact-fields{grid-template-columns:1fr}}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
      var form=document.querySelector('.surfside-information-form'),list=document.querySelector('[data-surfside-ministries]');
      if(!form||!list)return;
      var saved=<?php echo wp_json_encode($saved); ?>;
      if(!form.querySelector('[data-ministry-default-contact]')){
        var box=document.createElement('section');box.className='surfside-ministry-default-contact';box.setAttribute('data-ministry-default-contact','1');
        box.innerHTML='<h2>Default ministry contact</h2><p>If a ministry does not have its own email address, messages will use this address.</p><label><span>Default contact email</span><input type="email" name="ministry_default_email" value="<?php echo esc_js($default_email); ?>" placeholder="office@example.org"></label>';
        list.parentNode.insertBefore(box,list);
      }
      function sync(card){
        var keyInput=card.querySelector('input[name$="[key]"]');if(!keyInput)return;
        var match=(keyInput.name||'').match(/^ministries\[[^\]]+\]/);if(!match)return;
        var base=match[0];
        var map={name:'contact_name',email:'contact_email',phone:'contact_phone'};
        Object.keys(map).forEach(function(k){var input=card.querySelector('[data-ministry-contact-'+k+']');if(input)input.name=base+'['+map[k]+']';});
      }
      function addFields(card){
        if(card.querySelector('[data-ministry-contact-fields]')){sync(card);return;}
        var key=(card.querySelector('input[name$="[key]"]')||{}).value||'',data=saved[key]||{};
        var wrap=document.createElement('div');wrap.className='surfside-ministry-contact-fields';wrap.setAttribute('data-ministry-contact-fields','1');
        wrap.innerHTML='<label class="surfside-information-field"><span>Contact name</span><input type="text" data-ministry-contact-name></label><label class="surfside-information-field"><span>Contact email</span><input type="email" data-ministry-contact-email></label><label class="surfside-information-field"><span>Contact phone</span><input type="tel" data-ministry-contact-phone></label><p class="surfside-ministry-contact-note">Email may be left blank to use the default ministry contact. Phone is optional; if blank, no Call button will be shown.</p>';
        wrap.querySelector('[data-ministry-contact-name]').value=data.contact_name||'';
        wrap.querySelector('[data-ministry-contact-email]').value=data.contact_email||'';
        wrap.querySelector('[data-ministry-contact-phone]').value=data.contact_phone||'';
        var description=card.querySelector('.surfside-information-ministry-description');if(description)card.insertBefore(wrap,description);else card.appendChild(wrap);sync(card);
      }
      function scan(){list.querySelectorAll('.surfside-information-ministry').forEach(addFields);}
      scan();new MutationObserver(scan).observe(list,{childList:true,subtree:true});list.addEventListener('click',function(){setTimeout(scan,0);});
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_ministry_contacts_manager_fields', 102);

/** Add resolved contact actions to the public Ministry Directory modal. */
function surfside_tools_ministry_contacts_directory_details() {
    if (is_admin()) return;
    $contacts = array();
    foreach ((array) surfside_tools_get_ministries() as $ministry) {
        $name = (string) ($ministry['name'] ?? '');
        if ($name === '') continue;
        $contacts[$name] = function_exists('surfside_tools_resolve_ministry_contact') ? surfside_tools_resolve_ministry_contact($ministry) : array();
    }
    if (empty($contacts)) return;
    ?>
    <style>
      .surfside-ministry-dialog__contact{margin-top:1.15rem;padding-top:1rem;border-top:1px solid var(--surfside-color-border,#d8e1e9)}
      .surfside-ministry-dialog__contact p{margin:0 0 .7rem;color:#31566d;font-weight:700}.surfside-ministry-dialog__contact-actions{display:flex;gap:9px;flex-wrap:wrap}
      .surfside-ministry-dialog__contact-actions a{display:inline-flex;padding:9px 14px;border-radius:999px;background:var(--surfside-color-blue-700,#075c9c);color:#fff;text-decoration:none;font-weight:800}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
      var contacts=<?php echo wp_json_encode($contacts); ?>;
      document.addEventListener('click',function(e){
        var item=e.target.closest('[data-ministry-directory-item]');if(!item)return;
        var contact=contacts[item.getAttribute('data-name')||''];if(!contact)return;
        setTimeout(function(){
          var dialog=document.querySelector('[data-ministry-dialog][open]');if(!dialog)return;
          var panel=dialog.querySelector('.surfside-ministry-dialog__panel');if(!panel)return;
          var block=panel.querySelector('[data-ministry-contact-public]');if(!block){block=document.createElement('div');block.className='surfside-ministry-dialog__contact';block.setAttribute('data-ministry-contact-public','1');block.innerHTML='<p data-contact-label></p><div class="surfside-ministry-dialog__contact-actions"><a data-contact-email>Email</a><a data-contact-phone>Call</a></div>';panel.appendChild(block);}
          var label=block.querySelector('[data-contact-label]'),email=block.querySelector('[data-contact-email]'),phone=block.querySelector('[data-contact-phone]');
          label.textContent=contact.name?'Contact '+contact.name+' for more information.':'Contact the church office for more information.';
          if(contact.email){email.href='mailto:'+contact.email;email.hidden=false;}else email.hidden=true;
          if(contact.phone){phone.href='tel:'+String(contact.phone).replace(/[^0-9+]/g,'');phone.hidden=false;}else phone.hidden=true;
          block.hidden=!contact.email&&!contact.phone;
        },0);
      });
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_ministry_contacts_directory_details', 130);
