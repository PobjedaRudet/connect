<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxy odobrenje</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color:#111827; }
        .muted { color:#6b7280; font-size:12px; }
        .box { border:1px solid #e5e7eb; border-radius:6px; padding:12px; }
        .label { color:#374151; font-size:12px; }
        .value { color:#111827; font-size:14px; }
    </style>
</head>
<body>
    <p>Poštovani,</p>

    <p class="muted">Ovo je informativna poruka: proxy odobrenje je izvršeno za vaš nivo.</p>

    <div class="box">
        <p class="value"><strong>Nalog:</strong> {{ $orderNumber }}</p>
        @if(!empty($description))
            <p class="value"><strong>Opis:</strong> {{ $description }}</p>
        @endif
        <p class="value"><strong>Odobreno kao proxy za:</strong> {{ $proxyTargetFunkcija }}</p>
        <p class="value"><strong>Odobrio:</strong> {{ $proxyByName }} ({{ $proxyFromFunkcija }})</p>
        <p class="muted">Vrijeme: {{ $approvedAt }}</p>
        @if(!empty($comment))
            <p class="value"><strong>Komentar:</strong> {{ $comment }}</p>
        @endif
    </div>

    <p class="muted">Nema potrebe za akcijom s vaše strane za ovaj korak.</p>
</body>
</html>
