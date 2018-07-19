<?php

/**
 * To demonstrate how function register_shutdown_function() could affect HTTP responses.
 *
 * curl http://127.0.0.1/write1 # Print out what is received from given HTTP request.
 *     Executed when function exit() is called.
 *     Executed in a function registered through register_shutdown_function().
 *     Executed in the destruct method of an object during the shutdown sequence.
 *
 * curl http://127.0.0.1/read   # Print out what has been logged in a log file during previous HTTP request.
 *     Executed when function exit() is called.
 *     Executed in a function registered through register_shutdown_function().
 *     Executed in the destruct method of an object during the shutdown sequence.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use deminy\BackgroundProcessing\Helper;

(new Helper(Helper::WRITE))
    ->registerShutdownFunction()
    ->exit0();
