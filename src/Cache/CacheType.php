<?php

namespace Eppo\Cache;

enum CacheType: string
{
    case FLAG = 'FLAG';
    case META = 'META';
    case BANDIT = 'BANDIT';
}
