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

class CTClass extends CTClassBase
{
	function setupPoll( $poll )
	{
		$poll->attr( "title", "What game should we add next?" );

		$item = $poll->addItem( "Battlefield 3" );
		$item->attr( "bar-color", "#000060" );

		$item = $poll->addItem( "Call of Duty: Modern Warfare 3" );
		$item->attr( "bar-color", "#000064" );

		$item = $poll->addItem( "Planetside 2" );
		$item->attr( "bar-color", "#000068" );

		return true;
	}
}

?>