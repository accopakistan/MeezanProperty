<?php

namespace App\Services\InvestmentIndex\Collection;

use RuntimeException;

/** A collection problem: kind is robots | http | size | network | format. */
class CollectionError extends RuntimeException
{
    public function __construct(public string $kind, string $message)
    {
        parent::__construct($message);
    }
}
