<?php

class main extends api
{
  protected function Reserve( )
  {
    global $_SESSION;
    if (isset($_SESSION['uid']))
      $uid = $_SESSION['uid'];
    else
      $uid = 0;

    $ret = array(
      "design" => "main/body.ejs",
      "headers" => array("cache" => 60*30),
      "data" =>
        array(
          "uid" => $uid,
          "title" => "Independence BitCoin",
          "load" => array("api/login/menu", "api/menu"),

          ),
      "script" => array("main"),
      "routeline" => "Init"
      );
    return $ret;
  }
}

