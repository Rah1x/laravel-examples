<?php
use Illuminate\Support\Facades\Route;

/** Lorem Ipsum section **/
$rg_array = [
'section_id' => 6,
'section_prefix'=>'lorem',

'prefix' => 'lorem',
'namespace' => 'lorem',
'middleware' => ['web'],

'global_pg_title' => 'Lorem Ipsum Global Title',
'global_icon' => 'lorem.png',
];

Route::group($rg_array, function() use($rg_array) {

    Route::match(['GET', 'POST'], 'helloWorld-cert', ['as'=>"{$rg_array['section_prefix']}.helloWorld-cert", 'uses'=>'helloWorlds@index']); //list page
    Route::match(['GET', 'POST'], 'helloWorld-cert/opr', ['as'=>"{$rg_array['section_prefix']}.helloWorld-cert-opr", 'uses'=>'helloWorldOpr@index']); //operation page
});

/** common controller file, without namespace **/
Route::get('/', array_merge($rg_array, ['as'=>"{$rg_array['prefix']}.index", 'uses'=>'homeController@index']));
Route::get('home', array_merge($rg_array, ['as'=>"{$rg_array['prefix']}.home", 'uses'=>'homeController@index']));