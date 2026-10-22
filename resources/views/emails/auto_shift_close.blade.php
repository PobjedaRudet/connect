<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <title>Automatska odjava</title>
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px; margin:0 auto; background:#ffffff; border-radius:12px;">
        <tr>
            <td style="padding:24px;">
                <h1 style="margin:0 0 8px; font-size:20px;">Radnik se nije odjavio</h1>
                <p style="margin:0 0 16px; color:#4b5563;">Smjena {{ $shiftCode }}, automatska odjava u {{ $cutoffLabel }}.</p>
                <table width="100%" cellspacing="0" cellpadding="8" style="border-collapse:collapse; font-size:14px;">
                    <thead>
                        <tr style="background:#f9fafb; text-align:left;">
                            <th style="border-bottom:1px solid #e5e7eb;">Radnik</th>
                            <th style="border-bottom:1px solid #e5e7eb;">Smjena</th>
                            <th style="border-bottom:1px solid #e5e7eb;">Šta je zaboravljeno</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td style="border-bottom:1px solid #e5e7eb;">{{ $row['name'] }}</td>
                                <td style="border-bottom:1px solid #e5e7eb;">{{ $shiftCode }}</td>
                                <td style="border-bottom:1px solid #e5e7eb;">{{ $row['missing'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
