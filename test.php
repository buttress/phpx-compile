<?php

require_once __DIR__ . '/vendor/autoload.php';

$compiler = new \Buttress\Compiler((new \PhpParser\ParserFactory())->createForNewestSupportedVersion());

file_put_contents(__DIR__ . '/output.php', $compiler->compile(file_get_contents(__DIR__ . '/input.php'), 'x'));
