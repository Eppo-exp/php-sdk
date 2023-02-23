<?php

namespace Eppo\Tests\WebServer;

use Exception;

class MockWebServer
{
    /** @var string */
    private static $command = "php -S localhost:4000 " . __DIR__ . "/router.php";

    /** @var resource */
    private static $process;

    /**
     * @return void
     * @throws Exception
     */
    public static function start()
    {
        $descriptorSpec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];
        self::$process = proc_open(self::$command, $descriptorSpec, $pipes);
        if (!is_resource(self::$process)) {
            throw new Exception('Unable to start PHP built-in web server.');
        }
        usleep(500000);
    }

    public static function stop()
    {
        if (!is_resource(self::$process)) {
            return;
        }
        proc_terminate(self::$process, SIGTERM);
        proc_close(self::$process);
    }
}