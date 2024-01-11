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
    public function info($id){
        $user = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_users')->where('ID', $id)->select('ID','display_name as name','user_email as email','user_login as mobile')
        ->first();
        return $user;
    }
    public function index(Request $request)
    {
        //
        // $languare = env('DEFAULT_LANGUARE')?env('DEFAULT_LANGUARE'):"vi";

        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $orders = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_wc_order_stats')->join( $this->_PRFIX_TABLE .'_posts', $this->_PRFIX_TABLE .'_posts.ID', $this->_PRFIX_TABLE .'_wc_order_stats.order_id')->where( $this->_PRFIX_TABLE .'_wc_order_stats.customer_id', $store->user_id)->where( $this->_PRFIX_TABLE .'_posts.post_status','!=', 'trash')->orderBy( $this->_PRFIX_TABLE .'_wc_order_stats.date_created', 'DESC')->get();

        foreach ($orders as $key => $order) {
            $user = $this->info($order->customer_id);
            $orders[$key]->name = $user->name;
            $orders[$key]->id = $order->order_id;
            $orders[$key]->phone = $user->mobile;
            $orders[$key]->address = $this->getPostMeta($order->order_id,'_shipping_address_index');

            $orders[$key]->total_amount = $order->total_sales;
            $ghichu = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_comments')->where('comment_post_ID',$order->order_id)->where('comment_type','order_note')->where('comment_author','!=','WooCommerce')->first();
            if($ghichu){
                $ghichu = $ghichu->comment_content;
            }
            $orders[$key]->message = $ghichu;
            $orders[$key]->discount = 0;
            $orders[$key]->total_price = $order->total_sales;

            $coupon = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_wc_order_coupon_lookup')->where('order_id',$order->order_id)->first();
            if($coupon){
                $orders[$key]->discount = $coupon->discount_amount;
                $orders[$key]->total_price = $order->total_sales + $coupon->discount_amount;
            }

            // $orders[$key]->state = $this->getState($order->state);
            $orders[$key]->order_details = $this->detailOrder($order->order_id, $store);
            $temp = new stdClass;
            $temp->shipping_cost = 0;
            $orders[$key]->payment_meta = $temp;
            $history_user_point = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_woo_history_user_point')->where( 'order_id', $order->order_id)->where( 'user_id',$order->customer_id)->get();
            $pointUse =  0;
            $pointReceive =  0;
            $pointUseMoney =  0;
            foreach($history_user_point as  $history){
                if($history->status == 4){
                    $pointUse = $history->point;
                    $pointUseMoney = $pointUse * $history->points_converted_to_money;
                }
                if($history->status == 1){
                    $pointReceive = $history->point;
                }
            }
            if($pointUseMoney!=0){
                $orders[$key]->total_price = $orders[$key]->total_price + $pointUseMoney;
            }
            $orders[$key]->point_use = $pointUse;
            $orders[$key]->points_converted_to_money = $pointUseMoney;

            $orders[$key]->point_receive = $pointReceive;

        }

        return $this->returnSuccess($orders);
    }
    public function detailOrder($orderId, $store)
    {
        $ordersDetail = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_wc_order_product_lookup')->join( $this->_PRFIX_TABLE .'_posts', $this->_PRFIX_TABLE .'_posts.ID', $this->_PRFIX_TABLE .'_wc_order_product_lookup.product_id')->where( $this->_PRFIX_TABLE .'_wc_order_product_lookup.order_id', $orderId)->select( $this->_PRFIX_TABLE .'_wc_order_product_lookup.*', $this->_PRFIX_TABLE .'_posts.post_title')->get();
        $products = [];
        foreach ($ordersDetail as $key => $value) {
            $product[$key]['name']=$value->post_title;
            $temp = new stdClass;
            $temp->image = $this->getImage($value->product_id, $store);
            $total = $this->getOrderMeta($value->order_item_id,'_line_subtotal');
            $product[$key]['options']= $temp;
            $product[$key]['qty']= $value->product_qty;
            $product[$key]['price']= $total / $value->product_qty;
            $product[$key]['subtotal']= $total;

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
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        try {
            $validator = Validator::make($request->all(), [
                'payment_gateway' => 'required',
                'name' => 'required',
                'phone' => 'required',
                'address' => 'required',
                'order' => 'required',
                'email' => 'required',

            ], [
                'payment_gateway.required' => "Vui lòng nhập phương thức thanh toán",
                'name.required' => "Vui lòng nhập Họ và tên",
                'phone.required' => "Vui lòng nhập số điện thoại",
                'address.required' => "Vui lòng nhập địa chỉ",
                'address.order' => "Vui lòng nhập đơn hàng",
                'email.order' => "Vui lòng nhập email"
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                $data = $request->all();
                $store = $request['data_reponse'];
                $data['sdt'] = $store->sdt;
                $user = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_users')->where('user_login', $data['sdt'])->first();
                if (!$user) {
                    return $this->returnError([], "Số điện thoại chưa được đăng ký");
                }
                $data['country'] =1;
                $data['state'] =1;
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
