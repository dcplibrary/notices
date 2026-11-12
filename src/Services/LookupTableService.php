<?php

namespace Dcplibrary\Notices\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for retrieving lookup tables with override support.
 *
 * Provides methods to get enabled lookup table records with custom labels
 * applied from lookup_table_overrides. Used by controllers for dropdown
 * filters and selection inputs.
 */
class LookupTableService
{
    /**
     * Get enabled notification types for dropdown filters.
     *
     * Returns only enabled notification types (respects lookup_table_overrides)
     * with custom labels applied if they exist.
     *
     * @return Collection Collection of objects with 'id' and 'display_label' properties
     */
    public function getEnabledNotificationTypes(): Collection
    {
        return DB::table('notification_types as nt')
            ->leftJoin('lookup_table_overrides as lto', function ($join) {
                $join->on('nt.id', '=', 'lto.record_id')
                    ->where('lto.table_name', '=', 'notification_types')
                ;
            })
            ->select('nt.id', DB::raw('COALESCE(lto.custom_label, nt.description) as display_label'))
            ->where(function ($query) {
                $query->whereNull('lto.is_enabled')  // No override = enabled
                    ->orWhere('lto.is_enabled', '=', 1)  // Override with enabled
                ;
            })
            ->orderBy('display_label')
            ->get()
        ;
    }

    /**
     * Get enabled delivery options for dropdown filters.
     *
     * Returns only enabled delivery options (respects lookup_table_overrides)
     * with custom labels applied if they exist.
     *
     * @return Collection Collection of objects with 'id' and 'display_label' properties
     */
    public function getEnabledDeliveryOptions(): Collection
    {
        return DB::table('delivery_options as do')
            ->leftJoin('lookup_table_overrides as lto', function ($join) {
                $join->on('do.id', '=', 'lto.record_id')
                    ->where('lto.table_name', '=', 'delivery_options')
                ;
            })
            ->select('do.id', DB::raw('COALESCE(lto.custom_label, do.delivery_option) as display_label'))
            ->where(function ($query) {
                $query->whereNull('lto.is_enabled')  // No override = enabled
                    ->orWhere('lto.is_enabled', '=', 1)  // Override with enabled
                ;
            })
            ->orderBy('display_label')
            ->get()
        ;
    }
}
