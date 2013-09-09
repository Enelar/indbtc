<?php

include_once('lib/curl.php');

class sms extends api
{
  private $sign = '5dc6160637cc2a39212aa94ef05f2f96';
  
  protected function Reserve()
  {
    return md5($url . "&password=".urlencode(""));
  }
  
  public function SendUID( $uid, $message )
  {
    $row = db::Query("SELECT phone FROM users.logins WHERE uid=$1", array($uid), true);
    assert(isset($row['phone']));
    return $this->Send($row['phone'], $message);
  }
  
  private function Send( $phone, $message )
  {
    assert(false); // todo task_id
    // todo username comtube
    // todo signature
    $a = array(
      "action" => "send",
      "uid" => $task_id,
      "number" => $phone,
      "message" => $message,
      "charset" => "utf-8",
      "username" => $username,
      "type" => "json",
      "signature" => $sign,
      "report_to" => "sms@indbtc.com",
    );
    $res = http_post_request("http://api.comtube.ru/scripts/api/sms.php", $this->BuildSignature($a));
    $obj = json_decode($ret, true);
    var_dump($obj);
  }
  
  function BuildSignature($params)
  {
    ksort($params);
    $url = '';

    if (!is_array($params))
        return $url;

    foreach($params as $key => $value)
      $url .= $key . "=" . urlencode($value) . "&";

    $params['signature'] => $this->sign;
//    $signature = md5($url . "&password=".urlencode($this->password));
  //  $url .= "signature=" . $signature;

    return $params;
  }  
}
