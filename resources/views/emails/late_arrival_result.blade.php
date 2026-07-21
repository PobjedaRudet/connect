<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odobrenje kašnjenja — {{ config('app.name', 'Connect') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f3f4f6; font-family: Arial, Helvetica, sans-serif; min-height: 100vh;
               display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.08);
                max-width: 480px; width: 100%; overflow: hidden; }
        .header { padding: 20px 24px; }
        .header.success { background: #166534; }
        .header.error   { background: #991b1b; }
        .header .app  { font-size: 13px; color: #86efac; margin-bottom: 4px; }
        .header.error .app { color: #fca5a5; }
        .header h1 { font-size: 20px; font-weight: 700; color: #fff; }
        .body { padding: 24px; font-size: 14px; line-height: 22px; color: #334155; }
        .body p { margin-bottom: 12px; }
        .meta { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px;
                overflow: hidden; margin-top: 16px; font-size: 13px; }
        .meta-row { display: flex; border-bottom: 1px solid #e5e7eb; }
        .meta-row:last-child { border-bottom: none; }
        .meta-label { width: 140px; flex-shrink: 0; background: #f1f5f9; color: #475569;
                      padding: 9px 12px; }
        .meta-value { padding: 9px 12px; color: #0f172a; font-weight: 600; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 9999px;
                 font-size: 12px; font-weight: 700; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-red  { background: #fee2e2; color: #991b1b; }
        .footer { padding: 14px 24px; background: #f8fafc; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    @php
        $appName   = config('app.name', 'Connect');
        $isSuccess = $success ?? false;
        $tz        = config('app.timezone');
        $typeLabel = $typeLabel ?? ($pass?->type ?? '');
        $lateMin   = $pass?->late_minutes ?? $pass?->duration_minutes ?? null;
        $shiftStart = optional($pass?->start_time)->timezone($tz)->format('d.m.Y H:i');
        $arrival    = optional($pass?->end_time)->timezone($tz)->format('H:i');
        $fullName   = trim(($employee->firstName ?? '') . ' ' . ($employee->lastName ?? ''));
    @endphp

    <div class="card">
        <div class="header {{ $isSuccess ? 'success' : 'error' }}">
            <div class="app">{{ $appName }}</div>
            <h1>{{ $isSuccess ? 'Izlaznica odobrena' : 'Greška' }}</h1>
        </div>

        <div class="body">
            <p>{{ $message ?? '' }}</p>

            @if($isSuccess && $pass)
                <div class="meta">
                    @if($fullName)
                        <div class="meta-row">
                            <div class="meta-label">Radnik</div>
                            <div class="meta-value">{{ $fullName }}</div>
                        </div>
                    @endif
                    @if($typeLabel)
                        <div class="meta-row">
                            <div class="meta-label">Tip izlaznice</div>
                            <div class="meta-value">
                                @if(str_contains($typeLabel, 'Slu') || $typeLabel === 'službeni')
                                    <span class="badge badge-blue">Službena izlaznica</span>
                                @else
                                    <span class="badge badge-red">Privatna izlaznica</span>
                                @endif
                            </div>
                        </div>
                    @endif
                    @if($shiftStart)
                        <div class="meta-row">
                            <div class="meta-label">Početak smjene</div>
                            <div class="meta-value">{{ $shiftStart }}</div>
                        </div>
                    @endif
                    @if($arrival)
                        <div class="meta-row">
                            <div class="meta-label">Stvarni dolazak</div>
                            <div class="meta-value">{{ $arrival }}</div>
                        </div>
                    @endif
                    @if($lateMin !== null)
                        <div class="meta-row">
                            <div class="meta-label">Kašnjenje</div>
                            <div class="meta-value">{{ $lateMin }} min</div>
                        </div>
                    @endif
                    <div class="meta-row">
                        <div class="meta-label">ID izlaznice</div>
                        <div class="meta-value">#{{ $pass->id }}</div>
                    </div>
                </div>
            @endif
        </div>

        <div class="footer">
            Ova stranica je generisana sistemom {{ $appName }}. Možete zatvoriti ovaj prozor.
        </div>
    </div>
</body>
</html>
