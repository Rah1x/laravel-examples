<?php
/**
 * The controller for list/grid page that shows list of records.
 * The related file is helloWorldOpr.php.
 *
 * Operation in this file = read, search, sort, delete
 * entry point = index()
 */

namespace App\Http\Controllers\services;

#/ System
use Illuminate\Http\Request;

#/ Helpers
use h1, h2, h3, r, m, checkAttempts; //these are defined in the config/app.php and so can be called directly
use App\Http\Helpers\{c1, c2, c3}; //these are not defined in the config and needs to be called with full path

#/ Model
use App\Models\lorem_model_1;

#/ Abstract parent
use App\Http\Abstracts\adminGrid; //this abstract has the many properties and methods used in almost all grid pages, it also has common functions and partial methods so hence its not an intertface

class helloWorld extends adminGrid
{
    private $lorem_contacts = [];

    function __construct(Request $request)
    {
        parent::__construct($request);
        $this->grid_init();
    }

    protected function grid_init()
    {
        $this->view_ar['global_pg_title'] = 'Lorem Ipsum';
        $this->view_ar['global_icon'] = 'assets/images/loremIpsum.png';

        $this->grid_setup();
    }

    //////////////////////////////////////////////////////////////////////////////////////////

    protected function init_filters()
    {
        $this->search_it = (int)@$this->GET["search_it"];

        $this->sr_ = array_merge($this->sr_, [
        'generated_by'=> @array_diff(@explode('|', $this->GET["generated_by"]), ['']), //this field is a dropdown
        ]);

        $this->sr_ = array_merge($this->sr_, h1::make_filters($this->GET, [
        'certificate_number'=> 'opt_string',
        'start_ts'=> 'date',
        'co_name'=> 'opt_string',
        'generated_on'=> 'date',
        ]));

        $this->sr_ = array_merge($this->sr_, [
        'performed_by'=> @array_diff(@explode('|', $this->GET["performed_by"]), ['']), //this field is a dropdown
        ]);
    }


    protected function setup_sortOrder()
    {
        $this->setup_sortings([
        '6'=> 'generated_by',
        '1'=> 'certificate_number',
        '2'=> 'start_ts',
        '3'=> 'co_name',
        '4'=> 'performed_by',
        '5'=> 'generated_on',
        ]);
    }

