<?php

namespace Eppo\Logger;

class AssignmentEvent
{
    public function __construct(
        public string $experiment,
        public string $variation,
        public string $allocation,
        public string $featureFlag,
        public string $subject,
        public float $timestamp,
        public array $subjectAttributes = [],
        public array $sdkMetadata = [],
        public array $extraLogging = []
    ) {
    }
    public function toArray(): array
    {
        return [
            'experiment' => $this->experiment,
            'variation' => $this->variation,
            'allocation' => $this->allocation,
            'featureFlag' => $this->featureFlag,
            'subject' => $this->subject,
            'timestamp' => $this->timestamp,
            'subjectAttributes' => $this->subjectAttributes,
            'sdkMetadata' => $this->sdkMetadata,
            'extraLogging' => $this->extraLogging,
        ];
    }
}
