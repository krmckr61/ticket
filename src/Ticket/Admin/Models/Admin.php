<?php

namespace Ticket\Admin\Models;

use Ticket\PathFinder;

class Admin extends PathFinder{

    public function get($email, $password){
        return $this->app['db']->fetchAssoc('SELECT id, name from admins where email=? && password=?', [$email, $password]);
    }

}