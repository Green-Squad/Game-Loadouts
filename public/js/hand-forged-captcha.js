// Local demo checkbox only. Never contacts Google or creates a real CAPTCHA token.
(function(){
 var widgets=[];
 window.grecaptcha={render:function(id){
  var node=typeof id==='string'?document.getElementById(id):id;
  if(node.dataset.demoWidget!==undefined)return Number(node.dataset.demoWidget);
  var index=widgets.length;node.dataset.demoWidget=index;
  node.innerHTML='<label><input type="checkbox"> Demo verification (local sample)</label><input type="hidden" name="g-recaptcha-response" value="">';
  var check=node.querySelector('input[type=checkbox]'),field=node.querySelector('input[type=hidden]');
  check.addEventListener('change',function(){field.value=check.checked?'hand-forged-demo':'';});widgets.push(check);return index;
 },getResponse:function(index){var w=widgets[index||0];return w&&w.checked?'hand-forged-demo':'';},reset:function(index){var w=widgets[index||0];if(w){w.checked=false;w.dispatchEvent(new Event('change'));}}};
 document.addEventListener('DOMContentLoaded',function(){if(window.initRecaptcha)window.initRecaptcha();else document.querySelectorAll('.g-recaptcha').forEach(function(n){grecaptcha.render(n);});});
})();
