<?php
namespace model;

use \Exception;

class Trade extends ModelBase
{
	protected $require_fields = [
		'symbol',
		'price_len',
		'lot_len',
		'qty',
		'fee_p',
		'buy_price',
	];
	
	public function __construct($data = [])
	{
		parent::__construct($data);
	}
	
	public function setCoin($coin)
	{
		$this->data['symbol'] = $coin->symbol;
		$this->data['price_len'] = $coin->getPriceLen();
		$this->data['lot_len'] = $coin->getLotLen();
	}
	
	public function check()
	{
		$msg = "";
		foreach($this->require_fields as $one){
			if(!isset($this->data[$one])){
				$msg .= "$one is required. "; 
			}
		}
		if(!empty($msg)) throw new Exception($msg);
	}
	
	public function calculate()
	{
		$this->check();
		$this->data['cost'] = $this->data['qty'] * $this->data['buy_price'];
		$this->data['fee_qty'] = ceil_dec($this->data['qty'] * $this->data['fee_p'] , $this->data['lot_len']);
		$this->data['trade_qty'] = $this->data['fee_qty'] + $this->data['qty'];
		$this->data['be_price'] = number_format( ($this->data['trade_qty'] * $this->data['buy_price']) / ( ( 1 - $this->data['fee_p']) * $this->data['qty'] ) , 8);
		$this->data['waste_p'] = round(( $this->data['be_price'] - $this->data['buy_price'] ) / $this->data['buy_price'] * 100,3);
		
		if(!empty($this->data['atr']) && !empty($this->data['atr_multi'])){
			$this->data['stop_price'] = number_format(ceil_dec($this->data['be_price'] - $this->data['atr_multi'] * $this->data['atr'] ,$this->data['price_len']),8);
			$this->data['loss_p'] = round( ($this->data['buy_price'] - $this->data['stop_price']) / $this->data['buy_price'] * 100,3);
			if($this->data['waste_p'] != 0){
				$this->data['waste_loss_p'] = round($this->data['waste_p'] / $this->data['loss_p'] * 100 , 3);
			}else{
				$this->data['waste_loss_p'] = 0;
			}
			
		}
		
		return $this->data;
	}
	
	public function toCli()
	{
		$msg = "[".$this->data['symbol']."]\tqty:".$this->data['qty']."\tbuy:".$this->data['buy_price']."\tstop:".$this->data['stop_price']."\tbe:".$this->data['be_price']."\tw/l:".$this->data['waste_loss_p']."\t";
		return $msg;
	}
	
	public function getProfitBtc($bid)
	{
		return $profie = number_format((($bid * $this->data['qty'])) - ($this->data['buy_price'] * $this->data['trade_qty']) - (($bid*$this->data['qty']) * $this->data['fee_p']),8);
	}
	
	
}