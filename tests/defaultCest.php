<?php

use Codeception\Util\HttpCode;

/**
 * Class defaultCest
 */
class defaultCest
{
    /**
     * @param ApiTester $I
     */
    public function testEndpointWrite1(ApiTester $I)
    {
        $I->wantTo('demonstrate how function register_shutdown_function() could affect HTTP responses');
        $I->sendGET('/write1');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals(
            <<<EOT
Executed when function exit() is called.
Executed in a function registered through register_shutdown_function().
Executed in the destruct method of an object during the shutdown sequence.

EOT
        );

        $I->sendGET('/read');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals(
            <<<EOT
Executed when function exit() is called.
Executed in a function registered through register_shutdown_function().
Executed in the destruct method of an object during the shutdown sequence.

EOT
        );
    }

    /**
     * @param ApiTester $I
     */
    public function testEndpointWrite2(ApiTester $I)
    {
        $I->wantTo('demonstrate how function fastcgi_finish_request() affects HTTP responses');
        $I->sendGET('/write2');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('');

        $I->sendGET('/read');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals(
            <<<EOT
Executed after function fastcgi_finish_request() is called.
Executed when function exit() is called.
Executed in the destruct method of an object during the shutdown sequence.

EOT
        );
    }

    /**
     * @param ApiTester $I
     */
    public function testEndpointWrite3(ApiTester $I)
    {
        $I->wantTo(
            'show what happens when function register_shutdown_function() and fastcgi_finish_request() both in use'
        );
        $I->sendGET('/write3');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('');

        $I->sendGET('/read');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals(
            <<<EOT
Executed after function fastcgi_finish_request() is called.
Executed when function exit() is called.
Executed in a function registered through register_shutdown_function().
Executed in the destruct method of an object during the shutdown sequence.

EOT
        );
    }
}
