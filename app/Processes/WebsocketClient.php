<?php

namespace App\Processes;

use App\Api\BinanceApi;
use App\Entities\Price;

class WebsocketClient implements ProcessInterface
{
    protected $websocketFlag = true;

    public function run()
    {
        $this->initWebsocketClient();
    }

    protected function initWebsocketClient()
    {
        \Ratchet\Client\connect(env('BINANCE_WS_BASE_URL') . '/ws/btcusdt')->then(function($conn) {
            // Handle incoming message.
            $conn->on('message', function($msg) use ($conn) {
                $this->dispatchWebsocketMessage($msg);
            });

            // Subscribe streams.
            $subscriptPayload = [
                'method' => 'SUBSCRIBE',
                'params' => [
                    "btcusdt@markPrice@1s",
                    "btcusdt@aggTrade",
                ],
                'id' => 2
            ];

            $conn->send(json_encode($subscriptPayload));
        }, function ($e) {
            echo "Could not connect: {$e->getMessage()}\n";
        });
    }


    protected function dispatchWebsocketMessage($msg)
    {
        $message = json_decode($msg, true);

        switch ($message['e'] ?? null) {
            case 'aggTrade':
                $this->handleAggTrade($message);
                break;
            case 'markPriceUpdate':
                $this->handleMarkPrice($message);
                break;
        }
    }

    protected function handleAggTrade($message)
    {
        if ($this->websocketFlag) {
            $this->websocketFlag = false;
//            echo json_encode($message) . PHP_EOL;

            $price = (new Price())->fromAgg($message);

            echo $price->price . PHP_EOL;

            sleep(2);
            $this->websocketFlag = true;
        }
    }

    protected function handleMarkPrice($message)
    {
        $price = (new Price())->fromMark($message);

        echo $price->type . '-' . $price->price . PHP_EOL;
    }
}
