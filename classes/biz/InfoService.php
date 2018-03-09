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
	}
	
	public function __destruct()
	{
		$this->redis->close();
	}
	
	
	public function queryList()
	{
		$curl = new Curl;
		$curl->get(BN_BASE_URL.'/api/v1/ticker/24hr');
		if($curl->error){
			throw new Exception('Curl Error: '.__function__.' ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		$result = $curl->response;
		$curl->close();
		return  $result;
	}
	
	public function updateList()
	{
		$this->redis->delete(BOT_PREFIX.":LIST");
		$lists = $this->queryList();
		
		foreach($lists as $key=> $value){
			$this->redis->hset(BOT_PREFIX.":LIST",$value['symbol'],json_encode($value));
		}
		$this->redis->hSet(BOT_PREFIX.":BOT_INFO","list_updated",date('Y-m-d H:i:s'));
		return $lists;
	}
	
	public function getList()
	{
		return $this->redis->hKeys(BOT_PREFIX.":LIST");
	}

	public function queryExInfo()
	{
		$curl = new Curl;
		$curl->get(BN_BASE_URL."/api/v1/exchangeInfo");
		if($curl->error){
			throw new Exception('Curl Error: '.__function__.' ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		$result = $curl->response;
		$curl->close();
		return $result;
	}
	
	public function updateExInfo()
	{
		$this->redis->delete(BOT_PREFIX.":EXINFO");
		$assets = $this->queryExInfo();
		
		foreach($assets['symbols'] as $key => $value){
			$this->redis->hSet(BOT_PREFIX.":EXINFO",$value['symbol'],json_encode($value));
		}
		$this->redis->hSet(BOT_PREFIX.":BOT_INFO","exinfo_updated",date('Y-m-d H:i:s'));
	}
	
	public function getExInfo($symbol = "")
	{
		if(empty($symbol)){
			return $this->redis->hGetAll(BOT_PREFIX.":EXINFO");	
		}else{
			return $this->redis->hGet(BOT_PREFIX.":EXINFO",$symbol);
		}
	}
	
}