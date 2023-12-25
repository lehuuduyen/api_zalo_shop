<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use stdClass;
use Illuminate\Support\Str;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    public $_messageError = "Không thể tạo đơn hàng";
    public function returnSuccess($data = [], $message = "Lấy dữ liệu thành công")
    {

        return response()->json([
            'status' => 'success',
            'code' => empty($data) ? 204 : 200,
            'data' => $data,
            'message' => empty($data) ? "Dữ liệu rỗng" : $message /* Or optional success message */
        ]);
    }
    public function returnError($data = [], $message = "Lấy dữ liệu thất bại")
    {
        return response()->json([
            'status' => 'error',
            'code' => '500',
            'data' => $data,
            'message' => $message /* Or optional success message */
        ]);
    }
    public function connectDb($databaseStore)
    {
        Config::set("database.connections.mysql_external", [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'database' => $databaseStore,
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'port' => '3306',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
        ]);
    }

    public function encodeData($data, $key = "lhdmknaooqoqp!k")
    {
        $key = env('APP_KEY');
        $salt = openssl_random_pseudo_bytes(16); // Generate a random salt
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encryptedData = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        $en = base64_encode($salt . $iv . $encryptedData);
        // return $en;
        return str_replace("==", "", $en);
    }

    public function decodeData($encodedData, $key = "lhdmknaooqoqp!k")
    {
        $key = env('APP_KEY');
        $data = base64_decode($encodedData);
        $salt = substr($data, 0, 16);
        $iv = substr($data, 16, openssl_cipher_iv_length('aes-256-cbc'));
        $encryptedData = substr($data, 16 + openssl_cipher_iv_length('aes-256-cbc'));
        $decryptedData = openssl_decrypt($encryptedData, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $decryptedData;
    }
    public function getToken($store, $sdt, $databaseStore, $domain,$name,$user_id)
    {
        $minute = (env('EXPIRED_MINUTE')) ? env('EXPIRED_MINUTE') : "";
        try {
            $date = empty($minute) ? "" : strtotime(date('d-m-Y H:i:s', strtotime("+$minute min")));
            $token = $this->encodeData(json_encode(['store' => $store, 'sdt' => $sdt, 'databaseStore' => $databaseStore, 'domain' => $domain,'name'=>$name,'user_id'=>$user_id, 'expired_in' => strtotime($date)]));
            return $token;
        } catch (\Exception $e) {
            //throw $th;
        }
    }

    public function getImage($id, $store, $checkTerm = false)
    {

        $image = "";
        if (!$checkTerm) {
            $postMeta = DB::connection('mysql_external')->table('wp_postmeta')->where('meta_key', '_thumbnail_id')->where('post_id', $id)->first();
            if ($postMeta) {
                $image = DB::connection('mysql_external')->table('wp_postmeta')->where('meta_key', '_wp_attached_file')->where('post_id', $postMeta->meta_value)->first();
            }
        }else{
            $image = DB::connection('mysql_external')->table('wp_postmeta')->where('meta_key', '_wp_attached_file')->where('post_id', $id)->first();
        }


        if ($image) {
            $domain = "https://" . $store->domain . "/wp-content/uploads/" . $image->meta_value;
            $image->path = $domain;
        }

        return $image;
    }
    public function getGalleries($id, $store)
    {
        $response = [];
        $listImg = $this->getPostMeta($id, '_product_image_gallery');
        $arr = explode(",", $listImg);
        if (count($arr) > 0) {
            $data = DB::connection('mysql_external')->table('wp_posts')->whereIn('ID', $arr)->get();
            if ($data) {
                foreach ($data as $key => $value) {
                    $response[$key]['path'] =  "https://" . $store->domain . "/wp-content/uploads/" . $this->getPostMeta($value->ID, '_wp_attached_file');
                    $response[$key]['title'] =  $value->post_title;
                    $response[$key]['alt'] =  $value->post_title;
                }
            }
        }
        return $response;
    }

    public function getBlogCategory($id)
    {
        $languare = env('DEFAULT_LANGUARE') ? env('DEFAULT_LANGUARE') : "vi";
        $string = "";
        $category = DB::connection('mysql_external')->table('blog_categories')->select('title')->find($id);
        if ($category) {
            $category = json_decode($category->title);
            if (isset($category->$languare)) {
                $string = $category->$languare;
            }
        }
        return $string;
    }
    public function getCountry($id)
    {
        $string = "";
        $data = DB::connection('mysql_external')->table('countries')->select('name')->find($id);
        if ($data) {
            $string = $data->name;
        }
        return $string;
    }
    public function getState($id)
    {
        $string = "";
        $data = DB::connection('mysql_external')->table('states')->select('name')->find($id);
        if ($data) {
            $string = $data->name;
        }
        return $string;
    }
    public function getBrand($id, $store)
    {
        $response = new \stdClass();
        $data = DB::connection('mysql_external')->table('brands')->find($id);
        if ($data) {
            $data->image = $this->getImage($data->image, $store);
            $response = $data;
        }
        return $response;
    }
    public function getBadge($id, $store)
    {
        $response = new \stdClass();
        $data = DB::connection('mysql_external')->table('badges')->find($id);
        if ($data) {
            $data->name = $this->getTextByLanguare($data->name);
            $data->image =  $this->getImage($data->image, $store);
            $response = $data;
        }
        return $response;
    }
    public function getTextByLanguare($text)
    {
        $string = "";
        $text = json_decode($text);
        if (isset($text->vi)) {
            $string = $text->vi;
        }
        return $string;
    }
    public function getProductByCampaign($id, $store)
    {
        $data = DB::connection('mysql_external')->table('campaign_products')->join('products', 'products.id', 'campaign_products.product_id')->where('products.status_id', 1)->where('campaign_products.campaign_id', $id)->select('campaign_products.*')->get();
        if ($data) {
            foreach ($data as $key => $value) {
                $product = DB::connection('mysql_external')->table('products')->find($value->product_id);
                if ($product) {
                    $data[$key]->product = $product;
                    $data[$key]->product->image_id = $this->getImage($product->image_id, $store);
                    $data[$key]->product->brand_id = $this->getBrand($product->brand_id, $store);
                    $data[$key]->product->name = $this->getTextByLanguare($product->name);
                    $data[$key]->product->summary = $this->getTextByLanguare($product->summary);
                    $data[$key]->product->description = $this->getTextByLanguare($product->description);
                    $data[$key]->product->badge_id = $this->getBadge($product->badge_id, $store);
                    $data[$key]->product->category = $this->getCategoryByProduct($product->id, $store);
                    $data[$key]->product->galleries = $this->getGalleries($product->id, $store);
                    $data[$key]->product->product_inventory = $this->getProductInventory($product->id);
                    $data[$key]->product->delivery_option = $this->getProductDeliveryOption($product->id);
                    $data[$key]->product->unit = $this->getUnit($product->id);
                    $data[$key]->product->policy = $this->getPolicy($product->id);
                    $data[$key]->product->tag_name = $this->getTagName($product->id);
                    $data[$key]->product->review = $this->getreview($product->id);
                }
            }
        }

        return $data;
    }
    public function getCategoryByProduct($id, $store)
    {
        $response = new \stdClass();
        $cate = DB::connection('mysql_external')->table('wp_term_relationships')->where('object_id', $id)->get();
        $term = [];
        foreach ($cate as $key => $value) {
            $term[] = $value->term_taxonomy_id;
        }


        if (count($term) > 0) {
            $listTerm = DB::connection('mysql_external')->table('wp_term_taxonomy')->whereIn('term_id', $term)->where('taxonomy', 'product_cat')->get();
            $term = [];
            foreach ($listTerm as $val) {
                $term[] = $val->term_id;
            }
            if (count($term) > 0) {
                $listTerm = DB::connection('mysql_external')->table('wp_terms')->whereIn('term_id', $term)->first();
                if ($listTerm) {
                    $response->category_id =  $listTerm->term_id;
                    $response->name =  $listTerm->name;
                    $response->slug =  $listTerm->slug;
                    $thumbnail = DB::connection('mysql_external')->table('wp_termmeta')->where('term_id', $listTerm->term_id)->where('meta_key', 'thumbnail_id')->first();
                    $response->image =  ($thumbnail) ? $thumbnail->meta_value : "";
                    $response->sub_category = [];
                }
            }
        }

        return $response;
    }
    public function getSubCategoryByProduct($id, $store)
    {
        $response = new \stdClass();
        $data = DB::connection('mysql_external')->table('product_sub_categories')->join('sub_categories', 'sub_categories.id', 'product_sub_categories.sub_category_id')->where('product_sub_categories.product_id', $id)->first();
        if ($data) {
            $data->name =  $this->getTextByLanguare($data->name);
            $data->description =  $this->getTextByLanguare($data->description);
            $data->image =  $this->getImage($data->image_id, $store);
            $data->child_category =  $this->getChildCategoryByProduct($id, $store);
            $response = $data;
        }
        return $response;
    }
    public function getChildCategoryByProduct($id, $store)
    {
        $response = new \stdClass();
        $data = DB::connection('mysql_external')->table('product_child_categories')->join('child_categories', 'child_categories.id', 'product_child_categories.child_category_id')->where('product_child_categories.product_id', $id)->get();
        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key]->name =  $this->getTextByLanguare($value->name);
                $data[$key]->description =  $this->getTextByLanguare($value->description);
                $data[$key]->image =  $this->getImage($value->image_id, $store);
            }
            $response = $data;
        }
        return $response;
    }
    public  function calAttribute($list, $arrs)
    {


        foreach ($arrs as $keyAttr  => $item) {
            if (!isset($list[$keyAttr])) {
                $list[$keyAttr] = [];
            }
            $list[$keyAttr] = array_merge($list[$keyAttr], $item);
            $list[$keyAttr] = array_values(array_unique($list[$keyAttr]));
        }
        return $list;
    }
    public function uniqueList($arr)
    {
        $uniqueArray = [];

        foreach ($arr as $item) {
            $uniqueArray[$item["id"]] = $item;
        }

        // Convert the associative array back to a regular indexed array
        $uniqueArray = array_values($uniqueArray);
        return $uniqueArray;
    }
    public function getAuthor($authroId){
        $data = DB::connection('mysql_external')->table('wp_users')->where('ID', $authroId)->first();
        $name =($data)?$data->display_name:"";
        return $name;
    }
    // id: number;
    // product_id: number;
    // sku: string;
    // stock_count: number;
    // sold_count: number | null;
    // attribute: {
    //   color: { id: number; name: string; color_code: string }[];
    //   size: { id: number; name: string; size_code: string }[];
    //   [key: string]:
    //     | string[]
    //     | { id: number; name: string; color_code: string }[]
    //     | { id: number; name: string; size_code: string }[];
    // };
    // product_inventory_details: any;
    public function getProductInventory($id)
    {
        $response = new \stdClass();
        $sold_count = $this->getPostMeta($id, 'total_sales');
        $stock_count = $this->getPostMeta($id, '_stock');
        $proAttr = $this->getPostMeta($id, '_product_attributes');
        $trongLuong = $this->getPostMeta($id, '_weight');
        $length = $this->getPostMeta($id, '_length');
        $width = $this->getPostMeta($id, '_width');
        $height = $this->getPostMeta($id, '_height');

        $proAttr = unserialize($proAttr);
        $response->id = $id;
        $response->product_id = $id;
        $response->sku = '';
        $response->stock_count =  $stock_count;
        $response->sold_count = $sold_count;
        $list = [];
        $response->product_inventory_details = [];
        if($trongLuong){
            $list['Trọng lượng'] =[$trongLuong.' kg'] ;

        }
        if($length || $width || $height){
            $kichThuoc = "";
            if($length && $width && !$height){
                $kichThuoc = $length ." x ".$width;
            }
            else if($length && $height && !$width){
                $kichThuoc = $length ." x ".$height;
            }
            else if($width && $height && !$length){
                $kichThuoc = $width ." x ".$height;
            }
            else if($length && $width && $height){
                $kichThuoc = $length ." x ".$width ." x ".$height;
            }
            else if($length && !$width && !$height){
                $kichThuoc = $length ;
            }
            else if($width && !$length && !$height){
                $kichThuoc = $width ;
            }
            else if($height && !$width && !$length){
                $kichThuoc = $height ;
            }

            if($kichThuoc){
                $kichThuoc = $kichThuoc ." cm";
            }
            $list['Kích thước'] =[$kichThuoc] ;


        }
        if($proAttr ){
            foreach ($proAttr as $attr => $val) {
                $val['attribute'] = $val;
                $list[$val['name']]=explode('|',$val['value']);
                $response->product_inventory_details[]=$val;
            }
        }


        $response->attribute = $list;


        return $response;
    }
    public function getColor($id)
    {
        $response = new \stdClass();
        $data = DB::connection('mysql_external')->table('colors')->find($id);
        if ($data) {
            $data->name = $this->getTextByLanguare($data->name);
            $response = $data;
        }
        return $response;
    }
    public function getAttributeProduct($id)
    {
        $response = [];
        $data = DB::connection('mysql_external')->table('product_inventory_detail_attributes')->where('inventory_details_id', $id)->get();
        if ($data) {
            foreach ($data as $key => $value) {
                $response[$value->attribute_name][] = $value->attribute_value;
            }
        }
        return $response;
    }
    public function getSize($id)
    {
        $response = new \stdClass();
        $data = DB::connection('mysql_external')->table('sizes')->find($id);
        if ($data) {
            $data->name = $this->getTextByLanguare($data->name);
            $response = $data;
        }
        return $response;
    }
    public function getProductDeliveryOption($id)
    {
        $response = [];
        $data = DB::connection('mysql_external')->table('product_delivery_options')->join('delivery_options', 'delivery_options.id', 'product_delivery_options.delivery_option_id')->where('product_delivery_options.product_id', $id)->get();
        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key]->title = $this->getTextByLanguare($value->title);
                $data[$key]->sub_title = $this->getTextByLanguare($value->sub_title);
            }
            $response = $data;
        }
        return $response;
    }
    public function getUnit($id)
    {
        $response = DB::connection('mysql_external')->table('product_uom')->join('units', 'units.id', 'product_uom.unit_id')->select('product_uom.*', 'units.name')->where('product_uom.product_id', $id)->first();
        return $response;
    }
    public function getPolicy($id)
    {
        $response = DB::connection('mysql_external')->table('product_shipping_return_policies')->where('product_shipping_return_policies.product_id', $id)->first();
        if ($response) {
            $response->shipping_return_description = $this->getTextByLanguare($response->shipping_return_description);
        }
        return $response;
    }
    public function getTagName($id)
    {
        $response = DB::connection('mysql_external')->table('product_tags')->where('product_tags.product_id', $id)->first();
        return $response;
    }
    public function getreview($id)
    {
    //     id: number;
    // product_id: number;
    // user_id: number;
    // rating: number;
    // review_text: string | null;
    // created_at: string | null;
    // updated_at: string | null;
    // name: string;
        $data =[];
        $response = DB::connection('mysql_external')->table('wp_comments')->join('wp_commentmeta','wp_commentmeta.comment_id','wp_comments.comment_ID')->where('wp_comments.comment_post_ID', $id)->where('wp_comments.comment_type', 'review')->where('wp_commentmeta.meta_key', 'rating')->select('wp_comments.*','wp_commentmeta.meta_value')->get();

        foreach( $response as $key => $value){
            $data[$key]['id'] =$value->comment_ID;
            $data[$key]['product_id'] =$id;
            $data[$key]['user_id'] =$value->user_id;
            $data[$key]['rating'] =(int)$value->meta_value;
            $data[$key]['review_text'] =$value->comment_content;
            $data[$key]['name'] =$value->comment_author;
        }
        return $data;
    }
    public function calculateCoupon($data, $products)
    {
        $discount_total = 0;
        $paramCoupon = $data['coupon'];
        $coupon = DB::connection('mysql_external')->table('wp_posts')->where('post_title', $paramCoupon)->where('post_status', 'publish')->where('post_type', 'shop_coupon')->first();
       
        

        if (is_null($coupon)) {
            return $discount_total;
        }
        $date_expires = $this->getPostMeta($coupon->ID,'date_expires');
        if($date_expires < time()){
            return $discount_total;
        }
        
        $coupon_amount = $this->getPostMeta($coupon->ID,'coupon_amount');
        $coupon_type = $this->getPostMeta($coupon->ID,'discount_type');
        if($coupon_type == "percent"){
            $coupon_type = 'percentage';
        }


        // calculate based on coupon type
        if ($coupon_type === 'percentage') {
            $discount_total = $data['subtotal'] / 100 * $coupon_amount;
        } else { # =====
            $discount_total = $coupon_amount;
        }
        if ($discount_total > $data['subtotal']) {
            $discount_total = $data['subtotal'];
        }

        return $discount_total;
    }
    public function timeFormat(){
        $now = time();

        // Format the date and time
        $formattedDate = strftime('%B %d, %Y @ %I:%M %p', $now);
        return $formattedDate;
    }
    public function createOrder($data, $user)
    {   
        
        $timeNow = date('Y/m/d H:i:s');
        DB::connection('mysql_external')->beginTransaction();

        try {
            // them wp_posts
            $postId = DB::connection('mysql_external')->table('wp_posts')->insertGetId(
                array(
                    'post_date' => $timeNow,
                    'post_date_gmt' => $timeNow,
                    'post_modified' => $timeNow,
                    'post_modified_gmt' => $timeNow,
                    'post_title' => 'Order &ndash; '.$this->timeFormat(),
                    'post_status' => 'wc-pending',
                    'post_type' => 'shop_order',
                    'post_content' => '',
                    'post_excerpt' => '',
                    'to_ping' => '',
                    'pinged' => '',
                    'post_content_filtered' => '',
                    
                    'comment_count' => '0',
                )
            );
            $totalPriceDetails =  $this->getTotalPriceDetails($data['order'],$postId);
            if (!$totalPriceDetails) {
                throw new \Exception('');
            }

            $finalDetails = $this->getFinalPriceDetails($user, $data, $totalPriceDetails);
            
            
            
            
         
            
            
            // them wp_postmeta
            $postMeta = DB::connection('mysql_external')->table('wp_postmeta')->insert(
                array(
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_order_key',
                        'meta_value'=>'wc_order_'.Str::random(10),
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_customer_user',
                        'meta_value'=>$user['id'],
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_payment_method',
                        'meta_value'=>'cod',
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_payment_method_title',
                        'meta_value'=>'Thanh toán khi giao hàng',
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_billing_last_name',
                        'meta_value'=>$user['name'],
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_billing_address_1',
                        'meta_value'=>$user['address'],
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_billing_email',
                        'meta_value'=>$user['email'],
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_billing_phone',
                        'meta_value'=>$user['mobile'],
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_order_currency',
                        'meta_value'=>'VND',
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_cart_discount',
                        'meta_value'=>$finalDetails['coupon_discounted'],
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_cart_discount_tax',
                        'meta_value'=>0,
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_order_shipping',
                        'meta_value'=>0,
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_order_shipping_tax',
                        'meta_value'=>0,
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_order_tax',
                        'meta_value'=>0,
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_order_total',
                        'meta_value'=>$finalDetails['total'],
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_billing_address_index',
                        'meta_value'=>$user['name'].' '.$user['address'].' '.$user['email'].' '.$user['mobile'],
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_shipping_address_index',
                        'meta_value'=>$user['address'],
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_shipping_address_1',
                        'meta_value'=>$user['address'],
                    ),
                    array(
                        'post_id'=>$postId,
                        'meta_key'=>'_shipping_country',
                        'meta_value'=>'VN',
                    ),
                )
            );
            
            
            
           
            

            //them wp_wc_order_coupon_lookup && wp_woocommerce_order_items
            if($finalDetails['coupon_discounted'] && $finalDetails['coupon_discounted'] >0){
                $coupon = DB::connection('mysql_external')->table('wp_posts')->where('post_title', $data['used_coupon'])->where('post_status', 'publish')->where('post_type', 'shop_coupon')->first();
                $coupon_amount = $this->getPostMeta($coupon->ID,'coupon_amount');
                $coupon_type = $this->getPostMeta($coupon->ID,'discount_type');
                
                DB::connection('mysql_external')->table('wp_wc_order_coupon_lookup')->insertGetId(
                    array(
                        'order_id' => $postId,
                        'coupon_id' => $coupon->ID,
                        'date_created' => $timeNow,
                        'discount_amount' => $finalDetails['coupon_discounted'],
                    )
                );
                $orderItemIdCoupon = DB::connection('mysql_external')->table('wp_woocommerce_order_items')->insertGetId(
                    array(
                        'order_id' => $postId,
                        'order_item_type' => 'coupon',
                        'order_item_name' => $data['used_coupon'],
                    )
                );
                
                
                DB::connection('mysql_external')->table('wp_woocommerce_order_itemmeta')->insert(
                    array(
                        array(
                            'order_item_id'=>$orderItemIdCoupon,
                            'meta_key'=>'coupon_data',
                            'meta_value'=>'',
                        ),
                        array(
                            'order_item_id'=>$orderItemIdCoupon,
                            'meta_key'=>'discount_amount_tax',
                            'meta_value'=>0,
                        ),
                        array(
                            'order_item_id'=>$orderItemIdCoupon,
                            'meta_key'=>'discount_amount',
                            'meta_value'=>$finalDetails['coupon_discounted'],
                        )
                    ),
                );
                
            }
            //wp_wc_order_product_lookup
            $totalQuantity = array_sum($totalPriceDetails['quantity']);
            
            foreach($totalPriceDetails['products_id'] as $key  => $productId){
                $products = DB::connection('mysql_external')->table('wp_posts')->where('ID', $productId)->select('post_title')->first();
                
                
                $price = $this->getSellPrice($productId);
                $totalBanDau = $price * $totalPriceDetails['quantity'][$key];
                $tongGiaGiam = 0;
                if(isset($coupon) && $coupon){
                    if($coupon_type == 'fixed_cart'){
                        $giagiam = round($coupon_amount / $totalQuantity * $totalPriceDetails['quantity'][$key]);
                        $price = $price - $giagiam;
                        $tongGiaGiam=$tongGiaGiam + $giagiam;
                    }else{
                        $giagiam = round($price * $coupon_amount  / 100);
                        $price = $price - $giagiam;
                        $tongGiaGiam=$tongGiaGiam + $giagiam * $totalPriceDetails['quantity'][$key];
                    }
                }
                //wp_woocommerce_order_items
                $orderItemId = DB::connection('mysql_external')->table('wp_woocommerce_order_items')->insertGetId(
                    array(
                        'order_id' => $postId,
                        'order_item_type' => 'line_item',
                        'order_item_name' => $products->post_title,
                    )
                );
                DB::connection('mysql_external')->table('wp_woocommerce_order_itemmeta')->insert(
                    array(
                        array(
                            'order_item_id'=>$orderItemId,
                            'meta_key'=>'_reduced_stock',
                            'meta_value'=>$totalPriceDetails['quantity'][$key],
                        ),
                        array(
                            'order_item_id'=>$orderItemId,
                            'meta_key'=>'_line_tax_data',
                            'meta_value'=>'',
                        ),
                        array(
                            'order_item_id'=>$orderItemId,
                            'meta_key'=>'_line_tax',
                            'meta_value'=>0,
                        ),
                        array(
                            'order_item_id'=>$orderItemId,
                            'meta_key'=>'_line_total',
                            'meta_value'=>$price * $totalPriceDetails['quantity'][$key],
                        ),
                        array(
                            'order_item_id'=>$orderItemId,
                            'meta_key'=>'_line_subtotal_tax',
                            'meta_value'=>0,
                        ),
                        array(
                            'order_item_id'=>$orderItemId,
                            'meta_key'=>'_line_subtotal',
                            'meta_value'=>$totalBanDau ,
                        ),
                        array(
                            'order_item_id'=>$orderItemId,
                            'meta_key'=>'_tax_class',
                            'meta_value'=>'',
                        ),
                        array(
                            'order_item_id'=>$orderItemId,
                            'meta_key'=>'_qty',
                            'meta_value'=>$totalPriceDetails['quantity'][$key],
                        ),
                        array(
                            'order_item_id'=>$orderItemId,
                            'meta_key'=>'_variation_id',
                            'meta_value'=>0,
                        ),
                        array(
                            'order_item_id'=>$orderItemId,
                            'meta_key'=>'_product_id',
                            'meta_value'=>$productId,
                        )
                        
                    )
                );
                // wp_wc_order_product_lookup
                DB::connection('mysql_external')->table('wp_wc_order_product_lookup')->insert(
                    array(
                        'order_item_id' => $orderItemId,
                        'order_id' => $postId,
                        'product_id' => $productId,
                        'variation_id' => 0,
                        'customer_id' => $user['id'],
                        'date_created' => $timeNow,
                        'customer_id' => $user['id'],
                        'product_qty' => $totalPriceDetails['quantity'][$key],
                        'product_gross_revenue' => $price * $totalPriceDetails['quantity'][$key] ,
                        'product_net_revenue' => $price * $totalPriceDetails['quantity'][$key] ,
                        'coupon_amount' => $tongGiaGiam,
                        
                    )
                );
            }
            
            
            //them order wp_wc_order_stats
            DB::connection('mysql_external')->table('wp_wc_order_stats')->insertGetId(
                array(
                    'order_id' => $postId,
                    'date_created' => $timeNow,
                    'date_created_gmt' => $timeNow,
                    'num_items_sold' => array_sum($totalPriceDetails['quantity']),
                    'net_total' => $finalDetails['total'],
                    'total_sales' => $finalDetails['total'],
                    'returning_customer' => 1,
                    'customer_id' => $user['id'],
                    'status' => 'wc-pending',
                )
            );

            //tính hoa hồng
            
            DB::connection('mysql_external')->commit();

            
            
            return $postId;
        } catch (\Throwable $th) {
            //throw $th;
            echo '<pre>';
            print_r($th->getMessage());
            die;


            DB::connection('mysql_external')->rollBack();
            return false;
        }
    }

    public function getFinalPriceDetails($user, $validated_data, $totalPriceDetails)
    {
        
        
        $shipping_method = $validated_data['shipping_method'] ?? "";
        
        
        $state = $validated_data["state"];
        $country = $validated_data["country"];
        
        $price = $totalPriceDetails;
        $coupon = ["coupon" => $validated_data['used_coupon'],"subtotal" => $price['total']];

       


        $data = $this->get_product_shipping_tax(['country' => $country, 'state' => $state, 'shipping_method' => (int)$shipping_method]);
        $coupon['subtotal'] = $price['total'];
        $discounted_price = $this->calculateCoupon($coupon, []);
        
        

        $price['total'] -= $discounted_price;
        
        $product_tax = $data['product_tax'];
        $shipping_cost = $data['shipping_cost'];

        $taxed_price = ($price['total'] * $product_tax) / 100;
        $subtotal = $price['total'] + $discounted_price;
        $total['total'] = $price['total'] + $taxed_price + $shipping_cost;
        
        // $total['payment_meta'] = $this->payment_meta(compact('product_tax', 'shipping_cost', 'subtotal', 'total'));
        $total['coupon_discounted'] = $discounted_price;
        return $total;
    }
    private function payment_meta($data)
    {
        $meta = [
            'shipping_cost' => $data['shipping_cost'],
            'product_tax' => $data['product_tax'],
            'subtotal' => $data['subtotal'],
            'total' => current($data['total'])
        ];

        return json_encode($meta);
    }
    private function get_product_shipping_tax($request)
    {
        // $shipping_cost = 0;
        // $product_tax = 0;
        // $country_tax = CountryTax::where('country_id', $request['country'])->select('id', 'tax_percentage')->first();

        // if ($request['state'] && $request['country']) {
        //     $product_tax = StateTax::where(['country_id' => $request['country'], 'state_id' => $request['state']])
        //         ->select('id', 'tax_percentage')->first();

        //     if (!empty($product_tax)) {
        //         $product_tax = $product_tax->toArray()['tax_percentage'];
        //     } else {
        //         if (!empty($country_tax))
        //         {
        //             $product_tax = $country_tax->toArray()['tax_percentage'];
        //         }
        //     }
        // } else {
        //     $product_tax = $country_tax?->toArray()['tax_percentage'];
        // }

        // $shipping = ShippingMethod::find($request['shipping_method']);
        // $shipping_option = $shipping->options ?? null;

        // if ($shipping_option != null && $shipping_option?->tax_status == 1) {
        //     $shipping_cost = $shipping_option?->cost + (($shipping_option?->cost * $product_tax) / 100);
        // } else {
        //     $shipping_cost = $shipping_option?->cost;
        // }

        $data['product_tax'] = 0;
        $data['shipping_cost'] = 0;

        return $data;
    }

    public static function getCartProducts($cart): array
    {
        $cartArr = [];


        $i = 0;
        foreach ($cart as $item) {

            $cartArr[$i] = [
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'qty' => $item['qty'],
                'variant_id' => $item['options']['variant_id'] ?? '',
                'image' => $item['image'] ?? ""
            ];
            $i++;
        }


        return $cartArr;
    }
    public function getCampaignByProduct($productId)
    {
        $campaigns = DB::connection('mysql_external')->table('campaigns')->join('campaign_products', 'campaign_products.campaign_id', 'campaigns.id')->where('campaign_products.product_id', $productId)->where('campaigns.status', 'publish')->whereDate('campaigns.start_date', '<', Carbon::now())->whereDate('campaigns.end_date', '>=', Carbon::now())->select('campaigns.id', 'campaign_products.units_for_sale')->first();
        return $campaigns;
    }
    public function checkProductInventory($productId)
    {
        $productInventory = DB::connection('mysql_external')->table('product_inventories')->join('products', 'products.id', 'product_inventories.product_id')->where('product_inventories.product_id', $productId)->select('product_inventories.*', 'products.name')->first();
        $productInventory->name = $this->getTextByLanguare($productInventory->name);
        return $productInventory;
    }
    public function getSellPrice($postId){
        $priceGoc = $this->getPostMeta($postId, '_regular_price');
        $price = $this->getPostMeta($postId, '_sale_price');
        $price = ($price)?$price:$priceGoc;
            return $price;
    }
    public function getTotalPriceDetails($cart,$postId)
    {
        
        
        $total = 0.0;
        $cartArr = self::getCartProducts($cart);
        
        foreach ($cartArr as $key => $item) {
            $sold_count = $this->getPostMeta($item['id'], 'total_sales');
            $stock_count = $this->getPostMeta($item['id'], '_stock');
            $priceGoc = $this->getPostMeta($item['id'], '_regular_price');
            $price = $this->getPostMeta($item['id'], '_sale_price');
            $price = ($price)?$price:$priceGoc;
            $stockStatus = $this->getPostMeta($item['id'], '_stock_status');
           
            
            //checkcampaign
            $productId = $item['id'];
           
            //check số lượng trong kho
            if(!empty($stock_count) && gettype($stock_count) == 'integer' && $stock_count < $item['qty']){
                $this->_messageError = $item['name'] . " hết hàng trong kho";
                return false;
            }
            
            
            

            // trừ số lượng kho
            DB::connection('mysql_external')->table('wp_postmeta')->where('post_id', $productId)->where('meta_key','total_sales')->update(
                array(
                    'meta_value' => $sold_count + $item['qty']
                )
            );
            DB::connection('mysql_external')->table('wp_postmeta')->where('post_id', $productId)->where('meta_key','_stock')->update(
                array(
                    'meta_value' => $stock_count - $item['qty']
                )
            );

            $total += $price * $item['qty'];
            $products_id[] = $item['id'];
            $variant_id[] = $item['variant_id'];
            $quantity[] = $item['qty'];
           
        }

        $arr = [
            'total' => $total,
            'products_id' => $products_id,
            'variants_id' => $variant_id,
            'quantity' => $quantity
        ];
       
        return $arr;
    }
    public function getPostByCategoryId($id){
        $data = DB::connection('mysql_external')->table('wp_posts')
        ->join('wp_term_relationships', 'wp_term_relationships.object_id', 'wp_posts.ID')
        ->where('term_taxonomy_id', $id)->where('post_status', 'publish')->select('wp_posts.*')->orderBy('post_modified', 'DESC')->get();
        return $data;
    }
    public function getPostByCategory($nameCate)
    {
        $cate = DB::connection('mysql_external')->table('wp_terms')->where('slug', $nameCate)->first();
        $data = [];
        if ($cate) {
            $data = DB::connection('mysql_external')->table('wp_posts')
                ->join('wp_term_relationships', 'wp_term_relationships.object_id', 'wp_posts.ID')
                ->where('term_taxonomy_id', $cate->term_id)->where('post_status', 'publish')->orderBy('post_modified', 'DESC')->get();
        }
        return ['data' => $data, 'cate' => $cate];
    }
    public function lienhe($content, $text, $count)
    {
        $position = strpos($content, $text);
        $lineAfterPhone = "";
        if ($position !== false) {
            $endOfLinePosition = strpos($content, PHP_EOL, $position);
            if ($endOfLinePosition !== false) {
                $lineAfterPhone = substr($content, $position + $count, $endOfLinePosition - $position - $count);
            }
        }
        return $lineAfterPhone;
    }
    public function getPostMeta($postId, $meta)
    {
        $data = DB::connection('mysql_external')->table('wp_postmeta')->where('post_id', $postId)->where('meta_key', $meta)->first();
        if ($data) {
            return $data->meta_value;
        }
        return $data;
    }
    public function getUserMeta($userId, $meta)
    {
        $data = DB::connection('mysql_external')->table('wp_usermeta')->where('user_id', $userId)->where('meta_key', $meta)->first();
        if ($data) {
            return $data->meta_value;
        }
        return $data;
    }
    public function getOrderMeta($orderId, $meta)
    {
        $data = DB::connection('mysql_external')->table('wp_woocommerce_order_itemmeta')->where('order_item_id', $orderId)->where('meta_key', $meta)->first();
        if ($data) {
            return $data->meta_value;
        }
        return $data;
    }
    

}
