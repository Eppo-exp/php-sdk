<?php

namespace Eppo\API;

use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Webclient\Extension\Redirect\RedirectClientDecorator;

/**
 * Encapsulates request logic for retrieving configuration data from the Eppo API.
 *
 * This strictly handles fulfilling requests for configuration data and makes no attempts to
 * parse the response. Errors are inspected by the handling logic to determine if requests
 * can be retried and identify when invalid API credentials are used.
 */
class APIRequestWrapper
{
    /** @var string */
    private const UFC_ENDPOINT = '/flag-config/v1/config';
    private const BANDIT_ENDPOINT = '/flag-config/v1/bandits';
    private const CONFIG_BASE = 'https://fscdn.eppo.cloud/api';

    /** HTTP status codes, named per RFC 7231, RFC 7232, and RFC 7235. */
    private const HTTP_NOT_MODIFIED = 304;
    private const HTTP_BAD_REQUEST = 400;
    private const HTTP_UNAUTHORIZED = 401;
    private const HTTP_REQUEST_TIMEOUT = 408;
    private const HTTP_CONFLICT = 409;
    private const HTTP_INTERNAL_SERVER_ERROR = 500;

    private string $baseUrl;

    public bool $isUnauthorized = false;

    private ClientInterface $httpClient;

    private array $queryParams;

    private RequestFactoryInterface $requestFactory;

    public function __construct(
        string $apiKey,
        array $extraQueryParams,
        ClientInterface $baseHttpClient,
        RequestFactoryInterface $requestFactory,
        ?string $baseUrl = null
    ) {
        // Our HTTP Client needs to be able to follow redirects.
        $this->httpClient = new RedirectClientDecorator($baseHttpClient);
        $this->baseUrl = $baseUrl ?? self::CONFIG_BASE;
        $this->requestFactory = $requestFactory;
        $this->queryParams = [
            'apiKey' => $apiKey,
            ...$extraQueryParams
        ];
    }

    /**
     * @throws HttpRequestException|InvalidApiKeyException
     */
    private function getResource(string $endpoint, ?string $lastETag = null): APIResource
    {
        try {
            // Prepare the URL with query params
            $resourceURI = $this->baseUrl . '/' . ltrim($endpoint, '/') . '?' . http_build_query(
                $this->queryParams
            );

            $request = $this->requestFactory->createRequest('GET', $resourceURI);
            if ($lastETag != null) {
                $request = $request->withAddedHeader('IF-NONE-MATCH', $lastETag);
            }

            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            // Keep the chain. The message alone loses the client exception.
            throw new HttpRequestException($e->getMessage(), 0, false, $e);
        }
        if ($response->getStatusCode() >= self::HTTP_BAD_REQUEST) {
            $this->handleHttpError($response->getStatusCode(), (string)$response->getBody());
        }

        if ($response->getStatusCode() == self::HTTP_NOT_MODIFIED) {
            // Quick Return
            return new APIResource(null, false, $lastETag);
        }

        // The server should have returned a 304 status code when `IF-NONE-MATCH` is set and the content hasn't changed.
        // The code below works the same if the server returns a new/modified payload or with an ETag matching
        // `lastEtag` (unexpected).
        $responseETag = $response->getHeader('ETag')[0] ?? null;
        // If there's no ETag header (unexpected), we need to assume the data has changed otherwise we'll never load it.
        $isModified = $responseETag == null || $lastETag != $responseETag;

        return new APIResource(
            $response->getBody()->getContents(),
            $isModified,
            $responseETag
        );
    }

    /**
     * @throws HttpRequestException|InvalidApiKeyException
     */
    public function getUFC(?string $lastETag = null): APIResource
    {
        return $this->getResource(self::UFC_ENDPOINT, $lastETag);
    }


    /**
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     */
    public function getBandits(): APIResource
    {
        return $this->getResource(self::BANDIT_ENDPOINT);
    }

    /**
     * @param int $status
     * @param string $error
     *
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     */
    private function handleHttpError(int $status, string $error)
    {
        $this->isUnauthorized = $status === self::HTTP_UNAUTHORIZED;
        $isRecoverable = $this->isHttpErrorRecoverable($status);
        if ($this->isUnauthorized) {
            throw new InvalidApiKeyException();
        }

        throw new HttpRequestException($error, $status, $isRecoverable);
    }

    /**
     * @param int $status
     *
     * @return bool
     */
    private function isHttpErrorRecoverable(int $status): bool
    {
        if ($status >= self::HTTP_BAD_REQUEST && $status < self::HTTP_INTERNAL_SERVER_ERROR) {
            return $status === self::HTTP_CONFLICT || $status === self::HTTP_REQUEST_TIMEOUT;
        }
        return true;
    }
}
