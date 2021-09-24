<?php
/** I moved all Models into a this folder to keep things clean, though its not part of laravel 7x
 * In this particular example, the data is cached via laravel cache facade (redis at the back). However its not always the case and other models dont have to be as such
*/

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Temp_val extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;


    public static function clr_che(){
    Cache::forget('temp_vals');
    }

    //////////////////////////////////---------------------------------


    public static function get_recs($return_keys=[])
    {
        //self::clr_che(); //manual flush for debug

        #/ Get all records
        $records = Cache::rememberForever('temp_vals', function()
        {
            $records = self::all();
            if($records && $records->count()>0)
            {
                $records = $records->toArray();
                $records = @f::format_str(@r::cb89($records, 'key'));
            }
            return $records;
        });
        //r::var_dumpx($records);

        if(empty($return_keys)) {
        return $records;
        }


        #/ Extract required keys
        return array_intersect_key($records, array_flip($return_keys));
    }
}
