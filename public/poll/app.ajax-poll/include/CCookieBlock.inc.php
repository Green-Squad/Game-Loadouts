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

//----------------------------------------------------------------
// CCookieBlock
//----------------------------------------------------------------
class CCookieBlock
{
	function setup( $cookie_key, $period = null )
	{
		//-- cookie name must not contain '.'
		$cookie_key = str_replace( '.', '-', $cookie_key );

		$this->cookie_key = $cookie_key;
		if ( is_null($period) )
		{
			$period = 60*60*24*365;
		}
		$this->period = $period;
	}

	function add()
	{
		setcookie( $this->cookie_key, "yes", time() + $this->period );
		return true;
	}

	function exists()
	{
		return ( isset($_COOKIE[$this->cookie_key]) );
	}

	function validate()
	{
		if ( $this->exists() )
			return false;
		else
			return $this->add();
	}

	function clear()
	{
		setcookie( $this->cookie_key, "", time()-3600 );
		return true;
	}
}

?>