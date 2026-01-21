<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogViewController extends Controller
{
    /**
     * Display the log viewer page.
     */
    public function index(Request $request)
    {
        $logFile = storage_path('logs/laravel.log');
        $lines = min((int) $request->get('lines', 200), 2000); // Default to 200, max 2000
        $level = $request->get('level', 'all'); // Filter by log level
        
        $logs = [];
        
        if (File::exists($logFile)) {
            // Optimized: Read from end of file to avoid loading entire file into memory
            $logs = $this->readLogFileFromEnd($logFile, $lines, $level);
        }
        
        return view('logs.view', [
            'logs' => $logs,
            'lines' => $lines,
            'level' => $level,
            'logFile' => $logFile,
            'fileExists' => File::exists($logFile),
            'fileSize' => File::exists($logFile) ? File::size($logFile) : 0,
        ]);
    }
    
    /**
     * Read log file from the end (optimized for large files).
     */
    private function readLogFileFromEnd($filePath, $maxLines, $filterLevel)
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }
        
        // Get file size
        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);
        
        // Start reading from end
        $chunkSize = 8192; // 8KB chunks
        $position = $fileSize;
        $buffer = '';
        $lines = [];
        $lineCount = 0;
        
        // Read backwards in chunks
        while ($position > 0 && $lineCount < $maxLines * 2) { // Read extra to account for multi-line entries
            $readSize = min($chunkSize, $position);
            $position -= $readSize;
            
            fseek($handle, $position);
            $chunk = fread($handle, $readSize);
            $buffer = $chunk . $buffer;
            
            // Split into lines
            $chunkLines = explode("\n", $buffer);
            
            // Keep last line in buffer (might be incomplete)
            $buffer = array_pop($chunkLines);
            
            // Add lines in reverse order
            $lines = array_merge(array_reverse($chunkLines), $lines);
            $lineCount = count($lines);
        }
        
        // Add remaining buffer
        if (!empty($buffer)) {
            array_unshift($lines, $buffer);
        }
        
        fclose($handle);
        
        // Get last N lines
        $recentLines = array_slice($lines, -$maxLines);
        
        // Parse log entries
        $logs = [];
        $currentEntry = '';
        
        foreach ($recentLines as $line) {
            // Check if line starts a new log entry (starts with [timestamp])
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (local|production|testing)\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY): (.+)$/', $line, $matches)) {
                // Save previous entry if exists
                if (!empty($currentEntry)) {
                    $parsed = $this->parseLogEntry($currentEntry, $filterLevel);
                    if ($parsed) {
                        $logs[] = $parsed;
                    }
                }
                $currentEntry = $line;
            } else {
                // Continuation of previous entry
                $currentEntry .= "\n" . $line;
            }
        }
        
        // Add last entry
        if (!empty($currentEntry)) {
            $parsed = $this->parseLogEntry($currentEntry, $filterLevel);
            if ($parsed) {
                $logs[] = $parsed;
            }
        }
        
        // Reverse to show newest first
        return array_reverse($logs);
    }
    
    /**
     * Parse a log entry.
     */
    private function parseLogEntry($entry, $filterLevel = 'all')
    {
        if (empty(trim($entry))) {
            return null;
        }
        
        // Extract log level
        $level = 'INFO';
        if (preg_match('/\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):/', $entry, $matches)) {
            $level = $matches[1];
        }
        
        // Filter by level
        if ($filterLevel !== 'all' && strtoupper($level) !== strtoupper($filterLevel)) {
            return null;
        }
        
        // Extract timestamp
        $timestamp = '';
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $entry, $matches)) {
            $timestamp = $matches[1];
        }
        
        // Extract message (first line after level)
        $message = '';
        if (preg_match('/\.' . preg_quote($level, '/') . ':(.+?)(?:\n|$)/s', $entry, $matches)) {
            $message = trim($matches[1]);
        }
        
        // Get full content
        $content = $entry;
        
        return [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'content' => $content,
        ];
    }
    
    /**
     * Clear the log file.
     */
    public function clear()
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (File::exists($logFile)) {
            File::put($logFile, '');
        }
        
        return redirect()->route('logs.view')->with('success', 'Log file cleared successfully.');
    }
    
    /**
     * Download the log file.
     */
    public function download()
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (File::exists($logFile)) {
            return response()->download($logFile, 'laravel.log');
        }
        
        return redirect()->route('logs.view')->with('error', 'Log file not found.');
    }
}
