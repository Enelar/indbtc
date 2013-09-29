<?php

class matrix extends api
{
  public function AddToFriend( $uid, $level )
  {
    if (!defined("_ip_"))
      return array("error" => "IP undefined");

    $row = db::Query("SELECT matrix.add_to_system($1, $2, $3)", array($uid, $level, _ip_), true);

    assert(count($row));

    return $row['add_to_system'];


    $ids = db::Query("SELECT id FROM matrix.nodes WHERE uid=$1 AND matrix.count_childs(childs) < 2 AND commited=true", array($uid));
    foreach ($ids as $id)
      if (($nid = $this->AddChild($uid, $id['id'], $level)) != false)
        if ($nid != null)
          return $nid;
    if (count($ids) == 0)
      return $this->AddToTop($uid, $level);
    return false;
  }

  public function GetGrandParent( $nid )
  {
    $res = db::Query("SELECT matrix.get_parents($1, 2) as tid OFFSET 1", array($nid), true);
    if (!count($res))
      return null;
    return $res['tid'];
  }

  private function IsCompleted( $node, $depth = 2 )
  {
    $res = db::Query("SELECT * FROM matrix.is_completed($1, $2)", array($node, $depth), true);
    if (!count($res))
      return false;
    return $res['is_completed'] === 't';
  }

  protected function Invite( $node, $hash, $force = false )
  {
    global $_SESSION;

    $login = LoadModule('api', 'login');
    if (!$force)
      phoxy_protected_assert(!$login->IsLogined(),
      array(
          "script" => array("login"),
          "routeline" => "InviteDelogin",
          "data" => array("url" => "api/matrix/invite?node={$node}&hash={$hash}&force=1")
          ));
    else if ($login->IsLogined())
      $login->DoLogout();

    if ($hash != $this->NodeHash($node))
    {
      $res = db::Query("SELECT id, uid, parent, ip, snap FROM matrix.nodes WHERE id=$1", array($node), true);
      if (count($res))
        return array("error" => "Wrong invite link");
      $_SESSION['friend'] = null;
      return array("reset" => true);
    }

    $res = $this->GetNode($node);
    $_SESSION['friend'] = $res['uid'];

    return array("reset" => "#api/reg");
  }

  public function GenericInvite( )
  {
    $login = LoadModule('api', 'login');
    $row = db::Query("SELECT id FROM matrix.nodes WHERE uid=$1 ORDER BY id DESC LIMIT 1", array($login->UID()), true);
    phoxy_protected_assert(count($row),
      array("error" => "Для доступа к статистике зарегистрируйтесь в одном из циклов!"));
    return $this->MakeInvite($row['id']);
  }

  private function MakeInvite( $node )
  {
    $url = (phoxy_conf()['site']);

    $hash = $this->NodeHash($node);
    return "{$url}invite/{$node}/{$hash}";
  }

  private function NodeHash( $node )
  {
    $res = db::Query("SELECT id, uid, parent, ip, snap FROM matrix.nodes WHERE id=$1", array($node), true);
    if (!count($res))
    {
      $_SESSION['friend'] = null;
      throw new phoxy_protected_call_error(array("reset" => true));
    }
    $right_hash = md5(serialize($res));
    return $right_hash;
  }

  public function NodeOwner( $node )
  {
    if ($node === null)
      return null;
    $res = $this->GetNode($node);
    if ($res === false)
      return false;
    return $res['uid'];
  }

  public function GetNode( $node )
  {
    $res = db::Query("SELECT * FROM matrix.nodes WHERE id=$1", array($node), true);
    if (!count($res))
      return false;
    return $res;
  }

  public function NodeQuest( $node )
  {
    $quest = db::Query("SELECT * FROM finances.quest_status WHERE nid=$1", array($node), true);
    if (count($quest))
      return $quest['id'];
    $f = LoadModule('api', 'finances');
    return $f->MakeQuest($node);
  }

  public function CommitNode( $node )
  {
    db::Query("UPDATE matrix.nodes SET commited=true WHERE id=$1", array($node), true);
  }

