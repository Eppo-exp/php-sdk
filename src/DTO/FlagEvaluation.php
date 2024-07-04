<?php

namespace Eppo\DTO;

class FlagEvaluation
{
    public function __construct(
        public Variation $variation,
        public bool $doLog,
        public string $allocationKey,
        public array|null $extraLogging
    ) {
    }
}
