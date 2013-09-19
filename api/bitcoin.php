<?php

include_once('lib/curl.php');

class bitcoin extends api
{ 
  protected function GenCallback( $qid )
  {
    $secret = $this->GetSecret($qid);
    $callback = phoxy_conf()['site']."api/bitcoin/blockchain_callback?bill={$qid}&secret={$secret}";
	return array("data" => $callback);
  }
  
  public function ProtectWithCallback( $wallet, $qid )
  {
    $callback = $this->GenCallback($qid)['data'];
    $url = "https://blockchain.info/api/receive?method=create&shared=true&address={$wallet}&callback="
      . urlencode($callback);

    $res = http_request($url);

    if ($res == "Error Invalid Destination Bitcoin Address")
      return false;
    if (strpos($res, "Error") !== false)
      return false;
    $obj = json_decode($res, true);
    $input = $obj['input_address'];
    return $input;	  
  }
  
  public function ProtectWithoutCallback( $wallet )
  {
    $url = "https://blockchain.info/api/receive?method=create&shared=true&address={$wallet}";
    $res = http_request($url);
    if ($res == "Error Invalid Destination Bitcoin Address")
      return false;
    if (strpos($res, "Error") !== false)
    {
      echo json_encode(array("error" => "Blockchain returns: $res", "data" => debug_backtrace()));
      exit();
      return false;
    }
    $obj = json_decode($res, true);
    $input = $obj['input_address'];
    return $input;
  }
  
  public function SystemHide( $wallet, $bill_id )
  {
    $input = $this->ProtectWithoutCallback($wallet);
    db::Query("UPDATE finances.sys_bills SET wallet=$2 WHERE id=$1", array($bill_id, $input));
    return $input;
  }
 
  public function CreateWithoutCallback( $wallet, $amount, $bill_id )
  {
    $input = $wallet;
    $input = $this->ProtectWithoutCallback($wallet);
    if (!$input)
      return false;
    $row = db::Query("UPDATE finances.sys_bills SET wallet=$2 WHERE id=$1 RETURNING id", array($bill_id, $input));
    return $input;
  }
  
  public function CreateBill( $wallet, $amount, $bill_id )
  { // should be shared because double spends!!
    $qid = 
      db::Query("SELECT quest FROM finances.sys_bills WHERE id=$1",
      array($bill_id), true)['quest'];
    $input = $this->ProtectWithCallback($wallet, $qid);
    db::Query("UPDATE finances.sys_bills SET wallet=$2 WHERE id=$1", array($bill_id, $input));
    return $input;
  }

  private function GetSecret( $qid )
  {
    $row = db::Query("SELECT id, ip, snap FROM finances.quests WHERE id=$1", array($qid), true);
    return md5(serialize($row));
  }

  private function CheckBlockchainAuthority( $bill, $secret, $wallet )
  {
    if ($secret != $this->GetSecret($bill))
      return false;
    $row = db::Query("SELECT wallet FROM finances.bills WHERE id=$1", array($bill), true);
    if ($row['wallet'] != $wallet)
      return false;
    return true;
  }

  protected function blockchain_callback( )
  {
    global $_GET;
    echo "*ok*"; exit();    
    //var_dump($_GET);
    //debug_print_backtrace();
    $bill_id = $_GET['bill'];
    $secret = $_GET['secret'];

    $id = $_GET['invoice_id'];
    $hash = $_GET['transaction_hash'];
    $input_hash = $_GET['input_transaction_hash'];
    $input_address = $_GET['input_address'];
    $satochi = $_GET['value'];

    if (!$this->CheckBlockchainAuthority($bill_id, $secret, $input_address))
      return;    

    if (isset($_GET['test']))
      return;
    $this->BlockchainCallback($id, $hash, $input_address, $satochi);
  }

  protected function BlockchainCallback( $invoice_id, $transaction_hash, $input_addr, $value_in_satochi )
  {
	  /*
    $amount = $value_in_satochi / 100000000;
    $ret = db::Query(
      "UPDATE finances.sys_bills SET payed = payed + $2 WHERE id = $1 RETURNING id", array($invoice_id, $amount), true);
    $finances = IncludeModule("api", "finances");
    $finances->CloseBill($ret['id']);
    */
    db::Query(
      "INSERT INTO finances.accounts(uid, wallet) VALUES ((SELECT uid FROM finances.quests WHERE id=$1), $2)",
      array($invoice_id, $input_addr));
  }
  
  public function GetSourceByTransaction( $txid )
  {
    if (!strlen($txid))
      return false;
    $res = http_request("https://blockchain.info/tx/{$txid}?format=json");
    if ($res == false)
      return $this->ReserveGetSourceByTransaction($txid);
    $obj = json_decode($res, true);
    return $obj["inputs"][0]["prev_out"]["addr"];
  }
  
  public function ReserveGetSourceByTransaction( $txid )
  {
    if (!strlen($txid))
      return false;  
    $res = http_request("http://blockexplorer.com/tx/{$txid}");
    list($head, $nice) = explode('Inputs', $res);
    list($gar, $prefetch) = explode('a href="/address/', $nice);
    list($address, $del) = explode('"', $prefetch);
    return $address;
  }
}
