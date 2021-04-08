<?php

namespace App\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class BinanceApi
{
    /** @var Client */
    private $client;

    /**
     * Get guzzle client
     *
     * @return Client
     */
    public function getClient()
    {
        if (! $this->client) {
            $this->client = new Client([
                'headers' => [
                    'X-MBX-APIKEY' => env('BINANCE_PUBLIC_KEY'),
                ],
                'base_uri' => env('BINANCE_API_BASE_URL')
            ]);
        }

        return $this->client;
    }

    /**
     * Get User's wallet data
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUserData()
    {
        $query = [
            'recvWindow' => 5000,
            'timestamp' => time() * 1000
        ];

        $response = $this->getClient()->get('/fapi/v2/account', [
            'query' => $this->buildQuery($query),
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Get listen key
     *
     * @return bool|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getListenKey()
    {
        $response = $this->getClient()->post('/fapi/v1/listenKey');
        return json_decode($response->getBody()->getContents(), true)['listenKey'] ?? false;
    }

    /**
     * Sign request's query string
     *
     * @param $query
     * @return string
     */
    public function signature($query)
    {
        $query = http_build_query($query);
        return hash_hmac('sha256', $query, env('BINANCE_PRIVATE_KEY'));
    }

    /**
     * Make query signed and built
     *
     * @param $query
     * @return string
     */
    public function buildQuery($query)
    {
        $query += [
            'signature' => $this->signature($query)
        ];

        return http_build_query($query);
    }
}
