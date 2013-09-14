<?php

class db
{
  private static $db = null;
  private static $str = null;

  public function __construct( $str )
  {
    self::$str = $str;
  }

  private static function RequireConnect()
  {
    if (!is_null(self::$db))
      return;
    self::$db = pg_connect(self::$str);
    self::$str = null;
  }
  
  public static function Query( $q, $p = array(), $allow_one_row = false )
  {
    self::RequireConnect();
    if (!is_string($q))
      debug_print_backtrace();
    if (!is_array($p))
      debug_print_backtrace();

    $res = pg_query_params($q, $p);
	//debug_print_backtrace();	
	if (is_string($res))
	  assert(false, $res);
//debug_print_backtrace();
    $ret = array();
    while (($row = pg_fetch_assoc($res)) != false)
      array_push($ret, $row);
    if ($allow_one_row && count($ret))
      return $ret[0];
    return $ret;
  }
  public static function pg_query( $q )
  {
    self::RequireConnect();
    return pg_query($q);
  }
}


new db("dbname=m host=localhost user=postgres");