    protected function setup_queryFilters()
    {
        $where = $having = "";

    	if($this->search_it)
    	{
            $src = new srchLib('binding');
            $get_where = $get_having = '';

            //if($this->is_superAdmin)
            if(!empty($this->sr_['generated_by']))
            {
                $get_where.="AND (";
                $ci = 0;
                foreach($this->sr_['generated_by'] as $cat_vv)
                {
                    $rtx = $src->where_it($cat_vv, 'generated_by', 'generated_by', 'equals', ($ci==0?'':'OR'));
                    $get_where.=$rtx[0]; $this->binding = array_merge($this->binding, $rtx[1]);
                    $ci++;
                }
                $get_where.=") \n\t";
            }


            if(!empty($this->sr_['certificate_number']))
            {
                if(empty($this->sr_['certificate_number_opt']))
                $this->sr_['certificate_number_opt'] = 1;

                $sr_dir = 'equals';
                if($this->sr_['certificate_number_opt']==2)
                $sr_dir = 'contains';
                $rtx = $src->where_it($this->sr_['certificate_number'], 'certificate_number', 'certificate_number', $sr_dir); //, 'AND', 'quoted'
                $get_where.=$rtx[0]; $this->binding = array_merge($this->binding, $rtx[1]);
            }


            if(!empty($this->sr_['start_ts_from'])){$rtx = $src->where_it($this->sr_['start_ts_from'].'T00:00:00', 'start_ts', '', 'greater-than-equals'); $get_where.=$rtx[0]; $this->binding = array_merge($this->binding, $rtx[1]);}
            if(!empty($this->sr_['start_ts_to'])){$rtx = $src->where_it($this->sr_['start_ts_to'].'T23:59:59', 'start_ts', '', 'less-than-equals'); $get_where.=$rtx[0]; $this->binding = array_merge($this->binding, $rtx[1]);}

            if(!empty($this->sr_['co_name']))
            {
                if(empty($this->sr_['co_name_opt']))
                $this->sr_['co_name_opt'] = 2;

                $sr_dir = 'equals';
                if($this->sr_['co_name_opt']==2)
                $sr_dir = 'contains';

                $rtx = $src->where_it($this->sr_['co_name'], 'co_name', 'co_name', $sr_dir);
                $get_where.=$rtx[0]; $this->binding = array_merge($this->binding, $rtx[1]);
            }

            if(!empty($this->sr_['performed_by']))
            {
                $get_where.="AND (";
                $ci = 0;
                foreach($this->sr_['performed_by'] as $cat_vv)
                {
                    $rtx = $src->where_it($cat_vv, 'performed_by', 'performed_by', 'equals', ($ci==0?'':'OR'));
                    $get_where.=$rtx[0]; $this->binding = array_merge($this->binding, $rtx[1]);
                    $ci++;
                }
                $get_where.=") \n\t";
            }

            if(!empty($this->sr_['generated_on_from'])){$rtx = $src->where_it($this->sr_['generated_on_from'].'T00:00:00', 'generated_on', '', 'greater-than-equals'); $get_where.=$rtx[0]; $this->binding = array_merge($this->binding, $rtx[1]);}
            if(!empty($this->sr_['generated_on_to'])){$rtx = $src->where_it($this->sr_['generated_on_to'].'T23:59:59', 'generated_on', '', 'less-than-equals'); $get_where.=$rtx[0]; $this->binding = array_merge($this->binding, $rtx[1]);}

            $where .= $get_where;
            $having.= $get_having;

           // $this->binding = array_merge($this->binding, $this->binding_having);
            $this->srch_obj = $src;
    	}

        $this->where = $where;
        $this->having = $having;
    }


