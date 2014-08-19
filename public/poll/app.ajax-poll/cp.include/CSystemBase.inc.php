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

class CSystemBase
{
	function setup()
	{
		$this->tclass_name = TCLASS_NAME;
		$script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? $_SERVER['SCRIPT_NAME'] : '';
		$this->url_tclass_root = dirname( $script_name ) . '/';
		$this->url_app_root = dirname( $this->url_tclass_root ) . '/';

		$this->app_title = APP_TITLE;
		$this->app_version = "v" . APP_VERSION;
		$this->app_tver = $this->app_title . ' ' . $this->app_version;
		$this->app_pid = APP_PID;

		$this->url_site = APP_HOMEPAGE . "?pid={$this->app_pid}";
		$this->title = $this->app_title;
	}

	//----------------------------------------------------------------
	// printHeadSection()
	//----------------------------------------------------------------
	function printHeadSection()
	{
?>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $this->title; ?></title>

<?php
		include( dirname(__FILE__) . "/style.inc.php" );
	}

	//----------------------------------------------------------------
	// printTitle()
	//----------------------------------------------------------------
	function printTitle()
	{
?>

<div class='pd-pid'><?php echo $this->app_pid; ?></div>
<div class='clear'></div>
<div class='article-title'><?php echo $this->app_tver; ?></div>
<div class='tclass-name'><?php echo $this->tclass_name; ?></div>
<div class='clear'></div>

<?php
	}

	//----------------------------------------------------------------
	// printTClass()
	//----------------------------------------------------------------
	function printTClass()
	{
?>



<?php
	}

	//----------------------------------------------------------------
	// printHeader()
	//----------------------------------------------------------------
	function printHeader()
	{
?>

<div id='page-header'>
	<div class='page-title'><?php echo $this->title; ?></div>
	<a class='site-link' href='<?php echo $this->url_site; ?>' target='_blank'>phpkobo &gt;&gt;</a>
	<div class='clear'></div>
</div>

<?php
	}

	//----------------------------------------------------------------
	// printFooter()
	//----------------------------------------------------------------
	function printFooter()
	{
?>

<div id='page-footer'>
	<a class='top-link' href='#top'>TOP</a>
	<div class='copyright'>©2013 phpkobo. All Rights Reserved</div>
	<div class='clear'></div>
</div>

<?php
	}

	//----------------------------------------------------------------
	// printSectTitle()
	//----------------------------------------------------------------
	function printSectTitle( $title )
	{
		$title = htmlspecialchars( $title );
		echo "<div class='sect-title'>{$title}</div>";
	}

	//----------------------------------------------------------------
	// printSourceCode()
	//----------------------------------------------------------------
	function printSourceCode( $txt )
	{
		$txt = $this->enc( $txt );
		echo "<pre class='doc-code'>{$txt}</pre>";
	}

	//----------------------------------------------------------------
	// printHTML()
	//----------------------------------------------------------------
	function printHTML()
	{
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<?php $this->printHeadSection(); ?>
</head>
<body>
	<div class='contents'>
		<?php $this->printHeader(); ?>
		<div class='article'>
			<?php $this->printArticle(); ?>
		</div>
		<?php $this->printFooter(); ?>
	</div>
</body>
</html>
<?php
	}

	//----------------------------------------------------------------
	// printFatalError()
	//----------------------------------------------------------------
	function printFatalError( $msg )
	{
		if ( is_array( $msg ) )
		{
			$msg = implode( "<br/>", $msg );
		}

		$s = "";
		$s .= "<div style='margin:30px auto 0 auto;width:500px;padding:20px;font-size:18px;";
		$s .= "color:#ff0000;font-weight:bold;text-align:left;'>";
		$s .= "<div style='margin-bottom:20px;font-style:italicx;color:#808080;font-size:40px;'>";
		$s .= "(o_o)...Oops! </div>";
		$s .= $msg;
		$s .= "</div>";
		echo $s;
	}

