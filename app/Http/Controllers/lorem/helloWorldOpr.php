<?php
/**
 * Operation (Opr) file that does the Add/Edit/Clone/Read operations of the CRUD. I usually do the Delete with the list page where they can be deleted directly from the grid.
 *
 * Modes of Operation in this file = Add, Edit, Clone, readOnly, PDF Certificate Generate, PDF Download, PDF Email
 * entry point = index method (as listed in the route file)
 */
namespace App\Http\Controllers\lorem;

#/ Core
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Validator;

#/ Helpers
use h1, h2, h3, r, m, checkAttempts;
use App\Http\Helpers\{c1, c2, c3};
use App\Http\Helpers\pdfHelper;
use dPDF; //using DOMPDF to generate pdf as well

#/ Mail
use Illuminate\Support\Facades\Mail;
use App\Mail\testMailObject;

#/ Models
use App\Models\{lorem_model_1, Er_component, m3};

#/ Abstract
use App\Http\Abstracts\OprAbstract; //this abstract has the many properties and methods used in almost all Opr pages

class helloWorldOpr extends OprAbstract
{
    private $pdf_view='', $pdf_sess_key='';
    private $lorem_fixed_dropdown=[], $lorem_contacts=[];

    function __construct(Request $request)
    {
        parent::__construct($request);
        $this->initialize();
    }

    /**
     * [Notes]:
     *
     * rec_id = if provided, we are in the 'EDIT' mode
     * cp_rec = id of the record to clone
     *
     * opr_init = defined in the abstract
     *
     */
    protected function initialize()
    {
        $this->view_ar['global_pg_title'] = 'Lorem Ipsum';
        $this->view_ar['global_icon'] = 'assets/images/loremIpsum.png';

        $ignore = array_flip(['rec_id', 'cp_rec', 'pdfk', 'gpdf']);
        $this->opr_init($ignore);

        $this->cp_rec = (int)@$this->GET['cp_rec'];


        #/ list of fixed collections to be used in the module
        $this->lorem_fixed_dropdown = [
        'component_types'=> [
            'flash' => 'Flash',
            'disk' => 'Disk',
            'USB' => 'USB',
            ],

        'status' => [
            '0' => 'Success',
            '1' => 'Failure',
            ],
        ];
    }

    //////////////////////////////////////////////////////////////////////////////////////////

    protected function get_record(int $rec_id, $enforece_ownership=false)
    {
        if($rec_id<=0) return false;

        if($enforece_ownership)
        $recRow_obj = lorem_model_1::where(['generated_by'=>$this->loggedin_user_id, 'id'=>$rec_id])->find($rec_id); //double checking ID on purpose
        else
        $recRow_obj = lorem_model_1::find($rec_id);


        if(!empty($recRow_obj) && $recRow_obj->count()>0)
        {
            $recRow = @f::format_str($recRow_obj->toArray());

            if(!empty($this->cp_rec)) {
            $recRow['generated_on'] = '';
            }

            #/ break date fields
            $recRow['start_ts_dt'] = @date('Y-m-d', strtotime($recRow['start_ts']));
            $recRow['start_ts_tm'] = @date('H:i', strtotime($recRow['start_ts']));

            $recRow['end_ts_dt'] = @date('Y-m-d', strtotime($recRow['end_ts']));
            $recRow['end_ts_tm'] = @date('H:i', strtotime($recRow['end_ts']));

            return $recRow;
        }
        else
        {
            $err = "Record Not Found!";
            if($enforece_ownership)
            $err.= ' / or Permission Denied ..';

            if($this->read_only==1){echo $err; exit;}
            r::global_msg($this->S_PREFIX."MSG_GLOBAL", $err);
            return false;
    	}
    }


