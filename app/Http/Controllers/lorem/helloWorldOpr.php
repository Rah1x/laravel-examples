<?php
namespace App\Http\Controllers\lorem;

#/ Core
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Validator;

#/ Helpers
use h1, h2, h3, r, m, checkAttempts; //these are defined in the config/app.php and so can be called directly
use App\Http\Helpers\{c1, c2, c3}; //these are not defined in the config and needs to be called with full path
use App\Http\Helpers\pdfHelper; //or you can call them one by one
use dPDF; //using DOMPDF to generate pdf as well

#/ Mail
use Illuminate\Support\Facades\Mail;
use App\Mail\testMailClass;

#/ Models
use App\Models\{lorem_model_1, Er_component, m3};

#/ Abstract
use App\Http\Abstracts\OprAbstract; //this abstract has the many properties and methods used in almost all Opr pages, it also has common functions and partial methods so hence its not an intertface

/**
 * Operation (Opr) file that does the Add/Edit/Clone/Read operations of the CRUD.
 * I usually do the Delete with the list page where they can be deleted directly from the grid.
 * * The related file is helloWorld.php.
 *
 * Operation in this file = Add, Edit, Clone, readOnly, PDF Certificate Generate, PDF Download, PDF Email
 * entry point = index(), start reading from there. Also note the initialize and construct are called before the entry point
 */

class helloWorldOpr extends OprAbstract
{
    /**
     * @var string $pdf_view
     */
    private $pdf_view = '';

    /**
     * @var string $pdf_sess_key
     */
    private $pdf_sess_key = '';

    /**
     * @var array $lorem_fixed_dropdown
     */
    private $lorem_fixed_dropdown = [];

    /**
     * @var array $lorem_contacts
     */
    private $lorem_contacts = [];

    /**
     * @param Request $request
     */
    function __construct(Request $request)
    {
        //parent::__construct($request);
        $this->initialize();
    }

