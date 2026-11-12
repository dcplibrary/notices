<?php

namespace Dcplibrary\Notices\Tests\Unit;

use Dcplibrary\Notices\Support\StringSanitizer;
use PHPUnit\Framework\TestCase;

class StringSanitizerTest extends TestCase
{
    public function test_normalize_returns_null_for_null(): void
    {
        $this->assertNull(StringSanitizer::normalize(null));
    }

    public function test_normalize_returns_same_for_valid_utf8(): void
    {
        $value = 'Pokémon sword. [Nintendo Switch]';

        $this->assertSame($value, StringSanitizer::normalize($value));
    }

    public function test_ascii_strips_diacritics_and_non_ascii(): void
    {
        $value = "Pokémon – édition spéciale";

        $ascii = StringSanitizer::ascii($value);

        $this->assertSame('Pokemon - edition speciale', $ascii);
    }

    public function test_sanitize_uses_normalize_logic(): void
    {
        $value = 'Pokémon sword. [Nintendo Switch]';

        // sanitize() should behave like normalize(), returning a valid UTF-8 string
        $this->assertSame(StringSanitizer::normalize($value), StringSanitizer::sanitize($value));
    }
}
