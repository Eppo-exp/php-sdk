<?php

namespace Eppo;

use Eppo\Config\SDKData;
use Eppo\Exception\InvalidArgumentException;
use Eppo\Exception\InvalidApiKeyException;
use GuzzleHttp\Exception\GuzzleException;
use Sarahman\SimpleCache\FileSystemCache;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

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

            $cache = new FileSystemCache();
            $httpClient = new HttpClient($baseUrl, $apiKey, $sdkData);
            $configStore = new ConfigurationStore($cache);
            $configRequester = new ExperimentConfigurationRequester($httpClient, $configStore);

            self::$instance = new self($configRequester);
        }

        return self::$instance;
    }

    /**
     * @param $subjectKey
     * @param $experimentKey
     * @param $subjectAttributes
     * @return string|null
     * @throws Exception\HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws GuzzleException
     * @throws SimpleCacheInvalidArgumentException
     */
    public function getAssignment($subjectKey, $experimentKey, $subjectAttributes = [])
    {
        Validator::validateNotBlank($subjectKey, 'Invalid argument: subjectKey cannot be blank');
        Validator::validateNotBlank($experimentKey, 'Invalid argument: experimentKey cannot be blank');

        $experimentConfig = $this->configurationRequester->getConfiguration($experimentKey);

        if (!$experimentConfig->isEnabled()) return null;

        return '';
    }
}