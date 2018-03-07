<?php

namespace model;

use model\ModelBase;
use Curl\Curl;
use \Redis;
use \Exception;

class Coin extends ModelBase
{
	protected $redis;
	protected $klines;
	protected $atrs;
	
	public function __construct($data = [])
	{
		$this->redis = new Redis;
		$this->redis->connect(REDIS_HOST, REDIS_PORT);
		parent::__construct($data);
	}
	
	public function __destruct()
	{
		$this->redis->close();
	}
	
	static public function find($symbol, $list = "BTC")
	{
		$redis = new Redis;
		$redis->connect(REDIS_HOST, REDIS_PORT);
		$it = NULL;
		$redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
		while($arr_keys = $redis->hScan('list:'.$list, $it)) {
		    foreach($arr_keys as $str_field => $str_value) {
		    	if(strpos($str_field,$symbol) != false){
		    		$redis->close();
		    		return new Coin(json_decode($str_value,true));
		    	}
		    }
		}
		$redis->close();
		throw new Exception('not find symbol');
	}
	
	public function getKline($interval = "1m",$limit = 100)
	{
		if(empty($this->data['symbol'])) throw new Exception('no symbol assigned.');
		if(!empty($this->klines)) return $this->klines;
		
		$curl = new Curl;
		$curl->setOpt(CURLOPT_SSL_VERIFYPEER,false);
		$curl->setOpt(CURLOPT_SSL_VERIFYHOST,2);
		$curl->setTimeout(5);
		$curl->get(BN_BASE_URL.'/api/v1/klines',['symbol'=> $this->data['symbol'] , 'interval' => $interval , 'limit' => $limit]);
		if($curl->error){
			throw new Exception('Curl Error: '.__function__.' ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		$this->klines = $curl->response;
		$result = $curl->response;
		$curl->close();
		unset($curl);
		return $result;
	}
	
	public function getAskBid()
	{
		$curl = new Curl;
		$curl->setOpt(CURLOPT_SSL_VERIFYPEER,false);
		$curl->setOpt(CURLOPT_SSL_VERIFYHOST,2);
		$curl->setTimeout(5);
		$curl->get(BN_BASE_URL.'/api/v1/depth',['symbol' => $this->data['symbol'] ,'limit' => '5' ]);
		if($curl->error){
			throw new Exception('Curl Error: '.__function__.' ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		$result = $curl->response;
		$curl->close();
		unset($curl);
		return ['bid' => $result['bids'][0][0] , 'ask' => $result['asks'][0][0] ];
	}
	
	public function getExInfo()
	{
		return json_decode($this->redis->hget('list:ex_info',$this->data['symbol']),true);
	}
	
	public function getAtrs($period = 14)
	{
		if(empty($this->klines)) $this->getKline();
		$high = [];
		$low = [];
		$close = [];
		foreach($this->klines as $key => $value){
			$high[] = $value[2]*100000000;
			$low[] = $value[3]*100000000;
			$close[] = $value[4]*100000000;
		}
		$atrs =  trader_atr($high, $low, $close , $period);
		return $this->atrs = $atrs;
	}
	
	public function getAtr($period = 14)
	{
		if(empty($this->atrs)) $this->getAtrs($period);
		return end($this->atrs);
	}
	
	public function isTrading()
	{
		return $this->redis->hExists("BOT:TRADING",$this->data['symbol']);
	}

	
}