<?php

declare(strict_types=1);

/*
 * This file is part of the humbug/php-scoper package.
 *
 * Copyright (c) 2017 Théo FIDRY <theo.fidry@gmail.com>,
 *                    Pádraic Brady <padraic.brady@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Humbug\PhpScoper\Autoload;

use Humbug\PhpScoper\PhpParser\NodeVisitor\Collection\WhitelistedFunctionCollection;
use Humbug\PhpScoper\Whitelist;
use PhpParser\Node\Name\FullyQualified;
use const PHP_EOL;
use function array_map;
use function array_unshift;
use function count;
use function iterator_to_array;
use function sprintf;
use function str_repeat;
use function str_replace;

final class ScoperAutoloadGenerator
{
    private $whitelist;

    public function __construct(Whitelist $whitelist)
    {
        $this->whitelist = $whitelist;
    }

    public function dump(string $prefix): string
    {
        $whitelistedFunctions = $this->whitelist->getWhitelistedFunctions();

        $hasNamespacedFunctions = $this->hasNamespacedFunctions($whitelistedFunctions);

        $statements = implode(PHP_EOL, $this->createClassAliasStatements($prefix, $hasNamespacedFunctions)).PHP_EOL.PHP_EOL;
        $statements .= implode(PHP_EOL, $this->createFunctionAliasStatements($whitelistedFunctions, $hasNamespacedFunctions));

        if ($hasNamespacedFunctions) {
            $dump = <<<PHP
<?php

// scoper-autoload.php @generated by PhpScoper

namespace {
    \$loader = require_once __DIR__.'/autoload.php';
}

$statements

namespace {
    return \$loader;
}

PHP;
        } else {
            $dump = <<<PHP
<?php

// scoper-autoload.php @generated by PhpScoper

\$loader = require_once __DIR__.'/autoload.php';

$statements

return \$loader;

PHP;
        }

        $dump = $this->cleanAutoload($dump);

        return $dump;
    }

    /**
     * @return string[]
     */
    private function createClassAliasStatements(string $prefix, bool $hasNamespacedFunctions): array
    {
        $statements = array_map(
            function (string $whitelistedElement) use ($prefix): string {
                return sprintf(
                    'class_exists(\'%s\%s\');',
                    $prefix,
                    $whitelistedElement
                );
            },
            $this->whitelist->getClassWhitelistArray()
        );

        if ([] === $statements) {
            return $statements;
        }

        if ($hasNamespacedFunctions) {
            $statements = array_map(
                function (string $statement): string {
                    return str_repeat(' ', 4).$statement;
                },
                $statements
            );

            array_unshift($statements, 'namespace {');
            $statements[] = '}'.PHP_EOL;
        }

        array_unshift(
            $statements,
            <<<'EOF'
// Aliases for the whitelisted classes. For more information see:
// https://github.com/humbug/php-scoper/blob/master/README.md#class-whitelisting
EOF
        );

        return $statements;
    }

    /**
     * @return string[]
     */
    private function createFunctionAliasStatements(
        WhitelistedFunctionCollection $whitelistedFunctions,
        bool $hasNamespacedFunctions
    ): array {
        $statements = array_map(
            function (array $node) use ($hasNamespacedFunctions): string {
                /**
                 * @var FullyQualified
                 * @var FullyQualified $alias
                 */
                [$original, $alias] = $node;

                if ($hasNamespacedFunctions) {
                    $namespace = $original->slice(0, -1);

                    return sprintf(
                        <<<'PHP'
namespace %s{
    if (!function_exists('%s')) {
        function %s() {
            return \%s(...func_get_args());
        }
    }
}
PHP
                        ,
                        null === $namespace ? '' : $namespace->toString().' ',
                        $original->toString(),
                        null === $namespace ? $original->toString() : $original->slice(1)->toString(),
                        $alias->toString()
                    );
                }

                return sprintf(
                    <<<'PHP'
if (!function_exists('%1$s')) {
    function %1$s() {
        return \%2$s(...func_get_args());
    }
}
PHP
                    ,
                    $original->toString(),
                    $alias->toString()
                );
            },
            iterator_to_array($whitelistedFunctions)
        );

        if ([] === $statements) {
            return $statements;
        }

        array_unshift(
            $statements,
            <<<'EOF'
// Functions whitelisting. For more information see:
// https://github.com/humbug/php-scoper/blob/master/README.md#functions-whitelisting
EOF
        );

        return $statements;
    }

    private function hasNamespacedFunctions(WhitelistedFunctionCollection $functions): bool
    {
        foreach ($functions as [$original, $alias]) {
            /**
             * @var FullyQualified
             * @var FullyQualified $alias
             */
            if (count($original->parts) > 1) {
                return true;
            }
        }

        return false;
    }

    private function cleanAutoload(string $dump): string
    {
        $cleanedDump = $dump;

        do {
            $dump = $cleanedDump;
            $cleanedDump = str_replace("\n\n\n", "\n\n", $dump);
        } while ($cleanedDump !== $dump);

        return $dump;
    }
}
