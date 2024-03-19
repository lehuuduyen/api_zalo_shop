<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use stdClass;

class OrdersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function info($id)
    {
        $user = DB::table($this->_PRFIX_TABLE . '_users')->where('ID', $id)->select('ID', 'display_name as name', 'user_email as email', 'user_login as mobile')
            ->first();
        return $user;
    }
    public function index(Request $request)
    {
        $orders=[];
        //
        // $languare = env('DEFAULT_LANGUARE')?env('DEFAULT_LANGUARE'):"vi";
        $data = $request->all();
        if (isset($data['user'])) {
            $user = $data['user'];
            $orders = DB::table($this->_PRFIX_TABLE . '_wc_orders')->join($this->_PRFIX_TABLE . '_posts', $this->_PRFIX_TABLE . '_posts.ID', $this->_PRFIX_TABLE . '_wc_orders.id')->where($this->_PRFIX_TABLE . '_wc_orders.customer_id', $user->ID)->where($this->_PRFIX_TABLE . '_posts.post_status', '!=', 'trash')->orderBy($this->_PRFIX_TABLE . '_wc_orders.date_created_gmt', 'DESC')->get();

            foreach ($orders as $key => $order) {
                $user = $this->info($order->customer_id);
                $orders[$key]->name = $user->name;
                $orders[$key]->id = $order->id;
                $orders[$key]->phone = $user->mobile;

                $discount = DB::table($this->_PRFIX_TABLE . '_wc_orders_meta')->where('order_id', $order->id)->where('meta_key', 'discount')->first();

                $orders[$key]->discount = ($discount)?$discount->meta_value:0;
                $orders[$key]->total_last = (int)$order->total_amount ;

                $orders[$key]->total_price = $order->total_amount +$orders[$key]->discount;

                $coupon = DB::table($this->_PRFIX_TABLE . '_wc_order_operational_data')->where('order_id', $order->id)->first();
                if ($coupon) {
                    $orders[$key]->coupon = $coupon->discount_total_amount;
                    $orders[$key]->total_goc = $order->total_price + $coupon->discount_total_amount;
                }

                // $orders[$key]->state = $this->getState($order->state);
                $orders[$key]->order_details = $this->detailOrder($order->id);


            }
        }


        return $this->returnSuccess($orders);
    }
    public function detailOrder($orderId)
    {
        $ordersDetail = DB::table($this->_PRFIX_TABLE . '_wc_order_product_lookup')
        ->join($this->_PRFIX_TABLE . '_posts', $this->_PRFIX_TABLE . '_posts.ID', $this->_PRFIX_TABLE . '_wc_order_product_lookup.product_id')
        ->where($this->_PRFIX_TABLE . '_wc_order_product_lookup.order_id', $orderId)
        ->select($this->_PRFIX_TABLE . '_wc_order_product_lookup.*', $this->_PRFIX_TABLE . '_posts.post_title')->get();
        $products = [];
        foreach ($ordersDetail as $key => $value) {
            $phimId = $this->getPostMeta($value->order_id,'_film_selected');
            $phim = DB::table($this->_PRFIX_TABLE . '_films')->find($phimId);

            $product[$key]['name'] = $value->post_title;
            $temp = new stdClass;
            $temp->image = $this->getImage($value->product_id);
            $total = $this->getOrderMeta($value->order_item_id, '_line_subtotal');
            $product[$key]['phim_id'] = $phimId;
            $product[$key]['phim_name'] = ($phim)?$phim->film_name:'';
            $product[$key]['options'] = $temp;
            $product[$key]['qty'] = $value->product_qty;
            $product[$key]['price'] = $total / $value->product_qty;
            $product[$key]['subtotal'] = $total;
        }
        return $product;
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_gateway' => 'required',
                'name' => 'required',
                'user_id' => 'required',
                'sdt' => 'required',
                'address' => 'required',
                'order' => 'required',
                'email' => 'required',

            ], [
                'payment_gateway.required' => "Vui lòng nhập phương thức thanh toán",
                'name.required' => "Vui lòng nhập Họ và tên",
                'user_id' => 'user_id required',
                'sdt.required' => "Vui lòng nhập số điện thoại",
                'address.required' => "Vui lòng nhập địa chỉ",
                'address.order' => "Vui lòng nhập đơn hàng",
                'email.order' => "Vui lòng nhập email"
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                $data = $request->all();
                $user = $data['user'];
                $data['phone'] = $data['sdt'];
                $data['country'] = 1;
                $data['state'] = 1;
                $data['city'] = (isset($data['city'])) ? $data['city'] : "Việt Nam";
                $user = [
                    'id' => $user->ID,
                    'name' => $data['name'],
                    'mobile' => $data['phone'],
                    'country' => $data['country'],
                    'state' => $data['state'],
                    'city' => $data['city'],
                    'email' => $data['email'],
                    'address' => $data['address']
                ];
                $order = $this->createOrder($data, $user);
                if (!$order) {
                    return $this->returnError(new \stdClass, $this->_messageError);
                }
                return $this->returnSuccess($order, "Thêm đơn hàng thành công");
            }
        } catch (\Throwable $th) {
            $this->woo_logs('store', $th->getMessage());

            return $this->returnError(new \stdClass, $th->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
