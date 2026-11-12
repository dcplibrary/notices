<?php

namespace Dcplibrary\Notices\Contracts;

/**
 * Contract for reconciling and deduplicating notifications across all
 * imported data sources.
 */
interface NotificationReconcilerInterface
{
    /**
     * Deduplicate notifications for the given date window.
     *
     * @param \DateTimeInterface $date date window to deduplicate
     */
    public function deduplicate(\DateTimeInterface $date): void;

    /**
     * Reconcile statuses (generated, delivered, failed, etc.) across all sources.
     *
     * @param \DateTimeInterface $date date window to reconcile
     */
    public function reconcile(\DateTimeInterface $date): void;
}
