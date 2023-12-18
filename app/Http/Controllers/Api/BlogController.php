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
        $languare = env('DEFAULT_LANGUARE')?env('DEFAULT_LANGUARE'):"vi";

        $store = $request['data_reponse'];
        $blogs = DB::connection('mysql_external')->table('blogs')->where('status',1)->latest()->get();
        foreach($blogs as $key => $blog){
            $blogs[$key]->image = $this->getImage($blog->image,$store);
            $jsonTitle = json_decode($blog->title);
            $title ="";
            if(isset($jsonTitle->$languare)){
                $title = $jsonTitle->$languare;
            }
            $blogs[$key]->title = $title;
            
            $jsonContent = json_decode($blog->blog_content);
            $content = "";
            if(isset($jsonContent->$languare)){
                $content = $jsonContent->$languare;
            }
            $blogs[$key]->blog_content = $content;

            $jsonExcerpt = json_decode($blog->excerpt);
            $excerpt = "";
            if(isset($jsonExcerpt->$languare)){
                $excerpt = $jsonExcerpt->$languare;
            }
            $blogs[$key]->excerpt = $excerpt;
            $blogs[$key]->category_name = $this->getBlogCategory($blog->category_id);

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
