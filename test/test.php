<?php

use mindplay\stately\SessionService;
use mindplay\stately\SessionFileStorage;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

require dirname(__DIR__) . '/vendor/autoload.php';

require __DIR__ . '/src/MockStorage.php';
require __DIR__ . '/src/ActiveUser.php';

configure()->enableCodeCoverage(__DIR__ . '/build/clover.xml', dirname(__DIR__) . '/src');

test(
    'MockStorage works as expected',
    function () {
        $storage = new MockStorage();

        $ID = 'foo';
        $DATA = 'bar';

        eq($storage->read($ID), null, 'returns NULL for undefined value');

        $storage->write($ID, $DATA, 60);

        eq($storage->read($ID), $DATA, 'can read data');

        $storage->gc(60);

        eq($storage->read($ID), $DATA, 'retains fresh value');

        $storage->gc(0);

        eq($storage->read($ID), null, 'garbage collects expired value');
    }
);

test(
    'does not send a cookie if no session data was added',
    function () {
        $COOKIE_NAME = 'HELLO_WORLD';
        $URL = 'http://hello.test/';
        $METHOD = 'GET';

        $storage = new MockStorage();

        $request = new ServerRequest([], [], $URL, $METHOD);
        $response = new Response();

        $container = new SessionService($request, $storage, $COOKIE_NAME);

        $same_response = $container->commit($response);

        eq($response, $same_response, 'does not modify the response if no cookie was sent');
    }
);

test(
    'can update and recreate session models',
    function () {
        $COOKIE_NAME = 'HELLO_WORLD';
        $URL = 'http://hello.test/';
        $METHOD = 'GET';
        $UUID_LENGTH = 36;
        $UUID_PATTERN = '#^\w{8}\-\w{4}\-\w{4}\-\w{4}\-\w{12}$#';

        $storage = new MockStorage();

        $request = new ServerRequest([], [], $URL, $METHOD);
        $response = new Response();

        $container = new SessionService($request, $storage, $COOKIE_NAME);

        $container->update(function (ActiveUser $user) {
            $user->user_id = 123;
        });

        $new_response = $container->commit($response);

        ok($response !== $new_response, 'it generates a new response');

        $set_cookie = $new_response->getHeaderLine('Set-Cookie');

        ok(fnmatch("{$COOKIE_NAME}=*", $set_cookie), 'response has a cookie');

        $session_id = substr($set_cookie, strlen($COOKIE_NAME) + 1, $UUID_LENGTH);

        ok(preg_match($UUID_PATTERN, $session_id) === 1, 'looks like a UUID');

        unset($request, $response, $container);

        $request = new ServerRequest([], [], $URL, $METHOD);

        $request = $request->withCookieParams(array($COOKIE_NAME => $session_id));

        $container = new SessionService($request, $storage, $COOKIE_NAME);

        $got_user_id = null;

        $container->update(function (ActiveUser $user) use (&$got_user_id) {
            $got_user_id = $user->user_id;
        });

        eq($got_user_id, 123, 'session state was restored from cookie');

        $container->remove(ActiveUser::class);

        $response = new Response();

        $same_response = $container->commit($response);

        ok($response === $same_response, 'it generates the same response');
    }
);

test(
    'can use file-system storage',
    function () {
        $SESSION_ID = '9f303fa8-3da2-432b-af20-1cb458ad3f3d';
        $EXPECTED_PATH = __DIR__ . '/build/' . $SESSION_ID . '.' . SessionFileStorage::SESSION_FILE_EXT;
        $SAMPLE_DATA = serialize(['Foo' => ['bar','baz']]);

        if (file_exists($EXPECTED_PATH)) {
            unlink($EXPECTED_PATH);
        }

        ok(!file_exists($EXPECTED_PATH), 'pre-condition: no file left behind from previous test run');

        $f = new SessionFileStorage(__DIR__ . '/build');

        $content = $f->read($SESSION_ID);

        eq($content, null, 'read() returns NULL for new session ID');
        ok(file_exists($EXPECTED_PATH), 'session file has been created');

        $f->write($SESSION_ID, $SAMPLE_DATA, 60);

        ok(filesize($EXPECTED_PATH) === strlen($SAMPLE_DATA), 'data has been written');

        $f->gc(60);

        ok(file_exists($EXPECTED_PATH), 'fresh file was not garbage-collected');

        $f->gc(0);

        ok(!file_exists($EXPECTED_PATH), 'stale file was garbage-collected');
    }
);

exit(run());
