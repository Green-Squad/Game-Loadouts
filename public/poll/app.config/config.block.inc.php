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
// ENABLE_IP_BLOCK
//----------------------------------------------------------------
// true  : Enable IP block to prevent users from voting
//         more than once.
// false : Disable IP block
//----------------------------------------------------------------

define( 'ENABLE_IP_BLOCK', true );

//----------------------------------------------------------------
// ENABLE_COOKIE_BLOCK
//----------------------------------------------------------------
// true  : Enable cookie block to prevent users from voting
//         more than once.
// false : Disable cookie block.
//----------------------------------------------------------------

define( 'ENABLE_COOKIE_BLOCK', true );

//----------------------------------------------------------------
// COOKIE_BLOCK_PERIOD
//----------------------------------------------------------------
//
// Specifies the time period, in seconds, during which 
// cookie block is effective.
//
// (exmaple 1)
// define( 'COOKIE_BLOCK_PERIOD', 60*60*24 ); => 1 day
//
// (exmaple 2)
// define( 'COOKIE_BLOCK_PERIOD', 60*60*24*365 ); => 1 year
//
//----------------------------------------------------------------

define( 'COOKIE_BLOCK_PERIOD', 60*60*24*365 );

?>