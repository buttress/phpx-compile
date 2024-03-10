<?php

namespace Buttress;

use Generator;
use InvalidArgumentException;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard;

class Compiler extends NodeVisitorAbstract
{
    protected const NO_CLOSING_TAGS = ['meta', 'link'];

    protected string $phpxVariable = '';

    public function __construct(protected readonly Parser $parser) {}

    /**
     * @param string $input The PHP string
     * @param string $variable The name of the PHP variable that PHPX uses
     * @return string The PHP result
     */
    public function compile(string $input, string $variable = 'x'): string
    {
        $this->phpxVariable = $variable;
        try {
            $ast = $this->parser->parse($input);
        } catch (Error $error) {
            throw new CompilerError('Failed parsing file.', previous: $error);
        }

        if (!$ast) {
            return '';
        }

        $standard = new class () extends Standard {
            protected function pExpr_BinaryOp_Concat(Node\Expr\BinaryOp\Concat $node, int $precedence, int $lhsPrecedence): string
            {
                // This removes parenthesis and improves performance a bit
                return $this->pInfixOp(Node\Expr\BinaryOp\Concat::class, $node->left, '.', $node->right, 100, $lhsPrecedence);
            }
        };

        $ast = $this->process($ast);
        return "<?php\n" . $standard->prettyPrint($ast);
    }

    /**
     * @param Stmt[] $nodes
     * @return Stmt[]
     */
    protected function process(array $nodes): array
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor($this);

