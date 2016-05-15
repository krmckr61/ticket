<?php
namespace Ticket;

use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TwigServiceProvider;

class PathFinder {

    public $app;

    function __construct()
    {
        $this->app = new Application;
        $this->app['debug'] = 1;
        $this->setRequires();
        return $this->app;
    }

    private function setRequires(){
        $this->app->register(new DoctrineServiceProvider(), array(
            'db.options' => array(
                'driver' => 'pdo_mysql',
                'host' => '127.0.0.1',
                'dbname' => 'ticketdb',
                'user' => 'root',
                'password' => '',
                'charset' => 'utf8',
            ),
        ));

        $this->app->register(new TwigServiceProvider(), array(
            'twig.path' => __DIR__.'/Views',
        ));


        $this->app->register(new ValidatorServiceProvider());

        $this->app->register(new SessionServiceProvider());

    }

    public function getApp(){
        return $this->app;
    }

}