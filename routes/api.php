<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::post('gatewave', 'App\Http\Controllers\Api\GatewaveController@index')->middleware('CorsApi');

Route::group([  'middleware' => ['CorsApi','CheckStore']], function()
{
    Route::post('log', 'App\Http\Controllers\Api\StoreController@log');

    Route::get('products', 'App\Http\Controllers\Api\ProductController@index');
    Route::post('check_coupon', 'App\Http\Controllers\Api\ProductController@checkCoupon');
    Route::post('product/review', 'App\Http\Controllers\Api\ProductController@review');
    Route::get('brands', 'App\Http\Controllers\Api\BrandsController@index');
    Route::get('coupons', 'App\Http\Controllers\Api\CouponsController@index');
    Route::get('store', 'App\Http\Controllers\Api\StoreController@index');
    Route::get('blogs', 'App\Http\Controllers\Api\BlogController@index');
    Route::get('orders', 'App\Http\Controllers\Api\OrdersController@index');
    Route::get('campaigns', 'App\Http\Controllers\Api\FlashSaleController@index');
    Route::get('categories', 'App\Http\Controllers\Api\ProductController@getCategories');
    Route::post('order', 'App\Http\Controllers\Api\OrdersController@store');
    Route::put('update_payment_method', 'App\Http\Controllers\Api\StoreController@update_payment_method');
    Route::post('withdraw', 'App\Http\Controllers\Api\StoreController@withdraw');

    Route::get('country', 'App\Http\Controllers\Api\StoreController@country');
    Route::get('state', 'App\Http\Controllers\Api\StoreController@state');
    Route::get('get_payment_method', 'App\Http\Controllers\Api\StoreController@getPaymentMethod');
    Route::put('user', 'App\Http\Controllers\Api\StoreController@update');
    Route::get('user', 'App\Http\Controllers\Api\StoreController@info');
    Route::get('user_child', 'App\Http\Controllers\Api\StoreController@userChild');
    Route::get('history_withdraw', 'App\Http\Controllers\Api\StoreController@historyWithdraw');
    Route::get('ranks', 'App\Http\Controllers\Api\RanksController@index');
    Route::get('get_point_to_money', 'App\Http\Controllers\Api\RanksController@get_point_to_money');

    Route::post('storeImage', 'App\Http\Controllers\Api\StoreController@storeImage');

    Route::prefix('booking')->group(function () {
        Route::get('categories', 'App\Http\Controllers\Api\ProductController@getCategories');
        Route::get('banner', 'App\Http\Controllers\Api\StoreController@banner');
        Route::get('products', 'App\Http\Controllers\Api\ProductController@index');
    });

});

