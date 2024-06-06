<!DOCTYPE html>
<html>

<head>
 <meta charset="utf-8">
 <title>Siparişler PDF</title>
 <style>
 body {
  font-family: DejaVu Sans, sans-serif;
 }

 table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
 }

 th,
 td {
  padding: 8px;
  border: 1px solid #ddd;
  font-size: 10px;
 }

 th {
  background-color: #f2f2f2;
 }

 .order-title {
  margin-top: 30px;
 }
 </style>
</head>

<body>
 @if(!empty($orders) && $orders->count())
 @foreach ($orders as $order)
 <table>
  <thead>
   <tr>
    <th>Sipariş ID</th>
    <th>Sipariş Adı</th>
    <th>Durum</th>
    <th>Teklif Fiyatı</th>
    <th>Net Teklif Fiyatı</th>
   </tr>
  </thead>
  <tbody>
   <tr>
    <td>{{ $order->id }}</td>
    <td>{{ $order->order_code }}</td>
    <td>{{ $order->status }}</td>
    <td>{{ number_format($order->offer_price, 2) }}₺</td>
    <td>{{ number_format($order->net_offer_price, 2) }}₺</td>
   </tr>
  </tbody>
 </table>
 <table>
  <thead>
   <tr>
    <th>Miktar</th>
    <th>Renk</th>
    <th>Birim Fiyatı</th>
    <th>Ürün Kategorisi</th>
   </tr>
  </thead>
  <tbody>
   @foreach ($order->orderItems as $item)
   <tr>
    <td>{{ $item->quantity }}</td>
    <td>{{ $item->color }}</td>
    <td>{{ number_format($item->unit_price, 2) }}₺</td>
    <td>{{ $item->productCategory->category}}</td>
   </tr>
   @endforeach
  </tbody>
 </table>
 @endforeach

 <table>
  <thead>
   <tr>
    <th>Toplam Miktar</th>
    <th>Toplam Teklif Fiyatı</th>
    <th>Toplam Net Teklif Fiyatı</th>
   </tr>
  </thead>
  <tbody>
   <tr>
    <td>{{ $totals['total_quantities'] }}</td>
    <td>{{ number_format($totals['total_offer_price'], 2) }}₺</td>
    <td>{{ number_format($totals['total_net_offer_price'], 2) }}₺</td>
   </tr>
  </tbody>
 </table>
 @endif
</body>

</html>