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
	
	public function __construct($symbol)
	{
		$this->redis = new Redis;
		$this->redis->connect(REDIS_HOST, REDIS_PORT);
		
		$data_list = $this->redis->hGet(BOT_PREFIX.":LIST" , $symbol );
		$data_info = $this->redis->hGet(BOT_PREFIX.":EXINFO" , $symbol );
		
		if($data_list == false){
			throw new Exception("Not find symbol: $symbol in ".BOT_PREFIX.":LIST");
		}elseif($data_list == false){
			throw new Exception("Not find symbol: $symbol in ".BOT_PREFIX.":LIST");
		}
		
		$data = array_merge( json_decode( $data_list , true) , json_decode( $data_info , true ));
		parent::__construct($data);
	}
	
	public function __destruct()
	{
		$this->redis->close();
	}
	
	public function getKline($interval = "15m",$limit = 100)
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
		$this->data['bid'] = $result['bids'][0][0];
		$this->data['ask'] = $result['asks'][0][0];
		return ['bid' => $result['bids'][0][0] , 'ask' => $result['asks'][0][0] ];
	}
	
	public function getPriceLen()
	{
		return ( 9 - strlen($this->data['filters'][0]['minPrice']*100000000));
	}
	
	public function getLotLen()
	{
		return ( 9 - strlen($this->data['filters'][1]['minQty']*100000000));
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

	public function fee_qty($qty,$fee_p = 0.001)
	{
		$fee_qty = $qty * $fee_p;
		return $fee_qty = ceil_dec($fee_qty , $this->getLotLen());
	}
	
	public function trade_qty($qty,$fee_p = 0.001)
	{
		$fee_qty = $this->fee_qty($qty,$fee_p);
		return $fee_qty + $qty;
	}
	
	public function be_price($qty , $open_price)
	{
		$trade_qty = $this->trade_qty($qty);
		
		$close_price = ($trade_qty * $open_price) / ( ( 1 - 0.001 ) * $qty );
		
		return number_format($close_price,8);
	}
	
	public function stop_price($qty , $open_price , $sl)
	{
		
	}
	
	public function waste()
	{
		
	}
	
	public function isTrading()
	{
		return $this->redis->hExists("BOT:TRADING",$this->data['symbol']);
	}
	
	public function canTrade()
	{
		if($this->data['filters'][2]['minNotional'] > 0.001){
			return false;
		}
		if($this->data['status'] != "TRADING"){
			return false;
		}
		return true;
	}

	
}