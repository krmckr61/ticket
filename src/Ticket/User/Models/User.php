<?php

namespace Ticket\User\Models;

use Ticket\PathFinder;

class User extends PathFinder{

    public function get($email, $password){
        return $this->app['db']->fetchAssoc('SELECT id, name from users where email=? && password=?', [$email, $password]);
    }

}