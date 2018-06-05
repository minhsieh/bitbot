<?php


require_once "autoload.php";

use CommandLine\CommandLine;
use model\Coin;
$args = CommandLine::parseArgs($_SERVER['argv']);

$function = $args["m"];

if(isset($args["p"])){
	$function($args["p"]);
}else{
	$function();
}

function test_info()
{
	$info = new biz\InfoService();
	$info->updateList();
	$info->updateExInfo();
}

function test_new_coin()
{
	$coin = new Coin("ONTBTC");
	$klines = $coin->getKline("15m",100);
	$atr = $coin->getAtr();
	print_r($coin);
}

function test_entry()
{
	$bot = new biz\BotService;
	$bot->set_debug();
	$bot->loopEntry();
}

function test_account()
{
	$trade_s = new biz\TradeService;
	print_r($trade_s->queryAccount());
}


function sell_all()
{
	$trade_s = new biz\BotService;
	$trade_s->closeAll();
}

function cal_tread()
{
	$info = new biz\InfoService;
	$lists = $info->queryList();
	print_r($lists);
}