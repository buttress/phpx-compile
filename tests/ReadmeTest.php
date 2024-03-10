<?php

// Test the readme snippets

test('readme example', function () {
    $compiler = new \Buttress\Compiler((new \PhpParser\ParserFactory())->createForVersion(\PhpParser\PhpVersion::fromComponents(8, 3)));


    expect($compiler->compile(file_get_contents(__DIR__ . '/fixtures/readme_1.php'), 'x'))
        ->toBe(<<<'EXPECT'
            <?php
            $x = new Buttress\PHPX();
            $github = 'https://github.com/buttress/phpx';
            return '<div class="content"><h1 id="title">Hello World!</h1>'.'<p>Brought to you by '.'<a href="'.htmlspecialchars($github, 50).'">PHPX</a>'.'</p>'.'</div>';
            EXPECT);
});
