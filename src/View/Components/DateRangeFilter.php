<?php

namespace Dcplibrary\Notices\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * x-notices::date-range-filter.
 *
 * Wrapper class so the existing anonymous Blade view can be resolved via
 * the notices component namespace when using <x-notices::date-range-filter />.
 * All behavior lives in the Blade file.
 */
class DateRangeFilter extends Component
{
    public function __construct(
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public string $quickRange = 'all',
        public bool $showQuickSelect = true,
        public bool $autoSubmit = true,
        public string $variant = 'inline',
    ) {
        // No-op; properties are passed through to the Blade view.
    }

    public function render(): View
    {
        return view('notices::components.date-range-filter');
    }
}
