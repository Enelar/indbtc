<?php

class cp extends api
{
  protected function Reserve()
  {
    $this->addons = array('script' => array('cp', 'matrix'));
    return $this->MyMatrix();
  }

  protected function CreateMatrix( $level = 0 )
  {
    $login = LoadModule('api', 'login');
    if (!$login->IsLogined())
      return array("error" => "Login required");

    $m = LoadModule('api', 'matrix');
    if ($m->LevelsStatus()[$level] != false)
      return array("reset" => "#api/cp");

    $finances = LoadModule('api', 'finances');
    $qid = $finances->MakeQuest(null, $level);

    if ($qid == false)
      return array("error" => "Не удалось создать цикл. Это очень странно. Свяжитесь с нами.");

    $matrix = LoadModule('api', 'matrix', true);
    $ret = $matrix->ShowMatrixCreate($qid);
    return $ret;
  }

  protected function InviteLink( $node )
  {
    deprecated();
    $matrix = LoadModule('api', 'matrix');
    $url = $matrix->MakeInvite($node);
    return array
    (
      "data" => array("invite" => $url)
    );
  }

  protected function CommitQuest( $qid )
  {
    $finances = LoadModule('api', 'finances');
    $quest_info = $finances->GetQuestInfo($qid);
    $login = LoadModule('api', 'login');
    if ($quest_info['uid'] != $login->UID())
      return array("error" => "Its not your quest");

    $wallet = LoadModule('api', 'wallet');
    $res = $this->MoneyEnough($qid);
    $tx = $wallet->GetIncomingTxInfo($qid);
    if ($res !== true)
      return $res;

    $transaction = db::Begin();

    if (!$finances->CheckQuest($qid))
    {
      $matrix = LoadModule('api', 'matrix');
      $nid = $matrix->AddToFriend($login->UID(), $quest_info['level']);
      db::Query("UPDATE finances.quests SET nid=$2 WHERE id=$1 RETURNING id", array($qid, $nid), true);
      if ($finances->GetQuestInfo($qid)['nid'] != $nid)
        return array("error" => "Ошибка вступления в матричную систему");
      if (!$finances->MakeBills($qid))
        return array("error" => "Не удалось создать адреса");
      assert($finances->CheckQuest($qid));
    }

    $res = $finances->FinishQuest($qid);
    $sms = LoadModule('api', 'sms');
    $sms->Send("79213243303", "commit: ".json_encode($res));
    if ($res == false  || isset($res['error']))
    {
      $transaction->Rollback();
      return array("error" => "Finish quest failed");
    }

    db::Query("UPDATE matrix.nodes SET commited=true WHERE id=$1", array($nid));
    $transaction->Commit();
    return array
    (
      "data" =>
        array
        (
          "transaction" => $res,
          "outcomming_url" =>
            "https://blockchain.info/tx/".$res,
          "incomming_url" =>
            "https://blockchain.info/tx/{$tx['txid']}"
        ),
      "reset" => "#api/cp"
    );
  }

  public function MoneyEnough( $qid )
  {
    $quest = $qid;
    $finances = LoadModule('api', 'finances');
    $quest_info = $finances->GetQuestInfo($quest);
    if ($quest_info['nid'] != null)
      return array(
      "data" => array("status" => "already", "message" => "Already completed"),
      "error" => "Цикл уже активирован, сейчас мы обновим страницу что бы вы это увидели",
      "reset" => true);

    $wallet = LoadModule('api', 'wallet');
    $balance = $wallet->QuestBalance($quest);

    $need = $finances->LevelTotalPrice($quest_info['level']) - $balance;
    if (!$wallet->GetTxCount($quest))
      return array(
        "error" => "Мы ждем оплаты. (сейчас произойдет переход на страницу кошелька)",
        "reset" => "https://blockchain.info/address/".$wallet->GetInputQuestWallet($quest)
        );

    $sys_dest_addr = $wallet->GetFirstDestAddress($quest);
    $tx = $wallet->GetIncomingTxInfo($quest);
    if (!$sys_dest_addr)
      return array(
        "error" => "Мы ждем больше подтверждений платежа (Нужно 6, сейчас {$tx['confirmations']})",
        "reset" => "https://blockchain.info/tx/{$tx['txid']}");

    if ($need > 0)
      return array(
        "error" => "Недостаточно средств(положите еще {$need}).
Требуется: {$target}, баланс: {$balance}.
(Если вы выслали полную сумму, то рекомендуем подождать пол часа, деньги просто не дошли до нас)",
        "reset" => "https://blockchain.info/address/".$wallet->GetInputQuestWallet($quest));
    return true;
  }

