<?php
/** Ministry manager density and public contact-display refinements. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_ministry_ui_polish($output, $tag) {
    if ($tag === 'surfside_staff_ministries_manager') {
        $output .= <<<'HTML'
<style>
.surfside-information-ministries{display:grid;gap:16px}
.surfside-information-ministry{position:relative;padding:16px 18px!important;border:1px solid #d7e0e8!important;border-left:5px solid #0b5fa5!important;border-radius:14px!important;background:#fff!important;grid-template-columns:110px repeat(3,minmax(0,1fr))!important;gap:9px 12px!important;align-items:end}
.surfside-information-ministry:nth-child(even){background:#f5f8fb!important;border-left-color:#6b8ca4!important}
.surfside-ministry-card-heading{grid-column:1/-1;display:flex;align-items:center;gap:8px;margin:-2px 0 2px;color:#061b33;font-size:1.02rem;font-weight:900;line-height:1.25}
.surfside-ministry-card-heading small{color:#60708a;font-size:.76rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.surfside-ministry-audiences{margin:0!important;padding:4px 0 0!important;gap:10px!important}
.surfside-ministry-audiences legend{margin-bottom:2px!important;font-size:.9rem}
.surfside-ministry-contact-fields{padding:10px!important;gap:8px 10px!important;background:rgba(255,255,255,.58)!important}
.surfside-information-ministry:nth-child(even) .surfside-ministry-contact-fields{background:rgba(255,255,255,.82)!important}
.surfside-ministry-contact-note{font-size:.78rem!important;line-height:1.3}
.surfside-ministry-featured{margin:0!important;padding:1px 0!important}
.surfside-information-ministry-description textarea{min-height:64px!important;height:64px;resize:vertical}
.surfside-information-ministry-actions{margin-top:-2px!important}
.surfside-information-ministry .surfside-information-field>span{font-size:.86rem}
@media(max-width:900px){.surfside-information-ministry{grid-template-columns:90px repeat(2,minmax(0,1fr))!important}.surfside-ministry-contact-fields{grid-template-columns:1fr 1fr!important}}
@media(max-width:700px){.surfside-information-ministry,.surfside-ministry-contact-fields{grid-template-columns:1fr!important}.surfside-information-ministry{padding:14px!important}}
</style>
<script>
(function(){
  var list=document.querySelector('[data-surfside-ministries]'); if(!list)return;
  function decorate(){
    Array.from(list.querySelectorAll('.surfside-information-ministry')).forEach(function(card,index){
      var heading=card.querySelector('.surfside-ministry-card-heading');
      if(!heading){heading=document.createElement('div');heading.className='surfside-ministry-card-heading';card.insertBefore(heading,card.firstChild);}
      var nameInput=card.querySelector('input[name$="[name]"]');
      var iconInput=card.querySelector('input[name$="[icon]"]');
      var name=nameInput&&nameInput.value?nameInput.value:'New ministry';
      var icon=iconInput&&iconInput.value?iconInput.value:'';
      heading.innerHTML='<small>Ministry '+(index+1)+'</small><span>'+(icon?icon+' ':'')+name.replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];})+'</span>';
    });
  }
  list.addEventListener('input',function(e){if(e.target.matches('input[name$="[name]"],input[name$="[icon]"]'))decorate();});
  list.addEventListener('click',function(){setTimeout(decorate,0);});
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
  document.querySelectorAll('[data-ministry-directory-item]').forEach(function(item){
    item.addEventListener('click',function(){
      var dialog=document.querySelector('[data-ministry-dialog][open]'); if(!dialog)return;
      var email=dialog.querySelector('[data-ministry-dialog-email]');
      var phone=dialog.querySelector('[data-ministry-dialog-phone]');
      var emailValue=item.getAttribute('data-contact-email')||'';
      var phoneValue=item.getAttribute('data-contact-phone')||'';
      if(email&&emailValue){email.textContent='Email: '+emailValue;email.setAttribute('title','Email '+emailValue);}
      if(phone&&phoneValue){phone.removeAttribute('href');phone.textContent='Phone: '+phoneValue;phone.classList.add('surfside-phone-display');phone.setAttribute('aria-label','Phone '+phoneValue);}
    });
  });
})();
</script>
HTML;
    }

    return $output;
}
add_filter('do_shortcode_tag', 'surfside_tools_ministry_ui_polish', 40, 2);
