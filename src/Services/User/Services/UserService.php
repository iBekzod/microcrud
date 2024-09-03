<?php

namespace Microcrud\Services\User\Services;

use Usoft\Models\User;
use Microcrud\Abstracts\Service;

class UserService extends Service
{
    protected $model = User::class;
    protected $balance = 0;

    public function getUserId()
    {
        return $this->model->user_id;
    }
    private function setBalance($balance)
    {
        $this->balance = $balance;
        return $this;
    }

    public function getBalance()
    {
        $balance = (int)$this->model->balance;
        if (env('APP_DEBUG', false) == true) {
            $balance = (int)1000000;
        }
        $this->setBalance($balance);
        return  $balance;
    }
}
