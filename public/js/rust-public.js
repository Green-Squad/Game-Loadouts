/* Public browsing and progressively enhanced native forms. */
$(function () {
  $('#copyright-year').text(new Date().getFullYear());
  $('#autocomplete-ajax, #autocomplete-ajax-nav').autocomplete({serviceUrl:'/search_weapons', onSearchStart:function(){$(this).attr('autocomplete','off');}});
  $(document).on('click','#navbar-search:not(.active) button[type="submit"]',function(e){e.preventDefault();$(this).closest('form').addClass('active').find('input').focus();});
  $('#navbar-search button[type="reset"]').on('click',function(){$('#navbar-search').removeClass('active');});
  $(document).on('keyup',function(e){if(e.which===27){$('#navbar-search').removeClass('active').find('input').val('');}});
  $('#weaponSearch').on('input',function(){var q=this.value.toLowerCase();$('.type').toggleClass('hide',q!=='');$('#weapon-table').toggleClass('table-striped',q==='');$('.searchable').each(function(){$(this).toggleClass('hide',$(this).text().toLowerCase().indexOf(q)===-1);});});
  $(window).on('scroll',function(){$('.scrollup').toggle($(window).scrollTop()>900);});
  $('.scrollup').on('click',function(e){e.preventDefault();$('html,body').animate({scrollTop:0},600);});
  $('#submit-loadout input[type="radio"]').on('change',function(){var name=this.name;$('#submit-loadout input[type="radio"]').filter(function(){return this.name===name;}).each(function(){$(this).closest('label').toggleClass('btn-primary',this.checked && this.value!=='');});});
  $('form').on('submit',function(){var button=$(this).find('button[type="submit"]');button.prop('disabled',true);setTimeout(function(){button.prop('disabled',false);},8000);});
  var data=document.getElementById('chart-data');
  if(data){var chart=JSON.parse(data.textContent);chart.datasets.forEach(function(d){$('<span>').text(d.label+' ').css('color',d.strokeColor).appendTo('#chart-legend');});new Chart(document.getElementById('canvas').getContext('2d')).Line(chart);}
});
