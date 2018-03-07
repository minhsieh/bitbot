<?php

namespace biz;

use Curl\Curl;
use biz\InfoService;
use \Exception;
use \Redis;

class TradeService
{
	
	public function __construct()
	{
		$this->redis = new Redis;
		$this->redis->connect(REDIS_HOST, REDIS_PORT);
		//$this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
	}
	
	public function __destruct()
	{
		$this->redis->close();
	}
	
	public function getTimestamp($query = [])
	{
		$curl = new Curl;
		$curl->setOpt(CURLOPT_SSL_VERIFYPEER,false);
		$curl->setOpt(CURLOPT_SSL_VERIFYHOST,2);
		$curl->get(BN_BASE_URL."/api/v1/time");
		if($curl->error){
			throw new Exception('Curl Error: '.__function__.' ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		$result = $curl->response;
		$query['recvWindow'] = 5000;
		$query['timestamp'] = $result['serverTime'];
		$curl->close();
		unset($curl);
		return $query;
	}
	
	public function getSignature(array $query)
	{
		return hash_hmac('sha256' , urldecode(http_build_query($query)) , BN_SECKEY);
	}
	
	public function getCurl()
	{
		$curl = new Curl;
		$curl->setHeader('X-MBX-APIKEY',BN_PUBKEY);
		return $curl;
	}
	
	public function buy($symbol , $qty , $atr ,$price_len , $entry_type)
	{
		$input['symbol'] = $symbol;
		$input['side'] = "BUY";
		$input['type'] = "MARKET";
		$input['newOrderRespType'] = "FULL";
		$input['quantity'] = $qty;
		
		$result = $this->newOrder($input);
		
		print_r($result);
		
		//計算全部均價
		$count_up = 0;
		$count_qty = 0;
		$count_fee = 0;
		foreach($result['fills'] as $key => $value){
			$count_up += $value['price']*$value['qty'];
			$count_qty += $value['qty'];
			$count_fee += $value['commission'];
		}
		$price = number_format($count_up / $count_qty,$price_len);
		
		$record = [
			'symbol' => $result['symbol'],
			'price' => $price,
			'order_id' => $result['orderId'],
			'qty' => $result['executedQty'],
			'qty_fee' => $count_fee,
			'atr' => number_format($atr,$price_len),
			'sl' => number_format($price - 2 * $atr,$price_len),
			'time' => ceil($result['transactTime']/10000),
			'date' => date('Y-m-d H:i:s',ceil($result['transactTime']/10000)),
			'entry_type' => $entry_type,
			'profit' => 0,
		];
		
		$this->redis->hSet("BOT:TRADING",$symbol,json_encode($record));
		
		return ['result' => 'success' , 'detail' => $result];
	}
	
	public function sell($symbol)
	{
		$trading = $this->redis->hGet("BOT:TRADING" , $symbol);
		if(empty($trading)) throw new Exception('No holding trade to sell.');
		
		$trading = json_decode($trading,true);
		
		$info_s = new InfoService;
		$info = json_decode($info_s->getExInfo($trading['symbol']),true);
		
		$min_lots_len = 9 - strlen($info['filters'][1]['minQty']*100000000);
		$min_price_len = 9 - strlen($value['filters'][0]['minPrice']*100000000);
		
		//取得扣如手續費消耗後剩餘的數量 無條件捨去 100 - 0.1 = 99.9 取99做交易 剩餘0.9作為雜餘
		$qty =  floor_dec($trading['qty'] - $trading['qty_fee'],$min_lots_len);
		
		$input['symbol'] = $trading['symbol'];
		$input['side'] = "SELL";
		$input['type'] = "MARKET";
		$input['newOrderRespType'] = "FULL";
		$input['quantity'] = $qty;
		
		$result = $this->newOrder($input);
		
		//計算全部均價
		$count_up = 0;
		$count_qty = 0;
		$count_fee = 0;
		foreach($result['fills'] as $key => $value){
			$count_up += $value['price'] * $value['qty'];
			$count_qty += $value['qty'];
			$count_fee += $value['commission'];
		}
		$close_price = number_format($count_up / $count_qty,$min_price_len);
		
		
		
		//計算獲利            獲利                                成本                    手續費 
		$profit = (($close_price * $count_qty) - ($trading['price'] * $trading['qty']) ) - $count_fee;
		
		
		//紀錄成交訊息
		$trading['close_price'] = $close_price;
		$trading['close_fee'] = $count_fee;
		$trading['profit'] = $profit;
		$trading['close_time'] = ceil($result['transactTime']/10000);
		$trading['close_date'] = date('Y-m-d H:i:s' , ceil($result['transactTime']/10000));
		
		$this->redis->hdel('BOT:TRADING',$trading['symbol']);
		$this->redis->lPush("BOT:TRADED",json_encode($trading));
	}
	
	public function newOrder($query)
	{
		$curl = $this->getCurl();
		$query = $this->getTimestamp($query);
		$query['signature'] = $this->getSignature($query);
		
		print_r($query);
		$curl->post(BN_BASE_URL.'/api/v3/order',$query);
		if($curl->error){
			throw new Exception('Curl Error: '.__function__.' ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		$this->updateAccount();
		$result = $curl->response;
		$curl->close();
		unset($curl);
		return $result;
	}
	
	public function queryOrder($query)
	{
		$curl = $this->getCurl();
		$query = $this->getTimestamp($query);
		$query['signature'] = $this->getSignature($query);
		
		//print_r($query);
		$curl->get(BN_BASE_URL.'/api/v3/order',$query);
		if($curl->error){
			throw new Exception('Curl Error: '.__function__.' ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		$result = $curl->response;
		$curl->close();
		unset($curl);
		return $result;
	}
	
	public function cancelOrder($query)
	{
		$curl = $this->getCurl();
		$query = $this->getTimestamp($query);
		$query['signature'] = $this->getSignature($query);
		
		$curl->delete(BN_BASE_URL."/api/v3/order" , $query);
		if($curl->error){
			throw new Exception('Curl Error: ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		$result = $curl->response;
		$curl->close();
		unset($curl);
		return $result;
	}
	
	public function queryOpenOrders($query = [])
	{
		$curl = $this->getCurl();
		$query = $this->getTimestamp($query);
		$query['signature'] = $this->getSignature($query);
		
		print_r($query);
		
		$curl->get(BN_BASE_URL."/api/v3/openOrders" , $query);
		if($curl->error){
			throw new Exception('Curl Error: '.__function__.' ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		$result = $curl->response;
		$curl->close();
		unset($curl);
		return $result;
	}
	
	public function queryAllOrders($query = [])
	{
		$curl = $this->getCurl();
		$query = $this->getTimestamp($query);
		$query['signature'] = $this->getSignature($query);
		
		
		$curl->get(BN_BASE_URL."/api/v3/allOrders" , $query);
		if($curl->error){
			throw new Exception('Curl Error: '.__function__.' ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		$result = $curl->response;
		$curl->close();
		unset($curl);
		return $result;
	}
	
	public function queryAccount()
	{
		$curl = $this->getCurl();
		$curl->setOpt(CURLOPT_SSL_VERIFYPEER,false);
		$curl->setOpt(CURLOPT_SSL_VERIFYHOST,2);
		$query = $this->getTimestamp();
		$query['signature'] = $this->getSignature($query);
		
		$curl->get(BN_BASE_URL."/api/v3/account" , $query);
		if($curl->error){
			throw new Exception('Curl Error: '.__function__.' ' . $curl->errorCode . ': ' . $curl->errorMessage. "===" . json_encode($curl->response));
		}
		$result = $curl->response;
		$curl->close();
		unset($curl);
		return $result;
	}
	
	public function sellAll($query)
	{
		
	}
	
	public function updateAccount()
	{
		$result = $this->queryAccount();
		//print_r($result);
		
		foreach($result['balances'] as $one){
			if($one['asset'] == "BTC"){
				$this->redis->hSet("BOT:ACCOUNT","btc_free",$one['free']);
				$this->redis->hSet("BOT:ACCOUNT","btc_locked",$one['locked']);
			}
		}
	}
	
	public function initBalance($init_balance)
	{
		$this->redis->hSet("BOT:ACCOUNT","init_balance",$init_balance);
	}

}