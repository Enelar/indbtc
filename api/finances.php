<?php

class finances extends api
{
  public static $tax = 0.0005;
  private static $line_price = 0.001; // deprecated
  private static $count_bills = 6;
  /*
  private static $levels = array
  (
    0.0051,
    0.0052,
    0.0053,
    0.0054,
    0.0055,
    0.0056,
    0.0057,
    0.0058,
  );
  /**/
  private static $levels = array
  (
    0.5,
    1,
    2,
    4,
    8,
    16,
    32,
    0.05
  );
/*  
  private static $levels = array
  (
    0.5,
    1.5,
    4.5,
    13.5,
    40.5,
    121.5,
    364.5,
    1093.5
  );
  */
  public function MakeQuest( $node = null, $level = 0 )
  {
    $matrix = IncludeModule('api', 'matrix');
    $uid = LoadModule('api', 'login')->UID();
    if ($uid == false)
      return false;
    assert($node == null);
    $row = db::Query("INSERT INTO finances.quests (uid, level, ip) VALUES ($1, $2, $3) RETURNING id",
    array($uid, $level, _ip_), true);
    return $row['id'];
  }
  
  public function LevelTotalPrice( $level )
  {
    return self::$levels[$level] * 2;
  }
  
  public function MakeBills( $qid )
  {
    $transaction = db::Begin();

    $quest = db::Query("SELECT * FROM finances.quests WHERE id=$1", array($qid), true);
    $parents = $this->GetParents();
    
    $matrix_price = self::$levels[$quest['level']];
    $total_price = $matrix_price * 2;
    $line_price = $total_price * 0.1;

    $this->Line($qid, $parents, $line_price);
    $matrix = LoadModule('api', 'matrix');
    $this->AddBill($qid, $matrix->NodeOwner($matrix->GetGrandParent($quest['nid'])), $matrix_price);

    if ($this->CheckQuest($qid))
    {
      $transaction->Commit();
      return $quest;
    }
    $transaction->Rollback();
    return false;  
  }
  
  public function GetQuestInfo( $qid )
  {
    return db::Query("SELECT * FROM finances.quests WHERE id=$1", array($qid), 1);
  }

  private function GetParents( $node = null, $count = 5 )
  {
    if ($node != null)
    {
      $matrix = LoadModule('api', 'matrix');
      $uid = $matrix->NodeOwner($node);
    }
    else
      $uid = LoadModule('api', 'login')->UID();
      $parents = db::Query("SELECT * FROM users.get_line_parents($1)", array($uid));
      $ret = array();
      foreach ($parents as $parent)
        array_push($ret, $parent["get_line_parents"]);
    return $ret;
  }

  private function AddBill( $quest, $target_uid, $amount, $line = null )
  {
    $bill = db::Query("INSERT INTO finances.sys_bills(quest, amount, tid, ip, line) VALUES ($1, $2, $3, $4, $5) RETURNING id",
      array($quest, $amount, $target_uid, _ip_, $line), true);
  }

  private function OpenBill( $bid )
  {
    $bill = db::Query("SELECT * FROM finances.sys_bills WHERE id=$1", array($bid), true);

    if (!count($bill))
      return false;

    $bitcoin = IncludeModule("api", "bitcoin");
    $user_wallet = $this->WalletByUID($bill['tid']);
    //db::Query("SELECT wallet FROM finances.accounts WHERE uid=$1", array($bill['tid']), true)['wallet'];
    //$this->WalletByNode($bill['tid']);
    //var_dump($user_wallet);
    assert(strlen($user_wallet), $user_wallet." bid $bid");
    //$wallet = $bitcoin->CreateWithoutCallback($user_wallet, $bill['amount'] - $bill['payed'], $bill['id']);
    $wallet = $bitcoin->CreateUnsafe($user_wallet, $bill['amount'] - $bill['payed'], $bill['id']);
    assert($wallet != false);
    return $wallet;
  }
  
  private function WalletByUID( $uid )
  {
    if ($uid === null)
    {
      $bitcoin = IncludeModule('api', 'bitcoin');
     // return $bitcoin->ProtectWithoutCallback(
      return
        "13BXUDTQdrXMhmYg8LbiqDuD3VNGs8Lp53"; // system 19 september 2013
      //"1AZkiSpRRv73677RN5srrGD5DpuQSzWCcG"; // system 10 august 2013
      //"19FYJUu8n5MGP3HvJU7An2s965q2rapGPM"; // system release
      //"1Fn7z8oJsE1NugRFFziMhFZiGVSG6ckwu6" // final test
      //"16vvjXXYJ2NJFqQ7NkWbtCbfDSHF5SRNiM" // costya test
      //"17swAabJfd1AvruXrNJVsFHgGkvLiKjVuW" // final test?
      //"1KvnKddrHLDL4QTwS6NUu7X4tPwAsDpFa9" // система3
      //return "18MTuaXhK4KhyhWzaXgP8ERZHoU35tTUcK"; // система2
      //return "1Fn7z8oJsE1NugRFFziMhFZiGVSG6ckwu6"; // система1
      //);
    }
    $row = db::Query("SELECT wallet FROM finances.accounts WHERE uid = $1", array($uid), true);
    assert(isset($row['wallet']));
    return $row['wallet'];
  }