    /**
     * [notes]
     *
     * checkAttempts = my own system to enforce max attempts per form for a random timeout (prevents brute force)
     * POST_ori vs POST = POST_ori is as it was posted, where as POST is sanatized in the main controller
     * 'Components' = this form has dynamic set of mini forms within called Components (1:M relationship in one page basically)
     */
    private function process_form()
    {
        #/ Check Attempts
        if(checkAttempts::check_attempts(6, $this->S_PREFIX.'MSG_GLOBAL')==false){
            checkAttempts::update_attempt_counts();
            return 1;
        }


        #/ Set rec_id
        $rec_id = $this->rec_id;
        if($this->cp_rec>0)
        $rec_id = 0;

        $POST_ori = $this->POST_ori;
        $POST = $this->POST;
        $succ_msgs = [];


        #/ set date fields to compare
        $POST['start_ts'] = $POST_ori['start_ts'] = @date('Y-m-d H:i:s', strtotime($POST_ori['start_ts_dt'].' '.$POST_ori['start_ts_tm']));
        $POST['end_ts'] = $POST_ori['end_ts'] = @date('Y-m-d H:i:s', strtotime($POST_ori['end_ts_dt'].' '.$POST_ori['end_ts_tm']));


        ##/ Validate Fields
        $req_flds = [
        'lorem_ticket_id' => 'integer',
        'ipsum_serial_no' => 'max:50',
        'performed_by' => 'required',
        'witnessed_by' => 'required',

        'start_ts_dt' => 'required|date_format:"Y-m-d"',
        'start_ts_tm' => ['required', 'regex:/^[0-2]{0,1}[0-9]{1}\:[0-9]{1,2}$/'], //date_format:"G:i"

        'end_ts_dt' => 'required|date_format:"Y-m-d"',
        'end_ts_tm' => 'required|regex:/^[0-2]{0,1}[0-9]{1}\:[0-9]{1,2}$/', //date_format:"G:i"

        'end_ts' => 'date|date_format:Y-m-d H:i:s|after:start_ts',
        ];


        $validation_attribs = [
        'lorem_ticket_id'=> 'Ticket',
        'performed_by'=>'Performed By',
        'witnessed_by'=>'Witnessed By',

        'start_ts_dt'=>'Timestamp / Start Date',
        'start_ts_tm'=>'Timestamp / Start Time',
        'end_ts_dt' => 'Timestamp / End Date',
        'end_ts_tm' => 'Timestamp / End Time',
        'start_ts' => 'Timestamp / Start',
        'end_ts' => 'Timestamp / End',
        ];


        #/ Components
        if(isset($POST['component_index']) && is_array($POST['component_index']))
        foreach($POST['component_index'] as $pk=>$pv)
        {
            $req_flds = array_merge($req_flds, [
            "component_type.{$pk}" => 'required',
            "component_model.{$pk}" => 'required|max:150',
            "component_serial.{$pk}" => 'required|max:50',
            "component_asset_tag.{$pk}" => 'max:30',
            "component_size.{$pk}" => 'required|numeric|min:0',
            "component_er_level.{$pk}" => 'required',
            "component_status.{$pk}" => 'required',
            ]);

            $validation_attribs = array_merge($validation_attribs, [
            "component_type.{$pk}" => 'Component '.($pk+1).' / Type',
            "component_model.{$pk}" => 'Component '.($pk+1).' / Model',
            "component_serial.{$pk}" => 'Component '.($pk+1).' / Serial',
            "component_asset_tag.{$pk}" => 'Component '.($pk+1).' / Asset Tag',
            "component_size.{$pk}" => 'Component '.($pk+1).' / Size',
            "component_er_level.{$pk}" => 'Component '.($pk+1).' / Er Level',
            "component_status.{$pk}" => 'Component '.($pk+1).' / Status',
            ]);
        }


        $validator = Validator::make($POST_ori, $req_flds)->setAttributeNames($validation_attribs);

        if($validator->errors()->count()>0)
        {
            r::global_msg($this->S_PREFIX."MSG_GLOBAL", $validator->errors()->messages());
            checkAttempts::update_attempt_counts();
            return $validator->errors()->count();
        }
        #-



        ##/ Check if Ticket Exists
        $lorem_ticket_id = (int)@$POST['lorem_ticket_id'];
        $POST['old_ticket'] = (int)@$POST['old_ticket'];
        $tkt_co = '';

        if($lorem_ticket_id>0)
        {
            #/ Check if Ticket Exists
            $tkt = h2::check_ticket_exists($lorem_ticket_id);
            if($tkt!=true)
            {
                $err = ['A' => ["Unable to locate the <strong>Ticket</strong> in the Database!"]];
                r::global_msg($this->S_PREFIX."MSG_GLOBAL", $err);
                checkAttempts::update_attempt_counts();
                return 1;
            }

            #/ check & get Ticket Company - only in ADD/CLONE mode; or in EDIT if ticket value has changed;
            if($rec_id<=0 || $lorem_ticket_id!=$POST['old_ticket'])
            {
                $tkt_co = @h2::get_ticket_co($lorem_ticket_id);
                if(empty($tkt_co))
                {
                    $err = ['A' => ["Unable to locate the <strong>Ticket's Company</strong> in the Database!"]];
                    r::global_msg($this->S_PREFIX."MSG_GLOBAL", $err);
                    checkAttempts::update_attempt_counts();
                    return 1;
                }
            }
        }
        #-


        ###/ Save in DB

        #/ Setup payload
        $sv_ar = [
        'lorem_ticket_id'=> $lorem_ticket_id,
        'start_ts'=> $POST['start_ts'],
        'end_ts'=> $POST['end_ts'],
        'ipsum_serial_no'=> @$POST['ipsum_serial_no'],
        'performed_by' => @$POST['performed_by'],
        'witnessed_by' => @$POST['witnessed_by'],
        ];

        #/ add Ticket's Company - only in ADD/CLONE mode - or in EDIT if ticket value is changed
        if(($rec_id<=0 || $lorem_ticket_id!=$POST['old_ticket'])){
        $sv_ar = array_merge($sv_ar, ['co_name'=> $tkt_co]);
        }


        #/ Components
        $sv_ar_component = [];
        if(isset($POST['component_index']) && is_array($POST['component_index']))
        foreach($POST['component_index'] as $pk=>$pv)
        {
            $sv_t = [
            'type'=> @$POST['component_type'][$pk],
            'model'=> @$POST['component_model'][$pk],
            'asset_tag'=> @$POST['component_asset_tag'][$pk],
            'serial'=> @$POST['component_serial'][$pk],
            'size'=> (float)@$POST['component_size'][$pk].'#'.@$POST['component_sizeUnit'][$pk],
            'erasure_level'=> @$POST['component_er_level'][$pk],
            'status'=> @$POST['component_status'][$pk],
            ];

            if($rec_id>0)
            $sv_ar_component[$pv] = $sv_t;
            else
            $sv_ar_component[] = new Er_component($sv_t);
        }


        #/ Save
        $rec = [];
        $res_sv = false;
        $mode_opp = '';

        if($rec_id>0)  //Edit Mode
        {
            $mode_opp = 'edit';
            $rec = lorem_model_1::where('id', $rec_id);

            $rec_data = [];
            if($rec!=false && $rec->count()>0)
            {
                $rec->update($sv_ar);

                #/ Components
                if(!empty($sv_ar_component))
                Er_component::delete_update_insert($rec_id, $sv_ar_component);

                $res_sv = true;

                #/ fill generated on for pdf / email
                if(@$POST['generate_option']=='generate_pdf')
                {
                    $rec_data = @$rec->first();

                    $POST['generated_on'] = @$rec_data->generated_on;
                    $POST['certificate_number'] = @$rec_data->certificate_number;
                    $tkt_co = @$rec_data->co_name;
                }
            }
        }
        else //Add & Clone
        {
            $mode_opp = 'add';

            $sv_ar = array_merge($sv_ar, [
            'generated_by' => $this->loggedin_user_id, //dont want to alter the `generated_by` in EDIT mode, hence its only set in Add mode
            'generated_on' => date('Y-m-d H:i:s'),
            ]);

            $rec = lorem_model_1::create($sv_ar);
            $rec_id = (int)@$rec->id;

            if($rec_id>0)
            {
                #/ Components
                if(!empty($sv_ar_component))
                $rec->Er_component()->saveMany($sv_ar_component);

                $res_sv = true;

                #/ Add certificate number
                $POST['certificate_number'] = 'T-ERC-'.str_pad($rec_id, 10, '0', STR_PAD_LEFT);
                $rec->update(['certificate_number'=>$POST['certificate_number']]);

                $POST['generated_on'] = $sv_ar['generated_on'];
            }
        }



        if($res_sv == false) {
        r::global_msg($this->S_PREFIX."MSG_GLOBAL", 'Error saving data! Please contact the development team!');
        checkAttempts::update_attempt_counts();
        return 1;
        } else {
        $succ_msgs[] = "The records were successfully saved.";
        }
        #-


        #/ Delete cache
        if($rec_id>0) {
        @Redis::del($this->list_cache_key);
        $this->param2_ar['rec_id'] = $rec_id;
        }

        $this->rec_id = $rec_id;


        ###/ Setup PDF
        if(@$POST['generate_option']=='generate_pdf' || (@$POST['generate_option']=='email_now' && $lorem_ticket_id>0))
        {
            $POST['generated_by'] = $this->loggedin_user_id;
            $POST['co_name'] = $tkt_co;

            #/ Get components
            h2::get_lorem_contacts($this->lorem_contacts, ($mode_opp=='add'));


            #/ setup PDF
            $pdf_res = pdfHelper::set_pdf_dec($rec_id, $POST, $this->lorem_contacts, $this->lorem_fixed_dropdown);

            $pdf = @$pdf_res['pdf'];
            $this->pdf_filename = $file_name = @$pdf_res['fn'];
            $file_name_part = @$pdf_res['fnp'];


            #/ send via Email as attachment
            if(@$POST['generate_option']=='email_now' && $lorem_ticket_id>0)
            {
                #/ setup body
                $body_in = "
                <b>==Lorem Ipsum Form==</b>
                <br /><br />
                Cert. Generated By: <b>{$this->loggedin_user_id}</b><br />
                Cert. Generated On: <b>".r::pretty_date($POST['generated_on'])."</b><br /><br />
                Performed By: <b>{$POST['performed_by']}</b><br />
                Started: <b>".r::pretty_date($POST['start_ts'])."</b><br />
                Total Components: <b>".@count($POST['component_index'])."</b><br />
                <br />
                <i>via Lorem Ipsum Form</i>";

                #/ Setup email
                $mail_obj = new testMailObject();
                $mail_obj->send_mail("Subject {$lorem_ticket_id}", "", $body_in, [
                'text_only'=> true,
                'attachment_base64'=> true,
                'frm_nm'=> @$_ENV['MAIL_FROM_name'],
                'frm_email'=> @$_ENV['MAIL_FROM'],
                ], [[
                'data'=> @base64_encode($pdf->stream()),
                'name'=> $file_name,
                'mime'=> 'application/pdf'
                ]]);

                #/ Send email
                $mail_obj->hide_me();
                @Mail::to('lorem@ipsum.com')->send($mail_obj);
                $mail_obj->unhide_me();

                #/Check success
                if(count(Mail::failures())>0)
                {
                    $succ_msgs[] = '<span class="reset_cache_txt">NOTE: Unable to email Lorem ipsum dolor sit amet!</span>';
                }
                else
                {
                    $succ_msgs[] = 'The CR was successfully uploaded to the Lorem ipsum dolor sit amet.';
                }
            }
            else if(@$POST['generate_option']=='generate_pdf') //#/ Save in Session, its in `else` because cannot call $pdf->stream() twice and also cant save it in a var (vendor's issue)
            {
                $this->pdf_sess_key = $file_name_part.'_TSOXD'.sha1(microtime());
                r::sess_save($this->pdf_sess_key, $pdf->stream());
            }
        }

        #/ return
        if(!empty($succ_msgs))
        r::global_msg($this->S_PREFIX."MSG_GLOBAL", implode('<br />', $succ_msgs), true);

        checkAttempts::reset_attempt_counts();
        return 0;

    }


