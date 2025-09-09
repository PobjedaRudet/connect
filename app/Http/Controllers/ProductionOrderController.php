<?php

namespace App\Http\Controllers;

use App\Mail\OrderStatusUpdated;
use App\Models\Order;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\Partner;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ProductionOrderController extends Controller
{
    public function getOrderNumber()
    {
        // Pronađi zadnji OrderNumber iz production_orders i uvećaj za 1
        $lastOrder = ProductionOrder::orderByRaw('CAST(SUBSTRING_INDEX(OrderNumber, "/", 1) AS UNSIGNED) DESC')->first();
        if ($lastOrder && preg_match('/^(\d+)/', $lastOrder->OrderNumber, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        $yearShort = date('y');
        $orderNumber = $nextNumber . '/' . $yearShort;
        $workingOrders = ProductionOrder::all();
        return response()->json(['orderNumber' => $orderNumber, 'workingOrders' => $workingOrders]);
    }

    /*     public function create()
    {
        $workingOrders = ProductionOrder::all();
        Log::info($workingOrders);
        Log::info('Create order');

        return view('productionorders.createorder', compact('workingOrders'));
    } */

    public function showForm()
    {
        $workingOrders = ProductionOrder::all();
        $partners = Partner::all(['id', 'name']);
        Log::info($workingOrders);
        return Inertia::render('Nalozi/NaloziZaProizvodnju', [
            'workingOrders' => $workingOrders,
            'partners' => $partners,
            // ...ostali podaci
        ]);
    }
    public function store(Request $request)
    {
        Log::info('Request data:', $request->all());

        try {
            $request->validate([
                'orderNumber' => 'nullable|string',
                'partner_id' => 'required|integer|exists:partners,id',
                'productListNew' => 'required|array|min:1',
                'productListNew.*.id' => 'required|integer',
                'productListNew.*.quantity' => 'required|numeric',
            ]);

            // Generate next OrderNumber

            // Pronađi zadnji OrderNumber iz production_orders i uvećaj za 1
            $lastOrder = ProductionOrder::orderByRaw('CAST(SUBSTRING_INDEX(OrderNumber, "/", 1) AS UNSIGNED) DESC')->first();
            if ($lastOrder && preg_match('/^(\d+)/', $lastOrder->OrderNumber, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            } else {
                $nextNumber = 1;
            }
            $yearShort = date('y');
            $orderNumber = $nextNumber . '/' . $yearShort;

            $orderData = $request->except('productListNew');
            $orderData['partner_id'] = $request->input('partner_id');
            $orderData['OrderNumber'] = $orderNumber;

            $productListNew = $request->input('productListNew', []);

            // Create new order with partner_id and incremented OrderNumber
            $order = ProductionOrder::create($orderData);
            Log::info("Order created:", ['order' => $order]);

            // Save order details (products)
            foreach ($productListNew as $product) {
                ProductionOrderDetail::create([
                    'production_order_id' => $order->id,
                    'product_id' => $product['id'],
                    'quantity' => $product['quantity'],
                ]);
            }

            //find token by
            $token = ProductionOrder::where('OrderNumber', $orderData['OrderNumber'])->firstOrFail();
            Log::info("Token fetched:", ['token' => $token->token]);
            $order_send = ProductionOrder::where('token', $token->token)->firstOrFail();

            Log::info("Order fetched:", ['order_send' => $order_send]);

            // Send an email notification
            if (!empty('h.ahmet@pobjeda.com')) {
                Mail::to('h.ahmet@pobjeda.com')->send(new OrderStatusUpdated($order));
            } else {
                Log::warning("Order does not have a customer email, using default email h.ahmet@pobjeda.com:", ['order' => $order_send]);
                Mail::to('h.ahmet@pobjeda.com')->send(new OrderStatusUpdated($order));
            }

            return response()->json(['message' => 'Order successfully saved!'], 200);
        } catch (Exception $e) {
            Log::error("Error: {$e->getMessage()}");
            return response()->json(['message' => 'An error occurred while saving the order.'], 500);
        }
    }
}
