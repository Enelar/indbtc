<?php

error_reporting(E_ALL); ini_set('display_errors', '1');
include_once('lib/curl.php');

class sms extends api
{
  private static $sign = '5dc6160637cc2a39212aa94ef05f2f96';
  private static $user = 'indbtc';
  private static $pass = 'xxxhotbar18';
  
  protected function Reserve()
  {
    return md5($url . "&password=".urlencode(""));
  }
  
  public function TellAboutFinishedQuest( $quest )
  {
    $rows = db::Query("SELECT payed, tid FROM finances.sys_bills WHERE quest=$1", array($quest));
    $quest = db::Query("SELECT * FROM finances.quests WHERE id=$1", array($quest), true);
    foreach ($rows as $row)
	{
	  $amount = round($row['payed'], 3);
      $this->SendUID($row['tid'], "Вам отправлено {$amount} btc от №{$quest['uid']}.");
	}
  }
  
  public function SendUID( $uid, $message )
  {
    if ($uid == null)
	  return true;
    $row = db::Query("SELECT phone FROM users.logins WHERE id=$1", array($uid), true);
    assert(isset($row['phone']));
    return $this->Send($row['phone'], $message);
  }
  
  protected function ManualSend( $uid, $message )
  {
    if (_ip_ != '213.21.7.6')
      return false;
    return $this->SendUID($uid, $message);
  }
  
  private function WarningUncommited( )
  {
    $res = db::Query("SELECT * FROM matrix.nodes WHERE commited=false");
	$c = count($res);
	foreach($res as $row)
	  $this->SendUID($row['uid'],
	    "Если вы уже отправили средства, подтвердите цикл в течении 30 минут. Иначе не отправляйте: ваш цикл будет удален.");
	return $c;
  }
  
  protected function Send( $phone, $message )
  { // https://www.comtube.com/forum/viewtopic.php?f=33&t=494
    $task_id = time();
    $username = self::$user;
    $a = array(
      "action" => "send",
      "uid" => $task_id,
      "number" => $phone,
      "message" => $message,
      "charset" => "utf-8",
      "username" => $username,
      "type" => "json",
	  "senderid" => "indbtc.com"
    );
    //var_dump($this->BuildSignature($a));
    $res = http_post_request("http://api.comtube.ru/scripts/api/sms.php", $this->BuildSignature($a));
    $obj = json_decode($res, true);
    return $obj['code'] == 200;
  }
  
  private function BuildSignature($params)
  { // https://www.comtube.com/forum/viewtopic.php?f=33&t=495
    ksort($params);
    $url = '';

    if (!is_array($params))
        return $url;

    foreach($params as $key => $value)
      $url .= $key . "=" . urlencode($value) . "&";

    $signature = md5($url . "&password=".urlencode(self::$pass));
    $params['signature'] = $signature;
  //  $url .= "signature=" . $signature;

    return $params;
  }  
}
