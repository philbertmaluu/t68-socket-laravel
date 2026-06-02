<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Configures daily rotation so log files are named YYYY-MM-DD.log (date only).
 */
class DateNamedRotatingLogTap
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            if ($handler instanceof RotatingFileHandler) {
                $handler->setFilenameFormat('{date}', RotatingFileHandler::FILE_PER_DAY);
            }
        }
    }
}
