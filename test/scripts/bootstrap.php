<?php

use mindplay\stately\SessionFileStorage;
use mindplay\stately\SessionService;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\Server;
use Zend\Stratigility\MiddlewarePipe;

require dirname(dirname(__DIR__)) . '/vendor/autoload.php';

$APP = new MiddlewarePipe();
$REQUEST = ServerRequestFactory::fromGlobals();
$SERVER = Server::createServerfromRequest($APP, $REQUEST);
$SESSION = new SessionService($REQUEST, new SessionFileStorage(), 'SESSION');

class ActiveUser
{
    /**
     * @var int|null
     */
    public $user_id;
}
