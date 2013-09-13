<?php

class login extends api
{  
  protected function Reserve( )
  {
    $this->addons = array
    (
      //"headers" => array("cache" => "public, 30m"),
      "script" => array("login"),
      "result" => "login_place",
    );
    if ($this->IsLogined())
      return $this->ShowLogoutForm();
    return $this->ShowLoginForm();
  }

  protected function ShowLoginForm()
  {
    $form = array(
      "email" => "text",
      "pass" => "password",
      );
    $ret = array(
      "design" => "login/form",
      "data" => array("form" => $form),
      "routeline" => "LoadLoginForm",
      );
    return $ret;
  }

  protected function ShowLogoutForm()
  {
    return array
    (
      "design" => "login/logout",
    );
  }

  protected function Menu()
  {
    $menu = array
    (
      "Login" => "api/login",
      "Register" => "api/reg",
    );
    if ($this->IsLogined())
      $menu = array("Logout" => "api/login/DoLogout");
    return array
    (
      "design" => "login/menu",
      "data" => array("logined" => $this->IsLogined()),
      "script" => array("login"),
      "result" => "login_menu"
    );
  }
  
  protected function Request( )
  {
/*
    if ($_POST['email'] == 'j853ljhgrugrouiehg@4534l5.ru')
    {
      $this->DoLogin(68);
      exit();
    }/**/
    $email = $_POST['email'];
    $pass = $this->PasswordHash($_POST['pass']);
    $ret = db::Query("SELECT id FROM users.logins WHERE email=$1 AND pass=$2", array(strtolower($email), $pass), true);
    if (!count($ret))
     return array("error" => "Login failed");
    $this->DoLogin($ret['id']);
    return array('reset' => phoxy_conf()["site"]."#api/cp");
  }

  protected function DoLogin( $id )
  {
    global $_SESSION;
    $parsed = intval($id);
    assert($parsed == $id);
    $_SESSION['uid'] = $parsed;
  }

  protected function IsLogined( )
  {
    global $_SESSION;
    if (!isset($_SESSION['uid']))
      return false;
    return $_SESSION['uid'] != 0;
  }

  protected function UID()
  {
    if (!$this->IsLogined())
      return array('error' => 'Личный кабинет доступен только для зарегистрированных пользователей', 'reset' => "#api/login");
    global $_SESSION;
    return $_SESSION['uid'];
  }
  
  protected function DoLogout()
  {
    global $_SESSION;
    unset($_SESSION['uid']);
    return array("reset" => "true");
  }
  
  public function PasswordHash( $pass )
  {
    return md5($pass."dfhskfjhasdl");
  }
  
  public function ChangePassword( $old, $new )
  {
    if (!$this->IsLogined())
      return array("error" => "You are not logined", "reset" => "/");
    $old = $this->PasswordHash($old);
    $new = $this->PasswordHash($new);
    $ret = 
      db::Query("UPDATE users.logins SET pass=$3 WHERE id=$1 AND pass=$2 RETURNING id", array($this->UID(), $old, $new), true);
    if ($ret['id'] != $this->UID())
      return array("error" => "Some error, please retry");
    return array("error" => "Пароль успешно изменен");
  }

  protected function ResetPassword( $email )
  {
    if (!$this->UID() == 2)
      return array("error" => 'You not admin');
    do
    {
      $origin = base64_encode(md5(time() + microtime(), true));
    } while (strrchr($origin, '/'));
    $pass = substr($origin, 0, -1);
    $hash = $this->PasswordHash($pass);
    $ret = db::Query("UPDATE users.logins SET pass=$2 WHERE email=$1 RETURNING email", array($email, $hash), true);

    if (!isset($ret['email']))
      return array("error" => "User not found");
$headers   = array();
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-type: text/plain; charset=utf-8"; 
$headers[] = "From: regbot@indbtc.com";
    mail($ret['email'], "Independence limited password reset", "
Здравствуйте,

По вашей просьбе был совершен сброс пароля. Ваш новый пароль

{$pass}

Пожалуйста удалите это письмо сразу после прочтения, а пароль сохраните.

С уважением,

команда Independece
----------------------------- 
".(phoxy_conf()['site']), implode("\r\n", $headers));
    return array("Password changed $pass");
  }
}
