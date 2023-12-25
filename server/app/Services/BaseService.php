<?php

namespace App\Services;


class BaseService
{
    protected array $UserData;

    public function setUserData(array $userData): void
    {
        $this->UserData = $userData;
    }
}