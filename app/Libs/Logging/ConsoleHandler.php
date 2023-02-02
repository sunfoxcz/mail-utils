<?php declare(strict_types=1);

namespace App\Libs\Logging;

use Psr\Log\LogLevel;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConsoleHandler implements EventSubscriberInterface
{
    private static array $verbosityLevelMap = [
        OutputInterface::VERBOSITY_QUIET => [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
        ],
        OutputInterface::VERBOSITY_NORMAL => [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
        ],
        OutputInterface::VERBOSITY_VERBOSE => [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
        ],
        OutputInterface::VERBOSITY_VERY_VERBOSE => [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
        ],
        OutputInterface::VERBOSITY_DEBUG => [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ],
    ];

    private ?OutputInterface $output = null;

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onCommand', 255],
            ConsoleEvents::TERMINATE => ['onTerminate', -255],
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $output = $event->getOutput();
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }
        $this->setOutput($output);
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function write(string $level, string $message): void
    {
        if (!$this->output) {
            return;
        }

        $verbosity = $this->output->getVerbosity();
        if (in_array($level, self::$verbosityLevelMap[$verbosity], true)) {
            $this->output->write($message, false, $verbosity);
        }
    }
}
