<?php

namespace App\Processes;

use App\Api\BinanceApi;

class AccountWebsocketClient implements ProcessInterface
{
    public function run()
    {
        $this->initAccountWebsocketClient();
    }

    protected function initAccountWebsocketClient()
    {
        $listenKey = (new BinanceApi())->getListenKey();

        \Ratchet\Client\connect(env('BINANCE_WS_BASE_URL') . '/ws/' . $listenKey)->then(function($conn) {
            // Handle incoming message.
            $conn->on('message', function($msg) use ($conn) {
                echo $msg . PHP_EOL;
            });
        }, function ($e) {
            echo "Could not connect: {$e->getMessage()}\n";
        });
    }
}
