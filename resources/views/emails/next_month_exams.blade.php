<html>
<body>
    <h2>Nadolazeći ljekarski pregledi za idući mjesec</h2>
    <h3>Pregledi koji dolaze:</h3>
    <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">
        <tr>
            <th>#</th>
            <th>Ime i prezime</th>
            <th>SAP Id</th>
            <th>Pozicija</th>
            <th>Datum</th>
        </tr>
        @forelse($upcoming as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item['employee']->firstName }} {{ $item['employee']->lastName }}</td>
                <td>{{ $item['employee']->empID }}</td>
                <td>{{ $item['employee']->radno_mjesto }}</td>
                <td>{{ \Carbon\Carbon::parse($item['next_due'])->format('d.m.Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Nema nadolazećih pregleda za idući mjesec.</td></tr>
        @endforelse
    </table>
    <h3 style="margin-top:30px;">Istekli pregledi:</h3>
    <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">
        <tr>
            <th>#</th>
            <th>Ime i prezime</th>
            <th>SAP Id</th>
            <th>Pozicija</th>
            <th>Trebao biti</th>
        </tr>
        @forelse($expired as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item['employee']->firstName }} {{ $item['employee']->lastName }}</td>
                <td>{{ $item['employee']->empID }}</td>
                <td>{{ $item['employee']->radno_mjesto }}</td>
                <td>{{ \Carbon\Carbon::parse($item['next_due'])->format('d.m.Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Nema isteklih pregleda.</td></tr>
        @endforelse
    </table>
</body>
</html>