	//----------------------------------------------------------------
	// checkAppDataFolder()
	//----------------------------------------------------------------
	function checkAppDataFolder()
	{
		if ( !$this->checkPermission( "rw", PATH_APP_DATA ) )
		{
?>

<div style='font-size:20px;'>

<p>Well, it looks like your data folder [app.data] doesn't have proper permissions for reading and/or writing.</p>

<p>But don't panic. This is not the end of the world!</p>

<p>Just give the read and write permissions to the path shown above, and then refresh this page.</p>

<p>Everything is gonna be just fine!</p>

</div>

<?php
			throw new Exception( 'failed in checkAppDataFolder()' );
		}
	}

	//----------------------------------------------------------------
	// checkPermission
	//----------------------------------------------------------------
	function checkPermission( $ptype, $path )
	{
		$msg = array();

		if ( strpos( $ptype, 'r' ) !== false )
		{
			if ( !is_readable( $path ) )
			{
				$verb = "read";
				$msg[] = "Access Denied: {$verb} permisson is required on ( {$path} ).";
			}
		}

		if ( strpos( $ptype, 'w' ) !== false )
		{
			if ( !is_writeable( $path ) )
			{
				$verb = "write";
				$msg[] = "Access Denied: {$verb} permisson is required on ( {$path} ).";
			}
		}

		$b_success = ( count( $msg ) == 0 );

		if ( !$b_success )
		{
			$this->printFatalError( $msg );
		}

		return $b_success;
	}

	//----------------------------------------------------------------
	// checkVersion()
	//----------------------------------------------------------------
	function checkVersion()
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
			$msg .= "{$this->app_tver} requires PHP v{$ver_req} or later.<br/>";
			$msg .= "Unfortunately, your PHP version is {$ver_cur}.<br/>";
			$msg .= "Please upgrade your PHP and try again!";
			$this->printFatalError( $msg );
			throw new Exception( 'failed in checkVersion()' );
		}
		return true;
	}

	//----------------------------------------------------------------
	// checkMbString()
	//----------------------------------------------------------------
	function checkMbString()
	{
		if ( !function_exists( 'mb_strlen' ) )
		{ 
			$msg = '';
			$msg .= "{$this->app_tver} requires Multibyte Support (mbstring) extension.<br/>";
			$msg .= "Unfortunately, it's not installed in your PHP.<br/>";
			$msg .= "Please install it and try again!";
			$this->printFatalError( $msg );
			throw new Exception( 'failed in checkMbString()' );
		}
	}

	//----------------------------------------------------------------
	// enc()
	//----------------------------------------------------------------
	//=================================================================
	// enc( $txt );
	//
	// [aaa%]...[aaa] ==> <span class='aaa'>...</span>
	// [aaa#]...[aaa] ==> <div class='aaa'>...</div>
	//
	function enc2( $txt, $pattern )
	{
		preg_match_all($pattern, $txt, $matches );
		if ( count($matches) == 2 )
		{
			foreach( $matches[1] as $v )
			{
				$type = substr( $v, -1, 1 );
				$name = substr( $v, 0, strlen($v)-1 );
				switch ( $type )
				{
				case '%': $tag = 'span'; break;
				case '#': $tag = 'div'; break;
				}
				$tag_open = "<{$tag} class='{$name}'>";
				$tag_close = "</{$tag}>";
				$txt = str_replace( "[{$v}]", $tag_open, $txt );
				$txt = str_replace( "[{$name}]", $tag_close, $txt );
			}
		}
		return $txt;
	}

	function enc( $txt )
	{
		$txt = htmlspecialchars( $txt );
		$txt = $this->enc2( $txt, '/\[(\w+%)\]/' );
		$txt = $this->enc2( $txt, '/\[(\w+#)\]/' );
		return $txt;
	}

	//----------------------------------------------------------------
	// run()
	//----------------------------------------------------------------
	function run()
	{
		$this->printHTML();
	}
}

?>
