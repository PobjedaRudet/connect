<!DOCTYPE html>
<html>
<head>
    <title>Ljekarski pregledi</title>
</head>
<body>
    <h2>📅 Nadolazeći pregledi u ovom mjesecu</h2>

    @php
        // Sort $upcoming by lastName, then firstName
        $upcomingSorted = collect($upcoming)->sortBy([
            fn($a) => $a['employee']->lastName,
            fn($a) => $a['employee']->firstName,
        ])->values();

        // Sort $expired by lastName, then firstName
        $expiredSorted = collect($expired)->sortBy([
            fn($a) => $a['employee']->lastName,
            fn($a) => $a['employee']->firstName,
        ])->values();
    @endphp

    @if(count($upcomingSorted))
        <ul>
        @foreach($upcomingSorted as $index => $item)
            <li>
                {{ $index + 1 }}. {{ $item['employee']->lastName }} {{ $item['employee']->firstName }},
                pozicija: {{ $item['employee']->radno_mjesto }},
                pregled do: {{ \Carbon\Carbon::parse($item['next_due'])->format('d.m.Y') }}
            </li>
        @endforeach
        </ul>
    @else
        <p>Nema pregleda u ovom mjesecu.</p>
    @endif

    <hr>

    <h2 style="color:red">⛔ Istekli pregledi</h2>

    @if(count($expiredSorted))
        <ul>
        @foreach($expiredSorted as $index => $item)
            <li>
                {{ $index + 1 }}. {{ $item['employee']->lastName }} {{ $item['employee']->firstName }},
                pozicija: {{ $item['employee']->radno_mjesto }},
                pregled trebao biti do: <strong>{{ \Carbon\Carbon::parse($item['next_due'])->format('d.m.Y') }}</strong>
            </li>
        @endforeach
        </ul>
    @else
        <p>Nema isteklih pregleda.</p>
    @endif
</body>
</html>
