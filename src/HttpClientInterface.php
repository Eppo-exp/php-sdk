<?php

namespace Eppo;

interface HttpClientInterface {
    public function get($url, array $options = []);

    public function setBaseUrl($baseUrl);
    public function setEppoParameters(array $params);
    public function setTimeout($timeout);
}
