<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Orders Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom:4px;
        }
        th, td {
            border: 1px solid #cecece;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .mb{
            margin-bottom:30px;
        }
    </style>
</head>
<body>
    @foreach ($orders as $order)
    <div class="mb">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Adi</th>
                <th>Fatura Tipi</th>
                <th>O.Tutar</th>
                <th>T.Tutarı</th>
                <th>Odeme</th>
                <th>Tarih</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $order->id }}</td>
                <td>{{ $order->order_name }}</td>
                <td>{{ $order->invoice_type }}</td>
                <td>{{ $order->paid_amount }}</td>
                <td>{{ $order->offer_price }}</td>
                <td>{{ $order->payment_status }}</td>
                <td>{{ $order->created_at }}</td>
            </tr>
        </tbody>
    </table>
    <table>
        <thead>
            <tr>
                <th>Tip</th>
                <th>Renk</th>
                <th>Adet</th>
                <th>Teklif</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach ($order->orderItems as $item)
                <td>{{ $item->productType->type }}</td>
                <td>{{ $item->color }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ $item->unit_price }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
    </div>
    @endforeach
</body>
</html>