        return $traverser->traverse($nodes);
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\ArrayItem && $node->value instanceof MethodCall && $node->unpack) {
            $node->value->setAttribute('phpx.unpack', true);
        }

        if ($node instanceof MethodCall && $node->name->name === 'render') {
            if (count($node->args) === 1) {
                $value = $node->args[0]->value;
                if ($value instanceof MethodCall && $value->name->name === 'html') {
                    $node->setAttribute('phpx.doctype', true);
                }
            }
        }
    }

    public function leaveNode(Node $node)
    {
        if (!$node instanceof MethodCall || !$node->var instanceof Variable || $node->var->name !== $this->phpxVariable) {
            return null;
        }
        if ($node->name->name === 'render') {
            $nodes = array_map(fn($a) => $a->value, $node->args);
            if ($node->getAttribute('phpx.doctype') === true) {
                $nodes = [
                    new String_("<!DOCTYPE html>\n", ['phpx' => true]),
                    ...$nodes,
                ];
            }
            return $this->concatAll($nodes);
        }
        if ($node->name->name === 'out') {
            return new Stmt\Echo_([$this->concatAll(array_map(fn($a) => $a->value, $node->args))], ['phpx' => true]);
        }
        if ($node->name->name === 'raw') {
            $arg = $node->args[0]->value;
            $arg->setAttribute('phpx', true);
            return $arg;
        }
        if ($node->name->name === 'if' || $node->name->name === 'foreach') {
            return $this->simpleFuncCall('implode', [new String_(''), $node]);
        }

        if ($node->name->name === 'with') {
            if ($node->getAttribute('phpx.unpack') === true) {
                return $this->simpleFuncCall('implode', [new String_(''), $node]);
            }

            $node->setAttribute('phpx', true);
            return null;
        }

        /** @var Node\Expr\Array_|null $children */
        $child = null;
        $attributes = [];
        foreach ($node->args as $arg) {
            if ($arg instanceof Node\VariadicPlaceholder) {
                exit;
            }
            if ($arg instanceof Node\Arg) {
                if ($arg->name->name === 'attributes') {
                    $attributes[] = new String_(" ", ['phpx' => true]);
                    $attributes[] = $this->simpleFuncCall('implode', [
                        new String_(' '),
                        $this->simpleFuncCall('array_map', [
                            new Node\Expr\ArrowFunction([
                                'static' => true,
                                'params' => [
                                    new Node\Param(new Variable('value'), type: new Node\Name('string')),
                                    new Node\Param(new Variable('key'), type: new Node\Name('string')),
                                ],

                                'expr' => $this->concatAll([
                                    $this->makeSafe(new Variable('key', ['phpx' => true]), SpecialCharsMode::AttributeKey),
                                    new String_('="'),
                                    $this->makeSafe(new Variable('value', ['phpx' => true]), SpecialCharsMode::AttributeValue),
                                    new String_('"'),
                                ])
                            ]),
                            (
                                $arg->value instanceof Node\Expr\Array_
                                ? $arg->value
                                : new Node\Expr\Array_([
                                    new Node\ArrayItem($arg->value, attributes: ['phpx' => true], unpack: true),
                                ], ['phpx' => true])
                            ),
                            $this->simpleFuncCall('array_keys', [
                                new Node\Expr\Array_([
                                    new Node\ArrayItem($arg->value, attributes: ['phpx' => true], unpack: true),
                                ], ['phpx' => true]),
                            ])
                        ]),
                    ]);
                } else {
                    if ($arg->name->name === 'c') {
                        $child = $arg->value;
                    } elseif ($arg->value->getAttribute('phpx') !== true) {
                        $attributes[] = new String_(" {$arg->name->name}=\"", ['phpx' => true]);
                        $attributes[] = $this->makeSafe($arg->value, SpecialCharsMode::AttributeValue);
                        $attributes[] = new String_('"', ['phpx' => true]);
                    }
                }
            }
        }

        $children = [];
        if ($child instanceof Node\Expr\Array_) {
            foreach ($child->items as $item) {
                $children[] = $item->value;
            }
        } elseif ($child) {
            $children[] = $child;
        }

        if (in_array(strtolower($node->name->name), self::NO_CLOSING_TAGS)) {
            return $this->concatAll([
                new String_('<' . strtolower($node->name->name), ['phpx' => true]),
                ...$attributes,
                new String_('>', ['phpx' => true]),
            ]);
        }

        return $this->concatAll([
            new String_('<' . $node->name->name, ['phpx' => true]),
            ...$attributes,
            new String_('>', ['phpx' => true]),
            ...$this->sanitizedChildren($this->expandedChildren($children)),
            new String_('</' . $node->name->name . '>', ['phpx' => true])
        ]);
    }

    protected function makeSafe(Node $node, SpecialCharsMode $mode): Node|FuncCall
    {
        if ($node->getAttribute('phpx') === true) {
            return $node;
        }

        if ($node instanceof String_) {
            $node->value = htmlspecialchars($node->value, $mode->value);
            $node->setAttribute('phpx', true);
            return $node;
        }
        return $this->simpleFuncCall('htmlspecialchars', [
            $node,
            new Int_($mode->value),
        ]);
    }

    protected function sanitizedChildren(iterable $children): Generator
    {
        foreach ($children as $child) {
            if (!$child) {
                continue;
            }

            yield $this->makeSafe($child, SpecialCharsMode::Content);
        }
    }

    public function expandedChildren(iterable $children): Generator
    {
        foreach ($children as $child) {
            if ($child instanceof Node\Expr\Array_) {
                foreach ($child->items as $item) {
                    yield $item->value;
                }
                continue;
            }

            yield $child;
        }
    }

    private function concatAll(array $items): Node\Expr
    {
        $count = count($items);
        if ($count === 0) {
            throw new InvalidArgumentException('Cant concat empty array.');
        }

        $left = array_shift($items);

        if (count($items) === 0) {
            return $left;
        }

        $right = $this->concatAll($items);
        if ($left instanceof String_) {
            if ($right instanceof String_) {
                $left->value .= $right->value;
                return $left;
            }

            if ($right instanceof Node\Expr\BinaryOp\Concat && $right->left instanceof String_) {
                $left->value .= $right->left->value;
                $right = $right->right;
            }
        }

        return new Node\Expr\BinaryOp\Concat($left, $right, ['phpx' => true]);
    }

    private function simpleFuncCall(string $name, array $args): FuncCall
    {
        $attr = ['phpx' => true];
        return new FuncCall(new Node\Name($name, $attr), array_map(fn($e) => new Node\Arg($e), $args), $attr);
    }
}
