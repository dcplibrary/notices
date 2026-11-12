<?php

namespace Dcplibrary\Notices\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * x-notices::filter-panel.
 *
 * Standard filter shell used on list/data views:
 * - Search row (search input + Search/Clear buttons)
 * - Date Range row (x-notices::date-range-filter)
 * - Optional advanced filters section with its own Apply/Clear buttons.
 */
class FilterPanel extends Component
{
    public function __construct(
        public ?string $action = null,
        public string $method = 'GET',
        public string $searchLabel = 'Search',
        public string $searchName = 'search',
        public string $searchValue = '',
        public string $searchPlaceholder = 'Search...',
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public string $quickRange = 'all',
        public ?string $clearRoute = null,
        public bool $initialAdvancedOpen = false,
        public bool $hasAdvanced = false,
    ) {
        // No-op; properties are exposed directly to the Blade view.
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('notices::components.filter-panel');
    }
}
