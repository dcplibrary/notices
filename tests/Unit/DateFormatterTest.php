<?php

use Carbon\Carbon;
use Dcplibrary\Notices\Support\DateFormatter;

test('format date', function () {
    $date = Carbon::parse('2026-01-15');
    $formatted = DateFormatter::formatDate($date);
    expect($formatted)->toBe('Jan 15, 2026');

    // Test different months
    $date = Carbon::parse('2026-12-25');
    $formatted = DateFormatter::formatDate($date);
    expect($formatted)->toBe('Dec 25, 2026');

    // Test single digit day
    $date = Carbon::parse('2026-01-05');
    $formatted = DateFormatter::formatDate($date);
    expect($formatted)->toBe('Jan 5, 2026');
});

test('format date with null', function () {
    $formatted = DateFormatter::formatDate(null);
    expect($formatted)->toBeNull();
});

test('format datetime', function () {
    $date = Carbon::parse('2026-01-15 14:30:00');
    $formatted = DateFormatter::formatDateTime($date);
    expect($formatted)->toBe('Jan 15, 2026 2:30 PM');

    // Test AM time
    $date = Carbon::parse('2026-01-15 09:15:00');
    $formatted = DateFormatter::formatDateTime($date);
    expect($formatted)->toBe('Jan 15, 2026 9:15 AM');

    // Test midnight
    $date = Carbon::parse('2026-01-15 00:00:00');
    $formatted = DateFormatter::formatDateTime($date);
    expect($formatted)->toBe('Jan 15, 2026 12:00 AM');

    // Test noon
    $date = Carbon::parse('2026-01-15 12:00:00');
    $formatted = DateFormatter::formatDateTime($date);
    expect($formatted)->toBe('Jan 15, 2026 12:00 PM');
});

test('format datetime with null', function () {
    $formatted = DateFormatter::formatDateTime(null);
    expect($formatted)->toBeNull();
});

test('format constants', function () {
    expect(DateFormatter::DATE_FORMAT)->toBe('M j, Y');
    expect(DateFormatter::DATETIME_FORMAT)->toBe('M j, Y g:i A');
});

test('with datetime interface', function () {
    $date = new DateTime('2026-01-15 14:30:00');
    $formatted = DateFormatter::formatDateTime($date);
    expect($formatted)->toBe('Jan 15, 2026 2:30 PM');
});
