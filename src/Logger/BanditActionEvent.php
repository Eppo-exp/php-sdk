<?php

namespace Eppo\Logger;

use DateTime;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Bandit\BanditEvaluation;
use JsonSerializable;
use Serializable;

class BanditActionEvent implements Serializable, JsonSerializable
{
    public function __construct(
        public readonly string $flagKey,
        public readonly string $banditKey,
        public readonly string $subjectKey,
        public readonly ?string $action,
        public readonly ?float $actionProbability,
        public readonly ?float $optimalityGap,
        public readonly ?string $modelVersion,
        public readonly DateTime $timestamp,
        public readonly array $subjectNumericAttributes,
        public readonly array $subjectCategoricalAttributes,
        public readonly array $actionNumericAttributes,
        public readonly array $actionCategoricalAttributes,
        public readonly array $metaData
    ) {
    }

    public static function fromEvaluation(
        string $banditKey,
        BanditEvaluation $result,
        Bandit $bandit,
        $metaData
    ): BanditActionEvent {
        return new self(
            $result->flagKey,
            $banditKey,
            $result->subjectKey,
            $result->selectedAction,
            $result->actionWeight,
            $result->optimalityGap,
            $bandit->modelVersion,
            new DateTime(),
            $result->subjectAttributes->numericAttributes,
            $result->subjectAttributes->categoricalAttributes,
            $result->actionAttributes->numericAttributes,
            $result->actionAttributes->categoricalAttributes,
            $metaData
        );
    }

    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $unserializedData = unserialize($data);
        $this->__unserialize($unserializedData);
    }

    public function __serialize(): array
    {
        return [
            'flagKey' => $this->flagKey,
            'banditKey' => $this->banditKey,
            'subjectKey' => $this->subjectKey,
            'action' => $this->action,
            'actionProbability' => $this->actionProbability,
            'optimalityGap' => $this->optimalityGap,
            'modelVersion' => $this->modelVersion,
            'timestamp' => $this->timestamp->format('Y-m-d\TH:i:sP'), // Convert DateTime to a serializable format
            'subjectNumericAttributes' => $this->subjectNumericAttributes,
            'subjectCategoricalAttributes' => $this->subjectCategoricalAttributes,
            'actionNumericAttributes' => $this->actionNumericAttributes,
            'actionCategoricalAttributes' => $this->actionCategoricalAttributes,
            'metaData' => $this->metaData,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->flagKey = $data['flagKey'];
        $this->banditKey = $data['banditKey'];
        $this->subjectKey = $data['subjectKey'];
        $this->action = $data['action'];
        $this->actionProbability = $data['actionProbability'];
        $this->optimalityGap = $data['optimalityGap'];
        $this->modelVersion = $data['modelVersion'];
        $this->timestamp = DateTime::createFromFormat('Y-m-d\TH:i:sP', $data['timestamp']); // Convert back to DateTime
        $this->subjectNumericAttributes = $data['subjectNumericAttributes'];
        $this->subjectCategoricalAttributes = $data['subjectCategoricalAttributes'];
        $this->actionNumericAttributes = $data['actionNumericAttributes'];
        $this->actionCategoricalAttributes = $data['actionCategoricalAttributes'];
        $this->metaData = $data['metaData'];
    }

    public function jsonSerialize(): mixed
    {
        return $this->__serialize();
    }
}
