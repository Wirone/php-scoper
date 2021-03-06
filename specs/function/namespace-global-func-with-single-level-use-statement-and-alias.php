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

return [
    'meta' => [
        'title' => 'Global function call imported with an aliased use statement in a namespace',
        // Default values. If not specified will be the one used
        'prefix' => 'Humbug',
        'whitelist' => [],
        'whitelist-global-constants' => true,
        'whitelist-global-functions' => true,
    ],

    [
        'spec' => <<<'SPEC'
Global function call imported with a use statement in a namespace:
- prefix the namespace
- prefix the use statement
- prefix the call
- transform the call into a FQ call
SPEC
        ,
        'payload' => <<<'PHP'
<?php

namespace X;

use function main as foo;

foo();
----
<?php

namespace Humbug\X;

use function Humbug\main as foo;
\Humbug\main();

PHP
    ],

    [
        'spec' => <<<'SPEC'
Global function call imported with a use statement in a namespace:
- prefix the namespace
- prefix the use statement
- do not prefix the call: as the call is FQ, the use statement is irrelevant so the above assumption cannot apply
SPEC
        ,
        'payload' => <<<'PHP'
<?php

namespace X;

use function main as foo;

\foo();
----
<?php

namespace Humbug\X;

use function Humbug\main as foo;
\Humbug\foo();

PHP
    ],
];
