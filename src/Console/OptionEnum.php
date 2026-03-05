<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console;

use Symfony\Component\Console\Input\InputOption;

enum OptionEnum: string
{
    case ANSI = 'ansi';

    case CREATE = 'create';

    case CONFIG = 'config';

    case HELP = 'help';

    case VERBOSE = 'verbose';

    case VERSION = 'version';

    /**
     * @var string
     */
    private const PRE_NAME = '--';

    /**
     * @var string
     */
    private const PRE_SHORTCUT = '-';

    public function getName(): string
    {
        return self::PRE_NAME . $this->value;
    }

    public function getShortcut(): string
    {
        return match ($this) {
            self::CREATE => self::PRE_SHORTCUT . 'cr',
            self::CONFIG => self::PRE_SHORTCUT . 'c',
            self::HELP => self::PRE_SHORTCUT . 'h',
            self::VERBOSE => self::PRE_SHORTCUT . 'v|vv|vvv',
            self::VERSION => self::PRE_SHORTCUT . 'V',
            default => '',
        };
    }

    /**
     * @return InputOption[]
     */
    public static function getInputDefinition(string $defaultConfigPath): array
    {
        return [
            new InputOption(self::ANSI->getName(), self::ANSI->getShortcut(), InputOption::VALUE_NEGATABLE, 'Force (or disable --no-ansi) ANSI output'),
            new InputOption(self::CONFIG->getName(), self::CONFIG->getShortcut(), InputOption::VALUE_REQUIRED, 'Path to config file', $defaultConfigPath),
            new InputOption(self::CREATE->getName(), self::CREATE->getShortcut(), InputOption::VALUE_NONE, 'Create a config file'),
            new InputOption(self::HELP->getName(), self::HELP->getShortcut(), InputOption::VALUE_NONE, 'Display help for the given command.'),
            new InputOption(self::VERBOSE->getName(), self::VERBOSE->getShortcut(), InputOption::VALUE_NONE, 'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug'),
            new InputOption(self::VERSION->getName(), self::VERSION->getShortcut(), InputOption::VALUE_NONE, 'Display this application version'),
        ];
    }
}
