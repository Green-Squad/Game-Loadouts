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

include( dirname(__FILE__) . '/config/common.inc.php' );
include( dirname(__FILE__) . '/include/common.inc.php' );

//----------------------------------------------------------------
// CTClassApp
//----------------------------------------------------------------
class CTClassApp extends CTClassObject
{
	//----------------------------------------------------------------
	// setup
	//----------------------------------------------------------------
	function setup( $sys )
	{
		if ( !parent::setup( $sys ) ) return false;

		//-- setup data folder
		if ( !$this->setupDataFolder() ) return false;

		//-- setup config
		$this->config = array();
		if ( !$this->setupConfig( $this->config ) ) false;

		//-- setup admin list
		$this->admin = new CAdmin();
		if ( !$this->admin->setup() ) return false;

		//-- setup poll & poll items
		$this->poll = new CPoll();
		if ( !$this->poll->setup( $sys, $this ) ) return false;
		if ( !$this->setupPoll( $this->poll ) ) return false;

		return true;
	}

	//----------------------------------------------------------------
	// setupDataFolder
	//----------------------------------------------------------------
	function setupDataFolder()
	{
		//-- check app data folder
		$path = $this->getAppDataPath();
		if ( !$this->checkPermission( "rw", $path ) )
		{
			return false;
		}

		//-- tclass data folder exists?
		$path = $this->getDataFolderPath();
		if ( !file_exists( $path ) )
		{
			@mkdir( $path );
			@chmod( $path, TCLASS_DATA_FOLDER_PERMISSION );
			if ( !file_exists( $path ) )
			{
				$err_msg = "Can not create a folder in [app.data]";
				$this->showErrorMsg( $err_msg );
				return false;
			}
		}

		return true;
	}

	//----------------------------------------------------------------
	// setupConfig
	//----------------------------------------------------------------
	function setupConfig( &$config )
	{
		$config['enable-ip-block'] = ENABLE_IP_BLOCK;
		$config['enable-cookie-block'] = ENABLE_COOKIE_BLOCK;
		$config['cookie-block-period'] = COOKIE_BLOCK_PERIOD;

		return true;
	}

	//----------------------------------------------------------------
	// setupCmdSpec
	//----------------------------------------------------------------
	function setupCmdSpec( &$cmdspec )
	{
		parent::setupCmdSpec( $cmdspec );

		$cmdspec["init"] = "front";

		$cmdspec["front"] = array(
			"@hd_front",
			"tpl.front.inc.php",
			"js.front.inc.php",
		);

		$cmdspec["result"] = array(
			"@hd_result",
			"tpl.result.inc.php",
			"js.result.inc.php",
		);

		$cmdspec["vote"] = array(
			"@hd_vote",
			"tpl.result.inc.php",
			"js.result.inc.php",
		);

		$cmdspec["clear_block"] = array(
			"@hd_clear_block",
		);

		return true;
	}

	//----------------------------------------------------------------
	// isAdmin
	//----------------------------------------------------------------
	function isAdmin()
	{
		return $this->admin->isAdmin();
	}

	//----------------------------------------------------------------
	// addStyle
	//----------------------------------------------------------------
	function addStyle( $style )
	{
		$style = str_replace( "\r", "\\r", $style );
		$style = str_replace( "\n", "\\n", $style );
		$style = str_replace( "\"", "\\\"", $style );
		$s = "";
		$s .= "<script>(function(\$){";
		$s .= "\$('head').append( \"{$style}\" );";
		$s .= "}(jQuery));</script>";
		echo $s;
	}

	//----------------------------------------------------------------
	// checkDataFilePerm
	//----------------------------------------------------------------
	function checkDataFilePerm( $path )
	{
		if ( !file_exists( $path ) )
		{
			if ( is_writable( dirname($path) ) )
			{
				touch( $path );
			}
			else
			{
				return $this->checkPermission( 'rw', dirname($path) );
			}
		}
		return $this->checkPermission( 'rw', $path );
	}

	//----------------------------------------------------------------
	// getIPBlockFilePath
	//----------------------------------------------------------------
	function getIPBlockFilePath()
	{
		return $this->getDataFolderPath() . "ip-block.txt";
	}

	//----------------------------------------------------------------
	// getCookieBlockName
	//----------------------------------------------------------------
	function getCookieBlockName()
	{
		return $this->getIdName() . ".cookie-block";
	}

	//----------------------------------------------------------------
	// hd_front
	//----------------------------------------------------------------
	function hd_front( &$ret )
	{
		return true;
	}

	//----------------------------------------------------------------
	// hd_result
	//----------------------------------------------------------------
	function hd_result( &$ret )
	{
		$this->poll->load();
		$this->poll->calcStats();
		return true;
	}

	//----------------------------------------------------------------
	// hd_vote
	//----------------------------------------------------------------
	function hd_vote( &$ret )
	{
		//-- IP Block
		if ( $this->config['enable-ip-block'] )
		{
			$path = $this->getIPBlockFilePath();
			if ( !$this->checkDataFilePerm( $path ) ) return false;

			$ipblock = new CIPBlock();
			$ipblock->setup( $path );
			if ( !$ipblock->validate() )
			{
				$ret["cmd"] = "none";
				$ret["msg"] = array( "cmd" => "already_voted" );
				return false;
			}
		}

		//-- Cookie Block
		if ( $this->config['enable-cookie-block'] )
		{
			$cookie_name = $this->getCookieBlockName();
			$cookie_block = new CCookieBlock();
			$cookie_block->setup( $cookie_name,
				$this->config['cookie-block-period'] );
			if ( !$cookie_block->validate() )
			{
				$ret["cmd"] = "none";
				$ret["msg"] = array( "cmd" => "already_voted" );
				return false;
			}
		}

		//-- Load Data
		$this->poll->load(true);
		$this->poll->calcStats();

		$ret["msg"] = array( "cmd" => "thank_you" );
		return true;
	}

	//----------------------------------------------------------------
	// hd_clear_block
	//----------------------------------------------------------------
	function hd_clear_block( &$ret )
	{
		$ret["cmd"] = "alert";

		if ( $this->admin->isAdmin() )
		{
			//-- IP Block
			$path = $this->getIPBlockFilePath();
			if ( !$this->checkDataFilePerm( $path ) ) return false;
			$ipblock = new CIPBlock();
			$ipblock->setup( $path );
			if ( $ipblock->clear() )
			{
				echo "IP block was cleared!\r\n";
			}
			else
			{
				echo "Can not clear IP block\r\n";
			}

			//-- Cookie Block
			$cookie_name = $this->getCookieBlockName();
			$cookie_block = new CCookieBlock();
			$cookie_block->setup( $cookie_name,
				$this->config['cookie-block-period'] );
			if ( $cookie_block->clear() )
			{
				echo "Cookie block was cleared!\r\n";
			}
			else
			{
				echo "Can not clear Cookie block\r\n";
			}
		}
		else
		{
			echo "Unauthorized Access\r\n";
		}

		return true;
	}
}

?>