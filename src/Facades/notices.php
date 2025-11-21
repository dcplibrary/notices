<?php

namespace Dcplibrary\notices\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Dcplibrary\notices\notices
 */
class notices extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'notices';
    }
}
