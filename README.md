<p align="center">
    <sub><sup><code lang="html">&lt;PHPX compile="true"/&gt;</code></sup></sub>
</p>
<p align="center">
    An experimental compiler for PHPX
</p>
<hr>
**PHPX Compile** compiles [PHPX](https://github.com/buttress/phpx) into more optimized PHP significantly reducing function
calls when rendering PHPX files. With the example used in tests we see about a 6x speed improvement over plain PHPX

Compiled PHPX files are still plain PHP and can be swapped out for existing templates.

> [!warning]
> This project is experimental and has not been proven. Expect PHPX Compile to not always produce working output.

---

```php
<?php

$x = new Buttress\PHPX();
$github = 'https://github.com/buttress/phpx';

return $x->render(
    $x->div(class: 'content', c: [
        $x->h1(id: 'title', c: 'Hello World!'),
        $x->p(c: [
            'Brought to you by ',
            $x->a(href: $github, c: 'PHPX')
        ]),
    ])
);
```

becomes

```php
<?php
$x = new Buttress\PHPX();
$github = 'https://github.com/buttress/phpx';
return '<div class="content"><h1 id="title">Hello World!</h1>'.'<p>Brought to you by '.'<a href="'.htmlspecialchars($github, 50).'">PHPX</a>'.'</p>'.'</div>';
```

## Installation

To install PHPX Compile, use composer:

```bash
composer require buttress/phpx-compile
```

## Usage

```php
/** Basic Usage */

$parser = (new \PhpParser\ParserFactory())->createForHostVersion();
$compiler = new \Buttress\Compiler($parser);

$phpxVariable = 'x'; // The variable name I use in my phpx files.
$input = file_get_contents('/my_file_that_uses_phpx');
$php = $compiler->compile($input, $phpxVariable);
```

## Related Projects
- [PHPX](https://github.com/buttress/phpx) A fluent DOMDocument wrapper that makes it easy to write safe valid HTML in plain PHP.
- [PHPX Templates](https://github.com/buttress/phpx-templates) An experimental template engine built around PHPX and PHPX Compile.

## Contributing

Contributions to PHPX Compile are always welcome! Feel free to fork the repository and submit a pull request.

## License

PHPX is released under the MIT License.

## Githooks
To add our githooks and run tests before commit:
```bash
git config --local core.hooksPath .githooks
```

## Support

If you encounter any problems or have any questions, please open an issue on GitHub.

Thanks for checking out PHPX Compile ❤️