  public function GetUserMatrix( $uid )
  {
    $res = db::Query("SELECT id, uid, parent, commited FROM matrix.get_all_cycles($1)", array($uid));
    $ret = array();
    foreach ($res as $row)
    {
      $id = $row['id'];
      $row['childs'] = array();
      $ret[$id] = $row;
    }

    $cret = $ret;
    foreach ($cret as $key => $child)
    {
      if ($child['uid'] == $uid)
        continue;
      $child = $ret[$key];
      $pid = $child['parent'];
      assert(isset($ret[$pid]), $pid);
      $ret[$pid]['childs']['length'] = 0;
      $ret[$pid]['childs'][$key] = $child;
      $ret[$key] = &$ret[$pid]['childs'][$key];
      $count = count($ret[$pid]['childs']);
      if ($count > 1)
        $count--; // already has length
      $ret[$pid]['childs']['length'] = $count;
    }

    $return = array();
    foreach ($ret as $key => $child)
      if ($child['uid'] == $uid)
        $return[$key] = $child;
    return $return;
  }

  public function LevelsStatus()
  {
    $login = LoadModule("api", "login");
    $res = db::Query("WITH last_quests AS
    (
       SELECT max(id) as id, level FROM finances.quests WHERE uid=$1 GROUP BY level ORDER BY id DESC
    ), quest AS
    (
      SELECT finances.quests.* FROM finances.quests, last_quests WHERE last_quests.id=quests.id
    ) SELECT nid as id, level, matrix.is_completed(nid, 2) as status FROM quest;", array($login->UID()));
    $ret = array();
    foreach ($res as $t)
    if ($t['id'] == null)
      $ret[$t['level']] = true;
    else
    {
        if ($t['status'] == 'f')
          $ret[$t['level']] = $t['id'];
        else
          $ret[$t['level']] = false;
    }
    for ($i = 0; $i < 8; $i++)
      if (!isset($ret[$i]))
        $ret[$i] = false;
    return $ret;
  }

  protected function ShowLevelCreate( $level )
  {
    $login = LoadModule('api', 'login');
    $row = db::Query("SELECT * FROM finances.quests WHERE uid=$1 AND level=$2 ORDER BY id DESC LIMIT 1",
      array($login->UID(), $level), true);
    return $this->ShowMatrixCreate($row['id']);
  }

  protected function ShowMatrixCreate( $qid )
  {
    $quest = $qid;
    $wallet = LoadModule('api', 'wallet');
    $input_wallet = $wallet->GetInputQuestWallet($quest);
    phoxy_protected_assert($input_wallet != false,
      array("error" => "Matrix created, but bicoin subsystem wont open bill. Please contact us."));

    $finances = LoadModule('api', 'finances');
    $quest_info = $finances->GetQuestInfo($quest);

    $tax = finances::$tax;
    $amount = $finances->LevelTotalPrice($quest_info['level']) + $tax;
    phoxy_protected_assert($amount > $tax,
      array("error" => "Проблема с выпиской счета. Обратитесь к нам."));
    return array
    (
      "data" =>
        array
        (
          "node" => null,
          "quest" => $quest,
          "wallet" => $input_wallet,
          "wallet_qr_url" => phoxy_conf()['site']."api/qr/Bill?wallet={$input_wallet}&amount={$amount}",
          "bitcoin_uri" => "bitcoin:{$input_wallet}?amount={$amount}",
          "amount" => $amount,
          "level" => $quest_info['level']
        ),
      "design" => "matrix/create",
      "result" => "matrix_{$quest_info['level']}"
    );
  }
  
  /***
   * Unused
   ***/
  
  private function GetChilds( $node )
  {
    $ret = array();
    $res = db::Query("SELECT *, min(childs) as child1, max(childs) as child2 FROM matrix.nodes WHERE id = $1 ORDER BY id", array($node));
    foreach ($res as $row)
      array_push($ret, $row['id']);
    foreach ($res as $row)
    {
      array_push($ret, $row['child1']);
      array_push($ret, $row['child2']);
    }
    return $ret;
  }  
}
