<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function calPriceDiscount($price,$discount){
        if($discount > 0){
            $sale = $price - ($discount * $price /100);
            return $sale;
        }
        return 0;
    }
    function checkEven($number) {
        if ($number % 2 == 0) {
            return true;
        } else {
            return false;
        }
    }
    public function index(Request $request)
    {
        $store = new stdClass();
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

        // Combine protocol and host to get the full domain
        $fullDomain = $protocol . \env('APP_URL_BACKEND');
        $store->domain = $fullDomain;
        $this->_PRFIX_TABLE = 'wp';
        
        $discount = 0;
        
        if (isset($request['category'])) {
            $products = $this->getPostByCategoryId($request['category']);
        }elseif (isset($request['s'])) {
            $products = DB::table($this->_PRFIX_TABLE . '_posts')
            ->where('post_title', 'LIKE', '%' . $request['s'] . '%')->where('post_type', 'product')->where('post_status', 'publish')->orderBy('post_modified', 'DESC')->get();
        }else{
            $products = DB::table($this->_PRFIX_TABLE . '_posts')->where('post_type', 'product')->where('post_status', 'publish')->orderBy('post_modified', 'DESC')->get();

        }



        $time = time();
        $listProducts = [];
        $listChildProducts = [];
        foreach ($products as $key => $product) {
            // $childProduct = DB::table($this->_PRFIX_TABLE . '_posts')->where('post_parent', $product->ID)->where('post_type', 'product_variation')->where('post_status', 'publish')->get();
           
            
            $products[$key]->product_inventory = $this->getProductInventory($product->ID);
            $products[$key]->category = $this->getCategoryByProduct($product->ID, $store);
            $products[$key]->image_id = $this->getImage($product->ID, $store);
            $products[$key]->is_specical = $this->checkEven($product->ID);
            // foreach($childProduct as $keyChild =>  $child){
            //  $postMetaGiaGoc = $this->getPostMeta($child->ID, '_regular_price');
            //  $postMetaGiaKhuyenMai = $this->getPostMeta($child->ID, '_sale_price');
            //  $_sale_price_dates_from = $this->getPostMeta($child->ID, '_sale_price_dates_from');
            //  $_sale_price_dates_to = $this->getPostMeta($child->ID, '_sale_price_dates_to');
            //  $childProduct[$keyChild]->price =  $postMetaGiaGoc;
            //  $childProduct[$keyChild]->sale_price =  $postMetaGiaGoc;

            //  $childProduct[$keyChild]->price_discount =  $this->calPriceDiscount($childProduct[$keyChild]->price,$discount);
            //  $childProduct[$keyChild]->discount =   $discount;

            //  $childProduct[$keyChild]->is_campaign =  false;

            //  if ($postMetaGiaKhuyenMai && empty($_sale_price_dates_from) && empty($_sale_price_dates_to)) {
            //      $childProduct[$keyChild]->sale_price = $postMetaGiaKhuyenMai;
            //      $childProduct[$keyChild]->price_discount =  $this->calPriceDiscount($childProduct[$keyChild]->sale_price,$discount);

            //  }
            //  if ($postMetaGiaKhuyenMai && $time >= $_sale_price_dates_from && $time <= $_sale_price_dates_to) {
            //      $childProduct[$keyChild]->sale_price = $postMetaGiaKhuyenMai;
            //     $childProduct[$keyChild]->price_discount =  $this->calPriceDiscount($childProduct[$keyChild]->sale_price,$discount);

            //      $childProduct[$keyChild]->is_campaign = true;
            //      $childProduct[$keyChild]->end_date = date('Y/m/d H:i:s', $_sale_price_dates_to);
            //  }
            //  $childProduct[$keyChild]->id = $child->ID;
            //  $childProduct[$keyChild]->is_bien_the = true;
            //  $childProduct[$keyChild]->product_inventory = $products[$key]->product_inventory;
            //  $childProduct[$keyChild]->category = $products[$key]->category;
            //  $childProduct[$keyChild]->review = $this->getreview($child->ID);
            //  $imgChild = $this->getImage($child->ID, $store);
            //  $childProduct[$keyChild]->image_id = ($imgChild)?$imgChild:$products[$key]->image_id;
             
            //  $listChildProducts[]=$childProduct[$keyChild];
            // }

            $products[$key]->id = $product->ID;
            $products[$key]->product_id = $product->ID;
            $postMetaStatus = $this->getPostMeta($product->ID, '_stock_status');
            $postMetaStock = $this->getPostMeta($product->ID, '_stock');
            $postMetaGiaGoc = $this->getPostMeta($product->ID, '_regular_price');

            $postMetaGiaKhuyenMai = $this->getPostMeta($product->ID, '_sale_price');
            $_sale_price_dates_from = $this->getPostMeta($product->ID, '_sale_price_dates_from');
            $_sale_price_dates_to = $this->getPostMeta($product->ID, '_sale_price_dates_to');

            $postMetaStock = $this->getPostMeta($product->ID, '_stock');
            $products[$key]->is_campaign = false;
            $products[$key]->price =  $postMetaGiaGoc;
            $products[$key]->sale_price =  $postMetaGiaGoc;
            if ($postMetaGiaKhuyenMai && empty($_sale_price_dates_from) && empty($_sale_price_dates_to)) {
                $products[$key]->sale_price = $postMetaGiaKhuyenMai;

            }
            if ($postMetaGiaKhuyenMai && $time >= $_sale_price_dates_from && $time <= $_sale_price_dates_to) {
                $products[$key]->sale_price = $postMetaGiaKhuyenMai;

                $products[$key]->is_campaign = true;
                $products[$key]->end_date = date('Y/m/d H:i:s', $_sale_price_dates_to);
            }

            // $products[$key]->brand_id = $this->getBrand($product->brand_id, $store);
            $products[$key]->name = $product->post_title;
            $products[$key]->summary = $product->post_excerpt;
            $products[$key]->description = $product->post_content;
            $products[$key]->badge_id = [];

            if($products[$key]->image_id){
                $listImgThumb =[
                    'path'=>$products[$key]->image_id->path,
                    'title'=>"",
                    'alt'=>""
                ];
                
                $gale = $this->getGalleries($product->ID, $store);
                array_unshift($gale, $listImgThumb);
                $products[$key]->galleries = $gale;
                

            }else{
                $products[$key]->galleries = $this->getGalleries($product->ID, $store);
            }
            
            $products[$key]->delivery_option = [];
            // $products[$key]->delivery_option = $this->getProductDeliveryOption($product->id);
            $products[$key]->unit = [];
            // $products[$key]->unit = $this->getUnit($product->id);
            $products[$key]->policy = [];
            // $products[$key]->policy = $this->getPolicy($product->id);
            $products[$key]->tag_name = [];
            // $products[$key]->tag_name = $this->getTagName($product->id);

            $products[$key]->review = $this->getreview($product->ID);
            $products[$key]->sold_count =  $products[$key]->product_inventory->sold_count;
            $products[$key]->is_bien_the = false;

            $listProducts[] = $products[$key];
            // $products[$key]->state = $this->getState($order->state);
            // $products[$key]->order_details = json_decode($order->order_details);
            // $products[$key]->payment_meta = json_decode($order->payment_meta);

        }
        
        // $listProducts = array_merge($listProducts,$listChildProducts);
        return $this->returnSuccess($listProducts);
    }
    public function getCategories(Request $request)
    {
        $store = new stdClass();
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

        // Combine protocol and host to get the full domain
        $fullDomain = $protocol . \env('APP_URL_BACKEND');
        $store->domain = $fullDomain;
        $this->_PRFIX_TABLE = 'wp';

      
        $this->_PRFIX_TABLE = $store->prefixTable;
     
        $categories = DB::table($this->_PRFIX_TABLE . '_term_taxonomy')->join($this->_PRFIX_TABLE . '_terms', $this->_PRFIX_TABLE . '_terms.term_id', $this->_PRFIX_TABLE . '_term_taxonomy.term_id')->where($this->_PRFIX_TABLE . '_term_taxonomy.taxonomy', 'product_cat')->select($this->_PRFIX_TABLE . '_terms.*')->get();
        
        if ($categories) {
            foreach ($categories as $key => $val) {
                $img = DB::table($this->_PRFIX_TABLE . '_termmeta')->where($this->_PRFIX_TABLE . '_termmeta.meta_key', 'thumbnail_id')->where($this->_PRFIX_TABLE . '_termmeta.term_id', $val->term_id)->first();
                $categories[$key]->id =  $val->term_id;
                $categories[$key]->image =  ($img) ? $this->getImage($img->meta_value, $store, true) : "";
            }
        }


        return $this->returnSuccess($categories);
    }
    public function getAttribute(Request $request)
    {
        
        $store = $request['data_reponse'];
      
        $this->_PRFIX_TABLE = $store->prefixTable;
     
        $attribute = DB::table($this->_PRFIX_TABLE . '_postmeta')->where('meta_key','tm_meta')->select('meta_value')->get();
        $listTopping = [];
        $listBigSize = [];
        if ($attribute) {
            $listAttribute = unserialize($attribute[0]->meta_value)['tmfbuilder'];
            
            
            $listTitleSize = $listAttribute['multiple_radiobuttons_options_value'][0];
            $listPriceSize = $listAttribute['multiple_radiobuttons_options_price'][0];
            $listSaleOffeSize = $listAttribute['multiple_radiobuttons_options_sale_price'][0];
            
            $listTitleTopping = $listAttribute['multiple_checkboxes_options_value'][0];
            $listPriceTopping = $listAttribute['multiple_checkboxes_options_price'][0];
            $listSaleOffTopping = $listAttribute['multiple_checkboxes_options_sale_price'][0];
           
            
           
            foreach($listTitleSize as $key =>$value){
                
                $temp = [];
                $temp['title']=$value;
                $temp['price']=(int) $listPriceSize[$key];
                $temp['priceSale']=($listSaleOffeSize[$key] != "")?(int) $listSaleOffeSize[$key] :"";
                $listBigSize[]=$temp;
              
            }

            foreach($listTitleTopping as $key =>$value){
                
                $temp = [];
                $temp['title']=$value;
                $temp['price']=(int) $listPriceTopping[$key];
                $temp['priceSale']=($listSaleOffTopping[$key] != "")?(int) $listSaleOffTopping[$key] :"";
                $listTopping[]=$temp;
              
            }
        }
        $result['radio']['size'] = $listBigSize;
        if(isset($listAttribute['multiple_radiobuttons_options_value'][1])){
            $listTitleSuggar = $listAttribute['multiple_radiobuttons_options_value'][1];
            $listPriceSuggar = $listAttribute['multiple_radiobuttons_options_price'][1];
            $listSaleOffeSuggar = $listAttribute['multiple_radiobuttons_options_sale_price'][1];
            foreach($listTitleSuggar as $key =>$value){
            
                $temp = [];
                $temp['title']=$value;
                $temp['price']=(int) $listPriceSuggar[$key];
                $temp['priceSale']=($listSaleOffeSuggar[$key] != "")?(int) $listSaleOffeSuggar[$key] :"";
                $listSuggar[]=$temp;
              
            }
            $result['radio']['suggar'] = $listSuggar;
          
            
        }
        $result['checkbox']['topping'] = $listTopping;


        return $this->returnSuccess($result);
    }
    public function rewardPolicy(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $rewardPolicy = $this->getOptionsMeta('reward_policy');


        return $this->returnSuccess($rewardPolicy);
    }
    public function checkFavorite(Request $request){
        $data  = $request->all();
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $isFavorite = false;
        if(isset($data['product_id']) ){
            $userId = $store->user_id;
            $listFavorite = $this->getUserMeta($userId, 'favorite');
            if($listFavorite ){
                $result = json_decode($listFavorite );
                if(in_array($data['product_id'],$result)){
                    $isFavorite =  true;
                }
            }

        }
        return $this->returnSuccess($isFavorite);
    }
    public function getFavorite(Request $request){
        $data  = $request->all();
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $result = [];
        $userId = $store->user_id;
        $listFavorite = $this->getUserMeta($userId, 'favorite');
        if($listFavorite ){
            $result = json_decode($listFavorite );
        }
        return $this->returnSuccess($result);
    }
    public function addFavorite(Request $request){
        $data  = $request->all();
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        if( isset($data['product_id']) ){
            $userId = $store->user_id;

            $listFavorite = $this->getUserMeta($userId, 'favorite');

            if($listFavorite){
                $favorite = json_decode($listFavorite);
                if(in_array($data['product_id'],$favorite)){
                    $favorite = array_values(array_diff($favorite, [$data['product_id']]));
                }else{
                    $favorite[]=$data['product_id'];
                }
                $result =DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_usermeta')->where('user_id', $userId)->where('meta_key', 'favorite')->update(
                    array(
                        'meta_value' => json_encode($favorite)
                    )
                );
            }else{
                $result = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_usermeta')->insertGetId(
                    array(
                        'user_id'     =>   $userId,
                        'meta_key'     =>   'favorite',
                        'meta_value'     =>   json_encode([$data['product_id']])
                    )
                );
            }
            return $this->returnSuccess($result);

        }
        return $this->returnSuccess($data);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function review(Request $request)
    {
        try {
            $store = $request['data_reponse'];
            $this->_PRFIX_TABLE = $store->prefixTable;
            $data = $request->all();
            $data['sdt'] = $store->sdt;
            if (!isset($data['rating'])) {
                return $this->returnError([], "Bắt buộc phải nhập rating");
            } else if (!isset($data['product_id'])) {
                return $this->returnError([], "Bắt buộc phải nhập product");
            } else {
                $user = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_users')->where('user_login', $data['sdt'])->first();
                if (!$user) {
                    return $this->returnError([], "Số điện thoại chưa được đăng ký");
                }
                $insertId = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_comments')->insertGetId(
                    array(
                        'comment_post_ID'     =>   $data['product_id'],
                        'comment_author'     =>   $store->name,
                        'comment_content'     =>   $data['review_text'],
                        'comment_type'     =>   'review',
                        'user_id'   =>   $store->user_id,
                        'comment_date' => date('Y/m/d H:i:s'),
                        'comment_date_gmt' => date('Y/m/d H:i:s')

                    )
                );


                $insert = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_commentmeta')->insert(
                    array(
                        'comment_id'     =>   $insertId,
                        'meta_value'     =>   $data['rating'],
                        'meta_key'     =>   'rating',
                    )
                );
                return $this->returnSuccess($insert, 'Thêm review thành công');
            }
        } catch (\Throwable $th) {
            //throw $th;
            $this->woo_logs('review', $th->getMessage());

            return $this->returnError([], "Lỗi hệ thống");

        }
    }
    public function reviewProductOrder(Request $request)
    {
        try {
            $store = $request['data_reponse'];
            $this->_PRFIX_TABLE = $store->prefixTable;
            $data = $request->all();
            $data['sdt'] = $store->sdt;
            if (!isset($data['rating'])) {
                return $this->returnError([], "Bắt buộc phải nhập rating");
            } else if (!isset($data['product_id'])) {
                return $this->returnError([], "Bắt buộc phải nhập product");
            }
            else if (!isset($data['order_id'])) {
                return $this->returnError([], "Bắt buộc phải nhập order");
            }else {
                $user = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_users')->where('user_login', $data['sdt'])->first();
                if (!$user) {
                    return $this->returnError([], "Số điện thoại chưa được đăng ký");
                }
                $ordersDetail = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_wc_order_product_lookup')
                ->where( $this->_PRFIX_TABLE .'_wc_order_product_lookup.order_id', $data['order_id'])
                ->where( $this->_PRFIX_TABLE .'_wc_order_product_lookup.product_id', $data['product_id'])
                ->first();
                if($ordersDetail){
                    $insertId = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_comments')->insertGetId(
                        array(
                            'comment_post_ID'     =>   $data['product_id'],
                            'comment_author'     =>   $store->name,
                            'comment_content'     =>   $data['review_text'],
                            'comment_type'     =>   'review',
                            'comment_karma'     =>    $data['order_id'],
                            'user_id'   =>   $store->user_id,
                            'comment_date' => date('Y/m/d H:i:s'),
                            'comment_date_gmt' => date('Y/m/d H:i:s')

                        )
                    );


                    $insert = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_commentmeta')->insert(
                        array(
                            'comment_id'     =>   $insertId,
                            'meta_value'     =>   $data['rating'],
                            'meta_key'     =>   'rating',
                        )
                    );
                    return $this->returnSuccess($insert, 'Thêm review thành công');

                }
                return $this->returnError([], "Không thể đánh giá");


            }
        } catch (\Throwable $th) {
            //throw $th;
            $this->woo_logs('review', $th->getMessage());

            return $this->returnError([], "Lỗi hệ thống");

        }
    }

    public function checkCoupon(Request $request)
    {
        try {
            //code...
            $store = $request['data_reponse'];
            $this->_PRFIX_TABLE = $store->prefixTable;
            $data = $request->all();
            if (!isset($data['coupon'])) {
                return $this->returnError([], "Bắt buộc phải nhập coupon");
            }
            if (!isset($data['subtotal'])) {
                return $this->returnError([], "Bắt buộc phải nhập total");
            }
            $order = $data['order'];
            $listProductId = [];
            foreach ($order as $value) {
                $listProductId[] = $value['productId'];
            }
            $products = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_posts')->whereIn('id', $listProductId)->get();
            
            if(is_array($data['coupon'])){
                $listCoupon = [];
                $temp['subtotal']=0;
                $subtotal =0;
                foreach($data['coupon'] as $key=> $coupon ){
                    $temp['coupon']=$coupon;
                    if($key ==0){
                        $temp['subtotal']=$data['subtotal'];
                    }else{
                        $temp['subtotal']=$subtotal;
                        
                    }
                    $coupon_amount_total = $this->calculateCoupon($temp, $products, true);
                    $subtotal = $data['subtotal'] - $coupon_amount_total;
                    $listCoupon[]=[
                        'coupon'=>$coupon,
                        'discount'=>$coupon_amount_total
                    ];
                }
                return $this->returnSuccess($listCoupon);
                
            }else{
                $controller = new Controller();
                $da = $controller->getTotalPriceDetailsPos($order,1,1);
                $data['subtotal'] = $da['totalPriceTopping'];
                $data['email'] = $store->email;
                $coupon_amount_total = $this->calculateCoupon($data, $products, true);
                if ($coupon_amount_total > 0) {
                    return $this->returnSuccess($coupon_amount_total);
                } else {
                    return $this->returnError($coupon_amount_total, 'Mã khuyễn mãi không đúng');
                }
            }
           
        } catch (\Throwable $th) {
            //throw $th;
            $this->woo_logs('checkCoupon', $th->getMessage());
            return $this->returnError(0, $th->getMessage());
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
