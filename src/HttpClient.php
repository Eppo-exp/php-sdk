<?php

namespace Eppo;

use Eppo\Config\SDKData;
use Eppo\Exception\HttpRequestException;
use Teapot\StatusCode;

class HttpClient
{
    /** @var int */
    const REQUEST_TIMEOUT = 5;

    /** @var string */
    protected $baseUrl;

    /** @var bool */
    public $isUnauthorized = false;

    /** @var array */
    private $sdkParams = [];

    /**
     * @param string $baseUrl
     * @param string $apiKey
     * @param SDKData $SDKData
     */
    public function __construct(string $baseUrl, string $apiKey, SDKData $SDKData)
    {
        if (!$baseUrl) {
            $baseUrl = 'https://fscdn.eppo.cloud';
        }
        $this->baseUrl = $baseUrl;

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
     * @throws HttpRequestException
     */
    public function get($resource): string
    {
        $ch = curl_init();

        // Prepare the URL with query params
        $url = $this->baseUrl . '/' . ltrim($resource, '/') . '?' . http_build_query($this->sdkParams);
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT);
        
        $output = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new HttpRequestException(curl_error($ch), curl_errno($ch));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            $this->handleHttpError($status, $output);
        }

        return $output;
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