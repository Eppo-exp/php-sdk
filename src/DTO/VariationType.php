<?php

namespace Eppo\DTO;

enum VariationType: string
{
    case STRING = 'STRING';
    case BOOLEAN = 'BOOLEAN';
    case INTEGER = 'INTEGER';
    case NUMERIC = 'NUMERIC';
    case JSON = 'JSON';
}
