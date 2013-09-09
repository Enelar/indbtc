<?php

class annual extends api
{
  protected function Payed( $uid )
  {
    $uid = intval($uid);
    assert($uid > 0);

    $row = db::Query("SELECT (now() - reg_snap) < '1 month'::interval as free, (payed_tru > now()) as payed FROM users.logins WHERE id=$1", array(intval($uid)), true);

    assert(array_key_exists('payed', $row));
    assert(array_key_exists('free', $row));
    return $row['payed'] == 't' || $row['free'] == 't';
  }
}
