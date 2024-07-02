<?php

namespace Eppo;

use Eppo\Exception\HttpRequestException;

class Poller implements PollerInterface {
    /** @var bool */
    private $stopped = false;

    /** @var int */
    private $interval;

    /** @var int */
    private $jitterMillis;

    /** @var callable */
    private $callback;

    /**
     * @param int $interval (milliseconds)
     * @param int $jitterMillis
     * @param callable $callback
     */
    public function __construct(int $interval, int $jitterMillis, callable $callback) {
        $this->interval = $interval;
        $this->jitterMillis = $jitterMillis;
        $this->callback = $callback;
    }

    public function start(): void {
        $this->stopped = false;
        $this->poll();
    }

    public function stop(): void {
        $this->stopped = true;
    }

    private function poll(): void {
        if ($this->stopped) {
            return;
        }

        try {
            call_user_func($this->callback);
        } catch (HttpRequestException $error) {
            if (!$error->isRecoverable) {
                $this->stop();
            }
            error_log("[Eppo SDK] Error polling configurations: " . $error->getMessage());
        }

        $intervalWithJitter = $this->interval - mt_rand(0, $this->jitterMillis);
        usleep($intervalWithJitter * 1000);

        $this->poll();
    }
}
