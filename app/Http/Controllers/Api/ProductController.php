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
        $products = DB::connection('mysql_external')->table('wp_posts')->where('post_type', 'product')->where('post_status', 'publish')->orderBy('post_modified', 'DESC')->get();
        if (isset($request['category'])) {
            $products = $this->getPostByCategoryId($request['category']);
        }

        $store = $request['data_reponse'];



        foreach ($products as $key => $product) {
            $products[$key]->id = $product->ID;
            $products[$key]->product_id = $product->ID;
            $postMetaStatus = $this->getPostMeta($product->ID,'_stock_status');
            $postMetaStock = $this->getPostMeta($product->ID,'_stock');
            $postMetaGiaGoc = $this->getPostMeta($product->ID,'_regular_price');
            $postMetaGiaKhuyenMai = $this->getPostMeta($product->ID,'_sale_price');
            $postMetaStock = $this->getPostMeta($product->ID,'_stock');
            $products[$key]->is_campaign = false;
            $products[$key]->price =  $postMetaGiaGoc;
            $products[$key]->sale_price =  $postMetaGiaGoc;
            if($postMetaGiaKhuyenMai){
                $products[$key]->sale_price = $postMetaGiaKhuyenMai;
                $products[$key]->is_campaign = true;
            }

            $products[$key]->image_id = $this->getImage($product->ID, $store);
            // $products[$key]->brand_id = $this->getBrand($product->brand_id, $store);
            $products[$key]->name = $product->post_title;
            $products[$key]->summary = $product->post_excerpt;
            $products[$key]->description = $product->post_content;
            $products[$key]->badge_id =[];
            $products[$key]->category = $this->getCategoryByProduct($product->ID, $store);
            $products[$key]->galleries = $this->getGalleries($product->ID, $store);
            $products[$key]->product_inventory = $this->getProductInventory($product->ID);
            $products[$key]->delivery_option =[];
            // $products[$key]->delivery_option = $this->getProductDeliveryOption($product->id);
            $products[$key]->unit = [];
            // $products[$key]->unit = $this->getUnit($product->id);
            $products[$key]->policy = [];
            // $products[$key]->policy = $this->getPolicy($product->id);
            $products[$key]->tag_name = [];
            // $products[$key]->tag_name = $this->getTagName($product->id);

            $products[$key]->review = $this->getreview($product->ID);
            $products[$key]->sold_count =  $products[$key]->product_inventory->sold_count;

            // $products[$key]->state = $this->getState($order->state);
            // $products[$key]->order_details = json_decode($order->order_details);
            // $products[$key]->payment_meta = json_decode($order->payment_meta);

        }
        return $this->returnSuccess($products);
    }
    public function getCategories(Request $request)
    {
        $store = $request['data_reponse'];

        $categories = DB::connection('mysql_external')->table('wp_term_taxonomy')->join('wp_terms','wp_terms.term_id','wp_term_taxonomy.term_id')->where('wp_term_taxonomy.taxonomy', 'product_cat')->select('wp_terms.*')->get();

        if ($categories) {
            foreach ($categories as $key => $val) {
                $img = DB::connection('mysql_external')->table('wp_termmeta')->where('wp_termmeta.meta_key', 'thumbnail_id')->where('wp_termmeta.term_id', $val->term_id)->first();
                $categories[$key]->id =  $val->term_id;
                $categories[$key]->image =  ($img)?$this->getImage($img->meta_value, $store,true):"";
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
            $user = DB::connection('mysql_external')->table('wp_users')->where('user_login', $data['sdt'])->first();
            if (!$user) {
                return $this->returnError([], "Số điện thoại chưa được đăng ký");
            }
            $insertId = DB::connection('mysql_external')->table('wp_comments')->insertGetId(
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


            $insert = DB::connection('mysql_external')->table('wp_commentmeta')->insert(
                array(
                    'comment_id'     =>   $insertId,
                    'meta_value'     =>   $data['rating'],
                    'meta_key'     =>   'rating',
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
