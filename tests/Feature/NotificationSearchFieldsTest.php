<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Services\NotificationService;
use Dcplibrary\Notices\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for notification search functionality across all indexed fields.
 *
 * These tests verify that the search feature correctly finds notifications
 * by various criteria: patron name, phone, email, title, item barcode, and patron barcode.
 *
 * The search uses normalized columns for consistent matching:
 * - Names: lowercase, punctuation stripped (search_patron_first_name, search_patron_last_name)
 * - Phone: digits only (indexed_patron_phone)
 * - Email: lowercase (indexed_patron_email)
 * - Title: lowercase (search_title)
 * - Barcodes: lowercase (patron_barcode, search_item_barcode)
 */
class NotificationSearchFieldsTest extends TestCase
{
    protected NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationService::class);
    }

    #[Test]
    public function search_finds_notification_by_patron_last_name(): void
    {
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'search_patron_last_name' => 'blashbrook',
            'search_patron_first_name' => 'margaret',
            'first_exported_at' => now()->subMinute(),
        ]);

        Notification::factory()->create([
            'patron_barcode' => '20000016660002',
            'search_patron_last_name' => 'smith',
            'search_patron_first_name' => 'john',
            'first_exported_at' => now()->subMinute(),
        ]);

        $results = $this->service->searchNotifications(['search' => 'Blashbrook']);

        $this->assertSame(1, $results->total());
        $this->assertSame('20000016660001', $results->items()[0]->patron_barcode);
    }

    #[Test]
    public function search_finds_notification_by_patron_first_name(): void
    {
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'search_patron_first_name' => 'margaret',
            'search_patron_last_name' => 'blashbrook',
            'first_exported_at' => now()->subMinute(),
        ]);

        $results = $this->service->searchNotifications(['search' => 'Margaret']);

        $this->assertSame(1, $results->total());
        $this->assertSame('20000016660001', $results->items()[0]->patron_barcode);
    }

    #[Test]
    public function search_handles_names_with_apostrophes(): void
    {
        // Name with apostrophe is stored normalized (apostrophe removed)
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'search_patron_last_name' => 'obrien', // O'Brien normalized
            'first_exported_at' => now()->subMinute(),
        ]);

        // Search with apostrophe should find it
        $results = $this->service->searchNotifications(['search' => "O'Brien"]);
        $this->assertSame(1, $results->total());

        // Search without apostrophe should also find it
        $results = $this->service->searchNotifications(['search' => 'OBrien']);
        $this->assertSame(1, $results->total());
    }

    #[Test]
    public function search_handles_names_with_hyphens(): void
    {
        // Hyphenated name stored normalized (hyphen removed)
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'search_patron_first_name' => 'maryjane', // Mary-Jane normalized
            'first_exported_at' => now()->subMinute(),
        ]);

        // Search with hyphen should find it
        $results = $this->service->searchNotifications(['search' => 'Mary-Jane']);
        $this->assertSame(1, $results->total());

        // Search without hyphen should also find it
        $results = $this->service->searchNotifications(['search' => 'MaryJane']);
        $this->assertSame(1, $results->total());
    }

    #[Test]
    public function search_handles_names_with_underscores(): void
    {
        // Name with underscore stored normalized (underscore removed)
        // This handles data like "HANNAH_TCH" which should become "hannahtch"
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'search_patron_first_name' => 'hannahtch elizabeth', // HANNAH_TCH ELIZABETH normalized
            'first_exported_at' => now()->subMinute(),
        ]);

        // Search with underscore should find it
        $results = $this->service->searchNotifications(['search' => 'HANNAH_TCH']);
        $this->assertSame(1, $results->total());

        // Search without underscore should also find it
        $results = $this->service->searchNotifications(['search' => 'HANNAHTCH']);
        $this->assertSame(1, $results->total());

        // Partial search should work
        $results = $this->service->searchNotifications(['search' => 'hannah']);
        $this->assertSame(1, $results->total());
    }

    #[Test]
    public function search_finds_notification_by_phone_number(): void
    {
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'indexed_patron_phone' => '5555818536', // Stored as digits only
            'first_exported_at' => now()->subMinute(),
        ]);

        Notification::factory()->create([
            'patron_barcode' => '20000016660002',
            'indexed_patron_phone' => '5551234567',
            'first_exported_at' => now()->subMinute(),
        ]);

        // Search with formatted phone number
        $results = $this->service->searchNotifications(['search' => '(555) 581-8536']);
        $this->assertSame(1, $results->total());
        $this->assertSame('20000016660001', $results->items()[0]->patron_barcode);

        // Search with digits only
        $results = $this->service->searchNotifications(['search' => '5555818536']);
        $this->assertSame(1, $results->total());

        // Search with partial phone number
        $results = $this->service->searchNotifications(['search' => '581-8536']);
        $this->assertSame(1, $results->total());
    }

    #[Test]
    public function search_finds_notification_by_email(): void
    {
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'indexed_patron_email' => 'margaret@example.com', // Stored lowercase
            'first_exported_at' => now()->subMinute(),
        ]);

        Notification::factory()->create([
            'patron_barcode' => '20000016660002',
            'indexed_patron_email' => 'john@other.org',
            'first_exported_at' => now()->subMinute(),
        ]);

        // Search with mixed case
        $results = $this->service->searchNotifications(['search' => 'Margaret@Example.COM']);
        $this->assertSame(1, $results->total());
        $this->assertSame('20000016660001', $results->items()[0]->patron_barcode);

        // Search with partial email
        $results = $this->service->searchNotifications(['search' => 'margaret@example']);
        $this->assertSame(1, $results->total());
    }

    #[Test]
    public function search_finds_notification_by_title(): void
    {
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'search_title' => 'a traitor in whitehall', // Stored lowercase
            'first_exported_at' => now()->subMinute(),
        ]);

        Notification::factory()->create([
            'patron_barcode' => '20000016660002',
            'search_title' => 'the great gatsby',
            'first_exported_at' => now()->subMinute(),
        ]);

        // Search with mixed case
        $results = $this->service->searchNotifications(['search' => 'Whitehall']);
        $this->assertSame(1, $results->total());
        $this->assertSame('20000016660001', $results->items()[0]->patron_barcode);

        // Search with full title
        $results = $this->service->searchNotifications(['search' => 'A Traitor in Whitehall']);
        $this->assertSame(1, $results->total());
    }

    #[Test]
    public function search_finds_notification_by_item_barcode(): void
    {
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'search_item_barcode' => '30000007876758', // Stored lowercase
            'first_exported_at' => now()->subMinute(),
        ]);

        Notification::factory()->create([
            'patron_barcode' => '20000016660002',
            'search_item_barcode' => '30000007999999',
            'first_exported_at' => now()->subMinute(),
        ]);

        // Search by item barcode
        $results = $this->service->searchNotifications(['search' => '30000007876758']);
        $this->assertSame(1, $results->total());
        $this->assertSame('20000016660001', $results->items()[0]->patron_barcode);

        // Search by partial item barcode
        $results = $this->service->searchNotifications(['search' => '7876758']);
        $this->assertSame(1, $results->total());
    }

    #[Test]
    public function search_finds_notification_by_full_name(): void
    {
        // Full name search should work when first and last name are in separate fields
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'search_patron_first_name' => 'hannahtch elizabeth',
            'search_patron_last_name' => 'hallam',
            'search_full_name' => 'hannahtch elizabeth hallam', // Combined for full name search
            'first_exported_at' => now()->subMinute(),
        ]);

        // Search for full name should find it
        $results = $this->service->searchNotifications(['search' => 'HANNAH_TCH ELIZABETH HALLAM']);
        $this->assertSame(1, $results->total());

        // Partial full name should also work
        $results = $this->service->searchNotifications(['search' => 'elizabeth hallam']);
        $this->assertSame(1, $results->total());
    }

    #[Test]
    public function search_finds_notification_by_patron_barcode(): void
    {
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'first_exported_at' => now()->subMinute(),
        ]);

        Notification::factory()->create([
            'patron_barcode' => '20000016660002',
            'first_exported_at' => now()->subMinute(),
        ]);

        // Search by full patron barcode
        $results = $this->service->searchNotifications(['search' => '20000016660001']);
        $this->assertSame(1, $results->total());
        $this->assertSame('20000016660001', $results->items()[0]->patron_barcode);

        // Search by partial patron barcode
        $results = $this->service->searchNotifications(['search' => '6660001']);
        $this->assertSame(1, $results->total());
    }

    #[Test]
    public function search_returns_multiple_matches_across_fields(): void
    {
        // Notification where "Smith" appears in name
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'search_patron_last_name' => 'smith',
            'first_exported_at' => now()->subMinute(),
        ]);

        // Notification where "Smith" appears in title
        Notification::factory()->create([
            'patron_barcode' => '20000016660002',
            'search_title' => 'mr smith goes to washington',
            'first_exported_at' => now()->subMinute(),
        ]);

        // Search should find both
        $results = $this->service->searchNotifications(['search' => 'Smith']);
        $this->assertSame(2, $results->total());
    }

    #[Test]
    public function search_is_case_insensitive(): void
    {
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'search_patron_last_name' => 'blashbrook',
            'search_title' => 'the great gatsby',
            'indexed_patron_email' => 'test@example.com',
            'first_exported_at' => now()->subMinute(),
        ]);

        // Uppercase search
        $results = $this->service->searchNotifications(['search' => 'BLASHBROOK']);
        $this->assertSame(1, $results->total());

        // Mixed case search
        $results = $this->service->searchNotifications(['search' => 'BlAsHbRoOk']);
        $this->assertSame(1, $results->total());
    }

    #[Test]
    public function search_returns_empty_for_no_matches(): void
    {
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'search_patron_last_name' => 'blashbrook',
            'first_exported_at' => now()->subMinute(),
        ]);

        $results = $this->service->searchNotifications(['search' => 'NonexistentName']);
        $this->assertSame(0, $results->total());
    }

    #[Test]
    public function search_ignores_very_short_phone_searches(): void
    {
        // Phone searches with fewer than 4 digits should not match phone field
        // to avoid false positives (e.g., searching "123" shouldn't match phones)
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'indexed_patron_phone' => '1234567890',
            'search_title' => '123 main street', // Has "123" in title
            'first_exported_at' => now()->subMinute(),
        ]);

        // Search for "123" - should match title but not trigger phone search
        $results = $this->service->searchNotifications(['search' => '123']);
        $this->assertSame(1, $results->total());
    }

    #[Test]
    public function search_only_matches_email_field_when_search_contains_at_sign(): void
    {
        Notification::factory()->create([
            'patron_barcode' => '20000016660001',
            'indexed_patron_email' => 'test@example.com',
            'search_title' => 'test book',
            'first_exported_at' => now()->subMinute(),
        ]);

        // Search without @ should match title only
        $results = $this->service->searchNotifications(['search' => 'test']);
        $this->assertSame(1, $results->total());

        // Search with @ should match email
        $results = $this->service->searchNotifications(['search' => 'test@example']);
        $this->assertSame(1, $results->total());
    }
}
