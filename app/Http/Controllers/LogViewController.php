<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogViewController extends BaseController
{
    /**
     * Display the log viewer page.
     */
    public function index(Request $request)
    {
        $availableDates = $this->listAvailableLogDates();
        $selectedDate = $this->resolveSelectedDate($request->get('date'), $availableDates);
        $logFile = $this->logFilePathForDate($selectedDate);

        $lines = min((int) $request->get('lines', 200), 2000);
        $level = $request->get('level', 'all');

        $logs = [];

        if (File::exists($logFile)) {
            $logs = $this->readLogFileFromEnd($logFile, $lines, $level);
        }

        return view('logs.view', [
            'logs' => $logs,
            'lines' => $lines,
            'level' => $level,
            'logFile' => $logFile,
            'selectedDate' => $selectedDate,
            'availableDates' => $availableDates,
            'fileExists' => File::exists($logFile),
            'fileSize' => File::exists($logFile) ? File::size($logFile) : 0,
        ]);
    }

    /**
     * Clear the selected day's log file.
     */
    public function clear(Request $request)
    {
        $availableDates = $this->listAvailableLogDates();
        $selectedDate = $this->resolveSelectedDate($request->get('date'), $availableDates);
        $logFile = $this->logFilePathForDate($selectedDate);

        if (File::exists($logFile)) {
            File::put($logFile, '');
        }

        return redirect()
            ->route('logs.view', [
                'date' => $selectedDate,
                'lines' => $request->get('lines', 200),
                'level' => $request->get('level', 'all'),
            ])
            ->with('success', "Log file for {$selectedDate} cleared successfully.");
    }

    /**
     * Download the selected day's log file.
     */
    public function download(Request $request)
    {
        $availableDates = $this->listAvailableLogDates();
        $selectedDate = $this->resolveSelectedDate($request->get('date'), $availableDates);
        $logFile = $this->logFilePathForDate($selectedDate);

        if (File::exists($logFile)) {
            return response()->download($logFile, "{$selectedDate}.log");
        }

        return redirect()
            ->route('logs.view', ['date' => $selectedDate])
            ->with('error', "Log file not found for {$selectedDate}.");
    }

    /**
     * @return list<string> Dates (Y-m-d), newest first
     */
    private function listAvailableLogDates(): array
    {
        $dates = [];
        $pattern = storage_path('logs/*.log');

        foreach (glob($pattern) ?: [] as $file) {
            $basename = basename($file, '.log');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $basename)) {
                $dates[] = $basename;
            }
        }

        $dates = array_values(array_unique($dates));
        rsort($dates);

        $today = now()->format('Y-m-d');
        if (! in_array($today, $dates, true)) {
            array_unshift($dates, $today);
        }

        return $dates;
    }

    private function resolveSelectedDate(?string $date, array $availableDates): string
    {
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return $availableDates[0] ?? now()->format('Y-m-d');
    }

    private function logFilePathForDate(string $date): string
    {
        return storage_path('logs/'.$date.'.log');
    }

    /**
     * Read log file from the end (optimized for large files).
     */
    private function readLogFileFromEnd(string $filePath, int $maxLines, string $filterLevel): array
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return [];
        }

        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);

        $chunkSize = 8192;
        $position = $fileSize;
        $buffer = '';
        $lines = [];
        $lineCount = 0;

        while ($position > 0 && $lineCount < $maxLines * 2) {
            $readSize = min($chunkSize, $position);
            $position -= $readSize;

            fseek($handle, $position);
            $chunk = fread($handle, $readSize);
            $buffer = $chunk.$buffer;

            $chunkLines = explode("\n", $buffer);
            $buffer = array_pop($chunkLines);
            $lines = array_merge(array_reverse($chunkLines), $lines);
            $lineCount = count($lines);
        }

        if (! empty($buffer)) {
            array_unshift($lines, $buffer);
        }

        fclose($handle);

        $recentLines = array_slice($lines, -$maxLines);

        $logs = [];
        $currentEntry = '';

        foreach ($recentLines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (local|production|testing)\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY): (.+)$/', $line, $matches)) {
                if (! empty($currentEntry)) {
                    $parsed = $this->parseLogEntry($currentEntry, $filterLevel);
                    if ($parsed) {
                        $logs[] = $parsed;
                    }
                }
                $currentEntry = $line;
            } else {
                $currentEntry .= "\n".$line;
            }
        }

        if (! empty($currentEntry)) {
            $parsed = $this->parseLogEntry($currentEntry, $filterLevel);
            if ($parsed) {
                $logs[] = $parsed;
            }
        }

        return array_reverse($logs);
    }

    /**
     * Parse a log entry.
     */
    private function parseLogEntry(string $entry, string $filterLevel = 'all'): ?array
    {
        if (empty(trim($entry))) {
            return null;
        }

        $level = 'INFO';
        if (preg_match('/\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):/', $entry, $matches)) {
            $level = $matches[1];
        }

        if ($filterLevel !== 'all' && strtoupper($level) !== strtoupper($filterLevel)) {
            return null;
        }

        $timestamp = '';
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $entry, $matches)) {
            $timestamp = $matches[1];
        }

        $message = '';
        if (preg_match('/\.'.preg_quote($level, '/').':(.+?)(?:\n|$)/s', $entry, $matches)) {
            $message = trim($matches[1]);
        }

        return [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'content' => $entry,
        ];
    }
}
