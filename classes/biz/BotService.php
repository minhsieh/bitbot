<?php 
namespace biz;

use model\Coin;
use model\Trade;
use biz\InfoService;
use biz\TradeService;
use biz\EntryService;
use util\Console;

use \Exception;
use \Redis;


class BotService
{
	protected $debug = false;
	protected $atr_multi = 4;
	
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
		
		$watching_count = 0;
		
		foreach($coins as $key => $value){
			$coin = new Coin($key);
			
			$symbol = $coin->symbol;
			
			$skip_symbol = ['ONTBTC'];
			
			if(in_array($symbol, $skip_symbol)){
				continue;
			}
			
			if(!$coin->canTrade()){
				continue;
			}
			
			if(substr($symbol , -3,3) != "BTC"){
				continue;
			}
			
			if($coin->isTrading()){
				continue;
			}
			
			$trade = new Trade();
			
			//先用ATR和可"可承受風險金額"來計算要想要的總數 qty
			
			$klines = $coin->getKline("15m",100);
			if(count($klines) < 14){
				continue;
			}
			
			$atr = $coin->getAtr();
			
			$atr = ceil_dec(($atr/100000000) , $coin->getPriceLen() );
			
			if($atr == 0){
				 continue;
			}
			
			$trade->setCoin($coin);
			
			//0.00008
			$balance = 0.0001 * 100000000;
			$qty = ceil_dec($balance/($this->atr_multi*100000000*$atr),$coin->getLotLen() );
			
			$trade->qty = $qty;
			$trade->buy_price = number_format(end($klines)[4],8);
			$trade->fee_p = 0.001;
			$trade->atr = number_format($atr,8);
			$trade->atr_multi = $this->atr_multi;
			
			$trade->calculate();
			
			$minNational = $coin->getData()['filters'][2]['minNotional'];
			
			//檢查最小需交易量
			$min_qty = ceil_dec( $minNational / $trade->buy_price ,$coin->getLotLen());
			if($min_qty > $trade->qty){
				continue;
			}

			$free_bal = $this->redis->hGet("BOT:ACCOUNT","btc_free");

			if(($trade->qty * $trade->stop_price ) <= $minNational){
				continue;
			}
			
			if($trade->stop_price >= $trade->buy_price){
				continue;
			}
			
			//浪費損益比例
			if($trade->waste_loss_p > 15){
				continue;
			}
			
			
			if($this->debug){
				echo $trade->toCli().PHP_EOL;
			}
			
			$watching_count ++;
			
			
			if($free_bal - $trade->cost <= 0){
				continue;
			}
			
			if(EntryService::checkRsiEntry($coin->getKline() , 14 , '< 20')){
				$trade->entry_type = "RSI";
				echo $trade->toCli().PHP_EOL;
				echo Console::log("[$symbol] \t RSI ENTRY SIGNAL!!!","light_cyan");
				if(!$this->debug){
					$trade = $trade_s->buy($trade);
				}
				continue;
			}
			
			if(EntryService::checkStoch($coin->getKline() , 14 )){
				$trade->entry_type = "STOCH";
				echo $trade->toCli().PHP_EOL;
				echo Console::log("[$symbol] \t STOCH ENTRY SIGNAL!!!","light_cyan");
				if(!$this->debug){
					$trade = $trade_s->buy($trade);
				}
				continue;
			}
			
			if(EntryService::checkStochRsi($coin->getKline() , 14 )){
				$trade->entry_type = "STOCH_RSI";
				echo $trade->toCli().PHP_EOL;
				echo Console::log("[$symbol] \t STOCH_RSI ENTRY SIGNAL!!!","light_cyan");
				if(!$this->debug){
					$trade = $trade_s->buy($trade);
				}
				continue;
			}

		}
		
		$this->redis->hSet(BOT_PREFIX.":BOT_INFO","watching_count",$watching_count);
		$this->redis->hSet(BOT_PREFIX.":BOT_INFO","watching_updated",date('Y-m-d H:i:s'));
	}
	
	public function loopTrading()
	{
		$info = $this->info;
		$trade_s = $this->trade;
		
		$tradings = $this->redis->hGetAll("BOT:TRADING");
		
		foreach($tradings as $key => $value){
			//$value = json_decode($value , true);
			$trade = new Trade(json_decode($value , true));
			
			$coin = new Coin($key);
			
			$askbid = $coin->getAskBid();
			$trade->bid = $askbid['bid'];
			$trade->ask = $askbid['ask'];
			
			$trade->profit_btc = $trade->getProfitBtc($trade->bid);

			//檢查是否移動止損 (ATR)
			if( !empty( $trade->atr_multi ) && !empty($trade->atr) && !empty($trade->sl ) ){
				if($trade->bid  >= ($trade->sl + 2 * ($trade->atr_multi * $trade->atr))){
					$old_sl = $trade->sl;
					$trade->sl = number_format($trade->sl + ( $trade->atr_multi * $trade->atr ), $trade->price_len);
					echo Console::log("[".$trade->symbol."]\tMove Tail SL from $old_sl ---> ".$trade->sl." !!!","light_green");
				}
			}
			
			//檢查是否止損
			if($trade->bid <= $trade->sl){
				if(!$this->debug){
					$trade_s->sell($trade);	
				}
				echo Console::log("[".$trade->symbol."]\tHIT STOP LOSS !!! sl: ".$trade->sl." profit: ".$trade->profit_btc,"light_red");
				continue;
			}
			
			
			$this->redis->hSet("BOT:TRADING" , $trade->symbol , json_encode($trade));
		}
	}
	
	public function loopUpdate()
	{
		$info = $this->info;
		$trade_s = $this->trade;
		
		$trade_s->updateAccount();
		
		$info->updateList();
		$info->updateExInfo();
	}
}