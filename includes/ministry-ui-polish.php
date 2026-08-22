<?php
/** Ministry manager density and public contact-display refinements. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_ministry_ui_polish($output, $tag) {
    if ($tag === 'surfside_staff_ministries_manager') {
        $output .= <<<'HTML'
<style>
.surfside-information-ministries{display:grid;gap:18px}
.surfside-information-ministry{position:relative;padding:16px 18px 18px!important;border:1px solid #d7e0e8!important;border-left:5px solid #0b5fa5!important;border-radius:14px!important;background:#fff!important;grid-template-columns:72px repeat(3,minmax(0,1fr))!important;column-gap:12px!important;row-gap:13px!important;align-items:end}
.surfside-information-ministry:nth-child(even){background:#f5f8fb!important;border-left-color:#6b8ca4!important}
.surfside-ministry-card-heading{grid-column:1/-1;display:flex;align-items:center;gap:8px;margin:-2px 0 3px;padding-bottom:8px;border-bottom:1px solid rgba(96,112,138,.18);color:#061b33;font-size:1.02rem;font-weight:900;line-height:1.25}
.surfside-ministry-card-heading small{color:#60708a;font-size:.76rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.surfside-ministry-audiences{margin:2px 0 0!important;padding:7px 0 5px!important;gap:10px!important}
.surfside-ministry-audiences legend{margin-bottom:5px!important;font-size:.9rem}
.surfside-ministry-contact-fields{margin-top:2px!important;padding:12px!important;gap:10px 12px!important;background:rgba(255,255,255,.58)!important}
.surfside-information-ministry:nth-child(even) .surfside-ministry-contact-fields{background:rgba(255,255,255,.82)!important}
.surfside-ministry-contact-note{margin-top:3px!important;font-size:.78rem!important;line-height:1.35}
.surfside-ministry-featured{margin:2px 0!important;padding:5px 0!important}
.surfside-information-ministry-description{margin-top:1px!important}
.surfside-information-ministry-description textarea{min-height:64px!important;height:64px;resize:vertical}
.surfside-information-ministry-actions{margin-top:1px!important;padding-top:4px!important}
.surfside-information-ministry .surfside-information-field>span{display:block;margin-bottom:4px;font-size:.86rem}
.surfside-ministry-icon-control{display:block!important}
.surfside-ministry-icon-control input[data-ministry-icon-input]{box-sizing:border-box!important;width:58px!important;min-width:58px!important;max-width:58px!important;height:48px!important;padding:6px!important;border-radius:10px!important;text-align:center!important;font-size:1.45rem!important;line-height:1!important;cursor:pointer!important;caret-color:transparent!important}
.surfside-ministry-icon-control input[data-ministry-icon-input]:hover,.surfside-ministry-icon-control input[data-ministry-icon-input]:focus-visible{border-color:#0b5fa5!important;box-shadow:0 0 0 3px rgba(11,95,165,.12)!important;outline:0!important}
.surfside-ministry-icon-button{display:none!important}
@media(max-width:900px){.surfside-information-ministry{grid-template-columns:72px repeat(2,minmax(0,1fr))!important}.surfside-ministry-contact-fields{grid-template-columns:1fr 1fr!important}}
@media(max-width:700px){.surfside-information-ministry,.surfside-ministry-contact-fields{grid-template-columns:1fr!important}.surfside-information-ministry{padding:14px!important;row-gap:12px!important}.surfside-ministry-icon-field{max-width:72px}}
</style>
<script>
(function(){
  var list=document.querySelector('[data-surfside-ministries]'); if(!list)return;
  function formatPhone(value){
    var digits=(value||'').replace(/\D/g,'');
    if(digits.length===11&&digits.charAt(0)==='1')digits=digits.slice(1);
    if(digits.length!==10)return value;
    return '('+digits.slice(0,3)+') '+digits.slice(3,6)+'-'+digits.slice(6);
  }
  function decorate(){
    Array.from(list.querySelectorAll('.surfside-information-ministry')).forEach(function(card,index){
      var heading=card.querySelector('.surfside-ministry-card-heading');
      if(!heading){heading=document.createElement('div');heading.className='surfside-ministry-card-heading';card.insertBefore(heading,card.firstChild);}
      var nameInput=card.querySelector('input[name$="[name]"]');
      var iconInput=card.querySelector('input[name$="[icon]"]');
      var phoneInput=card.querySelector('input[name$="[contact_phone]"]');
      var name=nameInput&&nameInput.value?nameInput.value:'New ministry';
      var icon=iconInput&&iconInput.value?iconInput.value:'';
      heading.innerHTML='<small>Ministry '+(index+1)+'</small><span>'+(icon?icon+' ':'')+name.replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];})+'</span>';
      if(iconInput){iconInput.setAttribute('readonly','readonly');iconInput.setAttribute('aria-label','Choose ministry emoji');iconInput.setAttribute('title','Choose ministry emoji');}
      if(phoneInput){phoneInput.setAttribute('inputmode','tel');phoneInput.setAttribute('placeholder','(321) 555-1234');phoneInput.value=formatPhone(phoneInput.value);}
    });
  }
  list.addEventListener('input',function(e){if(e.target.matches('input[name$="[name]"],input[name$="[icon]"]'))decorate();});
  list.addEventListener('blur',function(e){if(e.target.matches('input[name$="[contact_phone]"]'))e.target.value=formatPhone(e.target.value);},true);
  list.addEventListener('click',function(e){
    var iconInput=e.target.closest('input[data-ministry-icon-input]');
    if(iconInput){var card=iconInput.closest('.surfside-information-ministry'),button=card&&card.querySelector('[data-ministry-icon-open]');if(button){e.preventDefault();button.click();return;}}
    setTimeout(decorate,0);
  });
  list.addEventListener('keydown',function(e){if((e.key==='Enter'||e.key===' ')&&e.target.matches('input[data-ministry-icon-input]')){e.preventDefault();var card=e.target.closest('.surfside-information-ministry'),button=card&&card.querySelector('[data-ministry-icon-open]');if(button)button.click();}});
  new MutationObserver(decorate).observe(list,{childList:true});
  decorate();
})();
</script>
HTML;
    }

    if ($tag === 'surfside_all_ministries') {
        $output .= <<<'HTML'
<style>
.surfside-ministry-dialog__contact-actions a[data-ministry-dialog-email]{max-width:100%;overflow-wrap:anywhere}
.surfside-ministry-dialog__contact-actions a[data-ministry-dialog-phone].surfside-phone-display{background:#eef4f7!important;color:#31566d!important;cursor:text;user-select:text}
</style>
<script>
(function(){
  function formatPhone(value){var digits=(value||'').replace(/\D/g,'');if(digits.length===11&&digits.charAt(0)==='1')digits=digits.slice(1);return digits.length===10?'('+digits.slice(0,3)+') '+digits.slice(3,6)+'-'+digits.slice(6):value;}
  document.querySelectorAll('[data-ministry-directory-item]').forEach(function(item){
    item.addEventListener('click',function(){
      var dialog=document.querySelector('[data-ministry-dialog][open]'); if(!dialog)return;
      var email=dialog.querySelector('[data-ministry-dialog-email]');
      var phone=dialog.querySelector('[data-ministry-dialog-phone]');
      var emailValue=item.getAttribute('data-contact-email')||'';
      var phoneValue=item.getAttribute('data-contact-phone')||'';
      if(email&&emailValue){email.textContent='Email: '+emailValue;email.setAttribute('title','Email '+emailValue);}
      if(phone&&phoneValue){phone.removeAttribute('href');phone.textContent='Phone: '+formatPhone(phoneValue);phone.classList.add('surfside-phone-display');phone.setAttribute('aria-label','Phone '+formatPhone(phoneValue));}
    });
  });
})();
</script>
HTML;
    }

    return $output;
}
add_filter('do_shortcode_tag', 'surfside_tools_ministry_ui_polish', 40, 2);
