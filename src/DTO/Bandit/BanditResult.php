<?php

namespace Eppo\DTO\Bandit;

class BanditResult
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

    public static function fromArray($arr): self
    {
        return new self($arr['Variation'], $arr['Action'] ?? null);
    }
}
