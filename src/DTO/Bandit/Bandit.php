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
     * @param array $arr
     * @return Bandit
     */
    public static function fromArray(array $arr): Bandit
    {
        try {
            if (!isset($arr['updatedAt'])) {
                $updatedAt = new DateTime();
            } elseif (is_array($arr['updatedAt'])) {// serialized datetime
                $updatedAt = new DateTime($arr['updatedAt']['date']);
            } else {
                $updatedAt = new DateTime($arr['updatedAt']);
            }
        } catch (\Exception $e) {
            syslog(
                LOG_WARNING,
                "[Eppo SDK] invalid timestamp for bandit model {$arr['updatedAt']}: " . $e->getMessage()
            );
            $updatedAt = new DateTime();
        } finally {
            return new Bandit(
                $arr['banditKey'],
                $arr['modelName'],
                $updatedAt,
                $arr['modelVersion'],
                BanditModelData::fromArray($arr['modelData'])
            );
        }
    }
}
