<?php

class qr extends api
{
  public function Draw( $str )
  {
    error_reporting(0);
    @ini_set('display_errors', 0);
    require_once "phpqrcode/qrlib.php";
    header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + 3600*4));
    header("Cache-Control: max-age=86400, public");
    header("Etag: qr_image_".md5($str));
    header_remove("Pragma");
    QRCode::png($str, false, QR_ECLEVEL_L, $size=3, 2);
    exit();
  }

  protected function Bill( $wallet, $amount )
  {
    if (strlen($wallet) < 30)
      return array("error" => "Corrupted wallet address");
    $amount = (float)$amount;
    if (!is_float($amount))
      return array("error" => "Cannot parce amount");
    $this->Draw("bitcoin:{$wallet}?amount={$amount}");
  }
}
