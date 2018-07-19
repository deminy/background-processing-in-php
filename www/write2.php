<?php

/**
 * To demonstrate how function fastcgi_finish_request() affects HTTP responses.
 *
 * curl http://127.0.0.1/write2 # Print out what is received from given HTTP request.
 *     (nothing printed out)
 *
 * curl http://127.0.0.1/read   # Print out what has been logged in a log file during previous HTTP request.
 *     Executed after function fastcgi_finish_request() is called.
 *     Executed when function exit() is called.
 *     Executed in the destruct method of an object during the shutdown sequence.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use deminy\BackgroundProcessing\Helper;

(new Helper(Helper::WRITE))
    ->addAndRunBackgroundTask()
    ->exit0();
