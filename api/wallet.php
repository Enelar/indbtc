<?php

class wallet extends api
{
  private static $rpc_url = "http://mp:XQedN1e7xG@127.0.0.1:8332/";
  private $rpc;
  public function wallet()
  {
    parent::api();
    $this->rpc = @$this->RPCBitcoin();
  }

  private function RPCBitcoin( )
  {
    if (!$this->rpc)
    {
      require_once 'jsonrpcphp/includes/jsonRPCClient.php';
      $this->rpc = new jsonRPCClient(self::$rpc_url);
    }
    return $this->rpc;
  }

  public function GetInputQuestWallet( $quest )
  {
    try
    {
      $this->rpc->getaccountaddress("quest_{$quest}");
      return $this->QuestWallets($quest)[0];
    } catch (Exception $e)
    {
      return false;
    }
    
  }

  public function QuestBalance( $quest )
  {
    try
    {
      $balance = $this->rpc->getbalance("quest_{$quest}");
      return (float)$balance;
    } catch (Exception $e)
    {
      return false;
    }
  }

  public function QuestWallets( $quest )
  {
    try
    {
      return $this->rpc->getaddressesbyaccount("quest_{$quest}");
    } catch (Exception $e)
    {
      return false;
    }
  }

  public function FinishQuest( $quest, $targets )
  {
    try
    {
      return $this->rpc->sendmany("quest_{$quest}", $targets);
    } catch (Exception $e)
    {
      return false;
    }      
  }

  public function Send( $account, $dest_wallet, $amount )
  {
    try
    {
      return $this->rpc->sendfrom($account, $toaddress, $btc);
    } catch (Exception $e)
    {
      return false;
    }
  }
  
  public function GetFirstSourceAddress( $quest )
  {
	$tx = $this->GetIncomingTxInfo($quest);
	if ($tx["confirmations"] > 5)
	  return $tx['address'];
	return false;
  }
  
  public function GetFirstSourceTxid( $quest )
  {
	$res = $this->GetIncomingTxInfo($quest);
	return $res['txid'];	  
  }
  
  public function GetIncomingTxInfo( $quest )
  {
	try
	{
	   $ret = $this->rpc->listtransactions("quest_{$quest}");
	   foreach ($ret as $tx)
	     if ($tx["category"] == "receive")
	       return $tx;
	} catch (Exception $e)
	{
		return false;
	}
  }
  
  public function GetTxCount( $quest )
  {
	try
	{
	   $ret = $this->rpc->listtransactions("quest_{$quest}");
	   return count($ret);
	} catch (Exception $e)
	{
		return false;
	}	  
  }

  public function Move()
  {
    try
    {
      $arr = $this->rpc->listaddressgroupings();
      foreach ($arr as $ar)
      {
        foreach ($ar as $a)
          echo "bitcoind setaccount {$a[0]}\n";
      }
      exit();
    } catch (Exception $e)
    {
      return false;
    }  
  }

  protected function Reserve()
  {
    return $this->Move();
    //$this->rpc->getaccount('1H1TfZJNgQYpor741BuTGJMvpACLWhx16E');
  }
}
