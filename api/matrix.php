<?php

class matrix extends api
{
  public function AddChild( $uid, $parent, $level )
  {
    debug_print_backtrace();
    echo "depricated";
    exit();
    if ($parent != null && !$this->IsCompleted($parent))
      return false;
    db::Query("BEGIN;");

    $row = db::Query("INSERT INTO matrix.nodes(uid, parent, ip, level) VALUES($1, $2, $3, $4) RETURNING id",
      array($uid, $parent, _ip_, $level), 1);
//    var_dump($row);
    if (!count($row))
      return false;
    $node = $row['id'];
//    var_dump($row);
    if ($node == NULL)
      return false;
    $finances = LoadModule("api", "finances");
    $quest = $finances->MakeQuest($node, $level);

//    var_dump($quest);
    if ($quest === false)
    {
      db::Query("ROLLBACK;");
      return false;
    }
    db::Query("COMMIT;");
    return $node;
  }
  
  public function AddToFriend( $uid, $level )
  {
    if (!defined("_ip_"))
      return array("error" => "IP undefined");
          
    $row = db::Query("SELECT matrix.add_to_system($1, $2, $3)", array($uid, $level, _ip_), true);

    assert(count($row));
    //var_dump($row);
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

  private function AddToTop( $uid, $level )
  {
    $nid = $this->AddChild($uid, null, $level);
//    var_dump($nid);
    if ($nid === null)
      return false;
    return $nid;
  }

  public function DeleteMatrix( $nid )
  {
    db::Query("DELETE FROM matrix.nodes WHERE id=$1", array($nid));
  }

  public function GetChilds( $node )
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
    return $res['is_completed'];
  }
  
  protected function Invite( $node, $hash, $force = false )
  {
    global $_SESSION;
    
    $login = LoadModule('api', 'login');
    if ($login->IsLogined())
      if (!$force)
        return array(
          "script" => array("login"),
          "routeline" => "InviteDelogin",
          "data" => array("url" => "api/matrix/invite?node={$node}&hash={$hash}&force=1")
          );
      else
        $login->DoLogout();

    //$right_hash = md5(serialize($res));
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
/* outdated
    $childs = db::Query("SELECT matrix.count_childs(childs) FROM matrix.nodes WHERE id=$1", array($node), true);
    if ($childs['count_childs'] == 2)
      return array("error" => "Matrix already full. No place to next child.");
      */
    return array("reset" => "#api/reg");
  }

  public function GenericInvite( )
  {
    $login = LoadModule('api', 'login');
    $row = db::Query("SELECT id FROM matrix.nodes WHERE uid=$1 ORDER BY id DESC LIMIT 1", array($login->UID()), true);
    if (!count($row))
      throw new phoxy_protected_call_error(array("error" => "Для доступа к статистике зарегистрируйтесь в одном из циклов!"));
    return $this->MakeInvite($row['id']);
  }

  public function MakeInvite( $node )
  {
    $url = (phoxy_conf()['site']); //.'#'.(phoxy_conf()["get_api_param"]).'/matrix/Invite';

    $hash = $this->NodeHash($node);
    return "{$url}invite/{$node}/{$hash}";
  }

  private function NodeHash( $node )
  {
    $res = db::Query("SELECT id, uid, parent, ip, snap FROM matrix.nodes WHERE id=$1", array($node), true);
    if (!count($res))
    {
      $_SESSION['friend'] = null;
      return array("reset" => true);
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
    $res = db::Query("WITH last_matrix AS
    (
      SELECT max(id) as id, level FROM matrix.nodes WHERE uid=$1 GROUP BY level ORDER BY id DESC
    ) SELECT id, level, matrix.is_completed(id, 2) as status FROM last_matrix", array($login->UID()));
    $ret = array();
    foreach ($res as $t)
      if ($t['status'] == 'f')
        $ret[$t['level']] = $t['id'];
      else
        $ret[$t['level']] = false;
    for ($i = 0; $i < 8; $i++)
      if (!isset($ret[$i]))
        $ret[$i] = false;
    return $ret;
  }

  protected function ShowMatrixCreate( $nid )
  {
    $node = db::Query("SELECT * FROM matrix.nodes WHERE id=$1", array($nid), true);
    $quest = $this->NodeQuest($nid);
    //var_dump($quest);
    $wallet = LoadModule('api', 'wallet');
    //debug_print_backtrace();
    $input_wallet = $wallet->GetInputQuestWallet($quest);
    if ($input_wallet == false)
    {
      return array("error" => "Matrix created, but bicoin subsystem wont open bill. Please contact us.");
    }
    //$bitcoin = LoadModule('api', 'bitcoin');
    //$protected = $bitcoin->ProtectWithCallback($input_wallet);
    $finances = LoadModule('api', 'finances');
    $quest_info = $finances->GetQuestInfo($quest);

    $tax = finances::$tax;
    $amount = $quest_info['amount'] - $quest_info['payed'] + $tax;
    return array
    (
      "data" =>
        array
        (
          "node" => $nid,
          "quest" => $quest,
          "wallet" => $input_wallet,
          "wallet_qr_url" => phoxy_conf()['site']."api/qr/Bill?wallet={$input_wallet}&amount={$amount}",
          "bitcoin_uri" => "bitcoin:{$input_wallet}?amount={$amount}",
          "amount" => $amount,
          "level" => $node['level']
        ),
      "design" => "matrix/create",
      "result" => "matrix_{$node['level']}"
    );
  }
}
