<?php

namespace Eppo\DTO;

class Shard {
    /** @var string */
    private $salt;
    /** @var array of `ShardRange` */
    private $ranges;

    public function getSalt(): string
    {
        return $this->salt;
    }

    public function getRanges(): array
    {
        return $this->ranges;
    }

    /**
     * @param string $salt
     * @param array $ranges
     */
    public function __construct(string $salt, array $ranges)
    {
        $this->salt = $salt;
        $this->ranges = $ranges;
    }

}