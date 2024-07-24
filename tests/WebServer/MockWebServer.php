<?php

namespace Eppo\Tests\WebServer;

use Exception;

class MockWebServer
{
    public readonly string $serverAddress;

    /**
     * @param resource $process
     * @param int $port
     */
    private function __construct(
        public readonly mixed $process,
        public readonly int $port
    ) {
        $this->serverAddress = "localhost:$port";
    }

    /**
     * @param string $defaultUFCFile
     * @return MockWebServer
     * @throws Exception
     */
    public static function start(string $defaultUFCFile = __DIR__ . '/../data/ufc/flags-v1.json'): MockWebServer
    {
        $descriptorSpec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];

        $port = self::getFreePort();

        $server = "localhost:$port";

        $cmd = "UFC=$defaultUFCFile php -S $server " . __DIR__ . '/router.php';
        $process = proc_open($cmd, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new Exception('Unable to start PHP built-in web server.');
        }
        usleep(500000);
        return new self($process, $port);
    }

    public function stop()
    {
        if (!is_resource($this->process)) {
            return;
        }
        proc_terminate($this->process, SIGTERM);
        proc_close($this->process);
    }

    private static function getFreePort(): ?int
    {
        $sock = socket_create_listen(0);
        socket_getsockname($sock, $addr, $port);
        socket_close($sock);

        return $port;
    }
}
