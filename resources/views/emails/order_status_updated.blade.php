<div>
    <h2>Status naloga ažuriran</h2>
    <p>Poštovani,</p>
    <p>Status Vašeg naloga je ažuriran.</p>
    <ul>
        <li><strong>Broj naloga:</strong> {{ $order->OrderNumber }}</li>
        <li><strong>Datum naloga:</strong> {{ $order->OrderDate }}</li>
        <li><strong>Opis:</strong> {{ $order->Description }}</li>
        <li><strong>Status:</strong> {{ $order->Status }}</li>
        <!-- Dodaj još polja po potrebi -->
    </ul>
    <p>Srdačan pozdrav,<br>Pobjeda Rudet</p>
</div>
