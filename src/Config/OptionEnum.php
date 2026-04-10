<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Config;

enum OptionEnum: string
{
    /**
     * @internal
     */
    case STORAGE_CONFIG = 'storage_config';

    /**
     * @internal
     */
    case SERVER_HOST = 'server_host';

    /**
     * @internal
     */
    case SERVER_PORT = 'server_port';

    /**
     * @internal
     */
    case SERVER_WORKERS = 'server_workers';

    /**
     * @internal
     */
    case SERVER_HTTPS = 'server_https';

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
    case SERVER_STORAGE = 'server_storage';

    /**
     * @internal
     */
    case DEPENDENCIES_INJECTION = 'dependencies_injections';

}
