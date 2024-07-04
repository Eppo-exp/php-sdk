<?php

namespace Eppo\DTO;

interface IDeserializable
{
    public static function fromJson($json): IDeserializable;
}
