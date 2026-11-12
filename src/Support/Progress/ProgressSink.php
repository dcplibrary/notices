<?php

namespace Dcplibrary\Notices\Support\Progress;

/**
 * Contract for receiving progress events from the notices import pipeline.
 *
 * Implementations can render output to the console (ConsoleProgressSink),
 * broadcast WebSocket events to the UI, or simply discard the calls (null sink).
 * The pipeline calls these methods in order for each date in the import range.
 */
interface ProgressSink
{
    /**
     * Signal that processing is beginning for the given date.
     *
     * @param \DateTimeImmutable $date The calendar date being processed
     */
    public function startDay(\DateTimeImmutable $date): void;

    /**
     * Report progress for a named pipeline step.
     *
     * Known step keys: "ftp", "email", "reconcile", "failures".
     * Known statuses: "pending" (step started), "done" (step completed).
     * Extra data is step-specific (e.g. ["files" => [...]] for "ftp"/"email").
     *
     * @param string               $key    Pipeline step identifier
     * @param string               $status "pending" | "done"
     * @param array<string, mixed> $extra  Optional step-specific metadata
     */
    public function step(string $key, string $status, array $extra = []): void;

    /**
     * Signal that all pipeline steps for the given date have completed.
     *
     * @param \DateTimeImmutable $date The calendar date that was processed
     */
    public function endDay(\DateTimeImmutable $date): void;
}
