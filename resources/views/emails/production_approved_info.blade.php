<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odobrenje proizvodnje</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color:#111827; }
        .muted { color:#6b7280; font-size:12px; }
        .box { border:1px solid #e5e7eb; border-radius:6px; padding:12px; }
        .value { color:#111827; font-size:14px; }
        .tag { display:inline-block; padding:2px 6px; border-radius:9999px; background:#f3f4f6; color:#111827; font-size:12px; }
    </style>
</head>
<body>
    <p>Poštovani,</p>

    <p class="muted">Informacija: nalog je odobren u proizvodnji.</p>

    <div class="box">
        <p class="value"><strong>Nalog:</strong> {{ $orderNumber }}</p>
        @if(!empty($description))
            <p class="value"><strong>Opis:</strong> {{ $description }}</p>
        @endif

        <p class="value"><strong>Odobrio:</strong> {{ $approvedByName }} ({{ $approvedByFunkcija }})
            @if(!empty($approvedAsProxy) && $approvedAsProxy)
                <span class="tag">proxy</span>
            @endif
        </p>

        <p class="muted">Vrijeme: {{ $approvedAt }}</p>

        @if(!empty($comment))
            <p class="value"><strong>Komentar:</strong> {{ $comment }}</p>
        @endif
    </div>
</body>
</html>
