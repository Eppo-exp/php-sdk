<?php

namespace Eppo;

class ConfigurationStore
{
    /**
     * @param string $key
     *
     * @return string
     */
    public function getConfiguration(string $key): string {
        return apcu_fetch($key);
    }

    /**
     * @param array $configs
     *
     * @return void
     */
    public function setConfigurations(array $configs) {
        foreach ($configs as $key => $value) {
            apcu_add($key, json_encode($value), 60);
        }
    }
}
