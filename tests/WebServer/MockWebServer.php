<?php

namespace Eppo\Tests\WebServer;

use Exception;

class MockWebServer
{
    public readonly string $serverAddress;
    public mixed $process;

    /**
     * @param int $port
     * @param string $ufcFile
     * @throws Exception
     */
    private function __construct(
        public readonly int $port,
        string $ufcFile
    ) {
        $this->serverAddress = "localhost:$port";
        $this->serveFile($ufcFile);
        usleep(500000);
    }

    /**
     * @param string $defaultUFCFile
     * @return MockWebServer
     * @throws Exception
     */
    public static function start(string $defaultUFCFile = __DIR__ . '/../data/ufc/flags-v1.json'): MockWebServer
    {
        $port = self::getFreePort();
        return new self($port, $defaultUFCFile);
    }

    /**
     * @throws Exception
     */
    public function setUfcFile(string $ufcFile): MockWebServer
    {
        $this->stop();
        $this->serveFile($ufcFile);
        usleep(500000);
        return $this;
    }

    public function stop(): void
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

    /**
     * @throws Exception
     */
    private function serveFile(string $ufcFile): void
    {
        print("serving file\n");
        $descriptorSpec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];

        $cmd = "UFC=$ufcFile php -S $this->serverAddress " . __DIR__ . '/router.php';
        print($cmd . "\n");

        $this->process = proc_open($cmd, $descriptorSpec, $pipes);
        if (!is_resource($this->process)) {
            throw new Exception('Unable to start PHP built-in web server.');
        }
    }
}
