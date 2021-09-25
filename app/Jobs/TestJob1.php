<?php
/** this is a test job to show what im doing in jobs as an example
 * This particular example pulls data from an api and stores (insert, update, or delete) into an SQL database
 * entry point = handle()
*/

namespace App\Jobs;

#/ Core
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#/ Helpers
use h1, h2; //these are defined in the config/app.php and so can be called directly
use App\Http\Helpers\{h3, h4}; //these are not defined in the config and needs to be called with full path

class TestJob1 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels; //there are Traits in php

    public $tries = 1; //The number of times the job may be attempted
    public $timeout = 3600; //1 hr  = The number of seconds the job can run before timing out

    private $job_msg = [];

    private $api_obj, $db_conn;

    public function __construct(){}

    //////------------------------------------------------------

    private function start_job()
    {
        #/ Step 1. Authenticate
        if($this->api_obj->auth()==false) {
            return ['Step1: Authenticate with API'=> '[r]Failure[/r]'];
        }

        #/ Step 2. Pull list of Lorem Ipsum
        $arr_1 = $this->api_obj->pull_list();
        if(empty($arr_1) || !is_array($arr_1)) {
            return ['Step2: Pull List from API'=> '[r]Failure[/r]'];
        }

        #/ Step 3. Loop each ipsum to pull data & setup payload to save
        $sv_ipsums = $sv_dolors = $del_ipsums = $ret_msgs = [];

        foreach($arr_1 as $ipsum_v)
        {
            if(empty($ipsum_v['ipsumId'])) continue;

            #/ Identify for deletion
            if($ipsum_v['Deactivated']=='1') {
                $del_ipsums[] = $ipsum_v['ipsumId'];
                continue;
            }

            if($ipsum_v['Suspended']=='1') continue; //some checks


            #/ setup ipsums payload
            $sv_ipsums[] = [
                'ipsum_id'=> $ipsum_v['ipsumId'],
                'ipsum_name'=> @h2::sanitize($ipsum_v['ipsumName']),
            ];

            //continue; //debug


            #/ paginated api pull of 2nd dimension list (and reauthenticate if needed)
            $page_n=0; $dolors_list=[]; $pull_more=false;
            do
            {
                $dolors_listx = $this->api_obj->pull_list($ipsum_v['ipsumId'], ++$page_n);

                if($dolors_listx==='token_epired')
                {
                    #/ re-auth and try again
                    if($this->api_obj->auth()==false)
                    {
                        $ret_msgs['Step 3.1: re-Authenticate'] = '[r]Failure[/r]';
                        break 2;
                    }
                    else
                    {
                        $dolors_listx = $this->api_obj->pull_list($ipsum_v['ipsumId'], $page_n);

                        if($dolors_listx==='token_epired') {
                            $ret_msgs['Step 3.2: re-Authenticate'] = '[r]Failure[/r]';
                            break 2;
                        }
                    }
                }

                if(isset($dolors_listx['error']))
                {
                    $ret_msgs[$ipsum_v['ipsumName']] = '[r]Error pulling dolors[/r][br]'.$dolors_listx['error'];
                    break;
                }

                if(!empty($dolors_listx[0]) && is_array($dolors_listx[0]))
                $dolors_list = array_merge($dolors_list, $dolors_listx[0]);
                else
                break;

                $pull_more = (bool)@$dolors_listx[1];

            } while(!empty($dolors_listx) || $pull_more);

            if(empty($dolors_list) || !is_array($dolors_list)) {
                continue;
            }


            #/ setup dolors payload
            $sv_dolors_i = [];
            foreach($dolors_list as $th)
            {
                if(empty($th['LastSeen'])) continue;

                $sv_dolors_i[] = [
                    'ipsum_id'=> $ipsum_v['ipsumId'],
                    'host_name'=> substr($th['HostName'], 0, 35),
                    'file_name'=> substr(h2::sanitize($th['FileName']), 0, 200), //names can have unwanted chars, so sanitizing the value
                    'user_name'=> @h2::sanitize($th['ExtendedInfo']['UserName']),
                    'last_seen'=> @date('Y-m-d H:i:s', strtotime($th['LastSeen'])),
                ];
            }

            if(empty($sv_dolors_i)) {
                $ret_msgs["{$ipsum_v['ipsumName']} / Setup dolor payload"] = '[r]Failure[/r]';
                continue;
            }

            $sv_dolors = array_merge($sv_dolors, $sv_dolors_i);

        }//end foreach ipsum...


        $db_obj = DB::connection($this->db_conn);

        #/ Save ipsums
        if(!empty($sv_ipsums))
        {
            $sv_ipsums_batches = array_chunk($sv_ipsums, 70); //chunking array to prevent sql's binding overload error
            foreach($sv_ipsums_batches as $v)
            {
                $db_obj->table('WAREHOUSE_ipsums_parent')
                ->insert($v);
            }

            #/ Save dolors
            if(!empty($sv_dolors))
            {
                $sv_dolors_batches = array_chunk($sv_dolors, 70); //chunking array to prevent sql's binding overload error
                foreach($sv_dolors_batches as $v)
                {
                    $db_obj->table('WAREHOUSE_dolors_children')
                    ->insert($v);
                }
            }
        }

        #/ Delete deactivated ipsums
        if(!empty($del_ipsums))
        foreach($del_ipsums as $v)
        {
            if(!empty($v))
            $db_obj->table('WAREHOUSE_ipsums_parent')->where('ipsum_id', $v)->delete();
        }


        #/ results message
        $ret_msgs["Outcome of the operation"] = "
        [br][b]Total ipsums processed[/b]: ".count($sv_ipsums)."
        [br][b]Total dolor Inserts[/b] attempted: ".count($sv_dolors)."
        [br][b]Total Deletes[/b] attempted (if found in the warehouse): ".count($del_ipsums);

        return $ret_msgs;
    }

    //////------------------------------------------------------

    public function handle()
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $this->api_obj = new h3();
        $this->db_conn = 'sqlsrv_lorem_1';

        #/ Run the process
        $results = [];
        $ret_msgs = $this->start_job();
        if(!empty($ret_msgs)) $results['Upload lorem data to ipsum task'] = $ret_msgs;

        #/ Email results
        h4::results_to_admin($results, 'Scheduled Job Results');

        #/ Report Notices to Admin (if any)
        if(!empty($this->job_msg))
        h4::report_back('Lorem Ipsum Job', implode("\n\n", $this->job_msg), 200);

        //sleep(1); //add a gap before next job starts (if needed)
        //exit; //debug - prevents queued job from finishing
    }

    public function failed(Exception $exception)
    {
        h4::debug_email(
            'Lorem Ipsum Error | Job Error @'.time(),
            @$exception->getMessage(),
            "Line: ".@$exception->getLine(),
            @$exception->getFile()
        );
    }
}