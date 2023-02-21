<?php

namespace Eppo;

class EppoClient
{
    /**
     * @var EppoClient
     */
    private static $instance;

    /**
     * @var ExperimentConfigurationRequester
     */
    private $configurationRequester;

    /**
     * The Singleton's constructor should always be private to prevent direct
     * construction calls with the `new` operator.
     */
    protected function __construct(ExperimentConfigurationRequester $configurationRequester) {
        $this->configurationRequester = $configurationRequester;
    }

    /**
     * Singletons should not be cloneable.
     */
    protected function __clone() { }

    /**
     * @param string $apiKey
     * @param string $baseUrl
     *
     * @return EppoClient
     */
    public static function init($apiKey, $baseUrl = ''): EppoClient {
        if (self::$instance === null) {
            $configRequester = new ExperimentConfigurationRequester();

            self::$instance = new self($configRequester);
        }

        return self::$instance;
    }

    public function getAssignment($subjectKey, $experimentKey, $subjectAttributes): string {
        return '';
    }
}