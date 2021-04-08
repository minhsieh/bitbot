<?php

namespace App\Entities;

use Carbon\Carbon;

class Price extends AbstractEntity
{
    protected $price;
    protected $symbol;
    protected $quantity;
    protected $time;
    protected $isMaker;
    protected $type; // agg, mark

    public function fromAgg($payload)
    {
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        $this->price = $payload['p'] ?? null;
        $this->symbol = $payload['s'] ?? null;
        $this->quantity = $payload['q'] ?? null;
        $this->time = new Carbon(($payload['T']) ? round($payload['T'] / 1000) : time());
        $this->isMaker = $payload['m'] ?? false;
        $this->type = 'agg';

        return $this;
    }

    public function fromMark($payload)
    {
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        $this->price = $payload['p'] ?? null;
        $this->symbol = $payload['s'] ?? null;
        $this->quantity = null;
        $this->time = new Carbon(($payload['E']) ? round($payload['E'] / 1000) : time());
        $this->isMaker = false;
        $this->type = 'mark';

        return $this;
    }
}
