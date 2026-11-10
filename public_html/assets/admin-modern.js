/* Compact Super Admin · internal tabbed workspace, no API calls.
   All embedded routes keep their own PHP/MFA/CSRF authorization checks. */
(function(){
'use strict';
function initialize(){
 if(!document.body || !document.body.classList.contains('sc-page-super-admin-satu'))return;
 const names=['ringkasan','laporan','modul','kemajuan','pengaturan','sc-admin-workspace'];
 const panels={};
 names.forEach(function(id){const el=document.getElementById(id);if(el)panels[id]=el;});
 const frame=document.getElementById('sc-admin-frame');
 const frameTitle=document.getElementById('sc-admin-frame-title');
 const nav=Array.from(document.querySelectorAll('header nav a[data-sc-tab]'));
 if(!frame||!panels.ringkasan||!panels.modul)return;
 function select(name,push){
   if(!Object.prototype.hasOwnProperty.call(panels,name))name='ringkasan';
   Object.keys(panels).forEach(function(k){panels[k].hidden=(k!==name);});
   nav.forEach(function(a){
     const active=(a.getAttribute('href')==='#'+name);
     a.classList.toggle('sc-selected',active);
     if(active)a.setAttribute('aria-current','page');
     else a.removeAttribute('aria-current');
   });
   if(push && location.hash!=='#'+name){
     history.replaceState(null,'','#'+name);
   }
   document.body.classList.add('sc-admin-tabs-ready');
   if(push)window.scrollTo({top:0,behavior:'instant'});
 }
 function showFromHash(){
   const raw=decodeURIComponent(location.hash.slice(1));
   select(raw||'ringkasan',false);
 }
 function openTool(anchor){
   const href=anchor.getAttribute('href');
   if(!href)return false;
   let url;
   try{url=new URL(href,location.href);}catch(_ignore){return false;}
   if(url.origin!==location.origin)return false;
   if(url.pathname==='/super-admin-satu.php')return false;
   if(!url.pathname.endsWith('.php') && url.pathname!=='/')return false;
   url.searchParams.set('sc_embed','1');
   if(frameTitle)frameTitle.textContent=anchor.getAttribute('data-sc-label')||
      anchor.querySelector('b,strong')?.textContent||anchor.textContent.trim()||'Modul Administrator';
   frame.src=url.href;
   select('sc-admin-workspace',true);
   return true;
 }
 document.addEventListener('click',function(event){
   const anchor=event.target.closest('a');
   if(!anchor)return;
   if(anchor.hasAttribute('data-sc-tool')){
      if(openTool(anchor)){event.preventDefault();return;}
   }
   if(anchor.hasAttribute('data-sc-tab')){
      const name=anchor.getAttribute('href')?.slice(1);
      if(name && Object.prototype.hasOwnProperty.call(panels,name)){
        event.preventDefault();select(name,true);
      }
   }
 });
 document.getElementById('sc-admin-close')?.addEventListener('click',function(){
   frame.src='about:blank';select('modul',true);
 });
 window.addEventListener('hashchange',showFromHash);
 showFromHash();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initialize,{once:true});
else initialize();
})();
