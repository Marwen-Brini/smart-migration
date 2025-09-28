<?php

namespace Flux\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Flux\Flux
 */
class Flux extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Flux\Flux::class;
    }
}
