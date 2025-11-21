<?php

namespace Dcplibrary\notices;

class notices
{
    /**
     * Create a new notices instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the package version.
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * Get the package name.
     */
    public function name(): string
    {
        return 'notices';
    }
}
