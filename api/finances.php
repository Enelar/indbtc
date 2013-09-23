<?php

class finances extends api
{
  public static $tax = 0.0005;
  private static $line_price = 0.001; // deprecated
  private static $count_bills = 6;

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

    assert(strlen($user_wallet), $user_wallet." bid $bid");

    $wallet = $bitcoin->CreateUnsafe($user_wallet, $bill['amount'] - $bill['payed'], $bill['id']);
    assert($wallet != false);
    return $wallet;
  }

  private function WalletByUID( $uid )
  {
    if ($uid === null)
    {
      $bitcoin = IncludeModule('api', 'bitcoin');
      return
        "13BXUDTQdrXMhmYg8LbiqDuD3VNGs8Lp53"; // system 19 september 2013
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

  public function OpenAllBills( $quest )
  {
    $ret = array();
    $bills = db::Query("SELECT * FROM finances.sys_bills WHERE quest=$1 AND wallet IS NULL", array($quest));

    foreach ($bills as $bill)
    {
      $wallet = $this->OpenBill($bill['id']);
      assert(!isset($ret[$wallet]));

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
