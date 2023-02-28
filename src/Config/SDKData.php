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
        $this->sdkVersion = InstalledVersions::getRootPackage()['version'];
        $this->sdkName = InstalledVersions::getRootPackage()['name'];
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
