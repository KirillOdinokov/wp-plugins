(function(){'use strict';
if(typeof ODCK==='undefined')return;
if(document.cookie.indexOf('odck_cookie_accepted=')!==-1)return;
var notice=document.getElementById('odck-cookie-notice');
if(!notice)return;
// Move to body to avoid transform/filter breaking position:fixed
if(notice.parentNode!==document.body){document.body.appendChild(notice);}
notice.style.display='block';
var btn=notice.querySelector('.odck-accept-btn');
if(!btn)return;
btn.addEventListener('click',function(){setCookie();hideNotice();var x=new XMLHttpRequest();x.open('POST',ODCK.ajaxUrl,true);x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');x.send('action=odck_accept&nonce='+encodeURIComponent(ODCK.nonce));});
function setCookie(){var e=new Date();e.setDate(e.getDate()+365);document.cookie='odck_cookie_accepted=1; expires='+e.toUTCString()+'; path=/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');}
function hideNotice(){notice.style.display='none';setTimeout(function(){if(notice.parentNode)notice.parentNode.removeChild(notice);},300);}
})();