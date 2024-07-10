<?php

namespace Eppo\DTO\Bandit;

use DateTime;

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

    /**
     * @param array $json
     * @return Bandit
     */
    public static function fromJson(array $json): Bandit
    {
        try {
            $updatedAt = new DateTime($json['updatedAt']);
        } catch (\Exception $e) {
            syslog(LOG_WARNING, "[Eppo SDK] invalid timestamp for bandit model ${json['updatedAt']}");
            $updatedAt = new DateTime();
        } finally {
            return new Bandit(
                $json['banditKey'],
                $json['modelName'],
                $updatedAt,
                $json['modelVersion'],
                BanditModelData::fromJson($json['modelData'])
            );
        }
    }
}
