<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use DateTime;
use DateTimeZone;
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
    public function cancel_order(Request $request, $id)
    {
        try {
            //code...
            DB::beginTransaction();

            $store = $request['data_reponse'];

            $order = DB::table($this->_PRFIX_TABLE . '_wc_order_stats')->join($this->_PRFIX_TABLE . '_posts', $this->_PRFIX_TABLE . '_posts.ID', $this->_PRFIX_TABLE . '_wc_order_stats.order_id')->where($this->_PRFIX_TABLE . '_wc_order_stats.customer_id', $store->user_id)->where($this->_PRFIX_TABLE . '_wc_order_stats.order_id', '=',  $id)->first();
            if (!$order) {
                return $this->returnError([], 'Đơn hàng không tồn tại');
            }

            if ($order->post_status == "wc-pending" || $order->post_status == "wc-processing") {


                //ghi chú
                $content = "Trạng thái đơn hàng đã được chuyển từ " . $order->post_status . " sang Đã hủy.";
                if (!empty($request->all()['content'])) {
                    $content .= $content . " - " . $request->all()['content'];
                }
                $date = new DateTime('now', new DateTimeZone('GMT'));
                $date->modify('-7 hours');
                $insertGetId = DB::table($this->_PRFIX_TABLE . '_comments')->insertGetId(
                    array(
                        'comment_post_ID'     =>   $order->order_id,
                        'comment_author'     =>   $store->name,
                        'comment_author_email'     =>   $store->email,
                        'comment_content'     =>   $content,
                        'comment_date_gmt'     =>   $date->format('Y-m-d H:i:s'),
                        'comment_date'     =>   date('Y-m-d H:i:s'),
                    )
                );
                $insertMetaUser = DB::table($this->_PRFIX_TABLE . '_wc_orders_meta')->updateOrInsert(
                    array(
                        'order_id' => $order->order_id,
                        'meta_key' => 'comment'
                    ),
                    array('meta_value' => $content)
                );
                DB::table('wp_posts')
                    ->where('ID', $order->ID)
                    ->update(['post_status' => 'wc-cancelled']);

                // huy coupon
                $coupon = DB::table($this->_PRFIX_TABLE . '_wc_order_coupon_lookup')->where('order_id', '=',  $order->ID)->first();
                if ($coupon) {
                    $meta = DB::table($this->_PRFIX_TABLE . '_postmeta')
                        ->where('post_id', $coupon->coupon_id)
                        ->where('meta_key', 'usage_count')
                        ->first();

                    $metaValue = $meta ? $meta->meta_value - 1 : 0;
                    $insertMetaUser = DB::table($this->_PRFIX_TABLE . '_postmeta')->updateOrInsert(
                        array(
                            'post_id' => $coupon->coupon_id,
                            'meta_key' => 'usage_count'
                        ),
                        array('meta_value' =>  $metaValue)
                    );
                }
                DB::commit();

                return $this->returnSuccess([$order->ID], 'Hủy đơn hàng thành công');
            }
            return $this->returnError([], 'Đơn này không được hủy');
        } catch (\Throwable $th) {
            //throw $th;
            $this->woo_logs('cancel_order', $th->getMessage());
            DB::rollBack();

            return $this->returnError([],  'Đơn này không được hủy');
        }
    }
    public function index(Request $request)
    {
        //
        // $languare = env('DEFAULT_LANGUARE')?env('DEFAULT_LANGUARE'):"vi";

        $store = $request['data_reponse'];
        $param = $request->all();
        $this->_PRFIX_TABLE = $store->prefixTable;
        if (isset($param['status']) && !empty($param['status'])) {
            $orders = DB::table($this->_PRFIX_TABLE . '_wc_order_stats')->join($this->_PRFIX_TABLE . '_posts', $this->_PRFIX_TABLE . '_posts.ID', $this->_PRFIX_TABLE . '_wc_order_stats.order_id')->where($this->_PRFIX_TABLE . '_wc_order_stats.customer_id', $store->user_id)->where($this->_PRFIX_TABLE . '_posts.post_status', '!=', 'trash')->where($this->_PRFIX_TABLE . '_posts.post_status', '=',  $param['status'])->orderBy($this->_PRFIX_TABLE . '_wc_order_stats.date_created', 'DESC')->get();
        } else {
            $orders = DB::table($this->_PRFIX_TABLE . '_wc_order_stats')->join($this->_PRFIX_TABLE . '_posts', $this->_PRFIX_TABLE . '_posts.ID', $this->_PRFIX_TABLE . '_wc_order_stats.order_id')->where($this->_PRFIX_TABLE . '_wc_order_stats.customer_id', $store->user_id)->where($this->_PRFIX_TABLE . '_posts.post_status', '!=', 'trash')->orderBy($this->_PRFIX_TABLE . '_wc_order_stats.date_created', 'DESC')->get();
        }

        foreach ($orders as $key => $order) {

            $user = $this->info($order->customer_id);
            $orders[$key]->name = $user->name;
            $orders[$key]->id = $order->order_id;
            $orders[$key]->status = $order->post_status;
            $orders[$key]->phone = $user->mobile;
            $feeShipping = $this->getPostMeta($order->order_id, '_order_shipping');
            $orders[$key]->fee_shipping = ($feeShipping) ? $feeShipping : 0;

            $orders[$key]->address = $this->getPostMeta($order->order_id, '_shipping_address_index');

            $orders[$key]->total_amount = $order->total_sales;
            $ghichu = DB::table($this->_PRFIX_TABLE . '_comments')->where('comment_post_ID', $order->order_id)->where('comment_type', 'order_note')->where('comment_author', '!=', 'WooCommerce')->first();
            if ($ghichu) {
                $ghichu = $ghichu->comment_content;
            }
            $orders[$key]->message = $ghichu;
            $orders[$key]->discount = 0;
            $orders[$key]->total_price = $order->total_sales;

            $coupons = DB::table($this->_PRFIX_TABLE . '_wc_order_coupon_lookup')->where('order_id', $order->order_id)->get();
            if ($coupons) {
                foreach ($coupons as $coupon) {
                    $orders[$key]->discount += $coupon->discount_amount;
                    $orders[$key]->total_price += $order->total_sales + $coupon->discount_amount;
                }
            }

            // $orders[$key]->state = $this->getState($order->state);
            $orders[$key]->order_details = $this->detailOrder($order->order_id, $store);
            $temp = new stdClass;
            $temp->shipping_cost = 0;
            $orders[$key]->payment_meta = $temp;
            $orders[$key]->payment_gateway = $this->getPostMeta($order->order_id, '_payment_method');

            $history_user_point = DB::table($this->_PRFIX_TABLE . '_woo_history_user_point')->where('order_id', $order->order_id)->where('user_id', $order->customer_id)->get();
            $pointUse =  1000000;
            $pointReceive =  0;
            $pointUseMoney =  0;
            // lý do hủy
            $ly_do = DB::table($this->_PRFIX_TABLE . '_wc_orders_meta')->where('order_id', $order->order_id)
                ->where('meta_key', 'comment')->orderBy('id', "DESC")->first();
            $orders[$key]->ly_do = ($ly_do) ? $ly_do->meta_value : "";

            if ($pointUseMoney != 0) {
                $orders[$key]->total_price = $orders[$key]->total_price + $pointUseMoney;
            }
            $orders[$key]->point_use = $pointUse;

            $orders[$key]->points_converted_to_money = $pointUseMoney;

            $orders[$key]->point_receive = $pointReceive;
        }

        return $this->returnSuccess($orders);
    }
    public function update_status_shipper($orderId)
    {


        $user = DB::table('wp_posts')->where('ID', $orderId)->where('post_status', "wc-pending")->first();
        if ($user) {
            DB::table('wp_posts')
                ->where('ID', $user->ID)
                ->update(['post_status' => 'waiting-for-shipment']);
        } else {
        }
        return $this->returnSuccess($user, "Cập nhật vận chuyển thành công");
    }
    public function indexPos(Request $request)
    {
        //
        // $languare = env('DEFAULT_LANGUARE')?env('DEFAULT_LANGUARE'):"vi";

        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        if ($store->role == "administrator") {
            $orders = DB::table($this->_PRFIX_TABLE . '_posts')->where('post_type', 'shop_order')->where('post_type', 'shop_order')->whereDate('post_date', '=', date("Y-m-d"))->get();
        } elseif ($store->role == "shop_manager" || $store->role == "contributor") {
            $orders = DB::table($this->_PRFIX_TABLE . '_posts')->where('post_type', 'shop_order')->whereDate('post_date', '=', date("Y-m-d"))->get();
        }

        foreach ($orders as $key => $order) {
            // if($order->post_author == 0){

            //     $user = new stdClass();
            //     $user->name = $this->getPostMeta($order->ID, '_billing_first_name') ." ".$this->getPostMeta($order->ID, '_billing_last_name');
            //     $user->mobile = $this->getPostMeta($order->ID, '_billing_phone');
            // }else{
            // $user = $this->info($order->post_author);

            // }
            $user = new stdClass();
            $user->name = $this->getPostMeta($order->ID, '_billing_first_name') . " " . $this->getPostMeta($order->ID, '_billing_last_name');
            $user->mobile = $this->getPostMeta($order->ID, '_billing_phone');

            $noteDes = json_decode($order->post_excerpt, true);
            $note = $order->post_excerpt;


            if (is_array($noteDes)) {
                $note = $noteDes;
            }
            $orders[$key]->post_excerpt = $note;
            $orders[$key]->name = (isset($user->name)) ? $user->name : "Khách online";
            $orders[$key]->id = $order->ID;
            $orders[$key]->payment_gateway = $this->getPostMeta($order->ID, '_payment_method');

            $orders[$key]->phone = (isset($user->mobile)) ? $user->mobile : $user;
            $feeShipping = $this->getPostMeta($order->ID, '_order_shipping');
            $orders[$key]->fee_shipping = ($feeShipping) ? $feeShipping : 0;

            $orders[$key]->address = $this->getPostMeta($order->ID, '_shipping_address_index');

            $orders[$key]->total_amount = $this->getPostMeta($order->ID, '_order_total');
            $ghichu = DB::table($this->_PRFIX_TABLE . '_comments')->where('comment_post_ID', $order->ID)->where('comment_type', 'order_note')->where('comment_author', '!=', 'WooCommerce')->first();
            if ($ghichu) {
                $ghichu = $ghichu->comment_content;
            }
            $orders[$key]->message = $ghichu;
            $orders[$key]->discount = $this->getPostMeta($order->ID, '_cart_discount') || 0;
            $orders[$key]->total_price = $this->getPostMeta($order->ID, '_order_total');

            $coupons = DB::table($this->_PRFIX_TABLE . '_wc_order_coupon_lookup')->where('order_id', $order->ID)->get();
            if ($coupons) {
                foreach ($coupons as $coupon) {
                    $orders[$key]->discount += $coupon->discount_amount;
                    $orders[$key]->total_price += $order->total_sales + $coupon->discount_amount;
                }
            }

            // $orders[$key]->state = $this->getState($order->state);
            $orders[$key]->order = $this->detailOrder($order->ID, $store);
            $temp = new stdClass;
            $temp->shipping_cost = 0;
            $orders[$key]->payment_meta = $temp;
            $history_user_point = DB::table($this->_PRFIX_TABLE . '_woo_history_user_point')->where('order_id', $order->ID)->where('user_id', $order->post_author)->get();
            $pointUse =  0;
            $pointReceive =  0;
            $pointUseMoney =  0;
            foreach ($history_user_point as  $history) {
                if ($history->status == 4) {
                    $pointUse = $history->point;
                    $pointUseMoney = $pointUse * $history->points_converted_to_money;
                }
                if ($history->status == 1) {
                    $pointReceive = $history->point;
                }
            }
            if ($pointUseMoney != 0) {
                $orders[$key]->total_price = $orders[$key]->total_price + $pointUseMoney;
            }
            $orders[$key]->discount = $this->getPostMeta($order->ID, '_cart_discount');
            $orders[$key]->point_use = $pointUse;
            $orders[$key]->points_converted_to_money = $pointUseMoney;

            $orders[$key]->point_receive = $pointReceive;
        }

        return $this->returnSuccess($orders);
    }
    public function detailOrder($orderId, $store)
    {
        $ordersDetail = DB::table($this->_PRFIX_TABLE . '_woocommerce_order_items')->where('order_id', $orderId)->where('order_item_type', 'line_item')->get();
        $products = [];
        foreach ($ordersDetail as $key => $value) {
            // $checkReview = DB::table($this->_PRFIX_TABLE . '_comments')
            //     ->where($this->_PRFIX_TABLE . '_comments.comment_post_ID', $value->product_id)
            //     ->where($this->_PRFIX_TABLE . '_comments.comment_karma', $orderId)->first();
            $productId = $this->getOrderMeta($value->order_item_id, '_product_id');
            $image = $this->getImage($productId, $store);
            if (!$image) {
                $parentProduct = DB::table($this->_PRFIX_TABLE . '_posts')->select('post_parent')->find($productId);

                if ($parentProduct) {
                    $image = $this->getImage($parentProduct->post_parent, $store);
                }
            }
            $qty = $this->getOrderMeta($value->order_item_id, '_qty');
            if ($qty == 0) {
                $qty = 1;
            }

            $products[$key]['name'] = $value->order_item_name;
            $temp = new stdClass;
            $temp->image = $image;
            $total = $this->getOrderMeta($value->order_item_id, '_tm_epo_product_original_price');
            if ($total) {
                $total = unserialize($total)[0];
            }

            $products[$key]['options'] = $temp;
            $products[$key]['attribute'] = unserialize($this->getOrderMeta($value->order_item_id, '_tmcartepo_data'));
            $products[$key]['qty'] = $this->getOrderMeta($value->order_item_id, '_qty');
            $products[$key]['price'] = $total;
            $products[$key]['subtotal'] = $total * $qty;
            $products[$key]['product_id'] = $productId;
            // $products[$key]['is_review'] = ($checkReview) ? 1 : 0;
        }
        return $products;
    }
    public function signature(Request $request)
    {

        $endpoint = env('URL_MOMO') . '/v2/gateway/api/create';
        $partnerCode =  env('PARNER_CODE_MOMO');

        $accessKey = env('ACCESSKEY_MOMO');
        $secretKey = env('SECRETKEY_MOMO');
        $orderInfo = "Thanh toán qua MoMo";
        $amount = "10000";
        $orderId = time() . "";
        // Lưu ý: link notifyUrl không phải là dạng localhost
        $extraData = '';

        $appScheme = 'vn.gsmilkteaproduct.gsmilkteaproduct';

        $requestId = time() . "";
        $requestType = "captureWallet";
        $ipnUrl = env('API_URL_BACKEND') . '/api/webhook';
        $redirectUrl = "vn.gsmilkteaproduct.gsmilkteaproduct";
        //before sign HMAC SHA256 signature
        $rawHash = "accessKey=" . $accessKey .
        "&amount=" . $amount .
        "&extraData=" . $extraData .
        "&ipnUrl=" . $ipnUrl .
        "&orderId=" . $orderId .
        "&orderInfo=" . urldecode($orderInfo) . // dùng urldecode để khớp với raw
        "&partnerCode=" . $partnerCode .
        "&redirectUrl=" . $redirectUrl .
        "&requestId=" . $requestId .
        "&requestType=" . $requestType;
        $signature = hash_hmac("sha256", $rawHash, $secretKey);
        $data = array(
            'partnerCode' => $partnerCode,
            'accessKey' => $accessKey,
            'requestId' => $requestId,
            'appScheme' => $appScheme,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl ,
            'extraData' => $extraData,
            'requestType' => $requestType,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',

            'signature' => $signature
        );
        $result = $this->execPostRequest($endpoint, json_encode($data));
        $jsonResult = json_decode($result, true);  // decode json
        return $this->returnSuccess($jsonResult);
        // $data = array(
        //     'partnerCode' => $partnerCode,
        //     'accessKey' => $accessKey,
        //     'requestId' => $requestId,
        //     'amount' => $amount,
        //     'orderId' => $orderId,
        //     'orderInfo' => $orderInfo,
        //     'extraData' => $extraData,
        //     'requestType' => $requestType,
        //     'signature' => $signature
        // );
        // $result = $this->execPostRequest($endpoint, json_encode($data));
        // $jsonResult = json_decode($result, true);  // decode json

    }
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
                $user = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', $data['sdt'])->first();
                if (!$user) {
                    return $this->returnError([], "Số điện thoại chưa được đăng ký");
                }
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
                    'user_email' => $user->user_email,
                    'address' => $data['address']
                ];
                $order = $this->createOrder($data, $user);
                if (!$order) {
                    return $this->returnError(new \stdClass, $this->_messageError);
                }
                if ($data['payment_gateway'] == "momo") {
                    return $this->returnSuccess($order, "Thanh toán đơn hàng thành công");
                }
                return $this->returnSuccess($order, "Thêm đơn hàng thành công");
            }
        } catch (\Throwable $th) {
            $this->woo_logs('store', $th->getMessage());

            return $this->returnError(new \stdClass, $th->getMessage());
        }
    }
    function execPostRequest($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data)
            )
        );
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        //execute post
        $result = curl_exec($ch);
        //close connection
        curl_close($ch);
        return $result;
    }
    public function storePos(Request $request)
    {
        $store = $request['data_reponse'];

        $this->_PRFIX_TABLE = $store->prefixTable;
        try {
            $validator = Validator::make($request->all(), [
                '*.payment_gateway' => 'required',
                // '*.phone' => 'required',
                '*.order' => 'required',

            ], [
                '*.payment_gateway.required' => "Vui lòng nhập phương thức thanh toán",
                // '*.phone.required' => "Vui lòng nhập số điện thoại",
                '*.order' => "Vui lòng nhập đơn hàng"
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                $listOrder = [];
                $list = $request->all();
                unset($list['data_reponse']);


                foreach ($list as $data) {
                    $store = $request['data_reponse'];

                    if (isset($data['phone'])) {
                        $user = $this->addUserDefault($this->formatPhoneToInternational($data['phone']), "user_" . time() . "_" . rand(1, 10000000));
                    } else {
                        $user = $this->addUserDefault(88888888);
                    }

                    $data['country'] = 1;
                    $data['state'] = 1;
                    $data['used_coupon'] = "";

                    $data['city'] = (isset($data['city'])) ? $data['city'] : "Việt Nam";
                    $user = [
                        'id' => $user->ID,
                        'name' => $user->user_nicename,
                        'mobile' => $user->user_login,
                        'country' => $data['country'],
                        'state' => $data['state'],
                        'city' => $data['city'],
                        'email' => $user->user_email,
                        'user_email' => $user->user_email,
                        'address' => "",
                        'user_created' => $store->user_id
                    ];
                    $order = $this->createOrderPos($data, $user);

                    if (!$order) {
                        $this->woo_logs('order', json_encode($data));

                        // return $this->returnError(new \stdClass, $this->_messageError);
                    }

                    $listOrder[] = $order;
                }


                return $this->returnSuccess($listOrder, "Thêm đơn hàng thành công");
            }
        } catch (\Throwable $th) {
            $this->woo_logs('store', $th->getMessage());

            return $this->returnError(new \stdClass, $th->getMessage());
        }
    }
    public function addUserDefault($phone, $name = "Khách vãng lai")
    {


        $user = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', $phone)->first();

        if ($user) {
            return $user;
        }
        $email = $this->randomEmail();
        $insertGetId = DB::table($this->_PRFIX_TABLE . '_users')->insertGetId(
            array(
                'user_login'     =>   $phone,
                'user_pass'     =>   "appid",
                'user_email'     =>   $email,
                'user_nicename'     =>   $name,
                'display_name'     =>   $name,
                'user_registered'     =>   date('Y-m-d H:i:s'),
            )
        );

        $insertMetaUser = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
            array(
                'user_id' => $insertGetId,
                'meta_key' => 'last_name'
            ),
            array('meta_value' => $name)
        );
        $insertMetaUser = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
            array(
                'user_id' => $insertGetId,
                'meta_key' => 'wp_capabilities'
            ),
            array('meta_value' => 'a:1:{s:10:"subscriber";b:1;}')
        );
        $customer = DB::table($this->_PRFIX_TABLE . '_wc_customer_lookup')->where('user_id', $insertGetId)->first();
        if (!$customer) {
            $insertCus = DB::table($this->_PRFIX_TABLE . '_wc_customer_lookup')->insert(
                array(
                    'customer_id'     =>   $insertGetId,
                    'username'     =>   $phone,
                    'first_name'     =>  '',
                    'last_name'     =>  $name,
                    'user_id'     =>   $insertGetId,
                    'email'     =>   $email,


                )
            );
        }
        $user = DB::table($this->_PRFIX_TABLE . '_users')->where('ID', $insertGetId)->first();

        return $user;
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
    public function webhook(Request $request)
    {
        $this->woo_logs('momo', json_encode($request->all()));
        return $this->returnSuccess(json_encode($request->all()));
    }
    public function webhookPost(Request $request)
    {
        $this->woo_logs('momopost', json_encode($request->all()));
        return $this->returnSuccess(json_encode($request->all()));
    }
}
