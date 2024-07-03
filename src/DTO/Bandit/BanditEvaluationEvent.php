<?php

namespace Eppo\DTO\Bandit;

use DateTime;
use JsonSerializable;

class BanditEvaluationEvent implements JsonSerializable
{
    public string $flagKey;
    public string $banditKey;
    public string $subjectKey;
    public ?string $action;
    public ?float $actionProbability;
    public ?float $optimalityGap;
    public ?string $modelVersion;
    public DateTime $timestamp;
    public array $subjectNumericAttributes;
    public array $subjectCategoricalAttributes;
    public array $actionNumericAttributes;
    public array $actionCategoricalAttributes;
    public array $metaData;

    public function __construct(
        string $flagKey,
        string $banditKey,
        string $subjectKey,
        string $action = null,
        float $actionProbability = null,
        float $optimalityGap = null,
        string $modelVersion = '',
        array $subjectNumericAttributes = [],
        array $subjectCategoricalAttributes = [],
        array $actionNumericAttributes = [],
        array $actionCategoricalAttributes = [],
        array $metaData = []
    ) {
        $this->flagKey = $flagKey;
        $this->banditKey = $banditKey;
        $this->subjectKey = $subjectKey;
        $this->action = $action;
        $this->actionProbability = $actionProbability;
        $this->optimalityGap = $optimalityGap;
        $this->modelVersion = $modelVersion;
        $this->subjectNumericAttributes = $subjectNumericAttributes;
        $this->subjectCategoricalAttributes = $subjectCategoricalAttributes;
        $this->actionNumericAttributes = $actionNumericAttributes;
        $this->actionCategoricalAttributes = $actionCategoricalAttributes;
        $this->metaData = $metaData;
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