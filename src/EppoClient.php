<?php

namespace Eppo;

use Eppo\Config\SDKData;
use Eppo\Exception\InvalidArgumentException;
use Eppo\Exception\InvalidApiKeyException;

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
    protected function __construct(ExperimentConfigurationRequester $configurationRequester)
    {
        $this->configurationRequester = $configurationRequester;
    }

    /**
     * Singletons should not be cloneable.
     */
    protected function __clone()
    {
    }

    /**
     * @param string $apiKey
     * @param string $baseUrl
     *
     * @return EppoClient
     */
    public static function init($apiKey, $baseUrl = ''): EppoClient
    {
        if (self::$instance === null) {
            $sdkData = new SDKData();

            $httpClient = new HttpClient($baseUrl, $apiKey, $sdkData);
            $configStore = new ConfigurationStore();
            $configRequester = new ExperimentConfigurationRequester($httpClient, $configStore);

            self::$instance = new self($configRequester);
        }

        return self::$instance;
    }

    /**
     * @param $subjectKey
     * @param $experimentKey
     * @param $subjectAttributes
     *
     * @return string
     *
     * @throws InvalidArgumentException|InvalidApiKeyException
     */
    public function getAssignment($subjectKey, $experimentKey, $subjectAttributes = []): string
    {
        Validator::validateNotBlank($subjectKey, 'Invalid argument: subjectKey cannot be blank');
        Validator::validateNotBlank($experimentKey, 'Invalid argument: experimentKey cannot be blank');

        $experimentConfig = $this->configurationRequester->getConfiguration($experimentKey);

        return '';
    }
}