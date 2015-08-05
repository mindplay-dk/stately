<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\Stratigility\Http\Response as Response;

require __DIR__ . '/bootstrap.php';

/**
 * @var \Zend\Diactoros\Server $SERVER
 */

$APP->pipe('/', function (Request $request, Response $response, $next) use ($SESSION) {
    $SESSION->update(function (ActiveUser $user) {
        $user->user_id = 123;
    });

    return $next($request, $response->write("Logged in! ;-)"));
});

$APP->pipe('/', function (Request $request, Response $response, $next) use ($SESSION) {
    $new_response = $SESSION->commit($response);

    return $next($request, $new_response);
});

// error-handler middleware: (in case an exception is thrown)

$APP->pipe('/', function ($error, $req, Response $res, $next) {
    return $res->write((string) $error);
});

$SERVER->listen();
