<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Queue Activity Report</title>
    <style>
        @page {
            margin: 28px 32px 40px 32px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1f1f1f;
            margin: 0;
            padding: 0;
            position: relative;
        }

        .watermark {
            position: fixed;
            top: 32%;
            left: 50%;
            width: 280px;
            margin-left: -140px;
            opacity: 0.08;
            z-index: 0;
            text-align: center;
        }

        .watermark img {
            width: 280px;
            height: auto;
        }

        .content {
            position: relative;
            z-index: 1;
        }

        .brand-bar {
            border-bottom: 3px solid #902d30;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .brand-row {
            width: 100%;
        }

        .brand-logo {
            width: 56px;
            height: auto;
            vertical-align: middle;
        }

        .brand-text {
            display: inline-block;
            vertical-align: middle;
            padding-left: 12px;
        }

        .brand-name {
            font-size: 16px;
            font-weight: bold;
            color: #902d30;
            letter-spacing: 0.3px;
            margin: 0;
        }

        .brand-sub {
            font-size: 9px;
            color: #666;
            margin: 2px 0 0 0;
        }

        .report-title {
            font-size: 14px;
            font-weight: bold;
            color: #222;
            margin: 0 0 4px 0;
        }

        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 18px 0;
        }

        .meta-grid td {
            vertical-align: top;
            padding: 4px 8px 4px 0;
        }

        .meta-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #888;
            margin: 0 0 2px 0;
        }

        .meta-value {
            font-size: 11px;
            font-weight: bold;
            color: #222;
            margin: 0;
        }

        .services-box {
            background: #faf5f5;
            border: 1px solid #e8d0d1;
            border-left: 3px solid #902d30;
            padding: 8px 10px;
            margin-bottom: 16px;
        }

        .services-title {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #902d30;
            font-weight: bold;
            margin: 0 0 6px 0;
        }

        .service-chip {
            display: inline-block;
            background: rgba(144, 45, 48, 0.1);
            color: #902d30;
            border: 1px solid rgba(144, 45, 48, 0.2);
            border-radius: 3px;
            padding: 2px 7px;
            margin: 0 4px 4px 0;
            font-size: 9px;
            font-weight: bold;
        }

        .service-empty {
            color: #888;
            font-size: 9px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.data thead th {
            background-color: #902d30;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-align: left;
            padding: 8px 6px;
            border: none;
        }

        table.data thead th:first-child {
            border-top-left-radius: 4px;
        }

        table.data thead th:last-child {
            border-top-right-radius: 4px;
        }

        table.data tbody td {
            padding: 7px 6px;
            border-bottom: 1px solid #ececec;
            font-size: 9px;
            color: #333;
            vertical-align: middle;
            word-wrap: break-word;
        }

        table.data tbody tr:nth-child(even) td {
            background: #fafafa;
        }

        .ticket-no {
            color: #902d30;
            font-weight: bold;
        }

        .status {
            font-weight: bold;
            text-transform: capitalize;
        }

        .empty {
            text-align: center;
            padding: 28px 12px;
            color: #888;
            border: 1px dashed #ddd;
        }

        .footer {
            position: fixed;
            bottom: -18px;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #888;
            border-top: 1px solid #e5e5e5;
            padding-top: 6px;
        }

        .footer-left {
            float: left;
        }

        .footer-right {
            float: right;
        }

        .summary {
            margin-top: 10px;
            font-size: 9px;
            color: #555;
        }

        .summary strong {
            color: #902d30;
        }
    </style>
</head>
<body>
    @if(!empty($logoPath))
        <div class="watermark">
            <img src="{{ $logoPath }}" alt="NSSF">
        </div>
    @endif

    <div class="content">
        <div class="brand-bar">
            <table class="brand-row" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="width: 64px;">
                        @if(!empty($logoPath))
                            <img class="brand-logo" src="{{ $logoPath }}" alt="NSSF">
                        @endif
                    </td>
                    <td>
                        <div class="brand-text">
                            <p class="brand-name">National Social Security Fund</p>
                            <p class="brand-sub">Queue Management System — Activity Report</p>
                        </div>
                    </td>
                    <td style="text-align: right; vertical-align: middle;">
                        <p class="report-title">Queue Activity</p>
                        <p class="brand-sub">Generated {{ $generatedAt }}</p>
                        <p class="brand-sub">Generated by {{ $generatedBy }}</p>
                    </td>
                </tr>
            </table>
        </div>

        <table class="meta-grid">
            <tr>
                <td style="width: 34%;">
                    <p class="meta-label">Queue / Counter</p>
                    <p class="meta-value">{{ $queueName }}</p>
                </td>
                <td style="width: 33%;">
                    <p class="meta-label">Counter</p>
                    <p class="meta-value">{{ $counterName ?: 'N/A' }}</p>
                </td>
                <td style="width: 33%;">
                    <p class="meta-label">Report Date</p>
                    <p class="meta-value">{{ $reportDate }}</p>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="meta-label">Office</p>
                    <p class="meta-value">{{ $officeName ?: 'N/A' }}</p>
                </td>
                <td>
                    <p class="meta-label">Total Tickets</p>
                    <p class="meta-value">{{ count($tickets) }}</p>
                </td>
                <td>
                    <p class="meta-label">Generated By</p>
                    <p class="meta-value">{{ $generatedBy }}</p>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="meta-label">Document</p>
                    <p class="meta-value">QMS-QA-{{ $dateKey }}</p>
                </td>
                <td colspan="2">
                    <p class="meta-label">Generated At</p>
                    <p class="meta-value">{{ $generatedAt }}</p>
                </td>
            </tr>
        </table>

        <div class="services-box">
            <p class="services-title">Services on this counter</p>
            @if(count($services) > 0)
                @foreach($services as $service)
                    <span class="service-chip">{{ $service }}</span>
                @endforeach
            @else
                <span class="service-empty">No services configured for this counter</span>
            @endif
        </div>

        @if(count($tickets) === 0)
            <div class="empty">No tickets recorded for this date.</div>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 6%;">#</th>
                        <th style="width: 12%;">Ticket</th>
                        <th style="width: 16%;">Service</th>
                        <th style="width: 14%;">Member</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 12%;">Time</th>
                        <th style="width: 10%;">Duration</th>
                        <th style="width: 12%;">Clerk</th>
                        <th style="width: 8%;">Counter</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tickets as $index => $ticket)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="ticket-no">{{ $ticket['ticketNumber'] }}</td>
                            <td>{{ $ticket['serviceType'] !== '' ? $ticket['serviceType'] : '—' }}</td>
                            <td>
                                {{ $ticket['memberName'] ?: '—' }}
                                @if(!empty($ticket['memberNumber']))
                                    <br><span style="color:#888;font-size:8px;">{{ $ticket['memberNumber'] }}</span>
                                @endif
                            </td>
                            <td class="status">{{ str_replace('_', ' ', $ticket['status'] ?? '') }}</td>
                            <td>
                                @php
                                    $timeSource = $ticket['completedAt'] ?? $ticket['createdAt'] ?? null;
                                @endphp
                                {{ $timeSource ? \Carbon\Carbon::parse($timeSource)->format('h:i A') : '—' }}
                            </td>
                            <td>{{ (int) ($ticket['durationMinutes'] ?? 0) }} min</td>
                            <td>{{ $ticket['clerkName'] ?? '—' }}</td>
                            <td>{{ $ticket['counterName'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="summary">
                Showing <strong>{{ count($tickets) }}</strong> ticket{{ count($tickets) === 1 ? '' : 's' }}
                for <strong>{{ $queueName }}</strong> on <strong>{{ $reportDate }}</strong>.
            </p>
        @endif
    </div>

    <div class="footer">
        <div class="footer-left">NSSF Queue Management System — Confidential</div>
        <div class="footer-right">Generated by {{ $generatedBy }} · {{ $generatedAt }}</div>
    </div>
</body>
</html>