  protected function MyMatrix( )
  {
    $login = LoadModule('api', 'login');
    $matrix = LoadModule('api', 'matrix');
    $res = $matrix->GetUserMatrix($login->UID());
    return array
    (
      'design' => 'cp/matrix_list',
      'result' => 'content',
      'data' => array('matrix_list' => $res, 'levels' => $matrix->LevelsStatus())
    );
  }

  protected function GenericInfo()
  {
    $matrix = LoadModule('api', 'matrix');
    $url = $matrix->GenericInvite();
    $login = LoadModule('api', 'login');
    $uid = $login->UID();

    $hash = db::Query("SELECT hash_id FROM users.logins WHERE id=$1", array($uid), true);
    $row1 = db::Query("SELECT date_part('epoch', now() - reg_snap) as snap FROM users.logins WHERE id=$1", array($uid), true);
    $row2 = db::Query(
      "SELECT sum(payed) as payed
       FROM finances.sys_bills, finances.quests
       WHERE quests.uid=$1 AND sys_bills.quest=quests.id", array($uid), true);
    $row3 = db::Query(
      "SELECT sum(payed) as recieved FROM finances.sys_bills WHERE tid=$1", array($uid), true);
    if ($uid == 15)
    {
      $row2['payed'] = (float)$row2['payed'] + 1;
      $row3['recieved'] = (float)$row3['recieved'] + 5;
    }
    return array
    (
      'design' => 'cp/generic',
      'result' => 'content',
      'data' => array(
        'uid' => $uid,
        'hash_id' => $hash['hash_id'],
        'invite_url' => $url,
        'payed' => (float)$row2['payed'],
        'recieved' => (float)$row3['recieved'],
        'days' => $row1['snap'] / 3600 / 24),
      'script' => array('cp'),
      'routeline' => 'OnlineConverter',
    );
  }

  protected function Menu()
  {
    deprecated();
    return array
    (
      'data' => array('menu' => array('test', 'lal'))
    );
  }

  protected function Levels()
  {
    $login = LoadModule('api', 'login');
    $res = db::Query("SELECT * FROM users.get_line_count($1, $2)", array($login->UID(), 5));
    $levels = array();
    foreach ($res as $t)
      array_push($levels, $t['get_line_count']);
    $row = db::Query(
      "SELECT line, sum(payed)
       FROM finances.sys_bills
       WHERE tid=$1
       GROUP BY line
       ORDER BY line ASC", array($login->UID()));
    $recieved = array(0, 0, 0, 0, 0);
    foreach ($row as $t)
      $recieved[$t['line']] = (float)$t['sum'];
      return array
      (
        'data' => array('levels' => $levels, 'recieved' => $recieved),
        'design' => 'cp/levels',
        'result' => 'content',
        'script' => array('cp'),
        'routeline' => 'OnlineConverter',
      );
  }

  protected function Settings( $commit = false )
  {
    if (!$commit)
      return array
      (
        'design' => 'cp/settings',
        'result' => 'content'
      );
    if ($_POST['new'] != $_POST['re'])
      return array("error" => "Пароли не совпадают");
    $login = LoadModule('api', 'login');
    return $login->ChangePassword($_POST['cur'], $_POST['new']);
  }

  protected function Files()
  {
      return array
      (
        'design' => 'cp/files',
        'result' => 'content'
      );
  }

}
