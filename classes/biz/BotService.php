<?php 
namespace biz;

use model\Coin;
use biz\InfoService;
use biz\TradeService;
use biz\EntryService;

use \Exception;
use \Redis;


class BotService
{
	protected $debug = false;
	
	public function __construct()
	{
		$this->redis = new Redis();
		$this->redis->connect('127.0.0.1', 6379);
		$this->info = new InfoService;
		$this->trade = new TradeService;
	}
	
	public function set_debug()
	{
		$this->debug = true;
	}
	
	public function __destruct()
	{
		$this->redis->close();
		unset($this->info);
		unset($this->trade);
	}
	
	public function loopEntry()
	{
		$info = $this->info;
		$trade_s = $this->trade;
		
		$coins = $info->getExInfo();
		//$trade_s->updateAccount();
		
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
			
			$coin = Coin::find($symbol);
			
			if($coin->isTrading()){
				//echo "[$symbol] \t TRADING NOW!!!".PHP_EOL;
				continue;
			}
			
			$min_price_len = 9 - strlen($value['filters'][0]['minPrice']*100000000);
			$min_lots_len = 9 - strlen($value['filters'][1]['minQty']*100000000);
			
			
			$klines = $coin->getKline("15m",100);
			$atr = $coin->getAtr();
			
			$atr = ceil_dec(($atr/100000000) , $min_price_len);
			
			$balance = 0.00005 * 100000000;
			$qty = ceil_dec($balance/(2*100000000*$atr),$min_lots_len);
			
			$last_kline = end($klines);
			
			$buy_prices = $last_kline[4];//Kline Close price;
			
			//$qty*$last_kline[4];
			$min_qty = ceil_dec($value['filters'][2]['minNotional']/$last_kline[4],$min_lots_len);
			
			$cost = $qty*$last_kline[4];
			
			$stop_price = number_format(ceil_dec($buy_prices - 2*$atr ,$min_price_len),$min_price_len);
			
			//計算停損損失%
			$loss = (($buy_prices - $stop_price)/$buy_prices) * 100;
			
			//計算手續費耗損
			$fee = $qty * 0.001;
			$fee_qty = ceil_dec($fee,$min_lots_len);
			$fee_loss_per = 100 - ((  ( $qty-$fee_qty )/ $qty )*100);
			
			//把手續費耗損的數量加上
			$qty = $qty + $fee_qty;
			
			
			if($min_qty < $qty && ($qty * $stop_price ) > $value['filters'][2]['minNotional'] && $fee_loss_per < 0.5 && $loss > 1.5){
				$status = "***Trade***";
				if($this->debug){
					echo "[$symbol]\ttradeqty: $qty\t\tminqty: $min_qty\tloss: $fee_loss_per\tporfit:-$loss\ttrade_cost: $cost\tbuy: $buy_prices\tstop: $stop_price".PHP_EOL;	
				}
				
				//
			}else{
				continue;
			}
			
			$free_bal = $this->redis->hGet("BOT:ACCOUNT","btc_free");
			
			
			if(EntryService::checkRsiEntry($coin->getKline() , 14 , '< 20')){
				if($free_bal - $cost < 0){
					echo "[$symbol] \t Not Enough Balance!!!".PHP_EOL;
					continue;
				}
				if(!$this->debug){
					echo "[$symbol]\ttradeqty: $qty\t\tminqty: $min_qty\tloss: $fee_loss_per\tporfit:-$loss\ttrade_cost: $cost\tbuy: $buy_prices\tstop: $stop_price".PHP_EOL;
					$trade_s->buy($coin->symbol ,$qty,$atr,$min_price_len,"RSI");
				}
				echo "[$symbol] \t RSI ENTRY SIGNAL!!!".PHP_EOL;
				continue;
			}
			
			if(EntryService::checkKdi($coin->getKline() , 14 )){
				if($free_bal - $cost < 0){
					echo "[$symbol] \t Not Enough Balance!!!".PHP_EOL;
					continue;
				}
				if(!$this->debug){
					echo "[$symbol]\ttradeqty: $qty\t\tminqty: $min_qty\tloss: $fee_loss_per\tporfit:-$loss\ttrade_cost: $cost\tbuy: $buy_prices\tstop: $stop_price".PHP_EOL;
					$trade_s->buy($coin->symbol ,$qty,$atr,$min_price_len,"KDI");
				}
				echo "[$symbol] \t KDI ENTRY SIGNAL!!!".PHP_EOL;
				continue;
			}

		}
	}
	
	public function loopTrading()
	{
		$info = new InfoService;
		$trade_s = new TradeService;
		
		$tradings = $this->redis->hGetAll("BOT:TRADING");
		
		foreach($tradings as $key => $value){
			$value = json_decode($value , true);
			$coin = Coin::find($value['symbol']);
			
			$askbid = $coin->getAskBid();
			$bid = $askbid['bid'];

			$old_sl = $value['sl'];
			//檢查是否移動止損 (ATR)
			if($bid  >= ($value['sl'] + 2 * (2 * $value['atr']) ) ){
				$value['sl'] = $value['sl'] + (2 * $value['atr']);
				echo "[".$value['symbol']."] \t Move Tail SL from $old_sl ---> ".$value['sl']." !!!".PHP_EOL;
			}
			
			//檢查是否止損
			if($bid <= $value['sl']){
				$tran = $trade_s->sell($value['symbol']);
				echo "[".$value['symbol']."] \t HIT STOP LOSS!!!".PHP_EOL;
			}else{
				//更新profit
				$value['profit'] = number_format($bid - $value['price'],8);
				$this->redis->hSet("BOT:TRADING" , $value['symbol'] , json_encode($value));
			}
		}
	}
}