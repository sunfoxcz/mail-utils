<?php declare(strict_types=1);

namespace App\Libs\Logging;

use DateTimeImmutable;
use Psr\Log\LogLevel;

final class ConsoleFormatter
{
    private const SimpleFormat = "[%datetime%] %start_tag%%level_name%%end_tag% %message%\n";
    private const SimpleDate = 'Y-m-d H:i:s';

    private static array $levelColorMap = [
        LogLevel::DEBUG => 'fg=white',
        LogLevel::INFO => 'fg=green',
        LogLevel::NOTICE => 'fg=blue',
        LogLevel::WARNING => 'fg=cyan',
        LogLevel::ERROR => 'fg=yellow',
        LogLevel::CRITICAL => 'fg=red',
        LogLevel::ALERT => 'fg=red',
        LogLevel::EMERGENCY => 'fg=white;bg=red',
    ];

    public static function format(string $level, string $message, array $context = []): string
    {
        return strtr(self::SimpleFormat, [
            '%datetime%' => (new DateTimeImmutable())->format(self::SimpleDate),
            '%start_tag%' => sprintf('<%s>', self::$levelColorMap[$level]),
            '%level_name%' => strtoupper($level),
            '%end_tag%' => '</>',
            '%message%' => self::replacePlaceHolder($message, $context),
        ]);
    }

    private static function replacePlaceHolder(string $message, array $context): string
    {
        if (!str_contains($message, '{')) {
            return $message;
        }

        $replacements = [];
        foreach ($context as $k => $v) {
            $replacements['{' . $k . '}'] = sprintf('<comment>%s</>', $v);
        }

        return strtr($message, $replacements);
    }
}
