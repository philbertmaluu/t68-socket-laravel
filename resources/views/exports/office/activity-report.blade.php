<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Queue Activity Report</title>
    <style>
        @page {
            margin: 24px 24px 28px 24px;
        }

        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #1f1f1f;
            margin: 0;
            padding: 0;
        }

        .watermark {
            position: fixed;
            top: 38%;
            left: 0;
            width: 100%;
            text-align: center;
            opacity: 0.07;
            z-index: -1000;
        }

        .watermark img {
            width: 160px;
            height: 160px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 3px solid #902d30;
            margin: 0 0 10px 0;
            padding: 0 0 8px 0;
        }

        .header-table td {
            vertical-align: middle;
            padding: 0;
        }

        .brand-logo {
            width: 42px;
            height: 42px;
        }

        .brand-name {
            font-size: 13px;
            font-weight: bold;
            color: #902d30;
            margin: 0;
            padding: 0;
        }

        .brand-sub {
            font-size: 8px;
            color: #666;
            margin: 1px 0 0 0;
            padding: 0;
        }

        .report-title {
            font-size: 12px;
            font-weight: bold;
            color: #222;
            margin: 0;
            padding: 0;
            text-align: right;
        }

        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 10px 0;
        }

        .meta-grid td {
            vertical-align: top;
            padding: 3px 6px 3px 0;
            width: 33%;
        }

        .meta-label {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #888;
            margin: 0;
            padding: 0;
        }

        .meta-value {
            font-size: 10px;
            font-weight: bold;
            color: #222;
            margin: 1px 0 0 0;
            padding: 0;
        }

        .services-box {
            background: #faf5f5;
            border: 1px solid #e8d0d1;
            border-left: 3px solid #902d30;
            padding: 6px 8px;
            margin: 0 0 10px 0;
        }

        .services-title {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #902d30;
            font-weight: bold;
            margin: 0 0 4px 0;
            padding: 0;
        }

        .service-chip {
            display: inline-block;
            background: #f3e4e5;
            color: #902d30;
            border: 1px solid #e0b8ba;
            padding: 1px 6px;
            margin: 0 3px 3px 0;
            font-size: 8px;
            font-weight: bold;
        }

        .service-empty {
            color: #888;
            font-size: 8px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0;
        }

        table.data thead {
            display: table-header-group;
        }

        table.data thead th {
            background-color: #902d30;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            text-align: left;
            padding: 6px 4px;
            border: none;
        }

        table.data tbody td {
            padding: 5px 4px;
            border-bottom: 1px solid #ececec;
            font-size: 8px;
            color: #333;
            vertical-align: top;
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
            padding: 18px 10px;
            color: #888;
            border: 1px dashed #ddd;
        }

        .summary {
            margin: 8px 0 0 0;
            font-size: 8px;
            color: #555;
        }

        .summary strong {
            color: #902d30;
        }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -12px;
            font-size: 7px;
            color: #888;
            border-top: 1px solid #e5e5e5;
            padding-top: 4px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            font-size: 7px;
            color: #888;
            padding: 0;
        }
    </style>
</head>
<body>
    @if(!empty($logoPath))
        <div class="watermark">
            <img src="{{ $logoPath }}" width="160" height="160" alt="NSSF">
        </div>
    @endif

    <table class="header-table">
        <tr>
            <td style="width: 50px;">
                @if(!empty($logoPath))
                    <img class="brand-logo" src="{{ $logoPath }}" width="42" height="42" alt="NSSF">
                @endif
            </td>
            <td>
                <p class="brand-name">National Social Security Fund</p>
                <p class="brand-sub">Queue Management System — Office Activity Report</p>
            </td>
            <td style="width: 32%; text-align: right;">
                <p class="report-title">Office Activities</p>
                <p class="brand-sub">Generated {{ $generatedAt }}</p>
                <p class="brand-sub">Generated by {{ $generatedBy }}</p>
            </td>
        </tr>
    </table>

    <table class="meta-grid">
        <tr>
            <td>
                <p class="meta-label">Office</p>
                <p class="meta-value">{{ $officeName ?: 'N/A' }}</p>
            </td>
            <td>
                <p class="meta-label">Report Date</p>
                <p class="meta-value">{{ $reportDate }}</p>
            </td>
            <td>
                <p class="meta-label">Total Tickets</p>
                <p class="meta-value">{{ count($tickets) }}</p>
            </td>
        </tr>
        <tr>
            <td>
                <p class="meta-label">Counters Active</p>
                <p class="meta-value">{{ count($counters) }}</p>
            </td>
            <td>
                <p class="meta-label">Generated By</p>
                <p class="meta-value">{{ $generatedBy }}</p>
            </td>
            <td>
                <p class="meta-label">Document</p>
                <p class="meta-value">QMS-OA-{{ $dateKey }}</p>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <p class="meta-label">Generated At</p>
                <p class="meta-value">{{ $generatedAt }}</p>
            </td>
        </tr>
    </table>

    <div class="services-box">
        <p class="services-title">Services in this office</p>
        @if(count($services) > 0)
            @foreach($services as $service)
                <span class="service-chip">{{ $service }}</span>
            @endforeach
        @else
            <span class="service-empty">No services recorded for this period</span>
        @endif
    </div>

    @if(count($counters) > 0)
        <div class="services-box">
            <p class="services-title">Counters in this report</p>
            @foreach($counters as $counter)
                <span class="service-chip">{{ $counter }}</span>
            @endforeach
        </div>
    @endif

    @if(count($tickets) === 0)
        <div class="empty">No tickets recorded for this date.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 12%;">Ticket</th>
                    <th style="width: 16%;">Service</th>
                    <th style="width: 15%;">Member</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 10%;">Time</th>
                    <th style="width: 9%;">Duration</th>
                    <th style="width: 13%;">Clerk</th>
                    <th style="width: 10%;">Counter</th>
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
                                <br><span style="color:#888;font-size:7px;">{{ $ticket['memberNumber'] }}</span>
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
            for <strong>{{ $officeName ?: 'office' }}</strong> on <strong>{{ $reportDate }}</strong>.
        </p>
    @endif

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>NSSF Queue Management System — Confidential</td>
                <td style="text-align: right;">Generated by {{ $generatedBy }} · {{ $generatedAt }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
