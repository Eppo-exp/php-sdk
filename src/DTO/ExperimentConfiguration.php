<?php

namespace Eppo\DTO;

class ExperimentConfiguration
{
    /** @var string */
    public $name = '';

    /** @var bool */
    public $enabled = false;

    /** @var int */
    public $subjectShards = 0;

    /** @var array */
    public $overrides = [];

    /** @var array */
    public $allocations = [];

    /** @var array */
    public $rules = [];
}