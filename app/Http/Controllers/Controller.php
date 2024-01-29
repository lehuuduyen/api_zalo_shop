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
    public $_PRFIX_TABLE = 'wp';

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
    public function woo_logs($api,$message = "Lấy dữ liệu thất bại",$level=2)
    {
        $log = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woocommerce_log')->insertGetId(
            array(
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => $level,
                'source' => $api,
                'message' =>$message,

            )
        );
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
    public function getToken($store, $sdt, $databaseStore, $domain, $name, $user_id)
    {
        $minute = (env('EXPIRED_MINUTE')) ? env('EXPIRED_MINUTE') : "";
        try {
            $date = empty($minute) ? "" : strtotime(date('d-m-Y H:i:s', strtotime("+$minute min")));
            $token = $this->encodeData(json_encode(['store' => $store, 'prefixTable' => $this->_PRFIX_TABLE, 'sdt' => $sdt, 'databaseStore' => $databaseStore, 'domain' => $domain, 'name' => $name, 'user_id' => $user_id, 'expired_in' => strtotime($date)]));
            return $token;
        } catch (\Exception $e) {
            //throw $th;
        }
    }

    public function getImage($id, $store, $checkTerm = false)
    {

        $image = "";
        if (!$checkTerm) {
            $postMeta = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_postmeta')->where('meta_key', '_thumbnail_id')->where('post_id', $id)->first();
            if ($postMeta) {
                $image = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_postmeta')->where('meta_key', '_wp_attached_file')->where('post_id', $postMeta->meta_value)->first();
            }
        } else {
            $image = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_postmeta')->where('meta_key', '_wp_attached_file')->where('post_id', $id)->first();
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
            $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_posts')->whereIn('ID', $arr)->get();
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
        $cate = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_term_relationships')->where('object_id', $id)->get();
        $term = [];
        foreach ($cate as $key => $value) {
            $term[] = $value->term_taxonomy_id;
        }


        if (count($term) > 0) {
            $listTerm = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_term_taxonomy')->whereIn('term_id', $term)->where('taxonomy', 'product_cat')->get();
            $term = [];
            foreach ($listTerm as $val) {
                $term[] = $val->term_id;
            }
            if (count($term) > 0) {
                $listTerm = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_terms')->whereIn('term_id', $term)->first();
                if ($listTerm) {
                    $response->category_id =  $listTerm->term_id;
                    $response->name =  $listTerm->name;
                    $response->slug =  $listTerm->slug;
                    $thumbnail = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_termmeta')->where('term_id', $listTerm->term_id)->where('meta_key', 'thumbnail_id')->first();
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
    public function getAuthor($authroId)
    {
        $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_users')->where('ID', $authroId)->first();
        $name = ($data) ? $data->display_name : "";
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
        if ($trongLuong) {
            $list['Trọng lượng'] = [$trongLuong . ' kg'];
        }
        if ($length || $width || $height) {
            $kichThuoc = "";
            if ($length && $width && !$height) {
                $kichThuoc = $length . " x " . $width;
            } else if ($length && $height && !$width) {
                $kichThuoc = $length . " x " . $height;
            } else if ($width && $height && !$length) {
                $kichThuoc = $width . " x " . $height;
            } else if ($length && $width && $height) {
                $kichThuoc = $length . " x " . $width . " x " . $height;
            } else if ($length && !$width && !$height) {
                $kichThuoc = $length;
            } else if ($width && !$length && !$height) {
                $kichThuoc = $width;
            } else if ($height && !$width && !$length) {
                $kichThuoc = $height;
            }

            if ($kichThuoc) {
                $kichThuoc = $kichThuoc . " cm";
            }
            $list['Kích thước'] = [$kichThuoc];
        }
        if ($proAttr) {
            foreach ($proAttr as $attr => $val) {
                $val['attribute'] = $val;
                $list[$val['name']] = explode('|', $val['value']);
                $response->product_inventory_details[] = $val;
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
        $data = [];
        $response = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_comments')->join($this->_PRFIX_TABLE . '_commentmeta', $this->_PRFIX_TABLE . '_commentmeta.comment_id', $this->_PRFIX_TABLE . '_comments.comment_ID')->where($this->_PRFIX_TABLE . '_comments.comment_post_ID', $id)->where($this->_PRFIX_TABLE . '_comments.comment_type', 'review')->where($this->_PRFIX_TABLE . '_commentmeta.meta_key', 'rating')->select($this->_PRFIX_TABLE . '_comments.*', $this->_PRFIX_TABLE . '_commentmeta.meta_value')->get();

        foreach ($response as $key => $value) {
            $data[$key]['id'] = $value->comment_ID;
            $data[$key]['product_id'] = $id;
            $data[$key]['user_id'] = $value->user_id;
            $data[$key]['rating'] = (int)$value->meta_value;
            $data[$key]['review_text'] = $value->comment_content;
            $data[$key]['name'] = $value->comment_author;
        }
        return $data;
    }
    public function calculateCoupon($data, $products, $isCheckApiCoupon = false)
    {
        $discount_total = 0;
        $paramCoupon = $data['coupon'];
        $coupon = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_posts')->where('post_title', $paramCoupon)->where('post_status', 'publish')->where('post_type', 'shop_coupon')->first();



        if (is_null($coupon)) {
            return $discount_total;
        }
        $checkPoint = $this->getPostMeta($coupon->ID, 'customer_user');

        $date_expires = $this->getPostMeta($coupon->ID, 'date_expires');
        if (!$checkPoint) {
            if ($date_expires < time()) {
                return $discount_total;
            }
        }


        $coupon_amount = $this->getPostMeta($coupon->ID, 'coupon_amount');
        $usage_limit = $this->getPostMeta($coupon->ID, 'usage_limit');
        $usage_count = $this->getPostMeta($coupon->ID, 'usage_count');
        if ($usage_limit <= $usage_count) {
            return $discount_total;
        }
        $coupon_type = $this->getPostMeta($coupon->ID, 'discount_type');
        if ($coupon_type == "percent") {
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
        if (!$isCheckApiCoupon) {
            DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_postmeta')->where('post_id', $coupon->ID)->where('meta_key', 'usage_count')->update(
                array(
                    'meta_value' => $usage_count + 1
                )
            );
        }

        return $discount_total;
    }
    public function timeFormat()
    {
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
            $postId = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_posts')->insertGetId(
                array(
                    'post_date' => $timeNow,
                    'post_date_gmt' => $timeNow,
                    'post_modified' => $timeNow,
                    'post_modified_gmt' => $timeNow,
                    'post_title' => 'Order &ndash; ' . $this->timeFormat(),
                    'post_status' => 'wc-processing',
                    'post_type' => 'shop_order',
                    'post_content' => '',
                    'post_excerpt' => '',
                    'to_ping' => '',
                    'pinged' => '',
                    'post_content_filtered' => '',

                    'comment_count' => '0',
                )
            );
            // $data['message'] wp_comments
            if ($data['message']) {
                DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_comments')->insert(
                    array(
                        'comment_post_ID' => $postId,
                        'comment_author' => $user['name'],
                        'comment_author_email' => $user['email'],
                        'comment_author_url' => '',
                        'comment_author_IP' => '',
                        'comment_date_gmt' => $timeNow,
                        'comment_date' => $timeNow,
                        'comment_content' => $data['message'],
                        'comment_approved' => 1,
                        'comment_agent' => 'WooCommerce',
                        'comment_type' => 'order_note',
                    )
                );
            }


            $totalPriceDetails =  $this->getTotalPriceDetails($data['order'], $postId);
            if (!$totalPriceDetails) {
                throw new \Exception('Không đủ số lượng trong kho');
            }
            $totalOrderBanDau = $totalPriceDetails['total'];
            $finalDetails = $this->getFinalPriceDetails($user, $data, $totalPriceDetails);






            //them wp_wc_order_coupon_lookup && wp_woocommerce_order_items
            if ($finalDetails['coupon_discounted'] && $finalDetails['coupon_discounted'] > 0) {
                $coupon = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_posts')->where('post_title', $data['used_coupon'])->where('post_status', 'publish')->where('post_type', 'shop_coupon')->first();
                $coupon_amount = $this->getPostMeta($coupon->ID, 'coupon_amount');
                $coupon_type = $this->getPostMeta($coupon->ID, 'discount_type');

                DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_wc_order_coupon_lookup')->insertGetId(
                    array(
                        'order_id' => $postId,
                        'coupon_id' => $coupon->ID,
                        'date_created' => $timeNow,
                        'discount_amount' => $finalDetails['coupon_discounted'],
                    )
                );
                $orderItemIdCoupon = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woocommerce_order_items')->insertGetId(
                    array(
                        'order_id' => $postId,
                        'order_item_type' => 'coupon',
                        'order_item_name' => $data['used_coupon'],
                    )
                );


                DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woocommerce_order_itemmeta')->insert(
                    array(
                        array(
                            'order_item_id' => $orderItemIdCoupon,
                            'meta_key' => 'coupon_data',
                            'meta_value' => '',
                        ),
                        array(
                            'order_item_id' => $orderItemIdCoupon,
                            'meta_key' => 'discount_amount_tax',
                            'meta_value' => 0,
                        ),
                        array(
                            'order_item_id' => $orderItemIdCoupon,
                            'meta_key' => 'discount_amount',
                            'meta_value' => $finalDetails['coupon_discounted'],
                        )
                    ),
                );
            }
            //wp_wc_order_product_lookup
            $totalQuantity = array_sum($totalPriceDetails['quantity']);

            foreach ($totalPriceDetails['products_id'] as $key  => $productId) {
                $products = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_posts')->where('ID', $productId)->select('post_title')->first();
                if (!$products) {
                    throw new \Exception('Sản phẩm không tồn tại');
                }

                $price = $this->getSellPrice($productId);

                $totalBanDau = $price * $totalPriceDetails['quantity'][$key];
                $tongGiaGiam = 0;
                if (isset($coupon) && $coupon) {
                    if ($coupon_type == 'fixed_cart') {
                        $giagiam = round($coupon_amount / $totalQuantity * $totalPriceDetails['quantity'][$key]);
                        $price = $price - $giagiam;
                        $tongGiaGiam = $tongGiaGiam + $giagiam;
                    } else {
                        $giagiam = round($price * $coupon_amount  / 100);
                        $price = $price - $giagiam;
                        $tongGiaGiam = $tongGiaGiam + $giagiam * $totalPriceDetails['quantity'][$key];
                    }
                }


                //wp_woocommerce_order_items
                $orderItemId = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woocommerce_order_items')->insertGetId(
                    array(
                        'order_id' => $postId,
                        'order_item_type' => 'line_item',
                        'order_item_name' => $products->post_title,
                    )
                );

                DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woocommerce_order_itemmeta')->insert(
                    array(
                        array(
                            'order_item_id' => $orderItemId,
                            'meta_key' => '_reduced_stock',
                            'meta_value' => $totalPriceDetails['quantity'][$key],
                        ),
                        array(
                            'order_item_id' => $orderItemId,
                            'meta_key' => '_line_tax_data',
                            'meta_value' => '',
                        ),
                        array(
                            'order_item_id' => $orderItemId,
                            'meta_key' => '_line_tax',
                            'meta_value' => 0,
                        ),
                        array(
                            'order_item_id' => $orderItemId,
                            'meta_key' => '_line_total',
                            'meta_value' => $price * $totalPriceDetails['quantity'][$key],
                        ),
                        array(
                            'order_item_id' => $orderItemId,
                            'meta_key' => '_line_subtotal_tax',
                            'meta_value' => 0,
                        ),
                        array(
                            'order_item_id' => $orderItemId,
                            'meta_key' => '_line_subtotal',
                            'meta_value' => $totalBanDau,
                        ),
                        array(
                            'order_item_id' => $orderItemId,
                            'meta_key' => '_tax_class',
                            'meta_value' => '',
                        ),
                        array(
                            'order_item_id' => $orderItemId,
                            'meta_key' => '_qty',
                            'meta_value' => $totalPriceDetails['quantity'][$key],
                        ),
                        array(
                            'order_item_id' => $orderItemId,
                            'meta_key' => '_variation_id',
                            'meta_value' => 0,
                        ),
                        array(
                            'order_item_id' => $orderItemId,
                            'meta_key' => '_product_id',
                            'meta_value' => $productId,
                        )

                    )
                );
                // wp_wc_order_product_lookup
                DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_wc_order_product_lookup')->insert(
                    array(
                        'order_item_id' => $orderItemId,
                        'order_id' => $postId,
                        'product_id' => $productId,
                        'variation_id' => 0,
                        'customer_id' => $user['id'],
                        'date_created' => $timeNow,
                        'customer_id' => $user['id'],
                        'product_qty' => $totalPriceDetails['quantity'][$key],
                        'product_gross_revenue' => $price * $totalPriceDetails['quantity'][$key],
                        'product_net_revenue' => $price * $totalPriceDetails['quantity'][$key],
                        'coupon_amount' => $tongGiaGiam,

                    )
                );
            }

            if (array_key_exists('point_use', $data)) {
                $history = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_point')->where('user_id', $user['id'])->orderBy('id', 'DESC')->get();
                $setting = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_setting')->where('id', 1)->first();
                $money_converted_to_point = 0;
                $points_converted_to_money = 0;
                if ($setting) {
                    $money_converted_to_point = $setting->amount_spent;
                    $points_converted_to_money = $setting->points_converted_to_money;
                }

                // tính điểm sang tiền
                $tienDoiThuong = $points_converted_to_money * $data['point_use'];
                if ($tienDoiThuong > $finalDetails['total']) {
                    throw new \Exception('Tiền đổi thưởng không được quá tổng đơn hàng');
                }



                $totalDoiThuong = 0;
                foreach ($history  as $value) {
                    if ($value->status == 1) {
                        $totalDoiThuong = $totalDoiThuong + $value->point;
                    }
                    if ($value->status == 2 || $value->status == 4) {
                        $totalDoiThuong = $totalDoiThuong - $value->point;
                    }
                }
                if ($data['point_use'] && $totalDoiThuong > 0 && $totalDoiThuong >= $data['point_use']) {
                    $finalDetails['total'] = $finalDetails['total'] - $tienDoiThuong;
                    DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_point')->insertGetId(
                        array(
                            'order_id' => $postId,
                            'total_order' => $finalDetails['total'],
                            'user_id' => $user['id'],
                            'point' => $data['point_use'],
                            'minimum_spending' => $totalOrderBanDau,
                            'points_converted_to_money' => $points_converted_to_money,
                            'status' => 4,
                        )
                    );
                } else if ($data['point_use'] == '' || $data['point_use'] == 0) {
                } else {
                    throw new \Exception('Vượt quá số điểm hiện có');
                }
                $convertMoneyToPoint = ($money_converted_to_point) > 0 ? floor($finalDetails['total'] / $money_converted_to_point) : 0;
                DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_point')->insertGetId(
                    array(
                        'order_id' => $postId,
                        'total_order' => $finalDetails['total'],
                        'user_id' => $user['id'],
                        'point' => $convertMoneyToPoint,
                        'minimum_spending' => $totalOrderBanDau,
                        'points_converted_to_money' => $points_converted_to_money,
                        'status' => 3,
                    )
                );
            }
            //them hoa hồng
            $getUserParent = $this->getUserMeta($user['id'], 'user_parent');
            $configAff = $this->getOptionsMeta('woo_aff_setting');
            if ($getUserParent && $configAff) {
                $getUserParent2 = $this->getUserMeta($getUserParent, 'user_parent');
                $configAff2 = $this->getOptionsMeta('woo_aff_setting_cap2');
                $commissions2 = 0;
                $commissions = $finalDetails['total'] * $configAff / 100;
                if ($getUserParent2 && $configAff2) {
                    $commissions2 = $finalDetails['total'] * $configAff2 / 100;
                }
                DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->insertGetId(
                    array(
                        'order_id' => $postId,
                        'total_order' => $finalDetails['total'],
                        'user_id' => $user['id'],
                        'commission' => $commissions,
                        'commission_level2' => $commissions2,
                        'minimum_spending' => $totalOrderBanDau,
                        'date' => date('d'),
                        'month' => date('m'),
                        'year' => date('Y'),
                        'status' => 3,
                    )
                );
            }

            if($data['payment_gateway'] == 'cod'){
                $paymentTitle  = 'Thanh toán khi giao hàng';
            }  else{
                $paymentTitle  = 'Chuyển khoản ngân hàng';
                
            }
            // them wp_postmeta
            $postMeta = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_postmeta')->insert(
                array(
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_order_key',
                        'meta_value' => 'wc_order_' . Str::random(10),
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_customer_user',
                        'meta_value' => $user['id'],
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_payment_method',
                        'meta_value' => $data['payment_gateway'],
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_payment_method_title',
                        'meta_value' => $paymentTitle,
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_billing_last_name',
                        'meta_value' => $user['name'],
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_billing_address_1',
                        'meta_value' => $user['address'],
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_billing_email',
                        'meta_value' => $user['email'],
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_billing_phone',
                        'meta_value' => str_replace('84','',$user['mobile']),
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_order_currency',
                        'meta_value' => 'VND',
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_cart_discount',
                        'meta_value' => $finalDetails['coupon_discounted'],
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_cart_discount_tax',
                        'meta_value' => 0,
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_order_shipping',
                        'meta_value' => 0,
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_order_shipping_tax',
                        'meta_value' => 0,
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_order_tax',
                        'meta_value' => 0,
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_order_total',
                        'meta_value' => $finalDetails['total'],
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_billing_address_index',
                        'meta_value' => $user['name'] . ' ' . $user['address'] . ' ' . $user['email'] . ' ' . $user['mobile'],
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_shipping_address_index',
                        'meta_value' => $user['address'],
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_shipping_address_1',
                        'meta_value' => $user['address'],
                    ),
                    array(
                        'post_id' => $postId,
                        'meta_key' => '_shipping_country',
                        'meta_value' => 'VN',
                    ),
                )
            );
            //them order wp_wc_order_stats

            try {
                DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_wc_order_stats')->insertGetId(
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
            } catch (\Throwable $th) {
                DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_wc_order_stats')->insertGetId(
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
            }


            //tính hoa hồng

            DB::connection('mysql_external')->commit();



            return $postId;
        } catch (\Throwable $th) {
            //throw $th;


            DB::connection('mysql_external')->rollBack();
            throw new \Exception($th->getMessage());
        }
    }

    public function getFinalPriceDetails($user, $validated_data, $totalPriceDetails)
    {


        $shipping_method = $validated_data['shipping_method'] ?? "";


        $state = $validated_data["state"];
        $country = $validated_data["country"];

        $price = $totalPriceDetails;
        $coupon = ["coupon" => $validated_data['used_coupon'], "subtotal" => $price['total']];




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
    public function getSellPrice($postId)
    {
        $priceGoc = $this->getPostMeta($postId, '_regular_price');
        $price = $this->getPostMeta($postId, '_sale_price');
        $time = time();
        $_sale_price_dates_from = $this->getPostMeta($postId, '_sale_price_dates_from');
        $_sale_price_dates_to = $this->getPostMeta($postId, '_sale_price_dates_to');
        if ($price && $time >= $_sale_price_dates_from && $time <= $_sale_price_dates_to) {
            $price = $price;
        } else {
            $price = $priceGoc;
        }
        return $price;
    }
    public function getTotalPriceDetails($cart, $postId)
    {


        $total = 0.0;
        $cartArr = self::getCartProducts($cart);
        $time = time();
        foreach ($cartArr as $key => $item) {
            $sold_count = $this->getPostMeta($item['id'], 'total_sales');
            $stock_count = $this->getPostMeta($item['id'], '_stock');
            $priceGoc = $this->getPostMeta($item['id'], '_regular_price');
            $price = $this->getPostMeta($item['id'], '_sale_price');
            $_sale_price_dates_from = $this->getPostMeta($item['id'], '_sale_price_dates_from');
            $_sale_price_dates_to = $this->getPostMeta($item['id'], '_sale_price_dates_to');
            if ($price && $time >= $_sale_price_dates_from && $time <= $_sale_price_dates_to) {
                $price = $price;
            } else {
                $price = $priceGoc;
            }



            $stockStatus = $this->getPostMeta($item['id'], '_stock_status');
            //checkcampaign
            $productId = $item['id'];

            //check số lượng trong kho
            if (!empty($stock_count) &&  $stock_count < $item['qty']) {


                $this->_messageError = $item['name'] . " hết hàng trong kho";
                return false;
            }




            // trừ số lượng kho
            DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_postmeta')->where('post_id', $productId)->where('meta_key', 'total_sales')->update(
                array(
                    'meta_value' => $sold_count + $item['qty']
                )
            );
            DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_postmeta')->where('post_id', $productId)->where('meta_key', '_stock')->update(
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
    public function getHistoryUser($userId)
    {
        $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_point')->where('user_id', $userId)->orderBy('id', 'DESC')->get();
        return $data;
    }

    public function getPointUser($history)
    {
        $total = 0;
        $totalDoiThuong = 0;
        $totalOrder = 0;
        foreach ($history  as $value) {
            if ($value->status == 1) {
                $total = $total + $value->point;
                $totalDoiThuong = $totalDoiThuong + $value->point;
                $totalOrder = $totalOrder + $value->total_order;
            }
            if ($value->status == 2 || $value->status == 4) {
                $totalDoiThuong = $totalDoiThuong - $value->point;
            }
        }
        $checkRank = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_rank')->where('minimum_spending', '<=', $totalOrder)->orderBy('minimum_spending', 'DESC')->first();
        $checkRankNext = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_rank')->where('minimum_spending', '>', $totalOrder)->orderBy('minimum_spending', 'ASC')->first();
        $pointNext = 0;
        if ($checkRankNext) {
            $minium = $checkRankNext->minimum_spending;
            $pointPriceSetiing = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_setting')->where('id', 1)->first();
            if ($pointPriceSetiing) {


                $pointNextSetting = ceil($minium / $pointPriceSetiing->amount_spent);
                $pointNext = $pointNextSetting - $total;
            }
        }
        return [
            'total' => $total,
            'totalDoiThuong' => $totalDoiThuong,
            'totalOrder' => $totalOrder,
            'rank' => $checkRank,
            'rankNext' => $checkRankNext,
            'point_next' => $pointNext,
        ];
    }
    public function getPostByCategoryId($id)
    {
        $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_posts')
            ->join($this->_PRFIX_TABLE . '_term_relationships',  $this->_PRFIX_TABLE . '_term_relationships.object_id',  $this->_PRFIX_TABLE . '_posts.ID')
            ->where('term_taxonomy_id', $id)->where('post_status', 'publish')->select($this->_PRFIX_TABLE . '_posts.*')->orderBy('post_modified', 'DESC')->get();
        return $data;
    }
    public function getPostByCategory($nameCate)
    {
        $cate = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_terms')->where('slug', $nameCate)->first();
        $data = [];
        if ($cate) {
            $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_posts')
                ->join($this->_PRFIX_TABLE . '_term_relationships',  $this->_PRFIX_TABLE . '_term_relationships.object_id',  $this->_PRFIX_TABLE . '_posts.ID')
                ->where('term_taxonomy_id', $cate->term_id)->where('post_status', 'publish')->orderBy('post_modified', 'DESC')->get();
        }
        return ['data' => $data, 'cate' => $cate];
    }
    public function lienhe($content, $text, $count)
    {
        $lineAfterPhone = '';
        $pattern = '/' . $text . ' (.+?)\n/';
        // Perform a regular expression match
        if (preg_match($pattern, $content, $matches)) {
            // Extracted email address
            $lineAfterPhone = trim($matches[1]);
        }
        return $lineAfterPhone;
    }
    public function getPostMeta($postId, $meta)
    {
        $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_postmeta')->where('post_id', $postId)->where('meta_key', $meta)->first();
        if ($data) {
            return $data->meta_value;
        }
        return $data;
    }
    public function getUserMeta($userId, $meta)
    {
        $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_usermeta')->where('user_id', $userId)->where('meta_key', $meta)->first();
        if ($data) {
            return $data->meta_value;
        }
        return $data;
    }
    public function getOptionsMeta($meta)
    {
        $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_options')->where('option_name', $meta)->first();
        if ($data) {
            return $data->option_value;
        }
        return $data;
    }
    public function getOrderMeta($orderId, $meta)
    {
        $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woocommerce_order_itemmeta')->where('order_item_id', $orderId)->where('meta_key', $meta)->first();
        if ($data) {
            return $data->meta_value;
        }
        return $data;
    }
    public function getPrefixTable()
    {
        $tables = DB::connection('mysql_external')->select('SHOW TABLES')[0];
        $array = get_object_vars($tables);
        $value = array_values($array)[0];

        return explode("_", $value)[0];
    }
    public function getPrefixTableFirst()
    {
        $tables = DB::connection('mysql_external')->select('SHOW TABLES')[0];
        $array = get_object_vars($tables);
        $value = array_values($array)[0];

        return explode("_", $value)[0];
    }
    public function getUserChild($userParent)
    {
        $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_usermeta')->where('meta_key', 'user_parent')->where('meta_value', $userParent)->pluck('user_id')->toArray();

        return $data;
    }
    public function getUserChild2($listChild1)
    {
        $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_usermeta')->where('meta_key', 'user_parent')->whereIn('meta_value', $listChild1)->pluck('user_id')->toArray();

        return $data;
    }
    public function choDoiSoat($userParent)
    {
        $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->where('user_id', $userParent)->where('status', 4)->sum('commission');
        return $data;
    }
    public function thucNhan($userParent, $date = null, $month = null, $year = null)
    {
        if ($date && $month && $year) {
            $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->where('user_id', $userParent)->where('status', 2)->where('date', $date)->where('month', $month)->where('year', $year)->sum('commission');
        } else {
            $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->where('user_id', $userParent)->where('status', 2)->sum('commission');
        }
        return $data;
    }
    public function tongHoaHong($userChild, $userChild2, $date = null, $month = null, $year = null)
    {
        if ($date && $month && $year) {
            $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->whereIn('user_id', $userChild)->where('status', 1)->where('date', $date)->where('month', $month)->where('year', $year)->sum('commission');
            $data2 = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->whereIn('user_id', $userChild2)->where('status', 1)->where('date', $date)->where('month', $month)->where('year', $year)->sum('commission_level2');
            $total = $data + $data2;
        } else {
            $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->whereIn('user_id', $userChild)->where('status', 1)->sum('commission');
            $data2 = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->whereIn('user_id', $userChild2)->where('status', 1)->sum('commission_level2');
            $total = $data + $data2;
        }
        return $total;
    }
    public function tongDoanhThu($userChild, $userChild2, $date = null, $month = null, $year = null)
    {
        $userChild = array_merge($userChild, $userChild2);
        if ($date && $month && $year) {
            $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->whereIn('user_id', $userChild)->where('status', 1)->where('date', $date)->where('month', $month)->where('year', $year)->sum('total_order');
        } else {
            $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->whereIn('user_id', $userChild)->where('status', 1)->sum('total_order');
        }
        return $data;
    }

    public function tongDonHang($userChild, $userChild2, $date = null, $month = null, $year = null)
    {
        $userChild = array_merge($userChild, $userChild2);
        if ($date && $month && $year) {
            $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->whereIn('user_id', $userChild)->where('status', 1)->where('date', $date)->where('month', $month)->where('year', $year)->get()->count();
        } else {
            $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->whereIn('user_id', $userChild)->where('status', 1)->get()->count();
        }
        return $data;
    }
}
