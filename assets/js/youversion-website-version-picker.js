(function(){
  const config=window.surfsideBibleWebsite||{};
  const dialog=document.querySelector('[data-surfside-scripture-dialog]');
  if(!dialog||!config.versionsEndpoint)return;

  const select=dialog.querySelector('[data-scripture-version-select]');
  if(!select)return;

  const languageNames={en:'English',es:'Spanish',pt:'Portuguese',vi:'Vietnamese',fr:'French',de:'German'};
  const languageOrder=['en','es','pt','vi','fr','de'];
  let versions=[];
  let loaded=false;
  let loadingPromise=null;

  function languageCode(tag){return String(tag||'').toLowerCase().split(/[-_]/)[0];}

  function populate(){
    select.innerHTML='';
    languageOrder.forEach(function(code){
      const matches=versions.filter(function(version){return languageCode(version.language_tag)===code;});
      if(!matches.length)return;
      const group=document.createElement('optgroup');
      group.label=code==='en'?'English':('Other languages — '+languageNames[code]);
      matches.forEach(function(version){
        const option=document.createElement('option');
        option.value=version.abbreviation;
        option.textContent=version.abbreviation+' — '+version.title;
        group.appendChild(option);
      });
      select.appendChild(group);
    });
  }

  async function ensureVersions(){
    if(loaded)return versions;
    if(loadingPromise)return loadingPromise;
    loadingPromise=fetch(config.versionsEndpoint,{credentials:'same-origin'})
      .then(function(response){if(!response.ok)throw new Error('Version list unavailable');return response.json();})
      .then(function(data){versions=Array.isArray(data.versions)?data.versions:[];populate();loaded=true;return versions;})
      .finally(function(){loadingPromise=null;});
    return loadingPromise;
  }

  document.addEventListener('surfside:scripture-open',function(event){
    const detail=event.detail||{};
    select.disabled=true;
    ensureVersions().then(function(){
      const wanted=String(detail.version||config.defaultVersion||'NIV').toUpperCase();
      const match=versions.find(function(version){return String(version.abbreviation||'').toUpperCase()===wanted;});
      if(match)select.value=match.abbreviation;
      select.disabled=false;
    }).catch(function(){select.disabled=true;});
  });

  select.addEventListener('change',function(){
    const version=select.value;
    if(!version)return;
    document.dispatchEvent(new CustomEvent('surfside:scripture-version-change',{detail:{version:version}}));
  });
})();
