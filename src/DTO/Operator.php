<?php

namespace Eppo\DTO;

enum Operator: string
{
    case GT = 'GT';
    case GTE = 'GTE';
    case LT = 'LT';
    case LTE = 'LTE';
    case MATCHES = 'MATCHES';
    case ONE_OF = 'ONE_OF';
    case NOT_ONE_OF = 'NOT_ONE_OF';
    case IS_NULL = 'IS_NULL';
}