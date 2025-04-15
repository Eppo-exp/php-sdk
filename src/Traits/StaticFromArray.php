<?php

declare(strict_types=1);

namespace Eppo\Traits;

trait StaticFromArray
{
    public static function fromArray(array $arr): self
    {
        $dto = new self();

        foreach ($arr as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->$key = $value;
            }
        }

        return $dto;
    }
}
