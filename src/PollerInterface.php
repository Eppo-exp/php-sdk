<?php

namespace Eppo;

interface PollerInterface
{
    public function start(): void;

    public function stop(): void;
}
