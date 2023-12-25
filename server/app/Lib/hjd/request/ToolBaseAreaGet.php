<?php

namespace App\Lib\hjd\request;


class ToolBaseAreaGet
{
    private $params = [];

    public function getApiMethodName(): string
    {
        return "tool.base.area.get";
    }

    public function getApiParams(): array
    {
        return $this->params;
    }

    public function setParentId($parent_id)
    {
        $this->params['parent_id'] = $parent_id;
    }

    public function setCountryId($country_id)
    {
        $this->params['country_id'] = $country_id;
    }
}