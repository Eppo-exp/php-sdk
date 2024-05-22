<?php

namespace Eppo;

use Eppo\Exception\HttpRequestException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Teapot\StatusCode\RFC\RFC7231;
use Webclient\Extension\Redirect\RedirectClientDecorator;

/**
 * Encapsulates request logic for retrieving configuration data from the Eppo API.
 *
 * This strictly handles fulfilling requests for configuration data and makes no attempts to
 * parse the response. Errors are inspected by the handling logic to determine if requests
 * can be retried and identify when invalid API credentials are used.
 */
class APIRequestWrapper
{    /** @var string */
    const RAC_ENDPOINT = '/api/randomized_assignment/v3/config';
    const CONFIG_BASE = 'https://fscdn.eppo.cloud';

    private string $baseUrl;

    public bool $isUnauthorized = false;

    private ClientInterface $httpClient;

    private array $queryParams;

    private RequestFactoryInterface $requestFactory;

    private string $resource;

    public function __construct(string $apiKey,
        array $extraQueryParams,
        ClientInterface $baseHttpClient,
        RequestFactoryInterface $requestFactory,
        string $baseUrl = self::CONFIG_BASE,
        string $resource = self::RAC_ENDPOINT)
    {
        // Our HTTP Client needs to be able to follow redirects.
        $this->httpClient = new RedirectClientDecorator($baseHttpClient);
        $this->baseUrl = $baseUrl;
        $this->requestFactory = $requestFactory;
        $this->resource = $resource;
        $this->queryParams = [
            'apiKey' => $apiKey, ...$extraQueryParams
        ];
    }

    /**
     * @throws ClientExceptionInterface|HttpRequestException
     */
    public function get(): string
    {
        // Prepare the URL with query params
        $resourceURI = $this->baseUrl . '/' . ltrim($this->resource, '/') . '?' . http_build_query($this->queryParams);

        $request = $this->requestFactory->createRequest('GET', $resourceURI);

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            $this->handleHttpError($response->getStatusCode(), $response->getBody());
        }

        return $response->getBody();
    }

    /**
     * @param int $status
     * @param string $error
     *
     * @throws HttpRequestException
     */
    private function handleHttpError(int $status, string $error)
    {
        $this->isUnauthorized = $status === 401;
        $isRecoverable = $this->isHttpErrorRecoverable($status);
        throw new HttpRequestException($error, $status, $isRecoverable);
    }

    /**
     * @param int $status
     *
     * @return bool
     */
    private function isHttpErrorRecoverable(int $status): bool
    {
        if ($status >= RFC7231::BAD_REQUEST && $status < RFC7231::INTERNAL_SERVER_ERROR) {
            return $status === RFC7231::CONFLICT || $status === RFC7231::REQUEST_TIMEOUT;
        }
        return true;
    }
}