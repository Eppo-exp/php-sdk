<?php

namespace Eppo\DTO\Bandit;

use DateTime;
use Eppo\DTO\IDeserializable;
use Eppo\Exception\EppoClientException;

class Bandit implements IDeserializable
{
    public function __construct(
        string $banditKey,
        string $modelName,
        DateTime $updatedAt,
        string $modelVersion,
        BanditModelData $modelData
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