    /**
     * [What is that]:
     *
     * rec_id = if provided, we are in the 'EDIT' mode, otherwise we are in ADD mode
     * cp_rec = id of the record to clone
     *
     * opr_init() = defined in the abstract
     */
    protected function initialize()
    {
        $this->view_ar['global_pg_title'] = 'Lorem Ipsum';
        $this->view_ar['global_icon'] = 'assets/images/loremIpsum.png';

        $ignore = array_flip(['rec_id', 'cp_rec', 'pdfk', 'gpdf']);
        $this->opr_init($ignore);

        $this->cp_rec = (int)@$this->GET['cp_rec'];

        #/ example list of fixed collections to be used in the module
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

    /**
     * function get_record
     * get a particular record from the db
     *
     * @param int $rec_id
     * @param bool $enforece_ownership; default false
     */
    protected function get_record(int $rec_id, bool $enforece_ownership = false)
    {
        if ($rec_id<=0) {
            return false;
        }

        if ($enforece_ownership) {
            $recRow_obj = lorem_model_1::where(['generated_by'=>$this->loggedin_user_id, 'id'=>$rec_id])->find($rec_id); //double checking ID on purpose
        } else {
            $recRow_obj = lorem_model_1::find($rec_id);
        }

        if (!empty($recRow_obj) && $recRow_obj->count()>0) {
            $recRow = @f::format_str($recRow_obj->toArray());

            if (!empty($this->cp_rec)) {
                $recRow['generated_on'] = '';
            }

            #/ break datetime field into Date and Time fields
            $recRow['start_ts_dt'] = @date('Y-m-d', strtotime($recRow['start_ts']));
            $recRow['start_ts_tm'] = @date('H:i', strtotime($recRow['start_ts']));

            $recRow['end_ts_dt'] = @date('Y-m-d', strtotime($recRow['end_ts']));
            $recRow['end_ts_tm'] = @date('H:i', strtotime($recRow['end_ts']));

            return $recRow;

        } else {
            $err = "Record Not Found!";
            if ($enforece_ownership) {
                $err.= ' / or Permission Denied ..';
            }

            if ($this->read_only==1)
            {
                echo $err; exit;
            }

            r::global_msg($this->S_PREFIX."MSG_GLOBAL", $err);
            return false;
    	}
    }

    /**
     * [What is that]:
     *
     * checkAttempts() = my own helper code I use to enforce max attempts per form for a random timeout (this prevents brute force)
     * POST_ori vs POST = POST_ori is as it was posted, where as POST is sanatized (all done in the parent controller)
     * 'Components' = this form has dynamic set of mini forms within called Components (1:M relationship in one page basically)
     */
    private function process_form()
    {
        #/ Check Attempts
        if (checkAttempts::check_attempts(10)==false) { //10 attempts
            checkAttempts::update_attempt_counts();
            return 1;
        }

        #/ Set rec_id
        $rec_id = $this->rec_id;
        if ($this->cp_rec>0) {
            $rec_id = 0;
        }

        $POST_ori = $this->POST_ori; //just so ive to type less words!
        $POST = $this->POST;
        $succ_msgs = [];

        #/ set datetime fields from posted date and time fields
        $POST['start_ts'] = $POST_ori['start_ts'] = @date('Y-m-d H:i:s', strtotime($POST_ori['start_ts_dt'].' '.$POST_ori['start_ts_tm']));
        $POST['end_ts'] = $POST_ori['end_ts'] = @date('Y-m-d H:i:s', strtotime($POST_ori['end_ts_dt'].' '.$POST_ori['end_ts_tm']));

        ##/ Validate Fields
        $req_flds = [
            'lorem_ticket_id' => 'integer',
            'ipsum_serial_no' => 'max:50',
            'performed_by' => 'required',

            'start_ts_dt' => 'required|date_format:"Y-m-d"',
            'start_ts_tm' => ['required', 'regex:/^[0-2]{0,1}[0-9]{1}\:[0-9]{1,2}$/'], //date_format:"G:i" is not enough so i had to use regex

            'end_ts_dt' => 'required|date_format:"Y-m-d"',
            'end_ts_tm' => 'required|regex:/^[0-2]{0,1}[0-9]{1}\:[0-9]{1,2}$/',

            'end_ts' => 'date|date_format:Y-m-d H:i:s|after:start_ts',
        ];

        $validation_field_labels = [
            'lorem_ticket_id'=> 'Ticket',
            'performed_by'=>'Performed By',
            'start_ts_dt'=>'Timestamp / Start Date',
            'start_ts_tm'=>'Timestamp / Start Time',
            'end_ts_dt' => 'Timestamp / End Date',
            'end_ts_tm' => 'Timestamp / End Time',
            'start_ts' => 'Timestamp / Start',
            'end_ts' => 'Timestamp / End',
        ];

        #/ Components
        if (isset($POST['component_index']) && is_array($POST['component_index'])) {
            foreach($POST['component_index'] as $pk=>$pv) {
                $req_flds = array_merge($req_flds, [
                    "component_type.{$pk}" => 'required',
                    "component_model.{$pk}" => 'required|max:150',
                    "component_serial.{$pk}" => 'required|max:50',
                    "component_asset_tag.{$pk}" => 'max:30',
                    "component_size.{$pk}" => 'required|numeric|min:0',
                    "component_er_level.{$pk}" => 'required',
                    "component_status.{$pk}" => 'required',
                ]);

                $validation_field_labels = array_merge($validation_field_labels, [
                    "component_type.{$pk}" => 'Component '.($pk+1).' / Type',
                    "component_model.{$pk}" => 'Component '.($pk+1).' / Model',
                    "component_serial.{$pk}" => 'Component '.($pk+1).' / Serial',
                    "component_asset_tag.{$pk}" => 'Component '.($pk+1).' / Asset Tag',
                    "component_size.{$pk}" => 'Component '.($pk+1).' / Size',
                    "component_er_level.{$pk}" => 'Component '.($pk+1).' / Er Level',
                    "component_status.{$pk}" => 'Component '.($pk+1).' / Status',
                ]);
            }
        }

        $validation_res = Validator::make($POST_ori, $req_flds)->setAttributeNames($validation_field_labels);

        if ($validation_res->errors()->count()>0) {
            r::global_msg($this->S_PREFIX."MSG_GLOBAL", $validation_res->errors()->messages());
            checkAttempts::update_attempt_counts();
            return $validation_res->errors()->count();
        }
        #-

        ##/ Check if Ticket Exists
        $lorem_ticket_id = (int)@$POST['lorem_ticket_id'];
        $POST['old_ticket'] = (int)@$POST['old_ticket']; //if the ticket is changed to a new value in EDIT mode
        $tkt_co = '';

        if ($lorem_ticket_id>0) {
            #/ Check if Ticket Exists
            $tkt = h2::check_ticket_exists($lorem_ticket_id);
            if ($tkt!=true) {
                $err = ['A' => ["Unable to locate the <strong>Ticket</strong> in the Database!"]];
                r::global_msg($this->S_PREFIX."MSG_GLOBAL", $err);
                checkAttempts::update_attempt_counts();
                return 1;
            }

            #/ check & get Ticket Company - only in ADD/CLONE mode; or in EDIT if ticket value has changed;
            if ($rec_id<=0 || $lorem_ticket_id!=$POST['old_ticket']) {
                $tkt_co = @h2::get_ticket_co($lorem_ticket_id);
                if (empty($tkt_co)) {
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
        ];

        #/ add Ticket's Company - only in ADD/CLONE mode - or in EDIT if ticket value is changed
        if ($rec_id<=0 || $lorem_ticket_id!=$POST['old_ticket']) {
            $sv_ar = array_merge($sv_ar, ['co_name'=> $tkt_co]);
        }

        #/ Components
        $sv_ar_component = [];
        if (isset($POST['component_index']) && is_array($POST['component_index'])) {
            foreach($POST['component_index'] as $pk=>$pv) {
                $sv_t = [
                    'type'=> @$POST['component_type'][$pk],
                    'model'=> @$POST['component_model'][$pk],
                    'asset_tag'=> @$POST['component_asset_tag'][$pk],
                    'serial'=> @$POST['component_serial'][$pk],
                    'size'=> (float)@$POST['component_size'][$pk].'#'.@$POST['component_sizeUnit'][$pk],
                    'erasure_level'=> @$POST['component_er_level'][$pk],
                    'status'=> @$POST['component_status'][$pk],
                ];

                if ($rec_id>0) {
                    $sv_ar_component[$pv] = $sv_t;
                } else {
                    $sv_ar_component[] = new Er_component($sv_t);
                }
            }
        }

        #/ Save
        $rec = [];
        $res_sv = false;
        $mode_opp = '';

        if ($rec_id>0) {  //Edit Mode

            $mode_opp = 'edit';
            $rec = lorem_model_1::where('id', $rec_id);

            $rec_data = [];
            if ($rec!=false && $rec->count()>0) {
                $rec->update($sv_ar);

                #/ Components
                if (!empty($sv_ar_component)) {
                    Er_component::delete_update_insert($rec_id, $sv_ar_component);
                }

                $res_sv = true;

                #/ fill generated on for pdf / email
                if (@$POST['generate_option']=='generate_pdf') {
                    $rec_data = @$rec->first();

                    $POST['generated_on'] = @$rec_data->generated_on;
                    $POST['file_number'] = @$rec_data->file_number;
                    $tkt_co = @$rec_data->co_name;
                }
            }

        } else { //Add & Clone

            $mode_opp = 'add';

            $sv_ar = array_merge($sv_ar, [
                'generated_by' => $this->loggedin_user_id, //dont want to alter the `generated_by` in EDIT mode, hence its only set in Add mode
                'generated_on' => date('Y-m-d H:i:s'),
            ]);

            $rec = lorem_model_1::create($sv_ar);
            $rec_id = (int)@$rec->id;

            if ($rec_id>0) {
                #/ Components
                if (!empty($sv_ar_component)) {
                    $rec->Er_component()->saveMany($sv_ar_component);
                }

                $res_sv = true;

                #/ Add certificate number
                $POST['file_number'] = 'F-'.str_pad($rec_id, 10, '0', STR_PAD_LEFT);
                $rec->update(['file_number'=>$POST['file_number']]);

                $POST['generated_on'] = $sv_ar['generated_on'];
            }
        }

        if ($res_sv == false) {
            r::global_msg($this->S_PREFIX."MSG_GLOBAL", 'Error saving data! Please contact the development team!');
            checkAttempts::update_attempt_counts();
            return 1;
        } else {
            $succ_msgs[] = "The records were successfully saved.";
        }
        #-

        #/ Delete cache of the list page this individual record is part of
        if ($rec_id>0) {
            @Redis::del($this->list_cache_key);
            $this->param2_ar['rec_id'] = $rec_id;
        }

        $this->rec_id = $rec_id;

        ###/ Setup PDF
        if (@$POST['generate_option']=='generate_pdf' || (@$POST['generate_option']=='email_now' && $lorem_ticket_id>0)) {
            $POST['generated_by'] = $this->loggedin_user_id;
            $POST['co_name'] = $tkt_co;

            #/ Get additonal related data
            h2::get_lorem_contacts($this->lorem_contacts, ($mode_opp=='add'));

            #/ setup PDF
            $pdf_res = pdfHelper::set_pdf_dec($rec_id, $POST, $this->lorem_contacts, $this->lorem_fixed_dropdown);

            $pdf = @$pdf_res['pdf'];
            $this->pdf_filename = $file_name = @$pdf_res['fn'];
            $file_name_part = @$pdf_res['fnp'];

            #/ send via Email as attachment
            if (@$POST['generate_option']=='email_now' && $lorem_ticket_id>0) {

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
                if (count(Mail::failures())>0) {
                    $succ_msgs[] = '<span class="reset_cache_txt">NOTE: Unable to email Lorem ipsum dolor sit amet!</span>';
                } else {
                    $succ_msgs[] = 'The CR was successfully uploaded to the Lorem ipsum dolor sit amet.';
                }

            } else if (@$POST['generate_option']=='generate_pdf') { //#/ Save in Session, its in `else` because cannot call $pdf->stream() twice and also cant save it in a var (vendor's issue)
                $this->pdf_sess_key = $file_name_part.'_TSOXD'.sha1(microtime());
                r::sess_save($this->pdf_sess_key, $pdf->stream());
            }
        }

        #/ return
        if (!empty($succ_msgs)) {
            r::global_msg($this->S_PREFIX."MSG_GLOBAL", implode('<br />', $succ_msgs), true);
        }

        checkAttempts::reset_attempt_counts();
        return 0;
    }

    /**
     * [What is that]:
     *
     * read_only = defined and set in the abstract file. it sets the mode of operation to `readonly`
     * global_msg = used to set global message displayed in the header to the user
     *
     * map_refill_fields = refills form data to what was posted by the user. Basically, if there is an error the form is to be refilled so the user doesnt have to fill all of it again
     */
    public function index()
    {
        #/ download pdf request
        if (!empty($this->GET['pdfk']) && !$this->read_only) {
            if (pdfHelper::output_pdf($this->GET['pdfk'])==false) {
                r::global_msg($this->S_PREFIX."MSG_GLOBAL", 'Unable to download the PDF! Please check the Ticket, or contact the development team.');
                return redirect()->route($this->cur_route, array_diff_key($this->GET, array_flip(array('pdfk'))));
            }
        }

        #/ CLONE mode: Get records
        if ($this->cp_rec>0 && !$this->read_only) {
            $this->recRow = $this->get_record($this->cp_rec);
            if ($this->recRow==false) {
                return redirect()->route($this->back_route, array_diff_key($this->GET, array_flip(array('cp_rec'))));
            }
        }

        #/ EDIT & ReadOnly modes: Get records
        if ($this->rec_id>0) {
            $this->recRow = $this->get_record($this->rec_id);
            if ($this->recRow==false) {
                return redirect()->route($this->back_route, array_diff_key($this->GET, array_flip(array('rec_id'))));
            }

            #/ Security Feature: only allow Owners or superadmin to Edit
            if (!$this->read_only && !$this->is_superAdmin && $this->recRow['generated_by']!=$this->loggedin_user_id) {
                r::global_msg($this->S_PREFIX."MSG_GLOBAL", "Permission Denied! You can only Edit the report you generated yourself!");
                return redirect()->route($this->back_route, array_diff_key($this->GET, array_flip(array('rec_id'))));
            }
        }

        #/ process form Postings
        if ($this->read_only<=0) {
            if (!empty($this->POST) && isset($this->POST['performed_by'])) {
                $tot_errors = (int)@$this->process_form();
                if ($tot_errors==0) {
                    if (!empty($this->pdf_sess_key)) {
                        r::sess_save('pdf_key_'.$this->rec_id, $this->pdf_sess_key); //saving pdf data in the cache to get it later
                    }

                    return redirect()->route($this->cur_route, array_diff_key($this->param2_ar, array_flip(array('cp_rec'))));
                }

            } else {
                #/ get pdf key from sess and start a pdf download request (logic: if the key is set the frontend JS code will request the pdf to be downloaded)
                $pdf_key = r::sess('pdf_key_'.$this->rec_id);
                if (!empty($pdf_key)) {
                    r::flush_sess('pdf_key_'.$this->rec_id);

                    $pdf_sess_data = @r::sess($pdf_key);
                    if (!empty($pdf_sess_data)) {
                        $this->pdf_sess_key = $pdf_key;
                    }
                }
            }
        }

        #/ Refill Fields
        if (isset($this->POST['performed_by'])) {
            $this->map_refill_fields();
        }

        #/ Get some Get additonal related data from helpers (might be in the cache or in the database)
        h2::get_lorem_contacts($this->lorem_contacts, true);

        ##/ pass vars to view
        $pg_title = $this->read_only > 0 ? "Lorem Ipsum Form RO &raquo; ":"Lorem Ipsum Form &raquo; ";
        if ($this->read_only>0) {
            $pg_title.= 'Review ';
        } else {
            $pg_title.= $this->rec_id>0? "Edit ": ($this->cp_rec>0? "Clone ":"New ");
        }

        $view_ar = array_merge($this->view_ar, [
            'pg_title' => $pg_title,
            'read_only' => $this->read_only,
            'back_page' => empty($this->back_route)? '':route($this->back_route),
            'param2' => $this->param2,
            'rec_id' => $this->rec_id,
            'cp_rec' => $this->cp_rec,
            'recRow' => $this->recRow,
            'lorem_contacts' => $this->lorem_contacts,
            'lorem_fixed_dropdown' => $this->lorem_fixed_dropdown,
        ]);

        if ($this->read_only==1) {
            $view_ar = array_merge($view_ar, [
                'no_header' => true,
            ]);

        } else {
            $view_ar = array_merge($view_ar, [
                'load_jquery_ui' => true,
                'pdfk'=> $this->pdf_sess_key,
            ]);
        }
        #-

        return view('lorem.helloWorldOpr', $view_ar);
    }
}
