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
	define( 'PATH_TCLASS', dirname(__FILE__) . "/" );
	include( PATH_TCLASS . "config.inc.php" );
	if ( !file_exists( dirname(PATH_TCLASS) . "/app." . BASE_TCLASS ) )
	{
		echo "<h3>It does not seem that this folder was placed inside of ";
		echo "<a href='http://www.phpkobo.com/ajax_poll.php'>Ajax Poll Script</a>!</h3>";
		exit;
	}
	include( dirname(PATH_TCLASS) . "/app." . BASE_TCLASS . "/cp.inc.php" );

//----------------------------------------------------------------
// CIndexPage
//----------------------------------------------------------------
class CIndexPage extends CSystem
{

}

//----------------------------------------------------------------
// _main
//----------------------------------------------------------------
function _main()
{
	$obj = new CIndexPage();
	$obj->setup();
	$obj->run();
}

//----------------------------------------------------------------
// start-up
//----------------------------------------------------------------
_main();

?>