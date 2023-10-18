<?php

namespace Eppo\Config;

class SDKData
{

    const SDK_VERSION = '1.3.0';
    const SDK_NAME = 'eppo-php-sdk';

    /**
     * @return string
     */
    public function getSdkVersion(): string
    {
        return self::SDK_VERSION;;
    }

    /**
     * @return string
     */
    public function getSdkName(): string
    {
        return self::SDK_NAME;
    }
}
