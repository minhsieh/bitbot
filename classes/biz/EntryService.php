<?php

namespace biz;

class EntryService
{
	public function __construct()
	{
		
	}
	
	static public function checkRsiEntry($klines,$period = 14,$formula = " > 60")
	{
		$close = [];
		foreach($klines as $key => $value){
			$close[] = $value[4]*100000000;
		}
		$rsis =  trader_rsi($close , $period);
		
		return self::checkOperator($formula,end($rsis));
	}
	
	static public function checkKdi($klines , $period = 14,$formula = "")
	{
		$count = count($klines);
		
		$k = 50;
		$d = 50;
		$kdis = [];
		for($i = $period ; $i < $count ; $i ++){
			//取前14天最低和最高
			$high = $klines[$i][4];
			$low = $klines[$i][4];
			for($j=0; $j < $period; $j ++){
				$jv = $klines[$i-$j][4];
				if($jv > $high) $high = $jv;
				if($jv < $low) $low = $jv;
			}
			//計算RSV
			$rsv = (($klines[$i][4] - $low)/($high - $low)) * 100;
			$k = $k*(2/3) + $rsv*(1/3);
			$d = $d*(2/3) + $k*(1/3);
			
			$kdis[$i] = ['k' => $k , 'd' => $d];
		}
		
		$kdi = end($kdis);
		
		$kdi_e2 = $kdis[count($kdis) - 1];
		
		
		if(empty($formula)){
			if($kdi_e2['k'] < 20 && $kdi['k'] > 20 && $kdi_e2['d'] < 20 && $kdi['d'] >20){
				return true;
			}else{
				return false;
			}
		}else{
			//解析運算式
			$check_k = false;
			$check_d = false;
			$fors = explode("&",$formula);
			foreach($fors as $one){
				if(strpos($one,"k") !== false){
					$one = str_replace("k","",$one);
					$check_k = self::checkOperator($one, $kdi['k']);
				}
				
				if(strpos($one,"d") !== false){
					$one = str_replace("d","",$one);
					$check_d = self::checkOperator($one, $kdi['d']);
				}
			}
			
			if($check_k && $check_d){
				return true;
			}else{
				return false;
			}
		}
		
	}
	
	
	static public function checkOperator($formula,$value)
	{
		preg_match('/(\s*)(?<operator>\>\=?|\<\=?|\!\=?|\=\=|\<\>|\>\<|\!?~|REGEXP)(\s*)(?<value>[a-zA-Z0-9_\.]+)(\s*)/i', $formula, $match);
		if(empty($match)) return false;
		
		switch($match['operator']){
			case "<":
				return ($value < $match['value']);
				break;
				
			case "<=":
				return ($value <= $match['value']);
				break;
				
			case ">":
				return ($value > $match['value']);
				break;
				
			case ">=":
				return ($value >= $match['value']);
				break;
			
			case "!=":
				return ($value != $match['value']);
				break;
				
			case "==":
				return ($value == $match['value']);
				break;
				
			default :
				return false;
				break;
		}
		
	}
}