<?php

namespace Eppo\Config;

class SDKData
{
    /** @var string */
    private string $sdkVersion;

    /** @var string */
    private string $sdkName;

    public function __construct()
    {
        $pkgDef = json_decode(file_get_contents(
            __DIR__ . '/../../composer.json'
        ), true);
        $this->sdkName = $pkgDef['name'];
        $this->sdkVersion = $pkgDef['version'];
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
