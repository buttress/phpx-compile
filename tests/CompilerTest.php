<?php

dataset('compiletime', function () {
    try {
        $compiler = new \Phpx\Compile\Compiler((new \PhpParser\ParserFactory())->createForHostVersion());
        $contents = file_get_contents(__DIR__ . '/fixtures/compile_input.php');
        $time = timing_us(function () use (&$output, $contents, $compiler) {
            $output = $compiler->compile($contents, 'x');
        });

        expect($output)->toStartWith("<?php\n\$features");
        $compiled = eval(substr($output, 6));
        $raw = require __DIR__ . '/fixtures/compile_input.php';
    } catch (\Throwable $e) {
        ob_end_flush();
        echo $e;
        die(1);
    }

    return [
        format_us($time) => [$raw, $compiled],
    ];
});

function normalize_html(string $html)
{
    $html = preg_replace('~<pre.+</pre>~s', '<pre></pre>', $html);
    $doc = new DOMDocument(1.0, 'UTF-8');
    $doc->formatOutput = true;
    if (function_exists('libxml_use_internal_errors')) {
        libxml_use_internal_errors(true);
    }
    if (!$doc->loadHTML($html)) {
        throw new \RuntimeException('Unable to normalize HTML.');
    }

    $result = $doc->saveHTML();
    if (str_starts_with($result, '<!DOCTYPE')) {
        return substr($result, strpos($result, '<html'));
    }

    return trim($result);
}

it('compiles properly', function ($a, $b) {
    expect(normalize_html($a))
        ->toBe(normalize_html($b));
})->with('compiletime');

dataset('performance', function () {
    try {
        $compiler = new \Phpx\Compile\Compiler((new \PhpParser\ParserFactory())->createForHostVersion());
        $contents = file_get_contents(__DIR__ . '/fixtures/compile_input.php');
        $output = $compiler->compile($contents, 'x');

        $out = tempnam(sys_get_temp_dir(), 'phpx-out');
        try {
            file_put_contents($out, $output);
            $raw = timing_us(fn() => require __DIR__ . '/fixtures/compile_input.php', 1000);
            ob_end_clean();
            file_put_contents('/tmp/test.php', file_get_contents($out));

            $compiled = timing_us(fn() => require $out, 1000);
        } finally {
            unlink($out);
        }

        $percent = round($compiled / $raw * 100, 2);
    } catch (\Throwable $e) {
        ob_end_flush();
        echo $e;
        die(1);
    }
    return [
        sprintf('Compiled version took %s%% as much time', $percent) => [$raw, $compiled, $percent],
    ];
});

it('performs well', function (float $raw, float $compiled, float $percent) {
    expect($percent)->toBeLessThanOrEqual(50);
})->with('performance');
