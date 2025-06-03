<!DOCTYPE html>
<html>
<head>
    <title>Ljekarski pregledi</title>
</head>
<body>
    <h2>📅 Nadolazeći pregledi u ovom mjesecu</h2>

    @if(count($upcoming))
        <ul>
        @foreach($upcoming as $item)
            <li>
                {{ $item['employee']->firstName }} {{ $item['employee']->lastName }},
                pozicija: {{ $item['employee']->jobTitle }},
                pregled do: {{ \Carbon\Carbon::parse($item['next_due'])->format('d.m.Y') }}
            </li>
        @endforeach
        </ul>
    @else
        <p>Nema pregleda u ovom mjesecu.</p>
    @endif

    <hr>

    <h2 style="color:red">⛔ Istekli pregledi</h2>

    @if(count($expired))
        <ul>
        @foreach($expired as $item)
            <li>
                {{ $item['employee']->firstName }} {{ $item['employee']->lastName }},
                pozicija: {{ $item['employee']->jobTitle }},
                pregled trebao biti do: <strong>{{ \Carbon\Carbon::parse($item['next_due'])->format('d.m.Y') }}</strong>
            </li>
        @endforeach
        </ul>
    @else
        <p>Nema isteklih pregleda.</p>
    @endif
</body>
</html>
