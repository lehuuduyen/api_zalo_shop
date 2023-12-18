<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $campaigns = DB::connection('mysql_external')->table('campaigns')->join('campaign_products', 'campaigns.id', 'campaign_products.campaign_id')->where('campaigns.status','publish')->whereDate('campaigns.start_date', '<', Carbon::now())->whereDate('campaigns.end_date', '>=', Carbon::now())->select('campaign_products.product_id','campaign_products.campaign_price')->get();
        $products = DB::connection('mysql_external')->table('products')->join('statuses', 'statuses.id', 'products.status_id')->where('products.status_id', 1)->whereNull('products.deleted_at');
        if (isset($request['category'])) {
            $products = $products->join('product_categories', 'product_categories.product_id', 'products.id')->where('product_categories.category_id', $request['category']);
        }
        $products = $products->select('products.*', 'statuses.name as status')->latest()->get();
        $store = $request['data_reponse'];
        
        

        foreach ($products as $key => $product) {
            $products[$key]->is_campaign = false;
            foreach($campaigns as $campaign){
                if($campaign->product_id == $product->id){
                    $products[$key]->price = $product->sale_price;
                    $products[$key]->sale_price = $campaign->campaign_price;
                    $products[$key]->is_campaign = true;
                    break;
                }
            }
            $products[$key]->image_id = $this->getImage($product->image_id, $store);
            $products[$key]->brand_id = $this->getBrand($product->brand_id, $store);
            $products[$key]->name = $this->getTextByLanguare($product->name);
            $products[$key]->summary = $this->getTextByLanguare($product->summary);
            $products[$key]->description = $this->getTextByLanguare($product->description);
            $products[$key]->badge_id = $this->getBadge($product->badge_id, $store);
            $products[$key]->category = $this->getCategoryByProduct($product->id, $store);
            $products[$key]->galleries = $this->getGalleries($product->id, $store);
            $products[$key]->product_inventory = $this->getProductInventory($product->id);
            $products[$key]->delivery_option = $this->getProductDeliveryOption($product->id);
            $products[$key]->unit = $this->getUnit($product->id);
            $products[$key]->policy = $this->getPolicy($product->id);
            $products[$key]->tag_name = $this->getTagName($product->id);
            $products[$key]->review = $this->getreview($product->id);

            // $products[$key]->state = $this->getState($order->state);
            // $products[$key]->order_details = json_decode($order->order_details);
            // $products[$key]->payment_meta = json_decode($order->payment_meta);

        }
        return $this->returnSuccess($products);
    }
    public function getCategories(Request $request)
    {
        $store = $request['data_reponse'];

        $categories = DB::connection('mysql_external')->table('categories')->where('status_id', 1)->whereNull('deleted_at')->get();
        if ($categories) {
            foreach ($categories as $key => $val) {
                $categories[$key]->name =  $this->getTextByLanguare($val->name);
                $categories[$key]->image =  $this->getImage($val->image_id, $store);
            }
        }
        return $this->returnSuccess($categories);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function review(Request $request)
    {
        $store = $request['data_reponse'];
        $data = $request->all();
        $data['sdt'] = $store->sdt;
        if (!isset($data['rating'])) {
            return $this->returnError([], "Bắt buộc phải nhập rating");
        } else if (!isset($data['product_id'])) {
            return $this->returnError([], "Bắt buộc phải nhập product");
        } else {
            $user = DB::connection('mysql_external')->table('users')->where('mobile', $data['sdt'])->first();
            if (!$user) {
                return $this->returnError([], "Số điện thoại chưa được đăng ký");
            }

            $insert = DB::connection('mysql_external')->table('product_reviews')->insert(
                array(
                    'product_id'     =>   $data['product_id'],
                    'rating'     =>   $data['rating'],
                    'review_text'     =>   isset($data['review_text']) ? $data['review_text'] : '',
                    'user_id'   =>   $user->id
                )
            );
            return $this->returnSuccess($insert, 'Thêm review thành công');
        }
    }


    public function checkCoupon(Request $request)
    {
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
            $listProductId[] = $value['id'];
        }
        $products = DB::connection('mysql_external')->table('products')->whereIn('id', $listProductId)->get();
        $coupon_amount_total = $this->calculateCoupon($data, $products);
        if ($coupon_amount_total > 0) {
            return $this->returnSuccess($coupon_amount_total);
        }else{
            return $this->returnError($coupon_amount_total, 'Mã khuyễn mãi không đúng');
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
