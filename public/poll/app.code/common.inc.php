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

//-- sys version
define( 'SYS_VERSION', "1.00" );

//-- app.config
define( 'PATH_APP_CONFIG', dirname(dirname(__FILE__)) . "/app.config/" );
include( PATH_APP_CONFIG . 'common.inc.php' );

//-- app.code
define( 'PATH_APP_CODE', dirname(__FILE__) . "/" );

//-- app.img
define( 'FOLDER_APP_IMG', "app.img" );

//-- files
include( dirname(__FILE__) . '/CJson.inc.php' );
include( dirname(__FILE__) . '/CTClassObject.inc.php' );
include( dirname(__FILE__) . '/CTClassSys.inc.php' );

?>