<?php


require_once "autoload.php";



use model\Coin;

use biz\InfoService;
use biz\TradeService;
use biz\EntryService;




$redis = new Redis;
$redis->connect('127.0.0.1', 6379);

$tradings = $redis->hGetAll("BOT:TRADING");

foreach($tradings as $key => $value){
	$value = json_decode($value,true);
	$redis->set("BOT:TRADING:".$value['symbol'] , json_encode($value));
	echo $value['symbol']." move".PHP_EOL;
}


exit;



$info = new InfoService;
$trade_s = new TradeService;


$info->updateCoinList();
// $result = $info->getExInfo();


// //$result = $info->updateExchangeInfo();

// print_r($result);

// foreach($result as $key => $value){
// 	$value = json_decode($value,true);
// 	if($value['filters'][2]['minNotional'] <= 0.001){
// 		echo $key." good to trade ".$value['filters'][2]['minNotional'].PHP_EOL;
// 	}
// }

//
$coin = Coin::find("TRIGBTC");


while(true){
	$askbid = $coin->getAskBid();
	print_r($askbid);
}



exit;


$coins = $info->getExInfo();

foreach($coins as $key => $value){
	$value = json_decode($value,true);
	$symbol = $value['symbol'];
	if(substr($symbol , -3,3) != "BTC"){
		continue;
	}
	if($value['filters'][2]['minNotional'] > 0.001){
		continue;
	}
	if($value['status'] != "TRADING"){
		continue;
	}
	
	$min_price_len = 9 - strlen($value['filters'][0]['minPrice']*100000000);
	$min_lots_len = 9 - strlen($value['filters'][1]['minQty']*100000000);
	
	$coin = Coin::find($symbol);
	$klines = $coin->getKline("15m",100);
	$atr = $coin->getAtr();
	
	$atr = ceil_dec(($atr/100000000) , $min_price_len);
	
	$balance = 0.0002 * 10000000;
	$qty = ceil_dec($balance/(2*100000000*$atr),$min_lots_len);
	
	$last_kline = end($klines);
	
	$buy_prices = $last_kline[4];//Kline Close price;
	
	//$qty*$last_kline[4];
	$min_qty = ceil_dec($value['filters'][2]['minNotional']/$last_kline[4],$min_lots_len);
	
	$cost = $qty*$last_kline[4];
	
	$stop_price = number_format(ceil_dec($buy_prices - $atr ,$min_price_len),$min_price_len);
	
	if($min_qty < $qty && ($qty * $stop_price ) > $value['filters'][2]['minNotional']){
		$status = "***Trade***";
		echo "[$symbol] \ttrade qty: $qty\tmin-qty: $min_qty\taction: $status\ttrade_cost: $cost\tstop_price: $stop_price".PHP_EOL;
	}else{
		continue;
	}
	
	
	if(EntryService::checkRsiEntry($coin->getKline() , 14 , '< 20')){
		echo "[$symbol] \t RSI ENTRY SIGNAL!!!".PHP_EOL;
		//if($coin->isTrading() == true) continue;
		//$trade_s->buy($coin->symbol ,$qty,$atr,$min_price_len,"RSI");
	}
	
	if(EntryService::checkKdi($coin->getKline() , 14 )){
		echo "[$symbol] \t KDI ENTRY SIGNAL!!!".PHP_EOL;
		//if($coin->isTrading() == true) continue;
		//$trade_s->buy($coin->symbol ,$qty,$atr,$min_price_len,"KDI");
	}
	
	
	
	
}
exit;



$coin = Coin::find("ZILBTC");
$klines = $coin->getKline("15m",100);
$atrs = $coin->getAtr();
print_r($klines);
print_r($atrs);

$balance = 0.0001 * 10000000;
$qty = $balance/end($atrs);

echo "qty".$qty.PHP_EOL;

exit;

$trade_s = new TradeService;

//$ex_info = $coin->getExInfo();
//print_r($ex_info);

// $input['symbol'] = $coin->symbol;
// $input['side'] = "SELL";
// $input['type'] = "LIMIT";
// $input['timeInForce'] = "GTC";

// $input['price'] = number_format(0.00000480,8);

// $qut = $ex_info['filters'][2]['minNotional']/ $input['price'];
// $input['quantity'] = ceil($qut);
// echo "qut:".ceil($qut).PHP_EOL;



// $result = $trade_s->newOrder($input);