    protected function form_query()
    {
        /** using direct sql here because ive got a lot of code coming in from filters and sortings that wont fit into ORM efficiently
         * but dont worry, I use bindings in the execution and also sanatize the values
        */

        #/ Main Query
        $query_m = sprintf("
        SELECT *
        FROM lorem_model_1s
        WHERE 1=1
        %s", $this->where);


        $query = $query_m.sprintf("
        ORDER BY %s %s
        ", $this->orderbyQuery, $this->orderdi);

        $query.= sprintf("LIMIT %d, %d", ($this->pageindex-1) * $this->pagesize, $this->pagesize);
        $this->query = $query;

        #/ Count Query
        $this->query_count = sprintf("
        SELECT count(*) AS C
        FROM lorem_model_1s
        WHERE 1=1
        %s", $this->where);
    }

    //////////////////////////////////////////////////////////////////////////////////////////

    protected function get_results($calculate_total=false, $custom_cache_key=false, $conType='mysql', $block_1=false)
    {
        /*
        some additional workout here
        */

        parent::get_results($calculate_total, "{$this->cur_page}{$this->param3c}", $conType); //defined in abstract

        /*
        some additional workout here
        */
    }

    private function delete_entries()
    {
        $POST = $this->POST;
        if(!isset($POST['command'])) {
        return false;
        }

        $rids = $POST['RecordID'];
        if(empty($rids)){
        return false;
        } else {
        $rids = @array_map(function($a){return ((int)$a);}, $rids);
        }


        ##/ Delete records
        $suc = false;
        $del_ids = $rids;

        if(!empty($del_ids))
        {
            if($this->is_superAdmin) //superadmin can delete everyone's entry
            {
                $rs = (int)@lorem_model_1::destroy($del_ids);
                $suc = ($rs==@count($del_ids));
            }
            else //regular user can delete their own entries only
            {
                $rs = (int)@lorem_model_1::where('generated_by', $this->loggedin_user_id)->whereIn('id', $del_ids)->delete();
                $suc = ($rs==@count($del_ids));
            }
        }
        #-


        #/ Return
        if($suc)
        {
            $msg = '<b>Success:</b> The selected record(s) were successfully deleted.';
        }
        else
        {
            $msg = 'Some of the selected record(s) were not successfully deleted!';
        }


        r::global_msg($this->S_PREFIX."MSG_GLOBAL", $msg, $suc);
        return true;
    }

    ///////////////////////////////////////////////////////////////// Main Controller

    public function index()
    {
        #/ Delete
        if(isset($this->POST['command']) && $this->POST['command']=='del')
        {
            if($this->delete_entries()==true){
            $this->clr_cache = 1;
            }
        }

        #/ initialize
        $ret_1 = $this->index_init(); //defined in the abstract
        $this->get_results();

        ///--------

        #/ Get some Get additonal related data from helpers (might be in the cache or in the database)
        h2::get_lorem_contacts($this->lorem_contacts, true); //active only due to load


        /** Generate header column <th>tags for the grid with orderby and order direction links */
        $th_ar = [
            ['label'=>'&nbsp;', 'width'=>'2'],
            ['label'=>'Generated By', 'width'=>'14', 'orderby'=>'6'],
            ['label'=>'Cert. Number', 'width'=>'15', 'orderby'=>'1'],
            ['label'=>'Start Time', 'width'=>'12', 'orderby'=>'2', 'orderdir'=>'DESC'],
            ['label'=>'Company', 'width'=>'23', 'orderby'=>'3', 'orderdir'=>'DESC'],
            ['label'=>'Performed By', 'width'=>'14', 'orderby'=>'4'],
            ['label'=>'Generated On', 'width'=>'12', 'orderby'=>'5', 'orderdir'=>'DESC'],
            ['label'=>'&nbsp;', 'width'=>'8'],
        ]; $THs = h1::generate_THs($this->cur_page, $this->orderby, $this->orderdi, $th_ar);


        /** Generate filters columns, this is special column to generate search fields right on top of the columns */
        $Tr_filters_ar = [
            ['type'=>'delete', 'field_id'=>''],
            ['type'=>'is_select_multiple', 'field_id'=>'generated_by', 'select_opts'=>$this->lorem_contacts, 'select_opt_value'=>'id', 'select_opt_display_name'=>'name', 'search_as_you_type'=>true],
            ['type'=>'equals_or_contains', 'field_id'=>'certificate_number'],
            ['type'=>'date_between', 'field_id'=>'start_ts'],
            ['type'=>'equals_or_contains', 'field_id'=>'co_name'],
            ['type'=>'is_select_multiple', 'field_id'=>'performed_by', 'select_opts'=>$this->lorem_contacts, 'select_opt_value'=>'id', 'select_opt_display_name'=>'name', 'search_as_you_type'=>true],
            ['type'=>'date_between', 'field_id'=>'generated_on'],
            ['type'=>'empty', 'field_id'=>''],
        ]; $Tr_filters = h1::generate_filters($this->sr_, $Tr_filters_ar);


        #/ pass vars to view
        $view_ar = array_merge($this->view_ar,
        $this->common_view_ar(['btr', 'multi_select']),
        array(
            'load_select_all' => true,
            'pg_title' => 'hello World List',

            'THs' => $THs,
            'Tr_filters' => $Tr_filters,
            'total_cols' => count($Tr_filters_ar),

            'dl_exdl' => false,

            'is_superAdmin' => $this->is_superAdmin,
            'lorem_contacts'=> $this->lorem_contacts,

            'add_btn' => 'Generate', //label to button
        ));

        return view($this->section_prefix.'.helloWorld', $view_ar);
    }
}