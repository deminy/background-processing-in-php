<?php

namespace deminy\BackgroundProcessing;

use CrowdStar\BackgroundProcessing\BackgroundProcessing;
use CrowdStar\BackgroundProcessing\Exception;

/**
 * Class Helper
 *
 * @package deminy\BackgroundProcessing
 */
class Helper
{
    const WRITE = 2;
    const READ  = 4;

    /**
     * @var string
     */
    protected $file;

    /**
     * @var int
     */
    protected $mode;

    public function __construct(int $mode)
    {
        header("Content-Type: text/plain");

        $this->mode = $mode;
        $this->file = sys_get_temp_dir() . '/php.log';

        if ((self::WRITE === $this->mode) && is_readable($this->file)) {
            unlink($this->file);
        }
    }

    public function __destruct()
    {
        if (self::WRITE === $this->mode) {
            $this->write("Executed in the destruct method of an object during the shutdown sequence.");
        }
    }

    /**
     * @return string
     */
    public function read()
    {
        return (is_readable($this->file) ? file_get_contents($this->file) : "");
    }

    /**
     * @param string $data The data to write.
     * @param bool $append If true, append the data to the file instead of overwriting it.
     */
    public function write(string $data, bool $append = true)
    {
        file_put_contents($this->file, "{$data}\n", ($append ? FILE_APPEND: 0));
        echo "{$data}\n";
    }

    public function registerShutdownFunction(): Helper
    {
        register_shutdown_function(
            function () {
                $this->write("Executed in a function registered through register_shutdown_function().");
            }
        );

        return $this;
    }

    public function addAndRunBackgroundTask(): Helper
    {
        BackgroundProcessing::add(
            function () {
                $this->write("Executed after function fastcgi_finish_request() is called.");
            }
        );
        BackgroundProcessing::run();

        return $this;
    }

    public function exit0()
    {
        $this->write("Executed when function exit() is called.");

        exit(0);

        $this->write("Executed right after function exit() is called.");
    }
}
