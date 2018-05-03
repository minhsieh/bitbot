<?php

ini_set('display_errors','1');
error_reporting(E_ALL);

require_once 'autoload.php';
require_once __DIR__.'/phpdaemon.php';

use biz\BotService;
use util\Console;

Console::log("[BITBOT NOW START!!!]",'light_blue');

function msg($msg) {
	echo "[".date('Y-m-d H:i:s')."] : ".$msg."\n";
}

set_error_handler("myErrorHandler");

function myErrorHandler($errno, $errstr, $errfile, $errline){
	if (!(error_reporting() & $errno)) {
        return false;
    }
    Console::log("[$errno]$errstr #$errfile [$errline]","light_red");
}


function handler($pno) {
	
	$bot = new BotService;
	//$bot->set_debug();
	
	for (;;) {
		/**
		 * Worker Deposit
		 * 
		 * 充值工人
		 * 
		 */
		if($pno == 1){
			try{
				$bot->loopEntry();
			}catch(Exception $ex){
				Console::log($ex,"red");
			}
			sleep(30);
		}
		
		//出場者
		elseif($pno == 2){
			try{
				$bot->loopTrading();
			}catch(Exception $ex){
				Console::log($ex,"red");
			}
			sleep(5);
		}
		
		elseif($pno == 3){
			try{
				$bot->loopUpdate();
			}catch(Exception $ex){
				Console::log($ex,"red");
			}
			sleep(30*60);
		}
	}
}

$obj = new PHPDaemon();
$obj->setProcessNum(3);
$obj->setHandler("handler");
$obj->run();


