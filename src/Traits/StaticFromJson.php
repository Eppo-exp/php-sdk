<?php

declare(strict_types=1);

namespace Eppo\Traits;

trait StaticFromJson
{
    public static function fromJson(array $values): self
    {
        $dto = new self();

        foreach ($values as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->$key = $value;
            }
        }

        return $dto;
    }
}
