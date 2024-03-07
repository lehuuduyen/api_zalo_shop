<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
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
        $results =[];

        $phims = DB::table($this->_PRFIX_TABLE . '_films');


        if (isset($request['category_id'])) {
            $phims = $phims->where('category_id',$request['category_id']);
        }
        $phims = $phims->orderBy('create_at', 'DESC')->get();


        $time = time();

        foreach ($phims as $key => $phim) {
            $phim->episode =[];
            $listTap =[];
            $listTap = DB::table($this->_PRFIX_TABLE . '_postmeta')->
            join($this->_PRFIX_TABLE . '_posts',$this->_PRFIX_TABLE . '_posts.ID',$this->_PRFIX_TABLE . '_postmeta.post_id')->
            select($this->_PRFIX_TABLE . '_posts.post_title as name',$this->_PRFIX_TABLE . '_posts.ID')->
            where($this->_PRFIX_TABLE . '_postmeta.meta_key','_film_selected')->
            where($this->_PRFIX_TABLE . '_postmeta.meta_value',$phim->id)->get();
            if($listTap){
                foreach($listTap as $key => $tap){
                    $listTap[$key]->link_movie = $this->getPostMeta($tap->ID,'_film_episode');
                    $listTap[$key]->price = $this->getPostMeta($tap->ID,'_price');

                }
                $phim->episode =$listTap;


            }
            $results[]=$phim;



        }
        return $this->returnSuccess($results);
    }
    public function getCategories(Request $request)
    {

        $categories = DB::table($this->_PRFIX_TABLE . '_term_taxonomy')->join($this->_PRFIX_TABLE . '_terms', $this->_PRFIX_TABLE . '_terms.term_id', $this->_PRFIX_TABLE . '_term_taxonomy.term_id')->where($this->_PRFIX_TABLE . '_term_taxonomy.taxonomy', 'product_cat')->select($this->_PRFIX_TABLE . '_terms.*')->get();
        $results = [];
        if ($categories) {
            foreach ($categories as $key => $val) {
               if($val->slug == "uncategorized"){
                continue;
               }
               $results[]=$val;
            }
        }


        return $this->returnSuccess($results);
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
                $user = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', $data['sdt'])->first();
                if (!$user) {
                    return $this->returnError([], "Số điện thoại chưa được đăng ký");
                }
                $insertId = DB::table($this->_PRFIX_TABLE . '_comments')->insertGetId(
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


                $insert = DB::table($this->_PRFIX_TABLE . '_commentmeta')->insert(
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
                $listProductId[] = $value['id'];
            }
            $products = DB::table($this->_PRFIX_TABLE . '_posts')->whereIn('id', $listProductId)->get();
            $coupon_amount_total = $this->calculateCoupon($data, $products, true);
            if ($coupon_amount_total > 0) {
                return $this->returnSuccess($coupon_amount_total);
            } else {
                return $this->returnError($coupon_amount_total, 'Mã khuyễn mãi không đúng');
            }
        } catch (\Throwable $th) {
            //throw $th;
            $this->woo_logs('checkCoupon', $th->getMessage());
            return $this->returnError(0, 'Mã khuyễn mãi không đúng');
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
