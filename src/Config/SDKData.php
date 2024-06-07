<?php

namespace Eppo\Config;

class SDKData
{
    private const SDK_LANGUAGE = "php";
    private const SDK_NAME = 'eppo/php-sdk';

    /** @var string */
    private string $sdkVersion;


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

    public function asArray(): array {
        return [
            "sdkVersion" => $this->sdkVersion,
            "sdkName" => self::SDK_NAME,
            "sdkLanguage"=>self::SDK_LANGUAGE
        ];
    }
}
