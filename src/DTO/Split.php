<?php

namespace Eppo\DTO;

class Split
{
private $variationKey;
private $shards;
private $extraLogging;

    /**
     * @param $variationKey
     * @param $shards
     */
    public function __construct($variationKey, $shards, $extraLogging)
    {
        $this->variationKey = $variationKey;
        $this->shards = $shards;
        $this->extraLogging = $extraLogging;
    }

}