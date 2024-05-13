<?php

namespace Eppo;

use Eppo\Exception\HttpRequestException;
use Http\Discovery\Psr18Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Teapot\StatusCode;
use Webclient\Extension\Redirect\RedirectClientDecorator;

class APIRequestWrapper
{
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
        string $resource,
        string $baseUrl = 'https://fscdn.eppo.cloud')
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
     * @throws ClientExceptionInterface
     */
    public function get(): string
    {
        // Prepare the URL with query params
        $resourceURI = $this->baseUrl . '/' . ltrim($this->resource, '/') . '?' . http_build_query($this->queryParams);

        $request = $this->requestFactory->createRequest('GET',$resourceURI );

        $response = $this->httpClient->sendRequest($request);

        //
//        $ch = curl_init();
//
//
//        curl_setopt($ch, CURLOPT_URL, $url);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
//        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT);
//
//        $output = curl_exec($ch);
//
//        if (curl_errno($ch)) {
//            throw new HttpRequestException(curl_error($ch), curl_errno($ch));
//        }
//
//        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        curl_close($ch);
//
//        if ($status >= 400) {
//            $this->handleHttpError($status, $output);
//        }


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
        if ($status >= StatusCode::BAD_REQUEST && $status < StatusCode::INTERNAL_SERVER_ERROR) {
            return $status === StatusCode::CONFLICT || $status === StatusCode::REQUEST_TIMEOUT;
        }
        return true;
    }
}