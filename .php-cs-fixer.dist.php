<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/docs')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        // Laravel-ish preferences
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],
        'concat_space' => ['spacing' => 'one'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'fully_qualified_strict_types' => true,
        'no_unused_imports' => true,
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,
        // PHPDoc
        'phpdoc_align' => ['align' => 'vertical'],
        'phpdoc_order' => true,
        'phpdoc_separation' => true,
        'phpdoc_trim' => true,
        // Misc
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
    ]);
