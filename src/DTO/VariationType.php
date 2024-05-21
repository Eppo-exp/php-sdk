<?php

namespace Eppo\DTO;

enum VariationType: string
{
    case STRING = 'STRING';
    case BOOLEAN = 'BOOLEAN';
    case NUMERIC = 'NUMERIC';
    case JSON = 'JSON';
}
