<?php

namespace Eppo;

interface IPoller
{
    public function start(): void;

    public function stop(): void;
}