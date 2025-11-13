<?php

require __DIR__ . '/vendor/autoload.php';

use Dcplibrary\Notices\Services\ShoutbombSubmissionParser;

$parser = new ShoutbombSubmissionParser();
$samplesDir = '/Users/blashbrook/Sites/notices/claude-2025-11-06/shoutbomb-samples';

echo "=== TESTING HOLDS ===\n";
$holdsFile = $samplesDir . '/holds_submitted_2025-11-10_08-05-01.txt';
$holds = $parser->parseHoldsFile($holdsFile);
echo "Parsed " . count($holds) . " holds records\n";
if (count($holds) > 0) {
    echo "First record:\n";
    print_r($holds[0]);
}

echo "\n=== TESTING OVERDUE ===\n";
$overdueFile = $samplesDir . '/overdue_submitted_2025-11-10_08-04-01.txt';
$overdues = $parser->parseOverdueFile($overdueFile);
echo "Parsed " . count($overdues) . " overdue records\n";
if (count($overdues) > 0) {
    echo "First record:\n";
    print_r($overdues[0]);
}

echo "\n=== TESTING RENEW ===\n";
$renewFile = $samplesDir . '/renew_submitted_2025-11-10_08-03-01.txt';
$renews = $parser->parseRenewFile($renewFile);
echo "Parsed " . count($renews) . " renew records\n";
if (count($renews) > 0) {
    echo "First record:\n";
    print_r($renews[0]);
}

echo "\n=== TESTING VOICE PATRONS ===\n";
$voiceFile = $samplesDir . '/voice_patrons_submitted_2025-11-10_04-00-01.txt';
$voicePatrons = $parser->parsePatronList($voiceFile);
echo "Parsed " . count($voicePatrons) . " voice patrons\n";
if (count($voicePatrons) > 0) {
    echo "First 3 patrons:\n";
    $count = 0;
    foreach ($voicePatrons as $barcode => $phone) {
        echo "  Barcode: $barcode => Phone: $phone\n";
        if (++$count >= 3) break;
    }
}

echo "\n=== TESTING TEXT PATRONS ===\n";
$textFile = $samplesDir . '/text_patrons_submitted_2025-11-10_05-00-01.txt';
$textPatrons = $parser->parsePatronList($textFile);
echo "Parsed " . count($textPatrons) . " text patrons\n";
if (count($textPatrons) > 0) {
    echo "First 3 patrons:\n";
    $count = 0;
    foreach ($textPatrons as $barcode => $phone) {
        echo "  Barcode: $barcode => Phone: $phone\n";
        if (++$count >= 3) break;
    }
}

echo "\n=== TESTING MATCHING ===\n";
if (count($holds) > 0) {
    $testBarcode = $holds[0]['patron_barcode'];
    echo "Testing holds patron barcode: '$testBarcode'\n";
    if (isset($voicePatrons[$testBarcode])) {
        echo "  ✓ FOUND in voice patrons!\n";
    } elseif (isset($textPatrons[$testBarcode])) {
        echo "  ✓ FOUND in text patrons!\n";
    } else {
        echo "  ✗ NOT FOUND in either patron list\n";
    }
}

if (count($overdues) > 0) {
    $testBarcode = $overdues[0]['patron_barcode'];
    echo "Testing overdue patron barcode: '$testBarcode'\n";
    if (isset($voicePatrons[$testBarcode])) {
        echo "  ✓ FOUND in voice patrons!\n";
    } elseif (isset($textPatrons[$testBarcode])) {
        echo "  ✓ FOUND in text patrons!\n";
    } else {
        echo "  ✗ NOT FOUND in either patron list\n";
    }
}
