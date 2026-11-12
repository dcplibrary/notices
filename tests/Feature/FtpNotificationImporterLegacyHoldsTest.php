<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\PolarisHoldNotification;
use Dcplibrary\Notices\Services\FtpNotificationImporter;
use Dcplibrary\Notices\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FtpNotificationImporterLegacyHoldsTest extends TestCase
{
    #[Test]
    public function it_imports_legacy_compact_holds_rows_without_datetime_errors(): void
    {
        $line = '891273|48304|868388|2|8|3|1530496|A Traitor in Whitehall|2025-11-29|3|2025-12-03|20000006373155|2025-12-01 08:05:01.487|30000007876758|DCPL|Daviess County Public Library|JEFFREY|FILBECK|5555040811|NULL|0|6|0|2025-11-24|NULL|1|0|NULL|0';

        $tmpPath = tempnam(sys_get_temp_dir(), 'legacy_holds_');
        $path    = $tmpPath . '.txt';
        rename($tmpPath, $path);
        file_put_contents($path, $line . PHP_EOL);

        try {
            $importer = new class extends FtpNotificationImporter
            {
                public function importHoldsForTest(string $path): void
                {
                    $this->importHoldsFile($path);
                }
            };

            $importer->importHoldsForTest($path);

            $row = PolarisHoldNotification::query()->first();

            $this->assertNotNull($row);
            $this->assertSame(8, (int) $row->delivery_option_id);
            $this->assertSame(2, (int) $row->notification_type_id);
            $this->assertSame('A Traitor in Whitehall', $row->title);
            $this->assertSame('2025-12-01 08:05:01.487', $row->export_datetime);
            $this->assertSame('20000006373155', $row->patron_barcode);
            $this->assertSame('30000007876758', $row->item_barcode);

            // Compact rows have no explicit notification creation datetime column.
            $this->assertNull($row->notification_creation_date);
        } finally {
            @unlink($path);
        }
    }
}
