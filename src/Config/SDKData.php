<?php

namespace Eppo\Config;

class SDKData
{
    /** @var string */
    private string $sdkVersion;

    const SDK_NAME = 'eppo/php-sdk';

    public function __construct()
    {
        $this->sdkVersion  = \Composer\InstalledVersions::getPrettyVersion(self::SDK_NAME);
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
        return self::SDK_NAME;
    }
}
