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
// CPollItem
//----------------------------------------------------------------
class CPollItem
{
	function attr( $key, $val = null )
	{
		if ( $this->checkNullItem( $msg ) ) return $msg;

		if ( is_null( $val ) )
		{
			if ( isset( $this->_attr_[$key] ) )
				return $this->_attr_[$key];
			else
				return $this->prt->getUnknownAttr( $key );
		}
		else
		{
			$this->_attr_[$key] = $val;
		}
	}

	function getName()
	{
		if ( $this->checkNullItem( $msg ) ) return $msg;

		return $this->name;
	}

	function getCount()
	{
		return $this->cnt;
	}

	function getPercent( $precision = 0 )
	{
		$total = $this->prt->getTotal();
		if ( $total == 0 )
			$v = 0;
		else
			$v = ( 100 * $this->getCount() ) / $total;
		$v = round( $v, $precision );
		$v = sprintf( "%01.{$precision}f", $v );
		return $v;
	}

	function getWidth()
	{
		$max_count = $this->prt->getMaxCount();
		if ( $max_count == 0 ) return 0;
		$max_width = $this->prt->getMaxWidth();
		return ( $max_width * round( $this->getCount() / $max_count, 2 ) );
	}

	//----------------------------------------------------------------
	// helper functions
	//----------------------------------------------------------------
	function setCount( $cnt )
	{
		if ( !$this->isInteger( $cnt ) ) $cnt = 0;
		$this->cnt = intval( $cnt );
	}

	function incCount( $d = 1 )
	{
		$this->cnt += $d;
	}

	function checkNullItem( &$msg )
	{
		if ( is_null( $this->name ) )
		{
			$msg = "<span style='color:red;'>Item not declared</span>";
			return true;
		}
		else
			return false;
	}

	function isInteger( $v )
	{
		if ( strlen( $v ) == 0 ) return false;
		if ( !is_numeric( $v ) ) return false;
		if ( doubleval( $v ) - intval( $v ) != 0 ) return false;
		return true;
	}

	//----------------------------------------------------------------
	// setup
	//----------------------------------------------------------------
	function setup( $sys, $prt, $name )
	{
		//-- pointers
		$this->sys =& $sys;
		$this->prt =& $prt;
		$this->name = $name;

		//-- attributes
		$this->_attr_ = array();

		//-- count
		$this->cnt = 0;
	}
}

//----------------------------------------------------------------
// CPoll
//----------------------------------------------------------------
class CPoll
{
	//----------------------------------------------------------------
	// attr
	//----------------------------------------------------------------
	function attr( $key, $val = null )
	{
		if ( is_null( $val ) )
		{
			if ( isset( $this->_attr_[$key] ) )
				return $this->_attr_[$key];
			else
				return $this->getUnknownAttr( $key );
		}
		else
		{
			$this->_attr_[$key] = $val;
		}
	}

	//----------------------------------------------------------------
	// getUnknownAttr
	//----------------------------------------------------------------
	function getUnknownAttr( $key )
	{
		return "<span style='color:red;'>?{$key}?</span>";
	}

	//----------------------------------------------------------------
	// getTotal
	//----------------------------------------------------------------
	function getTotal()
	{
		return $this->total;
	}

	//----------------------------------------------------------------
	// setMaxWidth
	//----------------------------------------------------------------
	function setMaxWidth( $max_width )
	{
		$this->max_width = $max_width;
	}

	//----------------------------------------------------------------
	// getMaxWidth
	//----------------------------------------------------------------
	function getMaxWidth()
	{
		return $this->max_width;
	}

	//----------------------------------------------------------------
	// getMaxCount
	//----------------------------------------------------------------
	function getMaxCount()
	{
		return $this->max_count;
	}

	//----------------------------------------------------------------
	// getNumOfItems
	//----------------------------------------------------------------
	function getNumOfItems()
	{
		return count( $this->items );
	}

	//----------------------------------------------------------------
	// getAllItems
	//----------------------------------------------------------------
	function getAllItems()
	{
		return $this->items;
	}

