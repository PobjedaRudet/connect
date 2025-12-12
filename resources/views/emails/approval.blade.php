<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novi nalozi na odobrenje</title>
    <style>
        /* Keep minimal styles; Outlook ignores most head CSS */
        body { font-family: Arial, Helvetica, sans-serif; color:#111827; }
        .muted { color:#6b7280; font-size:12px; }
        .table { width:100%; border-collapse: collapse; margin-top:8px; margin-bottom:16px; }
        .table th, .table td { border:1px solid #e5e7eb; padding:8px; text-align:left; font-size:14px; }
        .table th { background:#f3f4f6; color:#111827; }
        .small { font-size:12px; color:#374151; }
    </style>
    </head>
<body>
    <p>Poštovani,</p>
    <p>Sljedeći nalozi čekaju vaše odobrenje ({{ $funkcija }}):</p>
    <table class="table" role="presentation" cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <th>Broj naloga</th>
                <th>Opis</th>
                <th>Kreirao</th>
                <th>Kreirano</th>
                <th>Kupac</th>
                <th>Količina</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $o)
                <tr>
                    <td><strong>{{ $o['OrderNumber'] ?? '' }}</strong></td>
                    <td>{{ $o['Description'] ?? '' }}</td>
                    <td>{{ $o['creator'] ?? '' }}</td>
                    <td>{{ $o['created_at'] ?? '' }}</td>
                    <td>{{ $o['partner'] ?? '' }}</td>
                    <td>{{ isset($o['total_qty']) ? $o['total_qty'] : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>
        <!--[if mso]>
        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $directUrl }}" style="height:40px;v-text-anchor:middle;width:260px;" arcsize="12%" strokecolor="#16a34a" fillcolor="#16a34a">
            <w:anchorlock/>
            <center style="color:#ffffff;font-family:Arial, Helvetica, sans-serif;font-size:14px;">
                Odobri navedene naloge
            </center>
        </v:roundrect>
        <![endif]-->
        <!--[if !mso]><!-- -->
        <a href="{{ $directUrl }}" style="background-color:#16a34a;border:1px solid #16a34a;border-radius:6px;color:#ffffff;display:inline-block;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:40px;text-align:center;text-decoration:none;width:260px;-webkit-text-size-adjust:none;mso-hide:all;">
            Odobri navedene naloge
        </a>
        <!--<![endif]-->
    </p>

    <p class="muted">Napomena: Link je vremenski ograničen i važi samo dok su nalozi u statusu čekanja odobrenja kod vas.</p>
    <p>Hvala.</p>
</body>
</html>
