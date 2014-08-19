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
// CAdmin
//----------------------------------------------------------------
class CAdmin
{
	function setup()
	{
		$this->b_all = false;
		$this->b_none = false;
		$this->ips = array();

		$txt = ADMIN_IP;
		$txt = str_replace( "\r", "", $txt );
		$ax = explode( "\n", $txt );
		foreach( $ax as $ln )
		{
			$s = trim($ln);
			if (( $s != "" ) && ( substr( $s, 0, 2 ) != "//" ))
			{
				if ( $s == "ALL" )
				{
					$this->b_all = true;
					break;
				}
				if ( $s == "NONE" )
				{
					$this->b_none = true;
					break;
				}
				$this->ips[] = $s;
			}
		}

		return true;
	}

	function isAdmin()
	{
		if ( $this->b_none ) return false;
		if ( $this->b_all ) return true;
		if ( !isset( $_SERVER['REMOTE_ADDR'] ) ) return false;
		return in_array( $_SERVER['REMOTE_ADDR'], $this->ips );
	}
}

?>