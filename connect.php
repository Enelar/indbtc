<?php

class db_transaction
{
  private $name;
  private $child;

  public function db_transaction( $child )
  {
    $this->child = $child !== false;
    if (is_string($child))
      $this->name = $child;
    else
      $this->name = md5(microtime());
    $this->Begin();
  }
  
  private function Begin()
  {
    if ($this->child)
    {
      assert(!db::InTransaction());
      db::Query("BEGIN;");
    }
    else
      db::Query("SAVEPOINT {$this->name};");
  }

  public function Rollback()
  {
    if ($this->child)
    {
      db::Query("ROLLBACK TO SAVEPOINT {$this->name};");
      db::Query("RELEASE SAVEPOINT {$this->name};");
    }
    else
      db::Query("ROLLBACK;");
  }
  
  public function Commit()
  {
    if ($this->child)
      db::Query("RELEASE SAVEPOINT {$this->name};");
    else
      db::Query("COMMIT;");
  }
}

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

  public static function Begin()
  {
    return new db_transaction(self::InTransaction());
  }
  
  public static function InTransaction()
  {
    self::RequireConnect();
    $stat = pg_transaction_status($db);
    return $stat === PGSQL_TRANSACTION_ACTIVE || $stat === PGSQL_TRANSACTION_INTRANS;
  }
}


new db("dbname=m host=localhost user=postgres");
