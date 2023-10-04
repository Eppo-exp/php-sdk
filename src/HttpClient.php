<?php

namespace Eppo;

use Eppo\Config\SDKData;
use Eppo\Exception\HttpRequestException;
use Teapot\StatusCode;

class HttpClient
{
    /** @var int */
    const REQUEST_TIMEOUT = 5;

    /** @var Client */
    protected $client;

    /** @var bool */
    public $isUnauthorized = false;

    /** @var array */
    private $sdkParams = [];

    /** @var HttpClientInterface */
    private $httpClient;

    /**
     * @param string $baseUrl
     * @param string $apiKey
     * @param SDKData $SDKData
     */
    public function __construct(string $baseUrl, string $apiKey, SDKData $SDKData, HttpClientInterface $httpClient)
    {
        if (!$baseUrl) {
            $baseUrl = 'https://fscdn.eppo.cloud';
        }

        $this->sdkParams = [
            'apiKey' => $apiKey,
            'sdkName' => $SDKData->getSdkName(),
            'sdkVersion' => $SDKData->getSdkVersion(),
        ];

        $this->httpClient = $httpClient;
        $httpClient->setBaseUrl($baseUrl);
        $httpClient->setEppoParameters($this->sdkParams);
        $httpClient->setTimeout(self::REQUEST_TIMEOUT);
    }

    /**
     * @param $resource
     *
     * @return string
     *
     * @throws HttpRequestException
     */
    public function get($resource): string
    {
        try {
            $response = $this->httpClient->get($resource, ['query' => $this->sdkParams]);
            return (string)$response->getBody();
        } catch (RequestException $exception) {
            $this->handleHttpError($exception);
        }
    }

    /**
     * @param RequestException $exception
     *
     * @throws HttpRequestException
     */
    private function handleHttpError(RequestException $exception)
    {
        $status = $exception->getResponse()->getStatusCode();
        $this->isUnauthorized = $status === 401;
        $isRecoverable = $this->isHttpErrorRecoverable($status);
        throw new HttpRequestException($exception->getMessage(), $status, $isRecoverable);
    }

    /**
     * @param int $status
     *
     * @return bool
     */
    private function isHttpErrorRecoverable(int $status): bool
    {
        if ($status >= StatusCode::BAD_REQUEST && $status < StatusCode::INTERNAL_SERVER_ERROR) {
            return $status === StatusCode::CONFLICT || $status === StatusCode::REQUEST_TIMEOUT;
        }
        return true;
    }
}
