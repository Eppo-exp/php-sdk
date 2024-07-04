<?php

namespace Eppo\DTO\Bandit;

use DateTime;
use Eppo\DTO\IDeserializable;
use Eppo\Exception\EppoClientException;

class Bandit
{
    public function __construct(
        public readonly string $banditKey,
        public readonly string $modelName,
        public readonly DateTime $updatedAt,
        public readonly string $modelVersion,
        public readonly BanditModelData $modelData
    ) {
    }

    public static function fromJson($json): Bandit
    {
        try {
            return new Bandit(
                $json['banditKey'],
                $json['modelName'],
                new DateTime($json['updatedAt']),
                $json['modelVersion'],
                BanditModelData::fromJson($json['modelData'])
            );
        } catch (\Exception $e) {
            throw EppoClientException::from($e);
        }
    }
}
