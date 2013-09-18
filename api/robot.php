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
    $login = LoadModule('api', 'login');
    //if ($login->UID() == 2)
      //
	if (_ip_ == '213.21.7.6')
	  $login->DoLogin($uid);
  }
  /* should ignore empty wallets!
  protected function FixAddress()
  {
    $rows = db::Query("SELECT id, uid FROM finances.quests");
    $wallet = LoadModule('api', 'wallet');
    foreach ($rows as $row)
      db::Query("INSERT INTO finances.accounts(uid, wallet) VALUES ($1, $2)",
        array($row['uid'], $wallet->GetFirstSourceAddress($row['id'])));
  }
  */
}
