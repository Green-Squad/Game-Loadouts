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

define( 'APP_CLASS_TAG', 'tclass' );
define( 'APP_ID_TAG', 'tid' );

//----------------------------------------------------------------
// CTClassSys
//----------------------------------------------------------------
class CTClassSys
{
	//----------------------------------------------------------------
	// fatalError
	//----------------------------------------------------------------
	function fatalError( $msg )
	{
		$json = array( "result" => $msg );
		echo CJson::encode( $json );
		exit;
	}

	//----------------------------------------------------------------
	// getThisPageURL
	//----------------------------------------------------------------
	function getThisPageURL()
	{
		$pageURL = 'http';

		if (( isset($_SERVER["HTTPS"]) ) && ( $_SERVER["HTTPS"] == "on" ))
		{
			$pageURL .= "s";
		}
		$pageURL .= "://" . $_SERVER["SERVER_NAME"];

		if ($_SERVER["SERVER_PORT"] != "80")
		{
			$pageURL .= ":" . $_SERVER["SERVER_PORT"];
		}

		$pageURL .= $_SERVER['SCRIPT_NAME'];

		return $pageURL;
	}

	//----------------------------------------------------------------
	// checkPhpVersion
	//----------------------------------------------------------------
	function checkPhpVersion()
	{
		$ver_req = "5.2";
		$ver_cur = phpversion();
		if ( strnatcmp( $ver_cur, $ver_req ) >= 0 )
		{ 
			# equal or newer 
		} 
		else 
		{ 
			$msg = '';
			$msg .= "This script requires PHP v{$ver_req} or later.\r\n";
			$msg .= "Unfortunately, your PHP version is {$ver_cur}.\r\n";
			$msg .= "Please upgrade your PHP and try again!";
			$this->fatalError( $msg );
		}
	}

	//----------------------------------------------------------------
	// checkMbString()
	//----------------------------------------------------------------
	function checkMbString()
	{
		if ( !function_exists( 'mb_strlen' ) )
		{ 
			$msg = '';
			$msg .= "This script requires Multibyte Support (mbstring) extension.\r\n";
			$msg .= "Unfortunately, it's not installed in your PHP.\r\n";
			$msg .= "Please install it and try again!";
			$this->fatalError( $msg );
		}
	}

	//----------------------------------------------------------------
	// checkRequirement()
	//----------------------------------------------------------------
	function checkRequirement()
	{
		$this->checkPhpVersion();
		$this->checkMbString();
	}

	//----------------------------------------------------------------
	// sanitizeFilename
	//----------------------------------------------------------------
	static function sanitizeFilename( $str )
	{
		$str = substr( $str, 0, 255 );

		$res = '';
		$len = strlen( $str );
		for( $i = 0; $i < $len; $i++ )
		{
			$ch = mb_substr( $str, $i, 1 );
			if (
				ctype_alnum( $ch ) ||
				( $ch == '_' ) ||
				( $ch == '-' )
			)
			{
				$res .= $ch;
			}
			else
			{
				$res .= '-';
			}
		}
		return $res;
	}

	//----------------------------------------------------------------
	// loadTClassFile
	//----------------------------------------------------------------
	function loadTClassFile( $fn )
	{
		$path1 = $this->path_tclass . $fn;
		if ( file_exists( $path1 ) )
		{
			$path = $path1;
		}
		else
		{
			$path2 = $this->path_base_tclass . $fn;
			if ( file_exists( $path2 ) )
			{
				$path = $path2;
			}
			else
			{
				$this->fatalError( "file ({$fn}) does not exist" );
			}
		}
		include( $path );
	}

	//----------------------------------------------------------------
	// run
	//----------------------------------------------------------------
	function run()
	{
		//-- url
		$this->url_app_entry = $this->getThisPageURL();
		if ( defined( 'URL_APP_ROOT' ) )
			$this->url_app_root = URL_APP_ROOT;
		else
			$this->url_app_root = dirname($this->url_app_entry) . '/';
		$this->url_app_img = $this->url_app_root . FOLDER_APP_IMG . '/';

		//-- js params
		$this->app_object_selector = APP_OBJECT_SELECTOR;
		$this->app_init_cmd = APP_INIT_CMD;

		//-- run
		if ( isset( $_REQUEST['cmd'] ) )
		{
			//-- requirements
			$this->checkRequirement();

			//-- tclass name
			if ( isset( $_REQUEST[APP_CLASS_TAG] ) && !empty( $_REQUEST[APP_CLASS_TAG] ) )
			{
				$this->tclass = $this->sanitizeFilename( $_REQUEST[APP_CLASS_TAG] );
			}
			else
			{
				$this->tclass = APP_DEFAULT_TCLASS;
			}

			//-- tid name
			if ( isset( $_REQUEST[APP_ID_TAG] ) && !empty( $_REQUEST[APP_ID_TAG] ) )
			{
				$this->tid = $this->sanitizeFilename( $_REQUEST[APP_ID_TAG] );
			}
			else
			{
				$this->tid = APP_DEFAULT_TID;
			}

			//-- app.data path
			$this->path_app_data = PATH_APP_DATA;

			//-- tclass path
			$this->path_tclass = PATH_APP_ROOT . $this->tclass . "/"; 

			//-- include tclass config
			$this->path_tclass_config = $this->path_tclass . 'config.inc.php';
			if ( !file_exists( $this->path_tclass_config ) )
			{
				$this->fatalError(
					"tclass config file ({$this->tclass}/config.inc.php) " .
					"does not exist" );
			}
			include( $this->path_tclass_config );

			//-- base tclass
			if ( !defined( 'BASE_TCLASS' ) )
			{
				$this->fatalError( "base tclass not defined (BASE_TCLASS)" );
			}
			$this->base_tclass = BASE_TCLASS;

			//-- base tclass folder
			$this->folder_base_tclass = "app." . $this->base_tclass;

			//-- base tclass path
			$this->path_base_tclass = PATH_APP_ROOT . $this->folder_base_tclass . "/"; 

			//-- load CTClassApp
			$this->loadTClassFile( "class-app.inc.php" ); 

			//-- load CTClassBase
			$this->loadTClassFile( "class-base.inc.php" ); 

			//-- load CTClass
			$this->loadTClassFile( "class.inc.php" ); 

			//-- start-up
			$obj = new CTClass();
			$obj->run( $this );
		}
		else
		{
			header('Content-Type: application/javascript');
			include( PATH_APP_CODE . 'tclass.js.inc.php' );
		}
	}
}

$sys = new CTClassSys();
$sys->run();

?>