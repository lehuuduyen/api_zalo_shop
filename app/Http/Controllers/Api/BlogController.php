<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;

        $listBlogs = $this->getPostByCategory('blogs');
        if($listBlogs){
            $blogs = $listBlogs['data'];
            foreach($blogs as $key => $blog){
                $blogs[$key]->id = $blog->ID;
                $blogs[$key]->image = $this->getImage($blog->ID,$store);
                $blogs[$key]->title = $blog->post_title;
                $blogs[$key]->slug = $blog->post_name;
                $blogs[$key]->author =  $this->getAuthor($blog->post_author);
                $blogs[$key]->blog_content = $blog->post_content;
                $blogs[$key]->excerpt = $blog->post_excerpt;
                $blogs[$key]->category_name = $listBlogs['cate']->name;
            }
        }

        return $this->returnSuccess($blogs);
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
