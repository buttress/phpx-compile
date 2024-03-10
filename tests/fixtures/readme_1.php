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
