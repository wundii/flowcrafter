<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Config;

enum OptionEnum: string
{
    /**
     * @internal
     */
    case STORAGE_CLASS = 'storage_class';

    /**
     * @internal
     */
    case STORAGE_URL = 'url';

    /**
     * @internal
     */
    case STORAGE_APITOKEN = 'apiToken';

    /**
     * @internal
     */
    case STORAGE_HOST = 'host';

    /**
     * @internal
     */
    case STORAGE_PORT = 'port';

    /**
     * @internal
     */
    case STORAGE_USERNAME = 'username';

    /**
     * @internal
     */
    case STORAGE_PASSWORD = 'password';

    /**
     * @internal
     */
    case STORAGE_DATABASE = 'database';

    /**
     * @internal
     */
    case SERVER_SECRET = 'server_secret';

    /**
     * @internal
     */
    case SERVER_DESCRIPTION = 'server_description';

    /**
     * @internal
     */
    case DEPENDENCIES_INJECTION = 'dependencies_injections';
}
