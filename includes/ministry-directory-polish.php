<?php
/** Public ministry directory presentation refinements. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_ministry_directory_polish($output, $tag) {
    if ($tag !== 'surfside_all_ministries') {
        return $output;
    }

    $contact_url = esc_url(home_url('/contact/'));
    $closing = '<div class="surfside-all-ministries__closing"><h3>Interested in Serving?</h3><p>There are too many opportunities to list here! We’d love to help you find a place to use your gifts and talents.</p><a class="surfside-button" href="' . $contact_url . '">Contact Us About Serving</a></div>';
    $output = preg_replace('~</div><style>~', $closing . '</div><style>', $output, 1);
    $output .= <<<'HTML'
<style>
.surfside-all-ministries{position:relative;z-index:0;box-sizing:border-box;width:100%!important;max-width:none!important;margin:0!important;padding-block:clamp(2.5rem,4vw,3.25rem)}
.surfside-all-ministries::before{position:absolute;z-index:-1;inset-block:0;left:50%;width:100vw;width:100dvw;background:var(--surfside-color-white,#fff);content:"";transform:translateX(-50%)}
.surfside-all-ministries__inner{box-sizing:border-box!important;width:min(100% - 2rem,72rem)!important;max-width:none!important;margin-inline:auto!important}
.surfside-all-ministries__intro{text-align:center}
.surfside-all-ministries__filters{justify-content:center}
.surfside-all-ministries__closing{text-align:center;max-width:54rem;margin:1.2rem auto 0;padding-top:.2rem}
.surfside-all-ministries__closing h3{margin:0 0 .35rem;color:var(--surfside-color-ocean-950,#061b33);font-size:1.05rem}
.surfside-all-ministries__closing p{margin:0 0 .9rem;color:var(--surfside-color-muted,#536579)}
.surfside-all-ministries__closing .surfside-button{display:inline-flex;align-items:center;justify-content:center;text-decoration:none}
.surfside-ministry-dialog__contact-actions a[data-ministry-dialog-email]{max-width:100%;overflow-wrap:anywhere}
.surfside-ministry-dialog__contact-actions a[data-ministry-dialog-phone].surfside-phone-display{background:#eef4f7!important;color:#31566d!important;cursor:text;user-select:text}
@media(max-width:700px){.surfside-all-ministries__inner{width:min(100% - 1.25rem,72rem)!important}}
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

    return $output;
}
add_filter('do_shortcode_tag', 'surfside_tools_ministry_directory_polish', 40, 2);
