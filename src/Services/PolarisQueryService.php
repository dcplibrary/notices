<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\Polaris\Patron;
use Dcplibrary\Notices\Models\Polaris\ItemRecord;
use Dcplibrary\Notices\Models\Polaris\BibliographicRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Polaris Query Service
 *
 * Provides convenient methods for querying patron and item data from Polaris.
 * Implements caching to reduce load on the Polaris database.
 */
class PolarisQueryService
{
    /**
     * Cache duration in minutes.
     */
    protected int $cacheDuration = 60;

    /**
     * Get patron details by patron ID.
     *
     * @param int $patronId
     * @return Patron|null
     */
    public function getPatron(int $patronId): ?Patron
    {
        try {
            return Cache::remember("polaris:patron:{$patronId}", $this->cacheDuration, function () use ($patronId) {
                return Patron::byPatronId($patronId)->first();
            });
        } catch (\Exception $e) {
            Log::error("Failed to fetch patron {$patronId} from Polaris", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get patron details by barcode.
     *
     * @param string $barcode
     * @return Patron|null
     */
    public function getPatronByBarcode(string $barcode): ?Patron
    {
        try {
            return Cache::remember("polaris:patron:barcode:{$barcode}", $this->cacheDuration, function () use ($barcode) {
                return Patron::byBarcode($barcode)->first();
            });
        } catch (\Exception $e) {
            Log::error("Failed to fetch patron barcode {$barcode} from Polaris", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get item details by item record ID.
     *
     * @param int $itemRecordId
     * @return ItemRecord|null
     */
    public function getItem(int $itemRecordId): ?ItemRecord
    {
        try {
            return Cache::remember("polaris:item:{$itemRecordId}", $this->cacheDuration, function () use ($itemRecordId) {
                return ItemRecord::with('bibliographic')->byItemId($itemRecordId)->first();
            });
        } catch (\Exception $e) {
            Log::error("Failed to fetch item {$itemRecordId} from Polaris", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get item details by barcode.
     *
     * @param string $barcode
     * @return ItemRecord|null
     */
    public function getItemByBarcode(string $barcode): ?ItemRecord
    {
        try {
            return Cache::remember("polaris:item:barcode:{$barcode}", $this->cacheDuration, function () use ($barcode) {
                return ItemRecord::with('bibliographic')->byBarcode($barcode)->first();
            });
        } catch (\Exception $e) {
            Log::error("Failed to fetch item barcode {$barcode} from Polaris", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get bibliographic record details.
     *
     * @param int $bibRecordId
     * @return BibliographicRecord|null
     */
    public function getBibRecord(int $bibRecordId): ?BibliographicRecord
    {
        try {
            return Cache::remember("polaris:bib:{$bibRecordId}", $this->cacheDuration, function () use ($bibRecordId) {
                return BibliographicRecord::find($bibRecordId);
            });
        } catch (\Exception $e) {
            Log::error("Failed to fetch bib record {$bibRecordId} from Polaris", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get items associated with a notification.
     * Queries Polaris for hold/overdue item details if available.
     *
     * @param int $patronId
     * @param int $notificationTypeId
     * @param \Carbon\Carbon $notificationDate
     * @return \Illuminate\Support\Collection
     */
    public function getNotificationItems(int $patronId, int $notificationTypeId, \Carbon\Carbon $notificationDate): \Illuminate\Support\Collection
    {
        try {
            $items = collect();

            // For hold notifications (type 2, 18), query hold records
            if (in_array($notificationTypeId, [2, 18])) {
                $holds = \DB::connection('polaris')
                    ->table('Polaris.Polaris.SysHoldRequests')
                    ->where('PatronID', $patronId)
                    ->where('ActivationDate', '>=', $notificationDate->copy()->subDays(3))
                    ->where('ActivationDate', '<=', $notificationDate->copy()->addDays(1))
                    ->get();

                foreach ($holds as $hold) {
                    if (isset($hold->ItemRecordID) && $hold->ItemRecordID) {
                        $item = $this->getItem($hold->ItemRecordID);
                        if ($item) {
                            $items->push($item);
                        }
                    }
                }
            }

            // For overdue notifications (type 1, 12, 13), query overdue items from ItemCheckouts
            // ItemCheckouts.DueDate contains the actual due date for checked-out items
            if (in_array($notificationTypeId, [1, 12, 13])) {
                $overdues = \DB::connection('polaris')
                    ->table('Polaris.Polaris.ItemCheckouts')
                    ->where('PatronID', $patronId)
                    ->where('DueDate', '<', $notificationDate)
                    ->whereNull('CheckInDate')
                    ->get();

                foreach ($overdues as $overdue) {
                    if (isset($overdue->ItemRecordID) && $overdue->ItemRecordID) {
                        $item = $this->getItem($overdue->ItemRecordID);
                        if ($item) {
                            $items->push($item);
                        }
                    }
                }
            }

            return $items;
        } catch (\Exception $e) {
            Log::error("Failed to fetch notification items", [
                'patron_id' => $patronId,
                'notification_type' => $notificationTypeId,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Clear cached data for a specific patron.
     *
     * @param int $patronId
     * @return void
     */
    public function clearPatronCache(int $patronId): void
    {
        Cache::forget("polaris:patron:{$patronId}");
    }

    /**
     * Clear cached data for a specific item.
     *
     * @param int $itemRecordId
     * @return void
     */
    public function clearItemCache(int $itemRecordId): void
    {
        Cache::forget("polaris:item:{$itemRecordId}");
    }
}
