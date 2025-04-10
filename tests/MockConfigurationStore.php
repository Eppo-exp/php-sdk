<?php

namespace Eppo\Tests;

use Eppo\Config\Configuration;
use Eppo\Config\ConfigurationStore;
use Eppo\Tests\Config\MockCache;

class MockConfigurationStore extends ConfigurationStore
{
    public function __construct(private Configuration $config)
    {
        parent::__construct(new MockCache());
    }

    public function getConfiguration(): Configuration
    {
        return $this->config;
    }

    public function setConfiguration(Configuration $configuration): void
    {
        $this->config = $configuration;
    }
}