    private function get_pdf(int $rec_id)
    {
        if($rec_id<=0) return false;

        #/ Get records
        $recRow = $this->get_record($rec_id);
        if(empty($recRow) || empty(@$recRow['performed_by'])) {
        return false;
        }

        #/ Get components
        h2::get_lorem_contacts($this->lorem_contacts);

        #/ Setup pdf
        $pdf_res = pdfHelper::set_pdf_dec($rec_id, $recRow, $this->lorem_contacts, $this->lorem_fixed_dropdown);
        $pdf = @$pdf_res['pdf'];
        $file_name = @$pdf_res['fn'];
        if(empty($pdf) || empty($file_name)){
        return false;
        }

        #/ Output pdf
        $pdf_view = @$pdf->stream($file_name);
        if(empty($pdf_view)){
        return false;
        }

        pdfHelper::download_pdf($file_name, $pdf_view);
        return true;
    }

    ///////////////////////////////////////////////////////////////// Main Controller

    /**
     * Notes:
     *
     * read_only = defined, captured and set in the abstract file. Sets the mode of operation to readonly
     * global_msg = used to set global message displayed in the header to the user
     * map_refill_fields = refills form data to what was posted by the user. Basically, if there is an error the form is to be refilled to the user doesnt have to refill all of it again, and if its all good then its 'Post-RedireT-Get'
     */
    public function index()
    {
        #/ special direct pdf (from list page)
        if(!empty($this->GET['gpdf']) && $this->read_only && $this->rec_id>0)
        {
            if($this->get_pdf($this->rec_id)==false) {
            r::global_msg($this->S_PREFIX."MSG_GLOBAL", 'Unable to download the PDF! Please check the Ticket, or contact the development team.');
            return redirect()->route($this->back_route, array_diff_key($this->param2_ar, array_flip(array('ro'))));
            exit;
            }
        }

        #/ pdf download (from this page)
        if(!empty($this->GET['pdfk']) && !$this->read_only)
        {
            if(pdfHelper::output_pdf($this->GET['pdfk'])==false)
            {
                r::global_msg($this->S_PREFIX."MSG_GLOBAL", 'Unable to download the PDF! Please check the Ticket, or contact the development team.');
                return redirect()->route($this->cur_route, array_diff_key($this->GET, array_flip(array('pdfk'))));
                exit;
            }
        }


        #/ Get records for CLONE mode
        if($this->cp_rec>0 && !$this->read_only)
        {
            $this->recRow = $this->get_record($this->cp_rec);
            if($this->recRow==false) {
            return redirect()->route($this->back_route, array_diff_key($this->GET, array_flip(array('cp_rec'))));
            exit;
            }
        }

        #/ Get records for EDIT & ReadOnly modes
        if($this->rec_id>0)
        {
            $this->recRow = $this->get_record($this->rec_id);
            if($this->recRow==false) {
            return redirect()->route($this->back_route, array_diff_key($this->GET, array_flip(array('rec_id'))));
            exit;
            }

            #/ Only allow owners or superadmin to edit
            if(!$this->read_only && !$this->is_superAdmin && $this->recRow['generated_by']!=$this->loggedin_user_id)
            {
                r::global_msg($this->S_PREFIX."MSG_GLOBAL", "Permission Denied! You can only Edit the report you generated yourself!");
                return redirect()->route($this->back_route, array_diff_key($this->GET, array_flip(array('rec_id'))));
                exit;
            }
        }

        #/ Handle Form Postings
        if($this->read_only<=0)
        {
            if(!empty($this->POST) && isset($this->POST['performed_by']))
            {
                $tot_ers = (int)@$this->process_form();
                if($tot_ers==0)
                {
                    if(!empty($this->pdf_sess_key))
                    r::sess_save('pdf_key_'.$this->rec_id, $this->pdf_sess_key); //saving pdf data in the cache to get it later

                    return redirect()->route($this->cur_route, array_diff_key($this->param2_ar, array_flip(array('cp_rec'))));
                }
            }
            else
            {
                #/ get pdf key from sess
                $pdf_key = r::sess('pdf_key_'.$this->rec_id);

                if(!empty($pdf_key))
                {
                    r::flush_sess('pdf_key_'.$this->rec_id);

                    $pdf_sess_data = @r::sess($pdf_key);
                    if(!empty($pdf_sess_data)){
                    $this->pdf_sess_key = $pdf_key;
                    }
                }
            }
        }


        #/ Refill Fields
        if(isset($this->POST['performed_by'])) {
        $this->map_refill_fields();
        }


        #/ Get components
        h2::get_lorem_contacts($this->lorem_contacts, true);


        ##/ pass vars to view
        $pg_title = $this->read_only>0? "Lorem Ipsum Form &raquo; ":"Lorem Ipsum Form &raquo; ";

        if($this->read_only>0){ $pg_title.= 'Review '; }else{
        $pg_title.= $this->rec_id>0? "Edit ": ($this->cp_rec>0? "Clone ":"New ");
        }

        $view_ar = array_merge($this->view_ar, array(
        'pg_title' => $pg_title,
        'read_only' => $this->read_only,
        'back_page' => empty($this->back_route)?'':route($this->back_route),
        'param2' => $this->param2,
        'rec_id' => $this->rec_id,
        'cp_rec' => $this->cp_rec,
        'recRow' => $this->recRow,
        'lorem_contacts' => $this->lorem_contacts,
        'lorem_fixed_dropdown' => $this->lorem_fixed_dropdown,
        ));

        if($this->read_only==1){
        $view_ar = array_merge($view_ar, array(
        'no_header' => true,
        ));

        }else{
        $view_ar = array_merge($view_ar, array(
        'load_jquery_ui' => true,
        'pdfk'=> $this->pdf_sess_key,
        ));
        }
        #-

        return view('lorem.helloWorldOpr', $view_ar);

    }
}
