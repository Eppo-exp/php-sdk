<?php

use Eppo\HttpClientInterface;
use GuzzleHttp\Client;

class GuzzleAdapter implements HttpClientInterface {
    protected $client;
    protected $baseUrl = '';
    protected $defaultParameters = [];

    public function __construct(Client $client) {
        $this->client = $client;
    }

    public function setBaseUrl($baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/'); // Ensure no trailing slash
    }

    public function setEppoParameters(array $params) {
        $this->defaultParameters = $params;
    }

    public function setTimeout($timeout) {
        $this->client->getConfig('timeout', $timeout);
    }

    public function get($url, array $options = []) {
        $url = $this->baseUrl . '/' . ltrim($url, '/'); // Concatenate base URL
        $options['query'] = array_merge($this->defaultParameters, $options['query'] ?? []);
        return $this->client->get($url, $options);
    }
}