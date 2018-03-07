<?php

ini_set('display_errors','1');
error_reporting(E_ALL);

require_once 'autoload.php';
require_once __DIR__.'/phpdaemon.php';

use biz\BotService;


function msg($msg) {
	echo "[".date('Y-m-d H:i:s')."] : ".$msg."\n";
}

set_error_handler("myErrorHandler");
function myErrorHandler($errno, $errstr, $errfile, $errline){
	if (!(error_reporting() & $errno)) {
        return false;
    }
    
    msg("[$errno]$errstr #$errfile [$errline]");
}


function handler($pno) {
	msg("[BITBOT NO#$pno NOW WORKING!!!]");
	$bot = new BotService;
	
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
				msg($ex);
			}
			sleep(30);
			
		}
		
		//出場者
		elseif($pno == 2){
			try{
				$bot->loopTrading();
			}catch(Exception $ex){
				msg($ex);
			}
			sleep(5);
		}
	}
}

$obj = new PHPDaemon();
$obj->setProcessNum(2);
$obj->setHandler("handler");
$obj->run();


