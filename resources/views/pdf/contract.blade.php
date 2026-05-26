<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $contract->number }}</title>
    <style>
        @page {
            margin: 25mm 18mm 25mm 18mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #111;
            line-height: 1.5;
        }
        .meta {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            font-size: 10px;
            color: #555;
        }
        .meta .left { display: table-cell; text-align: left; }
        .meta .right { display: table-cell; text-align: right; }
        h1.title {
            font-size: 16px;
            text-align: center;
            margin: 12px 0 18px 0;
            font-weight: bold;
        }
        .number-line {
            text-align: center;
            margin-bottom: 8px;
            font-size: 12px;
        }
        .body {
            margin: 0;
            text-align: justify;
        }
        .body h1, .body h2, .body h3 {
            font-weight: bold;
        }
        .body h2 { font-size: 14px; margin: 12px 0 6px; }
        .body h3 { font-size: 12px; margin: 10px 0 6px; }
        .body p { margin: 6px 0; }
        .body table { width: 100%; border-collapse: collapse; margin: 6px 0; }
        .body table td, .body table th { border: 1px solid #444; padding: 6px; vertical-align: top; }
        .approvers {
            margin-top: 28px;
            page-break-inside: avoid;
        }
        .approvers-title {
            font-weight: bold;
            text-align: center;
            margin-bottom: 8px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .approvers table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .approvers td {
            border: 1px solid #333;
            padding: 8px;
            vertical-align: top;
            font-size: 10px;
            width: 25%;
        }
        .approvers .role {
            font-weight: bold;
            text-align: center;
        }
        .approvers .name {
            margin-top: 18px;
            font-weight: bold;
        }
        .approvers .position {
            color: #555;
            font-size: 9px;
        }
        .approvers .signature {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #666;
        }
        .approvers .decision {
            margin-top: 6px;
            font-size: 9px;
        }
        .approvers .decision.approved { color: #166534; }
        .approvers .decision.rejected { color: #991b1b; }
        .approvers .decision.pending  { color: #92400e; }
    </style>
</head>
<body>

    <div class="meta">
        <div class="left">{{ $contract->orderType?->getTranslation('title', $locale) }}</div>
        <div class="right">{{ now()->format('d.m.Y') }}</div>
    </div>

    <div class="number-line">{{ $contract->number }}</div>

    <h1 class="title">{{ $contract->getTranslation('title', $locale) }}</h1>

    <div class="body">
        {!! $bodyHtml !!}
    </div>

    @if ($approvers->isNotEmpty())
        <div class="approvers">
            <div class="approvers-title">{{ __('app.label.approval_signatures') }}</div>
            <table>
                <tr>
                    @foreach ($approvers as $approver)
                        @php
                            $statusKey = $approver->status ?? 'pending';
                        @endphp
                        <td>
                            <div class="role">
                                @switch($statusKey)
                                    @case('approved') {{ __('app.contract_approver.signature.approved') }} @break
                                    @case('rejected') {{ __('app.contract_approver.signature.rejected') }} @break
                                    @default          {{ __('app.contract_approver.signature.pending') }}
                                @endswitch
                            </div>

                            <div class="name">{{ $approver->user?->name }}</div>
                            <div class="position">{{ $approver->user?->position?->getTranslation('name', $locale) ?? '' }}</div>
                            <div class="position">{{ $approver->user?->department?->getTranslation('name', $locale) ?? '' }}</div>

                            <div class="signature">_______________</div>

                            @if ($approver->acted_at)
                                <div class="decision {{ $statusKey }}">
                                    {{ $approver->acted_at->format('d.m.Y') }}
                                </div>
                            @endif

                            @if ($approver->comment)
                                <div class="decision">«{{ $approver->comment }}»</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

</body>
</html>
