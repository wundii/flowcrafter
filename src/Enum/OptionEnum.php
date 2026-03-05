<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Enum;

enum OptionEnum: string
{
    /**
     * @internal
     */
    case STORAGE_CLASS = 'storage_class';

    /**
     * @internal
     */
    case URL = 'url';

    /**
     * @internal
     */
    case APITOKEN = 'apiToken';

    /**
     * @internal
     */
    case HOST = 'host';

    /**
     * @internal
     */
    case PORT = 'port';

    /**
     * @internal
     */
    case USERNAME = 'username';

    /**
     * @internal
     */
    case PASSWORD = 'password';

    /**
     * @internal
     */
    case DATABASE = 'database';
}
