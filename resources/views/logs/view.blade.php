<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Log Viewer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', 'source-code-pro', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: #252526;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #3e3e42;
        }
        
        .header h1 {
            color: #ffffff;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .controls {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .control-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        label {
            color: #cccccc;
            font-size: 14px;
        }
        
        select, input[type="number"] {
            background: #3c3c3c;
            border: 1px solid #3e3e42;
            color: #d4d4d4;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: inherit;
            font-size: 14px;
        }
        
        select:focus, input[type="number"]:focus {
            outline: none;
            border-color: #007acc;
        }
        
        .btn {
            background: #007acc;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #005a9e;
        }
        
        .btn-danger {
            background: #d32f2f;
        }
        
        .btn-danger:hover {
            background: #b71c1c;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .info {
            color: #858585;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #2d5016;
            color: #b8e994;
            border: 1px solid #3e7c1f;
        }
        
        .alert-error {
            background: #5a1e1e;
            color: #ff6b6b;
            border: 1px solid #7c2a2a;
        }
        
        .logs-container {
            background: #252526;
            border-radius: 8px;
            border: 1px solid #3e3e42;
            overflow: hidden;
        }
        
        .log-entry {
            padding: 12px 15px;
            border-bottom: 1px solid #3e3e42;
            transition: background 0.2s;
        }
        
        .log-entry:hover {
            background: #2a2d2e;
        }
        
        .log-entry:last-child {
            border-bottom: none;
        }
        
        .log-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .log-level {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .log-level.DEBUG {
            background: #6c757d;
            color: white;
        }
        
        .log-level.INFO {
            background: #0d7377;
            color: white;
        }
        
        .log-level.NOTICE {
            background: #17a2b8;
            color: white;
        }
        
        .log-level.WARNING {
            background: #ffc107;
            color: #000;
        }
        
        .log-level.ERROR {
            background: #dc3545;
            color: white;
        }
        
        .log-level.CRITICAL {
            background: #721c24;
            color: white;
        }
        
        .log-level.ALERT {
            background: #856404;
            color: white;
        }
        
        .log-level.EMERGENCY {
            background: #721c24;
            color: white;
        }
        
        .log-timestamp {
            color: #858585;
            font-size: 12px;
        }
        
        .log-message {
            color: #d4d4d4;
            margin-top: 5px;
            word-break: break-word;
        }
        
        .log-content {
            background: #1e1e1e;
            padding: 10px;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 12px;
            color: #858585;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #858585;
        }
        
        .empty-state h2 {
            margin-bottom: 10px;
            color: #cccccc;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Laravel Log Viewer</h1>
            
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            
            @if(session('error'))
                <div class="alert alert-error">
                    {{ session('error') }}
                </div>
            @endif
            
            <div class="controls">
                <form method="GET" action="{{ route('logs.view') }}" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                    <div class="control-group">
                        <label for="lines">Lines:</label>
                        <input type="number" id="lines" name="lines" value="{{ $lines }}" min="50" max="2000" step="50">
                    </div>
                    
                    <div class="control-group">
                        <label for="level">Level:</label>
                        <select id="level" name="level">
                            <option value="all" {{ $level === 'all' ? 'selected' : '' }}>All</option>
                            <option value="DEBUG" {{ $level === 'DEBUG' ? 'selected' : '' }}>DEBUG</option>
                            <option value="INFO" {{ $level === 'INFO' ? 'selected' : '' }}>INFO</option>
                            <option value="NOTICE" {{ $level === 'NOTICE' ? 'selected' : '' }}>NOTICE</option>
                            <option value="WARNING" {{ $level === 'WARNING' ? 'selected' : '' }}>WARNING</option>
                            <option value="ERROR" {{ $level === 'ERROR' ? 'selected' : '' }}>ERROR</option>
                            <option value="CRITICAL" {{ $level === 'CRITICAL' ? 'selected' : '' }}>CRITICAL</option>
                            <option value="ALERT" {{ $level === 'ALERT' ? 'selected' : '' }}>ALERT</option>
                            <option value="EMERGENCY" {{ $level === 'EMERGENCY' ? 'selected' : '' }}>EMERGENCY</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Filter</button>
                </form>
                
                <div style="margin-left: auto; display: flex; gap: 10px;">
                    <a href="{{ route('logs.download') }}" class="btn btn-secondary">Download Log</a>
                    <form method="POST" action="{{ route('logs.clear') }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear the log file?');">
                        @csrf
                        <button type="submit" class="btn btn-danger">Clear Log</button>
                    </form>
                </div>
            </div>
            
            <div class="info">
                @if($fileExists)
                    File: {{ $logFile }} | Size: {{ number_format($fileSize / 1024, 2) }} KB | Showing {{ count($logs) }} entries
                @else
                    Log file not found: {{ $logFile }}
                @endif
            </div>
        </div>
        
        <div class="logs-container">
            @if(!$fileExists)
                <div class="empty-state">
                    <h2>Log file not found</h2>
                    <p>The log file does not exist at: {{ $logFile }}</p>
                </div>
            @elseif(empty($logs))
                <div class="empty-state">
                    <h2>No logs found</h2>
                    <p>No log entries match your current filter criteria.</p>
                </div>
            @else
                @foreach($logs as $log)
                    <div class="log-entry">
                        <div class="log-header">
                            <span class="log-level {{ $log['level'] }}">{{ $log['level'] }}</span>
                            <span class="log-timestamp">{{ $log['timestamp'] }}</span>
                        </div>
                        <div class="log-message">
                            {{ \Illuminate\Support\Str::limit($log['message'], 200) }}
                        </div>
                        @if(strlen($log['content']) > 200)
                            <details style="margin-top: 8px;">
                                <summary style="color: #858585; cursor: pointer; font-size: 12px;">Show full entry</summary>
                                <div class="log-content">{{ $log['content'] }}</div>
                            </details>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</body>
</html>
