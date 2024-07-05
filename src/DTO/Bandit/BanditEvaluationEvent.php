<?php

namespace Eppo\DTO\Bandit;

use DateTime;
use JsonSerializable;

class BanditEvaluationEvent implements JsonSerializable
{
    public DateTime $timestamp;

    public function __construct(
        public readonly string $flagKey,
        public readonly string $banditKey,
        public readonly string $subjectKey,
        public readonly ?string $action = null,
        public readonly ?float $actionProbability = null,
        public readonly ?float $optimalityGap = null,
        public readonly string $modelVersion = '',
        public readonly array $subjectNumericAttributes = [],
        public readonly array $subjectCategoricalAttributes = [],
        public readonly array $actionNumericAttributes = [],
        public readonly array $actionCategoricalAttributes = [],
        public readonly array $metaData = []
    ) {
        $this->timestamp = new DateTime();
    }

    public function jsonSerialize(): array
    {
        return [
            'flagKey' => $this->flagKey,
            'banditKey' => $this->banditKey,
            'subjectKey' => $this->subjectKey,
            'action' => $this->action,
            'actionProbability' => $this->actionProbability,
            'optimalityGap' => $this->optimalityGap,
            'modelVersion' => $this->modelVersion,
            'timestamp' => $this->timestamp,
            'subjectNumericAttributes' => $this->subjectNumericAttributes,
            'subjectCategoricalAttributes' => $this->subjectCategoricalAttributes,
            'actionNumericAttributes' => $this->actionNumericAttributes,
            'actionCategoricalAttributes' => $this->actionCategoricalAttributes,
            'metaData' => $this->metaData,
        ];
    }
}
