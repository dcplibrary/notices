<?php

namespace Dcplibrary\Notices\Tests\Unit;

use Dcplibrary\Notices\Support\RawPayloadExtractor;
use PHPUnit\Framework\TestCase;

class RawPayloadExtractorTest extends TestCase
{
    public function test_get_patron_first_and_last_name(): void
    {
        $rows = [
            ['patron_first_name' => '', 'patron_last_name' => ''],
            ['patron_first_name' => 'Margaret', 'patron_last_name' => 'Blashbrook'],
        ];

        $this->assertSame('Margaret', RawPayloadExtractor::getPatronFirstName($rows));
        $this->assertSame('Blashbrook', RawPayloadExtractor::getPatronLastName($rows));
    }
}
