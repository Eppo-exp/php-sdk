<?php

namespace Eppo\DTO;

use Eppo\Traits\StaticFromJson;
use Eppo\Traits\ToArray;

class FlagConfigResponse
{
    use StaticFromJson;
    use ToArray;

    public string $createdAt;
    public string $format;
    public array $environment;

    public array $flags;

    /**
     * @var BanditReference[]
     */
    public array $banditReferences;

    public static function fromJson(array $arr): self
    {
        $dto = new self();
        $dto->format = $arr['format'] ?? 'SERVER';
        if (isset($arr['environment'])) {
            $dto->environment = $arr['environment'];
        }
        if (isset($arr['flags'])) {
            $dto->flags = array_map(function ($flag) {
                return Flag::fromJson($flag);
            }, $arr['flags']);
        }
        if (isset($arr['banditReferences'])) {
            $dto->banditReferences = array_map(function ($banditReference) {
                return BanditReference::fromJson($banditReference);
            }, $arr['banditReferences']);
        }
        return $dto;
    }

    public function __construct()
    {
        $this->banditReferences = [];
        $this->format = "SERVER";
    }
}
