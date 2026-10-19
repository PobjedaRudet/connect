<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <title>Neslaganja dolaska i odlaska</title>
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px; margin:0 auto; background:#ffffff; border-radius:12px;">
        <tr>
            <td style="padding:24px;">
                <h1 style="margin:0 0 8px; font-size:20px;">Neslaganja dolaska i odlaska</h1>
                <p style="margin:0 0 16px; color:#4b5563;">Period: {{ $periodLabel }}</p>
                <table width="100%" cellspacing="0" cellpadding="8" style="border-collapse:collapse; font-size:14px;">
                    <thead>
                        <tr style="background:#f9fafb; text-align:left;">
                            <th style="border-bottom:1px solid #e5e7eb;">Radnik</th>
                            <th style="border-bottom:1px solid #e5e7eb;">Datum</th>
                            <th style="border-bottom:1px solid #e5e7eb;">Neslaganje</th>
                            <th style="border-bottom:1px solid #e5e7eb;">Vremena</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($notifications as $item)
                            @php
                                $name = trim((string) ($item->employee->firstName ?? '') . ' ' . (string) ($item->employee->lastName ?? ''));
                                $times = collect([
                                    $item->gate_in_at ? 'Kapija ulaz: ' . $item->gate_in_at->timezone(config('app.timezone'))->format('H:i') : null,
                                    $item->gate_out_at ? 'Kapija izlaz: ' . $item->gate_out_at->timezone(config('app.timezone'))->format('H:i') : null,
                                    $item->building_in_at ? 'Prijava: ' . $item->building_in_at->timezone(config('app.timezone'))->format('H:i') : null,
                                    $item->building_out_at ? 'Odjava: ' . $item->building_out_at->timezone(config('app.timezone'))->format('H:i') : null,
                                ])->filter()->implode(' · ');
                            @endphp
                            <tr>
                                <td style="border-bottom:1px solid #e5e7eb;">{{ $name !== '' ? $name : ('#' . $item->employee_id) }}</td>
                                <td style="border-bottom:1px solid #e5e7eb;">{{ $item->work_date?->format('d.m.Y') }}</td>
                                <td style="border-bottom:1px solid #e5e7eb;">{{ $item->description }}</td>
                                <td style="border-bottom:1px solid #e5e7eb;">{{ $times !== '' ? $times : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
