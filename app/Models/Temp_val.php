<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * I moved all Models into this folder to keep things clean, though its not part of laravel 7x
 * In this particular example, the data is cached via laravel cache facade (redis at the back). However its not always the case and other models dont have to be as such
*/

class Temp_val extends Model
{
    /**
     * @var bool $timestamps; default = false
     */
    public bool $timestamps = false;

    /**
     * @var array $guarded
     */
    protected array $guarded = [];

    /**
     * @var string $primaryKey; default = 'key_field'
     */
    protected string $primaryKey = 'key_field';

    /**
     * @var string $keyType; default = 'string'
     */
    protected string $keyType = 'string';

    /**
     * @var bool $incrementing; default = false
     */
    public bool $incrementing = false;

    /**
     * function clr_che
     */
    public static function clr_che()
    {
        Cache::forget('temp_vals');
    }

    /**
     * function get_recs
     * @param array $return_keys
     */
    public static function get_recs(array $return_keys = [])
    {
        //self::clr_che(); //debug

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

        if(empty($return_keys)) {
            return $records;
        }

        #/ Extract required keys
        return array_intersect_key($records, array_flip($return_keys));
    }
}
