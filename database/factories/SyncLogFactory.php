<?php

namespace Dcplibrary\Notices\Database\Factories;

use Dcplibrary\Notices\Models\SyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class SyncLogFactory extends Factory
{
    protected $model = SyncLog::class;

    public function definition(): array
    {
        $startedAt = Carbon::now()->subMinutes($this->faker->numberBetween(1, 60));

        return [
            'operation_type'    => 'import_polaris',
            'status'            => 'completed',
            'started_at'        => $startedAt,
            'completed_at'      => $startedAt->copy()->addSeconds($this->faker->numberBetween(5, 300)),
            'duration_seconds'  => $this->faker->numberBetween(5, 300),
            'results'           => [],
            'error_message'     => null,
            'records_processed' => 0,
            'user_id'           => null,
        ];
    }

    public function operation(string $type): self
    {
        return $this->state(fn () => ['operation_type' => $type]);
    }

    public function withResults(array $results, int $records = 0): self
    {
        return $this->state(fn () => [
            'results'           => $results,
            'records_processed' => $records,
        ]);
    }

    public function failed(string $message): self
    {
        return $this->state(fn () => [
            'status'        => 'failed',
            'error_message' => $message,
        ]);
    }
}
