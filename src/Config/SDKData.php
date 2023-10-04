<?php

namespace Eppo\Config;

use Composer\InstalledVersions;

class SDKData
{
    /** @var string */
    private $sdkVersion;

    /** @var string */
    private $sdkName;

    public function __construct()
    {
        $this->sdkVersion = '1.2.1';
        $this->sdkName = 'php-sdk';
    }

    /**
     * @return string
     */
    public function getSdkVersion(): string
    {
        return $this->sdkVersion;
    }

    /**
     * @return string
     */
    public function getSdkName(): string
    {
        return $this->sdkName;
    }
}
