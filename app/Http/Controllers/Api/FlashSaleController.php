<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlashSaleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // ->leftJoin('wp_postmeta','wp_postmeta.post_id','wp_posts.ID')->where('wp_postmeta.meta_key', '')->whereNull('wp_postmeta.meta_value')
        $products = DB::connection('mysql_external')->table('wp_posts')->where('wp_posts.post_type', 'product')->where('wp_posts.post_status', 'publish')->orderBy('wp_posts.post_modified', 'DESC')->get();


        $store = $request['data_reponse'];
        $campaigns = [];
        $campaigns[0]= new \stdClass();
        $campaigns[0]->title = "Flash sale";

        $campaigns[0]->subtitle = "Flash sale";
        $campaigns[0]->image = "";
        $data = [];
        $i = 0;
        foreach ($products as $key => $product) {
            $checkFlashSale = $this->getPostMeta($product->ID, '_sale_price');
            if (empty($checkFlashSale)) {
                continue;
            }

            $data[$i]['product']['id'] = $product->ID;
            $data[$i]['product']['product_id'] = $product->ID;
            $postMetaStatus = $this->getPostMeta($product->ID, '_stock_status');
            $postMetaStock = $this->getPostMeta($product->ID, '_stock');
            $postMetaGiaGoc = $this->getPostMeta($product->ID, '_regular_price');
            $postMetaGiaKhuyenMai = $this->getPostMeta($product->ID, '_sale_price');
            $postMetaStock = $this->getPostMeta($product->ID, '_stock');
            $data[$i]['product']['is_campaign'] = true;
            $data[$i]['product']['price'] =  $postMetaGiaGoc;
            $data[$i]['campaign_price'] = $postMetaGiaKhuyenMai;
            $data[$i]['id'] = $product->ID;
            $data[$i]['product_id'] = $product->ID;

            $data[$i]['product']['image_id'] = $this->getImage($product->ID, $store);
            $data[$i]['product']['name'] = $product->post_title;
            $data[$i]['product']['summary'] = $product->post_excerpt;
            $data[$i]['product']['description'] = $product->post_content;
            $data[$i]['product']['badge_id'] = [];
            $data[$i]['product']['category'] = $this->getCategoryByProduct($product->ID, $store);
            $data[$i]['product']['galleries'] = $this->getGalleries($product->ID, $store);
            $data[$i]['product']['product_inventory'] = $this->getProductInventory($product->ID);
            $data[$i]['product']['delivery_option'] = [];
            $data[$i]['product']['unit'] = [];
            $data[$i]['product']['policy'] = [];
            $data[$i]['product']['tag_name'] = [];
            $data[$i]['product']['review'] = $this->getreview($product->ID);
            $data[$i]['product']['sold_count'] =  $data[$i]['product']['product_inventory']->sold_count;

            // $campaigns[0]->products = ;


            $i++;
        }
        $campaigns[0]->products =$data ;

        return $this->returnSuccess($campaigns);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
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
