<?php

namespace Eppo;

use Eppo\Config\SDKData;
use Eppo\Exception\HttpRequestException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\StreamInterface;

class HttpClient
{
    /**
     * @internal
     * @var Client
     */
    protected $client;

    /** @var bool */
    public $isUnauthorized = false;

    private $sdkParams = [];

    /**
     * @param string $baseUrl
     * @param string $apiKey
     * @param SDKData $SDKData
     */
    public function __construct(string $baseUrl, string $apiKey, SDKData $SDKData)
    {
        if (!$baseUrl) {
            $baseUrl = 'https://eppo.cloud';
        }
        $this->client = new Client(['base_uri' => $baseUrl]);

        $this->sdkParams = [
            'apiKey' => $apiKey,
            'sdkName' => $SDKData->getSdkName(),
            'sdkVersion' => $SDKData->getSdkVersion(),
        ];
    }

    /**
     * @param $resource
     *
     * @return string
     *
     * @throws GuzzleException
     * @throws HttpRequestException
     */
    public function get($resource): string
    {
        try {
            $response = $this->client->request('GET', $resource, ['query' => $this->sdkParams]);
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
     * @return bool
     */
    private function isHttpErrorRecoverable(int $status): bool
    {
        if ($status >= 400 && $status < 500) {
            return $status === 429 || $status === 408;
        }
        return true;
    }

}