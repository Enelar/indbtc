<?php

class line extends api
{
  protected function LineTargets( $uid )
  {
    $row = db::Query("SELECT * FROM users.get_line_parents($1)", array($uid));
    $ret = array();
    foreach ($row as $t)
      array_push($ret, $t['get_line_parents']);
    return $ret;
  }

  protected function LineChildCounts( $uid )
  {
    $row = db::Query("SELECT * FROM users.get_line_count($1, 5)", array($uid));
    $ret = array();
    foreach ($row as $t)
      array_push($ret, $t['get_line_count']);
    return $ret;
  }
}
