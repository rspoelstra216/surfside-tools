(function(){
  const config=window.surfsideBibleWebsite||{};
  const dialog=document.querySelector('[data-surfside-scripture-dialog]');
  if(!dialog||!config.passageEndpoint)return;

  const panel=dialog.querySelector('.surfside-scripture-dialog__panel');
  const title=dialog.querySelector('[data-scripture-title]');
  const versionLabel=dialog.querySelector('[data-scripture-version]');
  const status=dialog.querySelector('[data-scripture-status]');
  const content=dialog.querySelector('[data-scripture-content]');
  const attribution=dialog.querySelector('[data-scripture-attribution]');
  const link=dialog.querySelector('[data-scripture-link]');
  let previousFocus=null;
  let controller=null;

  const books={
    genesis:'GEN',exodus:'EXO',leviticus:'LEV',numbers:'NUM',deuteronomy:'DEU',joshua:'JOS',judges:'JDG',ruth:'RUT',
    '1 samuel':'1SA','2 samuel':'2SA','1 kings':'1KI','2 kings':'2KI','1 chronicles':'1CH','2 chronicles':'2CH',ezra:'EZR',nehemiah:'NEH',esther:'EST',job:'JOB',psalm:'PSA',psalms:'PSA',proverbs:'PRO',ecclesiastes:'ECC','song of songs':'SNG',isaiah:'ISA',jeremiah:'JER',lamentations:'LAM',ezekiel:'EZK',daniel:'DAN',hosea:'HOS',joel:'JOL',amos:'AMO',obadiah:'OBA',jonah:'JON',micah:'MIC',nahum:'NAM',habakkuk:'HAB',zephaniah:'ZEP',haggai:'HAG',zechariah:'ZEC',malachi:'MAL',
    matthew:'MAT',mark:'MRK',luke:'LUK',john:'JHN',acts:'ACT',romans:'ROM','1 corinthians':'1CO','2 corinthians':'2CO',galatians:'GAL',ephesians:'EPH',philippians:'PHP',colossians:'COL','1 thessalonians':'1TH','2 thessalonians':'2TH','1 timothy':'1TI','2 timothy':'2TI',titus:'TIT',philemon:'PHM',hebrews:'HEB',james:'JAS','1 peter':'1PE','2 peter':'2PE','1 john':'1JN','2 john':'2JN','3 john':'3JN',jude:'JUD',revelation:'REV'
  };

  function parseReference(text){
    const cleaned=String(text||'').replace(/[–—]/g,'-').trim();
    const match=cleaned.match(/^((?:[1-3]\s*)?[A-Za-z ]+?)\s+(\d+):(\d+[ab]?)(?:\s*-\s*(\d+[ab]?))?(?:,.*)?(?:\s+([A-Za-z0-9]+))?$/i);
    if(!match)return null;
    const bookKey=match[1].replace(/\s+/g,' ').trim().toLowerCase();
    const book=books[bookKey];
    if(!book)return null;
    const chapter=match[2];
    const start=match[3].replace(/[ab]$/i,'');
    const end=match[4]?match[4].replace(/[ab]$/i,''):'';
    const version=(match[5]||config.defaultVersion||'NIV').toUpperCase();
    return {reference:book+'.'+chapter+'.'+start+(end?'-'+end:''),version:version,display:cleaned.replace(/\s+[A-Za-z0-9]+$/,'')};
  }

  function closeDialog(){
    if(controller)controller.abort();
    dialog.hidden=true;
    document.documentElement.classList.remove('surfside-scripture-open');
    if(previousFocus&&typeof previousFocus.focus==='function')previousFocus.focus();
  }

  async function openPassage(trigger){
    const parsed=parseReference(trigger.textContent);
    if(!parsed)return;
    previousFocus=trigger;
    dialog.hidden=false;
    document.documentElement.classList.add('surfside-scripture-open');
    title.textContent=parsed.display;
    versionLabel.textContent=parsed.version;
    status.hidden=false;
    status.textContent='Loading Scripture…';
    content.hidden=true;
    content.innerHTML='';
    attribution.hidden=true;
    attribution.textContent='';
    link.hidden=true;
    panel.focus();

    if(controller)controller.abort();
    controller=new AbortController();
    const url=new URL(config.passageEndpoint,window.location.origin);
    url.searchParams.set('reference',parsed.reference);
    url.searchParams.set('version',parsed.version);

    try{
      const response=await fetch(url.toString(),{signal:controller.signal,credentials:'same-origin'});
      const data=await response.json();
      if(!response.ok)throw new Error(data&&data.message?data.message:'Passage unavailable');
      title.textContent=(data.passage&&data.passage.reference)||parsed.display;
      const v=data.version||{};
      versionLabel.textContent=[v.abbreviation,v.title].filter(Boolean).join(' · ');
      content.innerHTML=(data.passage&&data.passage.content)||'';
      content.hidden=false;
      status.hidden=true;
      if(data.attribution){attribution.textContent=data.attribution;attribution.hidden=false;}
      if(data.explore_more_url){link.href=data.explore_more_url;link.hidden=false;}
    }catch(error){
      if(error&&error.name==='AbortError')return;
      status.hidden=false;
      status.textContent='We could not load this passage right now.';
    }
  }

  function enhance(root){
    (root||document).querySelectorAll('.message-notes-reference').forEach(function(el){
      if(el.dataset.scriptureReady)return;
      if(!parseReference(el.textContent))return;
      el.dataset.scriptureReady='1';
      el.setAttribute('role','button');
      el.setAttribute('tabindex','0');
      el.setAttribute('aria-haspopup','dialog');
      el.addEventListener('click',function(){openPassage(el);});
      el.addEventListener('keydown',function(event){if(event.key==='Enter'||event.key===' '){event.preventDefault();openPassage(el);}});
    });
  }

  dialog.querySelectorAll('[data-scripture-close]').forEach(function(el){el.addEventListener('click',closeDialog);});
  document.addEventListener('keydown',function(event){if(event.key==='Escape'&&!dialog.hidden)closeDialog();});
  enhance(document);
  new MutationObserver(function(){enhance(document);}).observe(document.body,{childList:true,subtree:true});
})();
