<?php

namespace Dcplibrary\Notices\Contracts;

/**
 * Contract for ingesting notification-related emails via Microsoft Graph API.
 */
interface EmailIngestionInterface
{
    /**
     * Ingest all relevant emails for the given date window.
     *
     * Includes:
     * - Voice notices that were not delivered
     * - Invalid patron phone number reports
     * - Calls that generated errors
     * - Monthly and weekly summary reports
     *
     * @param \DateTimeInterface $date date used to select the ingestion window
     */
    public function ingestForDate(\DateTimeInterface $date): void;
}
