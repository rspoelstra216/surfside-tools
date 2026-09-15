<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Progressively enhance monthly-calendar navigation without removing its
 * normal links. The links remain usable as anchored reload fallbacks.
 */
function surfside_tools_month_calendar_navigation_assets() {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    wp_register_style(
        'surfside-tools-month-navigation',
        false,
        array(),
        defined('SURFSIDE_TOOLS_VERSION') ? SURFSIDE_TOOLS_VERSION : '2.3.0'
    );
    wp_enqueue_style('surfside-tools-month-navigation');
    wp_add_inline_style('surfside-tools-month-navigation', '
        .surfside-month-calendar[data-surfside-month-navigation][aria-busy="true"]{opacity:.55;pointer-events:none;transition:opacity .16s ease}
        @media(prefers-reduced-motion:reduce){.surfside-month-calendar[data-surfside-month-navigation][aria-busy="true"]{transition:none}}
    ');

    wp_register_script(
        'surfside-tools-month-navigation',
        '',
        array(),
        defined('SURFSIDE_TOOLS_VERSION') ? SURFSIDE_TOOLS_VERSION : '2.3.0',
        true
    );
    wp_enqueue_script('surfside-tools-month-navigation');
    wp_add_inline_script('surfside-tools-month-navigation', <<<'JS'
(function(){
'use strict';

var requestController = null;

function calendar(){
    return document.querySelector('[data-surfside-month-navigation]');
}

function announce(container,message){
    var status=container.querySelector('[data-surfside-month-status]');
    if(status)status.textContent=message;
}

function focusHeading(container){
    var heading=container.querySelector('.surfside-month-calendar-title');
    if(!heading)return;
    heading.setAttribute('tabindex','-1');
    heading.focus({preventScroll:false});
    heading.addEventListener('blur',function(){heading.removeAttribute('tabindex');},{once:true});
}

function loadMonth(url,pushHistory){
    var current=calendar();
    if(!current)return Promise.reject(new Error('Calendar not found'));

    if(requestController)requestController.abort();
    requestController=new AbortController();
    current.setAttribute('aria-busy','true');
    announce(current,'Loading calendar month.');

    return fetch(url,{
        credentials:'same-origin',
        headers:{'X-Requested-With':'XMLHttpRequest'},
        signal:requestController.signal
    }).then(function(response){
        if(!response.ok)throw new Error('Calendar request failed');
        return response.text();
    }).then(function(html){
        var page=new DOMParser().parseFromString(html,'text/html');
        var replacement=page.querySelector('[data-surfside-month-navigation]');
        if(!replacement)throw new Error('Updated calendar not found');

        replacement=document.importNode(replacement,true);
        current.replaceWith(replacement);

        if(pushHistory)window.history.pushState({surfsideMonth:true},'',url);

        var label=replacement.querySelector('.surfside-month-calendar-title');
        announce(replacement,label?'Showing '+label.textContent.trim()+'.':'Calendar updated.');
        focusHeading(replacement);
    }).catch(function(error){
        if(error.name==='AbortError')return;
        window.location.assign(url);
    });
}

document.addEventListener('click',function(event){
    var link=event.target.closest('[data-surfside-month-link]');
    if(!link||event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
    if(!calendar()||link.origin!==window.location.origin)return;
    event.preventDefault();
    loadMonth(link.href,true);
});

window.addEventListener('popstate',function(){
    if(calendar())loadMonth(window.location.href,false);
});
})();
JS
    );
}

