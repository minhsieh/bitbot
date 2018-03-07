<?php
namespace biz;


use Curl\Curl;
use \Redis;
use \Exception;

class InfoService
{
	protected $symbol;
	
	public function __construct()
	{
		$this->redis = new Redis;
		$this->redis->connect(REDIS_HOST, REDIS_PORT);
		$this->symbol = "BTC";
	}
	
	public function __destruct()
	{
		$this->redis->close();
	}
	
	public function setSymbol($symbol)
	{
		$this->symbol = $symbol;
	}
	
	public function curlCoinList()
	{
		$curl = new Curl;
		$curl->get(BN_BASE_URL.'/api/v1/ticker/24hr');
		if($curl->error){
			throw new Exception('Curl Error: '.__function__.' ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		
		$result = $curl->response;
		
		//按照BTC成交量來進行排序
		$volume = array();
		foreach ($result as $key => $row)
		{
		    $volume[$key] = $row['quoteVolume'];
		}
		array_multisort($volume, SORT_DESC, $result);
		
		//過濾主購買貨幣
		$new_result = [];
		foreach($result as $one){
			if(substr($one['symbol'] , -3 , 3)  == $this->symbol){
				$new_result[] = $one;
			}
		}
		
		return  $new_result;
	}
	
	public function getCoinList()
	{
		return $this->redis->hKeys("list:".$this->symbol);
	}
	
	public function updateCoinList()
	{
		$this->redis->delete("list:".$this->symbol);
		$hots = $this->curlCoinList();
		
		foreach($hots as $key=> $value){
			$this->redis->hset("list:".$this->symbol,str_pad($key,3,'0',STR_PAD_LEFT).":".$value['symbol'],json_encode($value));
		}
	}
	

	
	public function queryExInfo()
	{
		$curl = new Curl;
		$curl->get(BN_BASE_URL."/api/v1/exchangeInfo");
		if($curl->error){
			throw new Exception('Curl Error: '.__function__.' ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		return $result = $curl->response;
	}
	
	public function updateExInfo()
	{
		$this->redis->delete("list:ex_info");
		$assets = $this->queryExchangeInfo();
		foreach($assets['symbols'] as $key => $value){
			$this->redis->hSet("list:ex_info",$value['symbol'],json_encode($value));
		}
	}
	
	public function getExInfo($symbol = "")
	{
		if(empty($symbol)){
			return $this->redis->hGetAll("list:ex_info");	
		}else{
			return $this->redis->hGet("list:ex_info",$symbol);
		}
	}
	
}