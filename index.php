<?php

//$_SERVER['REMOTE_ADDR'] = '8.8.8.8';
if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1')
  define("_ip_", $_SERVER['REMOTE_ADDR']);
else
  define("_ip_", explode(',', $_SERVER["HTTP_X_FORWARDED_FOR"])[0]);

//error_reporting(E_ALL); ini_set('display_errors', '1');
function phoxy_conf()
{
  $ret = phoxy_default_conf();
  $ret['site'] = 'http://indbtc.com/';
  $ret['js_prefix'] = 'js/';
  return $ret;
}

function deprecated()
{
  $a = debug_backtrace();
  $sms = LoadModule('api', 'sms');
  $sms->Send("79213243303", var_dump($a));
}

include('connect.php');
include('phoxy/index.php');
