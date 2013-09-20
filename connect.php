<?php

class db_transaction
{
  private $name;
  private $child;

  public function __construct( $child )
  {
    $this->child = $child !== false;
    if (is_string($child))
      $this->name = $child;
    else
      $this->name = $this->UniversalName();
    $this->Begin();
  }
  
  private function UniversalName()
  {
    $pre = base64_encode(microtime());
    //return $pre;
    $ret = '';
    $s = strlen($pre);
    for ($i = 0; $i < $s; $i++)
    {
      $letter = $pre[$i];
      if (($letter >= 'a' && $letter <= 'z') ||
          ($letter >= 'A' && $letter <= 'Z'))
        $ret .= $letter;
    }
    return $ret;
  }
  
  private function Begin()
  {
    if ($this->child)
      db::Query("SAVEPOINT {$this->name};");
    else
    {
      assert(!db::InTransaction());
      db::Query("BEGIN;");
    }
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
    {
      db::Query("COMMIT;");
    }
  }
  
  public function IsNest()
  {
    return $this->child;
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

    $res = pg_query_params(self::$db, $q, $p);
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
    $s = self::InTransaction();
    $ret = new db_transaction($s);
    assert($ret->IsNest() == $s);
    assert(self::InTransaction(), "Already in transaction ($s), result ".pg_transaction_status(self::$db));
    return $ret;
  }
  
  public static function InTransaction()
  {
    self::RequireConnect();
    $stat = pg_transaction_status(self::$db);
    return $stat === PGSQL_TRANSACTION_ACTIVE || $stat === PGSQL_TRANSACTION_INTRANS || $stat === PGSQL_TRANSACTION_INERROR;
  }
}


new db("dbname=m host=localhost user=postgres");
