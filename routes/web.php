<?php
use Illuminate\Support\Facades\Route;

$rg_array = [
    //custom identification as per our project's concept
    'section_id' => 6,
    'section_prefix'=>'lorem',
    'prefix' => 'lorem',
    'namespace' => 'lorem',

    //there a few more middlewares I use, they would be in app\Http\Kernel.php as they are common to all routes
    'middleware' => ['web'],

    //some custom poarams you can pass directly from the route
    'global_pg_title' => 'Lorem Ipsum Global Page Title',
    'global_icon' => 'lorem.png',
];

Route::group($rg_array, function() use($rg_array) {

    Route::match(['GET', 'POST'], 'helloWorld', ['as'=>"{$rg_array['section_prefix']}.helloWorld", 'uses'=>'helloWorld@index']); //list page
    Route::match(['GET', 'POST'], 'helloWorld/opr', ['as'=>"{$rg_array['section_prefix']}.helloWorld-opr", 'uses'=>'helloWorldOpr@index']); //operation page
});

/** common controllers, without namespace **/
Route::get('/', array_merge($rg_array, ['as'=>"{$rg_array['prefix']}.index", 'uses'=>'homeController@index']));
Route::get('home', array_merge($rg_array, ['as'=>"{$rg_array['prefix']}.home", 'uses'=>'homeController@index']));