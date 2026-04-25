<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Service;

final readonly class CaddyfileBuilder
{
    public function __construct(
        private string $host,
        private int $port,
        private int $workers,
        private ?int $numThreads,
        private bool $https,
        private string $serviceDir,
    ) {
    }

    public function build(): string
    {
        $numThreads = $this->numThreads ?? $this->workers * 2;
        // FrankenPHP requires num_threads strictly greater than workers
        $numThreads = max($numThreads, $this->workers + 1);

        $maxThreads = $numThreads * 2;
        $scheme = $this->https ? 'https' : 'http';
        $autoHttps = $this->https ? '' : "\n\tauto_https off";

        return <<<CADDYFILE
            {
            	frankenphp {
            		num_threads {$numThreads}
            		max_threads {$maxThreads}
            	}
            	order php_server before file_server
            	admin off{$autoHttps}
            	log {
            		output stderr
            		format json
            		level INFO
            	}
            	servers {
            		timeouts {
            			read_header 5s
            			read_body 30s
            			write 60s
            			idle 120s
            		}
            	}
            }

            {$scheme}://:{$this->port} {
            	bind {$this->host}
            	root * {$this->serviceDir}

            	encode zstd gzip

            	php_server {
            		worker {$this->serviceDir}/worker.php {$this->workers}
            	}
            }

            CADDYFILE;
    }
}
