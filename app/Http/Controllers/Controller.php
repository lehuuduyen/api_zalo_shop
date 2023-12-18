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
        $daUser = $databaseStore->da_user;
        $domain = $databaseStore->domain;
        $path = "/home/{$daUser}/domains/{$domain}/public_html";


        if ( !$this->is_dir($path) ) {
			include $path.'/wp-config.php';
            $databaseUser = DB_USER;
            echo '<pre>';
            print_r($databaseUser);
            die;


		}


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
    public function is_dir($path)
	{
		return $this->toNumber($this->sshBash('[ ! -d "' . $path . '" ]; echo $?'));
	}
    public function toNumber($str)
    {
        return (int)$this->vnStrFilter($str, "");
    }
    public function vnStrFilter($text, $space = "-", $lower = true)
    {
        $text = html_entity_decode(trim($text), ENT_QUOTES, 'UTF-8');
        $replace = array(
            'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
            'd' => 'đ',
            'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
            'i' => 'í|ì|ỉ|ĩ|ị',
            'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
            'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
            'A' => 'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ặ|Ằ|Ẳ|Ẵ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
            'D' => 'Đ',
            'E' => 'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
            'I' => 'Í|Ì|Ỉ|Ĩ|Ị',
            'O' => 'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
            'U' => 'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
            'Y' => 'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
            ' ' => '[^a-z0-9]'
        );
        foreach ($replace as $to => $from) {
            $text = preg_replace("/($from)/i", $to, $text);
        }
        $text = trim($text);
        $text = str_replace(" ", $space, $text);
        while (strpos($text, "--") !== false) {
            $text = str_replace("--", "-", $text);
        }
        if ($lower) {
            $text = strtolower($text);
        }
        return $text;
    }
    public function sshBash($cmd, $out = false, $toend = '', $dbQuote = false)
    {
        if ($dbQuote) {
            $cmd = "sudo sh -c \"" . $cmd . "\"";
        } else {
            $cmd = 'sudo sh -c \'' . $cmd . '\'';
        }
        $cmd .= $toend;
        if ($out) {
            echo '<textarea>' . $cmd . '</textarea>';
            return;
        }
        return trim(shell_exec($cmd));
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
    public function getToken($store, $sdt, $databaseStore, $domain)
    {
        $minute = (env('EXPIRED_MINUTE')) ? env('EXPIRED_MINUTE') : "";
        try {
            $date = empty($minute) ? "" : strtotime(date('d-m-Y H:i:s', strtotime("+$minute min")));
            $token = $this->encodeData(json_encode(['store' => $store, 'sdt' => $sdt, 'databaseStore' => $databaseStore, 'domain' => $domain, 'expired_in' => strtotime($date)]));
            return $token;
        } catch (\Exception $e) {
            //throw $th;
        }
    }
    public function getImage($id, $store)
    {

        $domain = "https://" . $store->domain . "/assets/tenant/uploads/media-uploader/" . $store->store;


        $image = DB::connection('mysql_external')->table('media_uploaders')->select('id', 'title', 'path', 'alt', 'size', 'user_type', 'dimensions')->find($id);
        if ($image) {
            $image->path = $domain . "/" . $image->path;
        }

        return $image;
    }
    public function getGalleries($id, $store)
    {
        $response = [];
        $data = DB::connection('mysql_external')->table('product_galleries')->where('product_id', $id)->get();
        if ($data) {
            foreach ($data as $key => $value) {
                $response[] =  $this->getImage($value->image_id, $store);
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
        $data = DB::connection('mysql_external')->table('product_categories')->join('categories', 'categories.id', 'product_categories.category_id')->where('product_categories.product_id', $id)->first();
        if ($data) {
            $data->name =  $this->getTextByLanguare($data->name);
            $data->image =  $this->getImage($data->image_id, $store);
            $data->sub_category = $this->getSubCategoryByProduct($id, $store);
            $response = $data;
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
    public function getProductInventory($id)
    {
        $response = new \stdClass();
        $data = DB::connection('mysql_external')->table('product_inventories')->where('product_id', $id)->first();
        if ($data) {
            $product_inventory_details = DB::connection('mysql_external')->table('product_inventory_details')->where('product_id', $id)->where('product_inventory_id', $data->id)->get();
            $data->product_inventory_details = [];
            $list = [];
            foreach ($product_inventory_details as $value) {
                $value->color = $this->getColor($value->color);
                if (!empty((array)$value->color)) {
                    $list['color'][] = [
                        "id" => $value->color->id,
                        "name" => $value->color->name,
                        "color_code" => $value->color->color_code,
                    ];
                }
                // Create a new color entry
                if (!empty((array)$value->size)) {
                    $value->size = $this->getSize($value->size);
                    $list['size'][] = [
                        "id" => $value->size->id,
                        "name" => $value->size->name,
                        "size_code" => $value->size->size_code,
                    ];
                }


                $value->attribute = $this->getAttributeProduct($value->id);
                $list = $this->calAttribute($list, $value->attribute);
                // foreach($value->attribute as $key => $item){
                //     $list[$key][] = $item;
                // }
                $data->product_inventory_details[] = $value;
            }
            if (isset($list['color'])) {
                $list['color'] = $this->uniqueList($list['color']);
            }
            if (isset($list['size'])) {
                $list['size'] = $this->uniqueList($list['size']);
            }
            $data->attribute = $list;
            $response = $data;
        }
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
        $response = DB::connection('mysql_external')->table('product_reviews')->join('users', 'users.id', 'product_reviews.user_id')->where('product_reviews.product_id', $id)->select('product_reviews.*', 'users.name')->get();

        return $response;
    }
    public function calculateCoupon($data, $products)
    {
        $discount_total = 0;
        $paramCoupon = $data['coupon'];
        $coupon = DB::connection('mysql_external')->table('product_coupons')->where('code', $paramCoupon)->first();
        if (is_null($coupon)) {
            return $discount_total;
        }
        $coupon_amount = $coupon->discount;
        $discount_on = $coupon->discount_on;
        if ($discount_on == 'all') {
            $discount_total = $coupon_amount; // not needed
        }
        $coupon_type = $coupon->discount_type;

        // calculate based on coupon type
        if ($coupon_type === 'percentage') {
            $discount_total = $data['subtotal'] / 100 * $coupon_amount;
        } elseif ($coupon_type === 'amount') { # =====
            $discount_total = $coupon_amount;
        }
        if ($discount_total > $data['subtotal']) {
            $discount_total = $data['subtotal'];
        }

        return $discount_total;
    }
    public function createOrder($data, $user)
    {
        DB::connection('mysql_external')->beginTransaction();

        try {
            $totalPriceDetails =  $this->getTotalPriceDetails($data['order']);


            if (!$totalPriceDetails) {
                throw new \Exception('');
            }

            $finalDetails = $this->getFinalPriceDetails($user, $data, $totalPriceDetails);

            $finalPriceDetails = $finalDetails['total'];
            $payment_meta = $finalDetails['payment_meta'];
            $payment_gateway = $data['payment_gateway'] ?? null;
            $extra_note = $data['message'];
            $cart_data = json_encode($data['order']);
            $order_id = DB::connection('mysql_external')->table('product_orders')->insertGetId(
                array(
                    'user_id' => $user['id'] ?? \auth()->guard('web')->id() ?? null,
                    'coupon' => $data["used_coupon"],
                    'coupon_discounted' => $finalDetails['coupon_discounted'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['mobile'],
                    'country' => $user['country'],
                    'state' => $user['state'],
                    'city' => $user['city'],
                    'address' => $user['address'],
                    'message' => $extra_note,
                    'total_amount' => $finalPriceDetails,
                    'payment_gateway' => $payment_gateway,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'checkout_type' => 'cod',
                    'payment_track' => Str::random(10) . Str::random(10),
                    'order_details' => $cart_data,
                    'payment_meta' => $payment_meta,
                    'selected_shipping_option' => $data['shipping_method'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                )
            );
            foreach ($totalPriceDetails['products_id'] as $key => $ids) {
                DB::connection('mysql_external')->table('order_products')->insertGetId(
                    array(
                        'order_id' => $order_id,
                        'product_id' => $totalPriceDetails['products_id'][$key],
                        'variant_id' => !empty($totalPriceDetails['variants_id'][$key]) ? $totalPriceDetails['variants_id'][$key] : null,
                        'quantity' => $totalPriceDetails['quantity'][$key] ?? null,
                    )
                );
            }
            //tính hoa hồng

            DB::connection('mysql_external')->commit();


            return $order_id;
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
        $coupon = ["coupon" => $validated_data['used_coupon']];
        $state = $validated_data["state"];
        $country = $validated_data["country"];

        $price = $totalPriceDetails;

        $products = DB::connection('mysql_external')->table('products')->whereIn('id', $price['products_id'])->get();


        $data = $this->get_product_shipping_tax(['country' => $country, 'state' => $state, 'shipping_method' => (int)$shipping_method]);
        $coupon['subtotal'] = $price['total'];
        $discounted_price = $this->calculateCoupon($coupon, $products);


        $price['total'] -= $discounted_price;

        $product_tax = $data['product_tax'];
        $shipping_cost = $data['shipping_cost'];

        $taxed_price = ($price['total'] * $product_tax) / 100;
        $subtotal = $price['total'] + $discounted_price;
        $total['total'] = $price['total'] + $taxed_price + $shipping_cost;

        $total['payment_meta'] = $this->payment_meta(compact('product_tax', 'shipping_cost', 'subtotal', 'total'));
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
    public function getTotalPriceDetails($cart)
    {
        $total = 0.0;
        $cartArr = self::getCartProducts($cart);

        foreach ($cartArr as $item) {
            //checkcampaign
            $productId = $item['id'];
            $campaign = $this->getCampaignByProduct($productId);

            //check số lượng trong kho
            $checkProductInventory = $this->checkProductInventory($productId);

            if ($checkProductInventory->stock_count < $item['qty']) {
                $this->_messageError = $checkProductInventory->name . " hết hàng trong kho";
                return false;
            }

            if ($campaign) {
                //là chiến dịch kiểm tra xem tồn kho theo chiến dịch
                $campaingSoldProduct = DB::connection('mysql_external')->table('campaign_sold_products')->where('campaign_sold_products.product_id', $productId)->first();


                if ($campaingSoldProduct) {
                    if ($campaingSoldProduct->sold_count + $item['qty'] > $campaign->units_for_sale) {
                        $this->_messageError = "Sản phẩm " . $checkProductInventory->name . " trong flashsale đã hết";
                        return false;
                    }
                    DB::connection('mysql_external')->table('campaign_sold_products')->where('id', $campaingSoldProduct->id)->update(
                        array(
                            'sold_count' => $item['qty'] + $campaingSoldProduct->sold_count,
                            'total_amount' => $campaign->units_for_sale,
                        )
                    );
                } else {
                    // thêm sản phẩm chiến dịch
                    DB::connection('mysql_external')->table('campaign_sold_products')->insertGetId(
                        array(
                            'product_id' => $productId,
                            'sold_count' => $item['qty'],
                            'total_amount' => $campaign->units_for_sale,
                        )
                    );
                }
            }

            // trừ số lượng kho
            DB::connection('mysql_external')->table('product_inventories')->where('id', $checkProductInventory->id)->update(
                array(
                    'stock_count' => $checkProductInventory->stock_count - $item['qty'],
                    'sold_count' => $item['qty'] + $checkProductInventory->sold_count
                )
            );



            $total += $item['price'] * $item['qty'];
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
}
