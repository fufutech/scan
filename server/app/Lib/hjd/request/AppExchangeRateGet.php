<?php

namespace App\Lib\hjd\request;


class AppExchangeRateGet
{
    private $params = [];

    public function getApiMethodName(): string
    {
        return "app.exchange.rate.get";
    }

    public function getApiParams(): array
    {
        return $this->params;
    }

    public function setSource($source)
    {
        $this->params['source'] = $source;
    }

    public function setCurrencies($currencies)
    {
        $this->params['currencies'] = $currencies;
    }
}