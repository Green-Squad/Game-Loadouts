$(document).ready(function() {


/*************************  Slider Revolution  *****************************/
	if($('.banner').length) {
		var height=$('.homev2').length>0?447:415;
		$('.banner').revolution({
			delay:3000,
			hideThumbs:1,
			startwidth:1170,
			startheight:500,
			shadow:0,
			touchenabled:"on",
			navigationType: "none",
		});    
	}

	if($('.new-classes').length) {
		$(".new-classes").on({
			mouseenter : function() {
				$(this).find(".occult").slideDown(100);               
			},
			mouseleave : function() {
				$(this).find(".occult").slideUp(100);            
			}
		},".new-class");
	}


/***************************** Go to top  *********************************/
	 $(window).scroll(function(){
		 if ($(this).scrollTop() > 900) {
			 $('.scrollup').fadeIn();
		 } else {
			 $('.scrollup').fadeOut();
		 }
	 }); 
 
	 $('.scrollup').click(function(){
		 $("html, body").animate({ scrollTop: 0 }, 600);
		 return false;
	 });


/******************************  Isotope  **********************************/
	if ($('.magic-grid .content').length) {
		// cache container
		var $container = $('.magic-grid .content');
		
		// initialize isotope
		$container.isotope({ });

		// filter items when filter link is clicked
		$('.filters button').click(function(){
			$('.filters button').removeClass('active');
			$(this).addClass('active');
			
			var selector = $(this).attr('data-filter');
			$container.isotope({ filter: selector });
			return false;
		});
	}



/*******************************  GMaps  ***********************************/
	if ($('#map').length) {
		var map;
		map = new GMaps({
			div: '#map',
			lat: 41.8902624,
			lng: 12.4923096
		});
		map.addMarker({
			lat: 41.8902624,
			lng: 12.4923096,
			title: 'Contanct',
			infoWindow: {
				content: '15rd Avenue, New York,<br /> 156408, US<br /> <br /> Email: info@company.com <br /> Web: company.com'
			}
		});
	}



/***************************  Easy PaiChart  *******************************/
	if ($('.chart').length) {
		$('.chart').easyPieChart({
			animate: 2000,
			barColor: "#1c9746",
			trackColor: "#849bad",
			scaleColor: false,
			lineWidth: 11,
			lineCap: "square",
		});
	}



/******************************  Chart.js  *********************************/
	if ($('#linear-chart').length) {
		//Get context with jQuery - using jQuery's .get() method.
		var ctx = $("#linear-chart").get(0).getContext("2d");
		//This will get the first returned node in the jQuery collection.
		var myNewChart = new Chart(ctx);
		
		var data = {
			labels : ["10AM","11AM","12AM","1PM","2PM","3PM","4PM","5PM","6PM","7PM","8PM","9PM"],
			datasets : [
				//Actitude
				{
					fillColor : "rgba(220,220,220,0)",
					strokeColor : "rgba(29,153,69,1)",
					pointColor : "rgba(29,153,69,1)",
					pointStrokeColor : "#fff",
					data : [5,3,3,5,7,8,6,6,9,8,10,12]
				},
				{
					fillColor : "rgba(220,220,220,0)",
					strokeColor : "rgba(244,179,1,1)",
					pointColor : "rgba(244,179,1,1)",
					pointStrokeColor : "#fff",
					data : [3,4,4,6,2,3,3,7,3,7,11,11]
				}
			]
		}

		var options = {
						
			//Boolean - If we show the scale above the chart data			
			scaleOverlay : false,
			
			//Boolean - If we want to override with a hard coded scale
			scaleOverride : true,
			
			//** Required if scaleOverride is true **
			//Number - The number of steps in a hard coded scale
			scaleSteps : 13,
			//Number - The value jump in the hard coded scale
			scaleStepWidth : 1,
			//Number - The scale starting value
			scaleStartValue : 1,

			//Boolean - Whether to show labels on the scale	
			scaleShowLabels : false,
			
			///Boolean - Whether grid lines are shown across the chart
			scaleShowGridLines : false,
			
			//Boolean - Whether the line is curved between points
			bezierCurve : false,
			
			//Number - Radius of each point dot in pixels
			pointDotRadius : 6,
			
			
		}

		myNewChart.Line(data,options);
	}


	if ($('#pie-chart1').length) {
		//Get context with jQuery - using jQuery's .get() method.
		var ctx = $("#pie-chart1").get(0).getContext("2d");
		//This will get the first returned node in the jQuery collection.
		var myNewChart = new Chart(ctx);
		
		var data = [
			{
				value: 50,
				color:"#1d9945"
			},
			{
				value : 50,
				color : "#849bad"
			},		
		]

		var options = { }

		myNewChart.Pie(data,options);
	}

	if ($('#pie-chart2').length) {
		//Get context with jQuery - using jQuery's .get() method.
		var ctx = $("#pie-chart2").get(0).getContext("2d");
		//This will get the first returned node in the jQuery collection.
		var myNewChart = new Chart(ctx);
		
		var data = [
			{
				value: 75,
				color:"#1d9945"
			},
			{
				value : 25,
				color : "#849bad"
			},		
		]

		var options = { }

		myNewChart.Pie(data,options);
	}

/***************************  Flickr Feed  *******************************/
	if ($('.flickr-feed').length) {
	    $('.flickr-feed').jflickrfeed({
    	    limit: 6,
        	qstrings: {
            	id: '52617155@N08'
        	},
        	itemTemplate: '<li><a href="{{link}}" target="_blank"><img src="{{image_s}}" alt="{{title}}" /></a></li>'
    	});
	}
	


	
/*****************************  Carousel  *********************************/
	$('.carousel').carousel({
	  interval: 2000
	})



/*****************************  Forms  *********************************/
	$("#newsletter").submit(function() {
		var elem = $(this);
		var urlTarget = $(this).attr("action");
		$.ajax({
			type : "POST",
			url : urlTarget,
			dataType : "html",
			data : $(this).serialize(),
			beforeSend : function() {
				elem.prepend("<div class='loading alert'>" + "<a class='close' data-dismiss='alert'>×</a>" + "Loading" + "</div>");
			},
			success : function(response) {
				elem.prepend(response);
				elem.find(".loading").hide();
				elem.find("input[type='text'],input[type='email'],textarea").val("");
			}
		})
		return false;
	});


	$("#contact-form").submit(function() {
		var elem = $(this);
		var urlTarget = $(this).attr("action");
		$.ajax({
			type : "POST",
			url : urlTarget,
			dataType : "html",
			data : $(this).serialize(),
			beforeSend : function() {
				elem.prepend("<div class='loading alert'>" + "<a class='close' data-dismiss='alert'>×</a>" + "Loading" + "</div>");
			},
			success : function(response) {
				elem.prepend(response);
				elem.find(".loading").hide();
				elem.find("input[type='text'],input[type='email'],textarea").val("");
			}
		})
		return false;
	});

});






