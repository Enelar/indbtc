<?php

class reg extends api
{  
  protected function Reserve( )
  {
    $form = array(
      "email" => "text",
      "pass" => "password",
      "repass" => "password",
      //"capcha" => "hidden",
      //"capcha_id" => "hidden",
      "phone" => "text",
      );
    $capcha = $this->GenCapcha();
    global $_SESSION;
    $_SESSION['capcha'][$capcha["id"]] = $capcha["val"];
    
    if (!isset($_SESSION['friend']))
      $form['referer'] = 'text';

    
    $val = array(
      "capcha_id" => $capcha['id'],
      "capcha" => $capcha["val"]
    );
    
    
    $ret = array(
      "design" => "reg/form",
      "headers" => array("cache" => "public, 30m"),
      "data" => array("form" => $form, "values" => $val),
      "script" => array("reg"),
      "routeline" => "LoadRegForm",
      "result" => "reg_place"
      );
    return $ret;
  }
  
  private function GenCapcha()
  {
    return array("id" => rand(), "val" => "aaa");
  }
  
  private function Capcha( $id, $value )
  {
    global $_SESSION;
    return $_SESSION['capcha'][$id] == $value;
  }
  
  private function SafeEmail( $e )
  {
    $arr = explode("@", $e);
    if (count($arr) < 2 || strlen($arr[1]) < 4)
      throw new phoxy_protected_call_error(array("error" => "Unrecognized email"));
    $name = $arr[0];
    $domain = $arr[1];
    
    $ret = $name[0];
    for ($i = 1; $i < strlen($name); $i++)
      $ret .= "*";
    $ret .= "@";
    $ret .= $domain[0];
    for ($i = 1; $i < strlen($domain); $i++)
      $ret .= "*";
    return $ret;
  }

  private function SendInvite( $mail, $url )
  {
$headers   = array();
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-type: text/plain; charset=utf-8"; 
$headers[] = "From: regbot@indbtc.com";
    mail($mail, "Independence limited confirm", "
Здравствуйте,

Благодарим вас за регистрацию учётной записи Independence. Чтобы активировать учётную запись, перейдите по следующей ссылке.

{$url}

Если щелчок на ссылке не работает, скопируйте ссылку в окно вашего браузера или введите непосредственно с клавиатуры.

С уважением,

команда Independece
----------------------------- 
".(phoxy_conf()['site']), implode("\r\n", $headers));
  }
  
  protected function Request( )
  {
    /*
    $id = $_POST['capcha_id'];
    $value = $_POST['capcha'];
    if (!$this->Capcha($id, $value))
      return array("error" => "Capcha not match");
      */
    
    if (strlen($_POST['phone']) < 5)
      return array("error" => "Крайне важно указать настоящий телефон. Серьезно.");
    $login = LoadModule('api', 'login');      
    if (!isset($_POST['age']) && $_POST['age'] != 'checked')
      return array("error" => "Вы должны быть старше 18 лет");
    $code = md5(rand());

    $row = db::Query("INSERT INTO users.request(email, pass, ip, code, phone) VALUES ($1, $2, $3, $4, $5) RETURNING id;",
      array(strtolower($_POST["email"]),
      $login->PasswordHash($_POST['pass']),
      phoxy_conf()["ip"],
      $code,
      $_POST['phone']), true);
    if (!count($row))
      return array("error" => "Создать аккаунт не удалось. Попробуйте написать в обратную связь");
      
    $id = $row['id'];
    global $_SESSION;
    $friend = null;
    if (isset($_SESSION['friend']))
      $friend = $_SESSION['friend'];
    else
    {
      $friend = $_POST['referer'];
      if (!is_numeric($friend))
      {
        $t = db::Query("SELECT id FROM users.logins WHERE hash_id=$1", array($friend), true);
        $friend = $t['id'];
      }
    }

    if ($friend > 0)
      db::Query("UPDATE users.request SET friend=$2 WHERE id=$1", array($id, $friend));
    
    $url = phoxy_conf()["site"]."#api/reg/email/html";
    $url .= "&id={$id}&code={$code}";

    $this->SendInvite($_POST['email'], $url);
    
    return array(
      "design" => "reg/request",
      "data" => array(
        "email" => $this->SafeEmail($_POST['email']),
        //"url" => $url
          ),
      "scripts" => "reg",
      "routeline" => "ClearAfterReg",
      "result" => "content"
      );
  }
  
  protected function Email( )
  {
    $id = $_GET['id'];
    $code = $_GET['code'];
    $res = db::Query("SELECT * FROM users.request WHERE id = $1 AND code = $2", array($id, $code), 1);

    $this->addons['hash'] = '';
    if (!count($res))
      return array("error" => "Record not found");
    if ($res['mail_verified'] == 't')
      return array("error" => "Mail already verified");
    
    $rec = db::Query("SELECT count(*) FROM users.logins WHERE email = (SELECT email FROM users.request WHERE id = $1)",
    array($id), true);
    if ($rec['count'] == 0)
    {
      $uid_row = db::Query("SELECT * FROM users.add_user_by_code($1, $2)", 
          array($id, $code), 1);
      if (!count($uid_row))
        return array("error" => "Cant create user(already exsist?)");
      $uid = $uid_row['add_user_by_code'];
    }
    db::Query("DELETE FROM users.request WHERE id = $1 AND code = $2", array($id, $code));

    if ($rec['count'] != 0)
      return array("error" => "Already registered");
    $login = IncludeModule("api", "login");
    $login->DoLogin($uid);
    
    return array(
      "design" => "reg/email",
      "data" => array("status" => "success"),
      "result" => "content",
      "reset" => "#api/cp"
        );
  }
}