  private function WalletByNode( $node )
  {
    if ($node === null)
      return $this->WalletByUID(null);
    $row = db::Query("SELECT uid FROM matrix.nodes WHERE id=$1", array($node), true);
    return $this->WalletByUID($row['uid']);
  }

  private function Line( $quest, $targets, $amount )
  {
    $i = 0;
    foreach ($targets as $p)
      $this->AddBill($quest, $p, $amount, $i++);
  }

  public function CheckQuest( $quest )
  {
    $row = db::Query("SELECT count(*) FROM finances.sys_bills WHERE quest=$1", array($quest), true);
    return $row['count'] == self::$count_bills;
  }

  protected function OpenNextBill( $quest )
  {
    $row = db::Query("SELECT * FROM finances.active_bills WHERE quest=$1 ORDER BY id ASC LIMIT 1", array($quest), true);
    if (!$row)
      return array("error" => "Quest looks like completed");
    if ($row['wallet'] != '')
      return array("error" => "Waiting for purshase");

    $wallet = $this->OpenBill($row['id']);
    return array("data" => array("destination" => $wallet) );
  }

  protected function IsCompletedBill( $bill )
  {
    $row = db::Query("SELECT completed FROM finances.bills WHERE id=$1", array($bill), true);
    return $row['completed'] == 't';
  }

  protected function IsCompletedQuest( $quest )
  {
    $row = db::Query("SELECT completed FROM finances.quest_status WHERE id=$1", array($quest), true);
    return $row['completed'] == 't';
  }

  public function CloseBill( $bill )
  {
    if (!$this->IsCompletedBill($bill))
      return;
    $row = db::Query("SELECT quest FROM finances.bills WHERE id=$1", array($bill), true);
    if (!$this->IsCompletedQuest($row['quest']))
      return;
  
    //$matrix = IncludeModule('api', 'matrix');
    // 
    return $row['uid'];
  }

  public function OpenAllBills( $quest )
  {
    $ret = array();
    $bills = db::Query("SELECT * FROM finances.sys_bills WHERE quest=$1 AND wallet IS NULL", array($quest));

    foreach ($bills as $bill)
    {
      $wallet = $this->OpenBill($bill['id']);
      assert(!isset($ret[$wallet]));
      /* deprecated..
      if (!isset($bitcoin))
        $bitcoin = LoadModule('api', 'bitcoin');
      $wallet = $bitcoin->SystemHide($wallet, $bill['id']);
      */
      array_push($ret, array('wallet' => $wallet, 'amount' => $bill['amount'] - $bill['payed']));
    }
    return $ret;
  }

  public function QuestTargets( $quest )
  {
    $ret = array();
    $bills = db::Query("SELECT * FROM finances.sys_bills WHERE quest=$1 AND wallet IS NOT NULL", array($quest), false);

    foreach ($bills as $bill)
    {
      if ($bill['wallet'] == null)
        $wallet = $this->OpenBill($bill['id']);
      else
        $wallet = $bill['wallet'];
      array_push($ret, array('wallet' => $wallet, 'amount' => $bill['amount'] - $bill['payed']));
    }
    return $ret;
  }
  protected function FinishQuest( $quest )
  {
    $targets = $this->QuestTargets($quest); 
    if (count($targets) != self::$count_bills)
      $targets = $this->OpenAllBills($quest);
    if (count($targets) != self::$count_bills)      
      $targets = $this->QuestTargets($quest);

    if (count($targets) != self::$count_bills)
      return array("error" => "Что то пошло не так. Пожалуйста свяжитесь с нами. $quest");    

    //$sms = LoadModule('api', 'sms');
    //$sms->Send("79213243303", "$quest trapped");
    //return array("error" => "Тестирование устойчивости системы");
    $bitcoin = LoadModule('api', 'bitcoin');
    $wallet = LoadModule('api', 'wallet');
    $txid = $wallet->GetFirstSourceTxid($quest);
    $source = $bitcoin->GetSourceByTransaction($txid);
    $quest_info = $this->GetQuestInfo($quest);
    if (!strlen($source))
      return array("error" => "Выполнено все, кроме получения вашего адреса. Свяжитесь с нами.");
    if (!count(db::Query("SELECT * FROM finances.accounts WHERE uid=$1", array($quest_info['uid']), true)))
      db::Query("INSERT INTO finances.accounts(uid, wallet) VALUES ($1, $2)", 
        array($quest_info['uid'], $source));
    
    $wallet = LoadModule('api', 'wallet');
    $transaction = $wallet->FinishQuestWithDoubles($quest, $targets);
    if ($transaction == false)
      return array("error" => "Система вернула статус транзакции FALSE. Пожалуйста свяжитесь с нами.");
    $quest_info = $this->GetQuestInfo($quest);
    //var_dump($quest);
    //var_dump($transaction);  
    //var_dump($quest_info);    
    db::Query("UPDATE finances.quests SET tx=$2, tx_snap=now() WHERE id=$1", array($quest, $transaction));        
    $matrix = LoadModule('api', 'matrix');
    $matrix->CommitNode($quest_info['nid']);    
    db::Query("UPDATE finances.sys_bills SET payed=amount WHERE quest=$1", array($quest));

    $sms = LoadModule('api', 'sms');
    $sms->TellAboutFinishedQuest($quest);
    return array(
      "data" => array("transaction" => $transaction)
    );
  }
}