	//----------------------------------------------------------------
	// getItem
	//----------------------------------------------------------------
	function getItem( $key = null )
	{
		$err_msg = array();

		if ( is_null($key) )
		{
			if ( !isset( $this->item_idx ) )
			{
				$this->item_idx = 0;
			}

			if ( $this->item_idx >= count($this->item_key_list) )
			{
				return $this->dummy_item;
			}
			else
			{
				$key = $this->item_key_list[$this->item_idx];
				$this->item_idx++;
				return $this->items[$key];
			}
		}
		else if ( !isset( $this->items[$key] ) )
		{
			$err_msg[] = "getItem( key ) : Unknown Item Key ({$key})";
		}

		if ( count( $err_msg ) > 0 )
		{
			$this->prt->showErrorMsg( $err_msg );
		}

		return $this->items[$key];
	}

	//----------------------------------------------------------------
	// addItem
	//----------------------------------------------------------------
	function addItem( $key )
	{
		$item = new CPollItem();
		$item->setup( $this->sys, $this, $key );
		$this->items[$key] = $item;
		$this->item_key_list[$this->item_key_idx] = $key;
		$this->item_key_idx++;
		return $item;
	}

	//----------------------------------------------------------------
	// calcStats
	//----------------------------------------------------------------
	function calcStats()
	{
		$total = 0;
		$max_count = 0;
		foreach( $this->items as $key => $item )
		{
			$count = $item->getCount();
			$total += $count;
			if ( $max_count < $count ) $max_count = $count;
		}
		$this->total = $total;
		$this->max_count = $max_count;
	}

	//----------------------------------------------------------------
	// setup
	//----------------------------------------------------------------
	function setup( $sys, $prt )
	{
		//-- pointers
		$this->sys =& $sys;
		$this->prt =& $prt;

		//-- attributes
		$this->_attr_ = array();

		//-- poll items
		$this->items = array();
		$this->item_key_list = array();
		$this->item_key_idx = 0;

		//-- dummy item
		$this->dummy_item = new CPollItem();
		$this->dummy_item->setup( $sys, $this, null );

		//-- default max width
		$this->setMaxWidth( 100 );

		return true;
	}

	//----------------------------------------------------------------
	// getDataFilePath
	//----------------------------------------------------------------
	function getDataFilePath()
	{
		return $this->prt->getDataFolderPath() . "votes.txt";
	}

	//----------------------------------------------------------------
	// load
	//----------------------------------------------------------------
	function load( $b_vote = false )
	{
		$path = $this->getDataFilePath();
		if ( !file_exists( $path ) ) touch( $path );
		if ( !$this->prt->checkPermission( 'rw', $path ) ) return false;

		$handle = @fopen( $path, "r+" );

		//-- do an exclusive lock
		if ( !( @flock( $handle, LOCK_EX ) ) )
		{
			$this->prt->showErrorMsg( "Can not write [app.data] ({$path})" .
				" (Could not get the lock!)" );
			return false;
		}

		$txt = '';
		$size = filesize( $path );
		if ( $size > 0 )
		{
			$txt = fread( $handle, $size );
		}

		//-- load into CPollItem
		$txt = str_replace( "\r", "", $txt );
		$ax = explode( "\n", $txt );
		foreach( $ax as $ln )
		{
			if ( strpos( $ln, "=" ) !== false )
			{
				$bx = explode( "=", $ln );
				if ( array_key_exists( $bx[0], $this->items ) )
				{
					$item = $this->items[ $bx[0] ];
					$item->setCount( $bx[1] );
				}
			}
		}

		//-- add votes
		$answer = isset( $_REQUEST['answer'] ) ? $_REQUEST['answer'] : "";
		$answer = json_decode( $answer, true );
		if ( $b_vote && !empty($answer) )
		{
			$b_found = false;
			foreach( $answer as $ans )
			{
				if ( isset( $this->items[$ans] ) )
				{
					$item = $this->items[$ans];
					$item->incCount();
					$b_found = true;
				}
			}

			if ( $b_found )
			{
				//-- truncate file
				fseek( $handle, 0 );
				ftruncate( $handle, 0 );

				//-- write votes to txt file
				fwrite( $handle, $this->getDataTxt() );
			}
			else
			{
				$this->prt->showErrorMsg( "Item ({$answer}) not declared" );
			}
		}

		//-- release the lock
		flock( $handle, LOCK_UN );

		//-- close the file
		fclose($handle);

		return true;
	}

	//----------------------------------------------------------------
	// getDataTxt
	//----------------------------------------------------------------
	function getDataTxt()
	{
		$ax = array();
		foreach( $this->items as $key => $item )
		{
			$ax[] = "{$key}=" . $item->getCount();
		}
		return implode( "\r\n", $ax );
	}
}

?>