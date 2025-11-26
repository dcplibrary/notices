<?php

namespace Dcplibrary\Notices\Services;

use Carbon\Carbon;

/**
 * Data class representing the verification status of a notice.
 *
 * Tracks the complete lifecycle: created → submitted → verified → delivered
 */
class VerificationResult
{
    public bool $created = false;

    public ?Carbon $created_at = null;

    public bool $submitted = false;

    public ?Carbon $submitted_at = null;

    public ?string $submission_file = null;

    public bool $verified = false;

    public ?Carbon $verified_at = null;

    public ?string $verification_file = null;

    public bool $delivered = false;

    public ?Carbon $delivered_at = null;

    public ?string $delivery_status = null;

    public ?string $failure_reason = null;

    /**
     * Overall status: success, failed, pending, partial.
     */
    public string $overall_status = 'pending';

    /**
     * Array of timeline events.
     */
    public array $timeline = [];

    /**
     * Create a new verification result.
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        $this->determineOverallStatus();
    }

    /**
     * Determine the overall status based on verification steps.
     */
    public function determineOverallStatus(): void
    {
        if ($this->delivered && $this->delivery_status === 'Delivered') {
            $this->overall_status = 'success';
        } elseif ($this->delivery_status === 'Failed' || $this->failure_reason) {
            $this->overall_status = 'failed';
        } elseif ($this->created && !$this->submitted) {
            $this->overall_status = 'pending';
        } elseif ($this->submitted && !$this->delivered) {
            $this->overall_status = 'partial';
        } else {
            $this->overall_status = 'unknown';
        }
    }

    /**
     * Add a timeline event.
     */
    public function addTimelineEvent(string $step, ?Carbon $timestamp, string $source, array $details = []): void
    {
        $this->timeline[] = [
            'step' => $step,
            'timestamp' => $timestamp?->toISOString(),
            'source' => $source,
            'details' => $details,
        ];
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'verification' => [
                'created' => $this->created,
                'submitted' => $this->submitted,
                'verified' => $this->verified,
                'delivered' => $this->delivered,
                'overall_status' => $this->overall_status,
            ],
            'timestamps' => [
                'created_at' => $this->created_at?->toISOString(),
                'submitted_at' => $this->submitted_at?->toISOString(),
                'verified_at' => $this->verified_at?->toISOString(),
                'delivered_at' => $this->delivered_at?->toISOString(),
            ],
            'status' => [
                'delivery_status' => $this->delivery_status,
                'failure_reason' => $this->failure_reason,
            ],
            'files' => [
                'submission_file' => $this->submission_file,
                'verification_file' => $this->verification_file,
            ],
            'timeline' => $this->timeline,
        ];
    }

    /**
     * Get a human-readable status message.
     */
    public function getStatusMessage(): string
    {
        return match($this->overall_status) {
            'success' => '✅ Notice verified and delivered successfully',
            'failed' => '❌ Notice delivery failed' . ($this->failure_reason ? ": {$this->failure_reason}" : ''),
            'pending' => '⏳ Notice created but not yet submitted',
            'partial' => '🔄 Notice submitted but delivery pending',
            default => '❓ Verification status unknown',
        };
    }
}
