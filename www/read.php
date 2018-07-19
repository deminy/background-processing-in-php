<?php

/**
 * Fetch content from the disk file and print it out.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use deminy\BackgroundProcessing\Helper;

echo (new Helper(Helper::READ))->read();
