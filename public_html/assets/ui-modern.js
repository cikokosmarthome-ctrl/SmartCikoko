/* SmartCikoko shared shell: never touches login, MFA, forms or databases. */
(function(){
  'use strict';
  function init(){
    if(!document.body || !document.body.classList.contains('sc-body'))return;
    try{
      if(window.self !== window.top && window.top.location.origin === window.location.origin){
        document.body.classList.add('sc-embedded');
        document.documentElement.classList.add('sc-embedded-html');
      }
    }catch(_ignored){}
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
})();
