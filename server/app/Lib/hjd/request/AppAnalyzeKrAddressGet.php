<?php

namespace App\Lib\hjd\request;


class AppAnalyzeKrAddressGet
{
    private $params = [];

    public function getApiMethodName(): string
    {
        return "app.analyze.kr.address.get";
    }

    public function getApiParams(): array
    {
        return $this->params;
    }

    public function setQ(string $query)
    {
        $this->params['q'] = $query;
    }

    public function setZip($zip)
    {
        $this->params['zip'] = $zip;
    }
}