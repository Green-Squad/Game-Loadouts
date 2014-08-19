<?php
//==>>>==>>>==>>>==>>>==>>>==>>>==>>>==>>>==>>>==>>>==>>>==>>>==>>>
//
// Ajax Poll Script v2.05
// Copyright (c) phpkobo.com ( http://www.phpkobo.com/ )
// Email : admin@phpkobo.com
// ID : AP201-205
// URL : http://www.phpkobo.com/ajax_poll.php
//
// This software is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; version 2 of the
// License.
//
//==<<<==<<<==<<<==<<<==<<<==<<<==<<<==<<<==<<<==<<<==<<<==<<<==<<<
?>
<script>
(function($){

//----------------------------------------------------------------
// CPage
//----------------------------------------------------------------
function CPage( app )
{
	this.name = "app.result";
	this.app = app;
	app.addChild( this );
	this.front = app.getChild( "app.front" );

	if ( this.app.jobj.find('.demo-multi-selections').length )
	{
		this.config = {
			transition_effect: {
				opening: {
					type:"slide",
					speed:"slow"
				},
				ending: {
					type:"slide",
					speed:"slow"
				}
			}
		};
	}
	else if ( this.app.jobj.find('.demo-test').length )
	{
		this.config = {
			transition_effect: {
				opening: {
					type:"fade",
					speed:"slow"
				},
				ending: {
					type:"slide",
					speed:"slow"
				}
			}
		};
	}
	else
	{
		this.config = {
		};
	}

	this.run();
}

CPage.prototype =
{
	//-----------------------------------------------
	// hc_front
	//-----------------------------------------------
	hc_front : function( e )
	{
		e.preventDefault();

		var _this = this;
		this.front.enabled = true;

		//-- [BEGIN] transition
		if ( typeof this.config.transition_effect === "undefined" )
		{
			var type = "";
		}
		else
		{
			var cfg = this.config.transition_effect.ending;
			var type = cfg.type;
		}

		switch( type )
		{
		case "slide":
			this.app.jobj.find('.poll-result').slideUp(cfg.speed,function(){
				$(this).remove();
			});
			this.app.jobj.find('.poll-front').slideDown(cfg.speed);
			break;

		case "fade":
			this.app.jobj.find('.poll-result').fadeOut(cfg.speed,function(){
				$(this).remove();
				_this.app.jobj.find('.poll-front').fadeIn(cfg.speed);
			});
			break;

		default:
			this.app.jobj.find('.poll-result').hide();
			this.app.jobj.find('.poll-result').remove();
			this.app.jobj.find('.poll-front').show();
			break;
		}
		//-- [END] transition
	},

	//-----------------------------------------------
	// run
	//-----------------------------------------------
	run : function()
	{
		var _this = this;

		//-- [BEGIN] transition
		if ( typeof this.config.transition_effect === "undefined" )
		{
			var type = "";
		}
		else
		{
			var cfg = this.config.transition_effect.opening;
			var type = cfg.type;
		}

		switch( type )
		{
		case "slide":
			this.app.jobj.find('.poll-front').slideUp(cfg.speed);
			this.app.jobj.find('.poll-result').slideDown(cfg.speed);
			break;

		case "fade":
			this.app.jobj.find('.poll-front').fadeOut(cfg.speed, function(){
				_this.app.jobj.find('.poll-result').fadeIn(cfg.speed);
			});
			break;

		default:
			this.app.jobj.find('.poll-front').hide();
			this.app.jobj.find('.poll-result').show();
			break;
		}
		//-- [END] transition

		//-- Animate Bars
		this.app.jobj.find( ".ap-bar" ).each( function() {
			var w = $(this).css( 'width' );
			$(this).css( 'width', 0 );
			$(this).show();
			$(this).animate({
				width: w
			}, 1000 );
		});

		//-- Back button
		this.app.jobj.find( ".ap-front" ).click( function(e) {
			_this.hc_front( e );
		});
	},

	//-----------------------------------------------
	// thankYou
	//-----------------------------------------------
	thankYou : function()
	{
		var orig = this.app.jobj.find( ".ap-front" );
		var cfg = { "period":2500, "background-color":"#008000" };
		var tbox = this.app.jobj.find( ".tip-box-thank-you" );
		if ( tbox.length )
		{
			this.app.showTipBox( orig, cfg, tbox );
		}
		else
		{
			var txt = this.app.jobj.find( 'input[name="msg-thank-you"]').val();
			if ( typeof(txt) != 'undefined' )
			{
				cfg["txt"] = txt;
				this.app.showTipBox( orig, cfg );
			}
		}

		return true;
	},

	//-----------------------------------------------
	// msgProc
	//-----------------------------------------------
	msgProc : function( msg )
	{
		switch( msg.cmd )
		{
		case "thank_you":
			var _this = this;
			var f = function(){ _this.thankYou(); };
			setTimeout( f, 1000 );
			return 1;
		}

		return 0;
	}
}

//----------------------------------------------------------------
// main
//----------------------------------------------------------------
var page = new CPage( window['<?php echo $this->appid; ?>'] ); 

}(jQuery));

</script>
<?php /* -- END OF FILE -- */ ?>