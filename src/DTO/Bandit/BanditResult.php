<?php

namespace Eppo\DTO\Bandit;

use Eppo\DTO\IDeserializable;
use JsonSerializable;

class BanditResult implements JsonSerializable, IDeserializable
{
    public string $variation;
    public ?string $action;

    public function __construct(string $variation, ?string $action = null)
    {
        $this->variation = $variation;
        $this->action = $action;
    }

    public function jsonSerialize(): array
    {
        return [
            'Variation' => $this->variation,
            'Action' => $this->action,
        ];
    }

    public function __toString(): string
    {
        return $this->action ?? $this->variation;
    }

    public static function fromJson($json): IDeserializable
    {
        return new self($json['Variation'], $json['Action'] ?? null);
    }
}
