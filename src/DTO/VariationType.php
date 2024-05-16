<?php

namespace Eppo\DTO;

enum VariationType: string
{
    case STRING = 'string';
    case BOOLEAN = 'BOOLEAN';
    case NUMERIC = 'NUMERIC';
    case JSON = 'JSON';
}
