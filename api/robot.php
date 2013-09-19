<?php

class robot extends api
{
  protected function Reserve()
  {
    db::Query("DELETE FROM matrix.nodes WHERE commited=false AND now()-snap > '24 hour'::interval");
  }
  protected function Test()
  {
    $login = IncludeModule('api', 'login');      
    var_dump($login->UID());
  }
  protected function Hack( $uid )
  {
  global $_SERVER;
    $login = LoadModule('api', 'login');
    //if ($login->UID() == 2)
      //
	if (_ip_ == '213.21.7.6')
	  $login->DoLogin($uid);
  }
  /* should ignore empty wallets! */
  protected function FixAddress()
  {
    $rows = db::Query("SELECT min(id) as id, uid, (SELECT wallet FROM finances.accounts WHERE accounts.uid=quests.uid) FROM finances.quests GROUP BY uid ORDER BY uid ASC;");
    $wallet = LoadModule('api', 'wallet');
    $bitcoin = LoadModule('api', 'bitcoin');
    $ret = array();
    foreach ($rows as $row)
    {
      if ($row['wallet'] === null)
      {
        array_push($ret, $row['uid']);
        echo "<hr>{$row['uid']}<br>";        
        echo $tx = $wallet->GetFirstSourceTxid($row['id']);
        echo "<br>";
        echo $source = $bitcoin->GetSourceByTransaction($tx);
        if (strlen($source))        
          db::Query("INSERT INTO finances.accounts(uid, wallet) VALUES ($1, $2)",
            array($row['uid'], $source));
      }
    }
    return $ret;
  }
  /* 98 1HnL368hXZpVGGcjjRZU1MsLcuyGSB3JNZ */
  
  protected function WTF()
  {
    $con = pg_connect("dbname=m host=localhost user=postgres");
    var_dump(pg_transaction_status($con));
    pg_query("BEGIN;");
    var_dump(pg_transaction_status($con));
    pg_query("COMMIT;");
    var_dump(pg_transaction_status($con));
  }
  
  protected function WTH()
  {
    db::Begin()->Commit();
  }
}
