@extends('layouts.shell')
@section('innerBody')

<?php if ($read_only<=0) { ?>
<script>
$(document).ready(function() {

    //JQuery calendars
    $(function() {
        $.init_calendar('start_ts_dt');
        $.init_calendar('end_ts_dt');
    });

    /** frontend form validation. This is for user mistakes and it not really a security feature, real validation is done at the backend
    Also note that the frontend has its own form attempts checker based on js. **/
    $.check_this = function()
    {
        var err = '';

        if (document.getElementById('lorem_ticket_id').value=='') {} else
        if (document.getElementById('lorem_ticket_id').value.search(/^[0-9]{1,}$/i)<0) {
            err += '<li><strong>Ticket Number</strong> can be numbers only!</li>';
        }

        if (document.getElementById('performed_by').value=='') {
            err += '<li><strong>Er Performed By</strong> cannot be empty!</li>';
        }

        if (document.getElementById('witnessed_by').value=='') {
            err += '<li><strong>Er Witnessed By</strong> cannot be empty!</li>';
        }

        //#/Timestamp
        if (document.getElementById('start_ts_dt').value=='') {
            err += '<li>Timestamp / <strong>Start Date</strong> cannot be empty!</li>';
        }

        document.getElementById('start_ts_tm').value = document.getElementById('start_ts_hour').value+':'+document.getElementById('start_ts_min').value;
        if (document.getElementById('start_ts_tm').value==':') {
            err += '<li>Timestamp / <strong>Start Time</strong> cannot be empty!</li>';
        } else if (document.getElementById('start_ts_tm').value.search(/^[0-9]{1,2}:[0-9]{1,2}$/im)) {
            err += '<li>Timestamp / <strong>Start Time</strong> is invalid!</li>';
        }

        if (document.getElementById('end_ts_dt').value=='') {
            err += '<li>Timestamp / <strong>End Date</strong> cannot be empty!</li>';
        }

        document.getElementById('end_ts_tm').value = document.getElementById('end_ts_hour').value+':'+document.getElementById('end_ts_min').value;
        if (document.getElementById('end_ts_tm').value==':') {
            err += '<li>Timestamp / <strong>End Time</strong> cannot be empty!</li>';
        } else if (document.getElementById('end_ts_tm').value.search(/^[0-9]{1,2}:[0-9]{1,2}$/im)) {
            err += '<li>Timestamp / <strong>End Time</strong> is invalid!</li>';
        }

        var dt_1 = document.getElementById('start_ts_dt').value+' '+document.getElementById('start_ts_tm').value+':0';
        var dt_2 = document.getElementById('end_ts_dt').value+' '+document.getElementById('end_ts_tm').value+':0';
        if (compare_date(dt_1, dt_2)==false) err += '<li>Timestamp / <strong>End</strong> must be greater than or equals to <strong>Start</strong>!</li>';
        //#-

        //#/ COMPONENTS
        indx=0;
        $('.component_items .type_').each(function() {
            indx++;
            if ($(this).val()=='' || $(this).val()==null) {
                err += '<li>Component '+indx+' / <strong>Type</strong> cannot be empty!</li>';
            }
        });

        indx=0;
        $('.component_items .model_').each(function() {
            indx++;
            if ($(this).val().length<5) {
                err += '<li>Component '+indx+' / <strong>Model</strong> must be at least 5 characters!</li>';
            }
        });

        indx=0;
        $('.component_items .serial_').each(function() {
            indx++;
            if ($(this).val()=='') {
                err += '<li>Component '+indx+' / <strong>Serial Number</strong> cannot be empty!</li>';
            }
        });

        indx=0;
        $('.component_items .size_').each(function() {
            indx++;
            if (parseFloat($(this).val())<0) {
                err += '<li>Component '+indx+' / <strong>Size</strong> is empty or invalid!</li>';
            }
        });

        indx=0;
        $('.component_items .Er_level_').each(function() {
            indx++;
            if ($(this).val()=='' || $(this).val()==null)
            err += '<li>Component '+indx+' / <strong>Er Level</strong> cannot be empty!</li>';
        });

        indx=0;
        $('.component_items .status_').each(function() {
            indx++;
            if ($(this).val()=='' || $(this).val()==null) {
                err += '<li>Component '+indx+' / <strong>Status</strong> cannot be empty!</li>';
            }
        });

        //return true; //debug

        if (err!='') {
            fancyAlert('Error', "Please clear the following <strong>ERROR(s)</strong>:<div style='height:10px;' /><ul>"+err+"</ul><div style='height:10px;' />");
            return false;

        } else {
            //#/ Check attempts to form submission
            if (aCheck(20)==false) {
                err += '<strong class="red-txt">Too Many Too Fast!</strong><br />Please try again after a few minutes ..';
                fancyAlert('Error', "<div style='height:10px;' />"+err+"<div style='height:15px;' />");
                return false;
            }

            return true;
        }

        return false;
    };

    $.submit_report = function()
    {
        if ($.check_this()==false) {
            return false;
        }
        return true;
    }

    //------------------------------------------------------------------

    var tab_index = 2000;
    var new_smax = -1;

    /** This method creates mini forms within the form dynamically on click. I will leave this code out, let me know if you need to see it **/
    function add_more_component_items(def_v, copy_first_row)
    {
        $(clone_v).insertBefore('.'+keyword+'items #last_divi');
    };
    //------------------------------------------------------------------

});
</script>
<?php } ?>

<?php if ($read_only<=0) { ?>
<form action="" method="POST" name="f2" id="f2" onsubmit="return $.submit_report();" novalidate="" enctype="multipart/form-data">
{{csrf_field()}}

<?php if ($rec_id) { ?>
<input type="hidden" name="rec_id" id="rec_id" value="<?php echo $rec_id; ?>" />
<?php } ?>
<?php } ?>

<?php $tabindex=1110;?>
<table border="0" cellpadding="0" cellspacing="0" class="datagrid dg_opp" width="100%">

    <tr>
	   <th colspan="2">Er</th>
	</tr>

    <tr>
    <td valign="middle" style="width:35%;">

        <?php if ($read_only) {
        echo "<div class=\"label\">Certificate Number:</div>
        <div class=\"val\">{$empt['certificate_number']}</div>
        <div style=\"clear:both; height:10px;\"></div>";
        } ?>

        <div class="label">Ticket:</div>
        <div class="val">
            <?php $lorem_ticket_id = (int)@$empt['lorem_ticket_id']; ?>

            <?php if ($read_only<=0) { ?>
            <input type="hidden" name="old_ticket" value="<?=@$empt['old_ticket']?>" />
            <input type="number" min="1" id="lorem_ticket_id" name="lorem_ticket_id" max="2147483647" value="<?php echo $lorem_ticket_id>0? $lorem_ticket_id:''; ?>"
            pattern="^[0-9]{0,}$" style="width:120px;" tabindex="<?=$tabindex++?>" />
            <?php } else {
                echo !empty($lorem_ticket_id)? $lorem_ticket_id:'-';
            } ?>
        </div>
        <div style="clear:both; height:10px;"></div>

        <div class="label">Performed By:</div>
        <div class="val">
            <?php if ($read_only<=0) { ?>
            <?php $performed_by = (empty($empt['performed_by']))? $lorem_contacts:$empt['performed_by']; ?>
            <select id="performed_by" name="performed_by" style="width:200px;" tabindex="<?=$tabindex++?>">
            <option value="">Select-</option>
            <?php foreach($lorem_contacts as $v) {echo "<option value=\"{$v['id']}\">{$v['name']}</option>";} ?>
            </select>
            <?php if (!empty($performed_by)) {echo "<script>document.getElementById('performed_by').value='{$performed_by}';</script>";} ?>
            <span class="req">&nbsp;*</span>

            <?php } else { ?><?php echo empty($lorem_contacts[$empt['performed_by']])? $empt['performed_by']:$lorem_contacts[$empt['performed_by']]["name"]; ?><?php } ?>
        </div>
        <div style="clear:both; height:10px;"></div>

        <div class="label">Timestamp:</div>
        <div class="val">
            <?php if ($read_only>0) { ?><br class="dsktop_only" /><?php } ?>
            <span class="submsg" style='display:block;'>Start:</span>
            <?php if ($read_only<=0) { ?>
            <input type="text" id="start_ts_dt" name="start_ts_dt" value="<?=@$empt['start_ts_dt']?>" readonly="" maxlength="10" style="width:119px;" tabindex="<?=$tabindex++?>" />
            <span class="req">&nbsp;*</span>
            <div style="clear:both; height:4px;"></div>

            <?php echo adminHelper::create_time_field('start_ts', $tabindex, $empt); ?>
            <span class="req">&nbsp;*</span>
            <?php } else { ?><?=@date('d/m/Y', strtotime($empt['start_ts_dt']))?> &nbsp;<?=@r::pretty_time($empt['start_ts_tm'])?><br /><?php } ?>

            <?php if ($read_only<=0) { ?><br /><?php } ?>
            <br />

            <span class="submsg" style='display:block;'>End:</span>
            <?php if ($read_only<=0) { ?>
            <input type="text" id="end_ts_dt" name="end_ts_dt" value="<?=@$empt['end_ts_dt']?>" readonly="" maxlength="10" style="width:119px;" tabindex="<?=$tabindex++?>" />
            <span class="req">&nbsp;*</span>
            <div style="clear:both; height:4px;"></div>

            <?php echo adminHelper::create_time_field('end_ts', $tabindex, $empt); ?>
            <span class="req">&nbsp;*</span>
            <?php } else { ?><?=@date('d/m/Y', strtotime($empt['end_ts_dt']))?> &nbsp;<?=@r::pretty_time($empt['end_ts_tm'])?><br /><?php } ?>

            <?php
            if ($read_only>0) {
                $request_change_dur = @r::time_diff_str(
                "{$empt['start_ts_dt']} {$empt['start_ts_tm']}:00",
                "{$empt['end_ts_dt']} {$empt['end_ts_tm']}:00");

                if (!empty($request_change_dur)) {
                    echo '<br />
                    <span class="submsg" style="display:block;">Duration:</span>
                    '.$request_change_dur;
                }
            }
            ?>
        </div>

        <div style="clear:both; height:1px;"></div>
    </td>
    </tr>

    <?php if ($read_only<=0) { ?>
    <tr><td style="padding: 0px !important;">&nbsp;</td></tr>
    <?php } ?>

    <tr>
	   <th colspan="2">COMPONENTS</th>
	</tr>

    <tr>
    <td valign="middle" style="width:35%;">

        <div class="component_items">
            <?php if ($read_only<=0) { ?>
            <span id="last_divi" style="clear:both; height:1px; padding:0; margin:0;"></span>

            <br />
            <div style='margin-top:-10px;'><a href="javascript:void(0);" class="reset_cache_txt" style="font-weight:bold; text-shadow: 0 0 3px #fcf764;"
            onclick="add_more_component_items();">Add another blank row? Click here.</a></div><br />

            <div style='margin-top:-10px;'><a href="javascript:void(0);" class="reset_cache_txt" style="font-weight:bold; text-shadow: 0 0 3px #fcf764;"
            onclick="add_more_component_items(null, true);">Or copy first row into a new row? Click here.</a></div>

            <?php
            } else {
                $a_i = 0;
                if (isset($empt['component_index']) && is_array($empt['component_index'])) {
                    foreach($empt['component_index'] as $pv) {
                        $a_i++;

                        echo '
                        <div class="label">Type '.$a_i.':</div>
                        <div class="val">'.$fixed_lists['component_types'][$pv['type']].'</div>
                        <div style="clear:both; height:10px;"></div>

                        <div class="label">Model:</div>
                        <div class="val">'.$pv['model'].'</div>
                        <div style="clear:both; height:10px;"></div>

                        <div class="label">Serial:</div>
                        <div class="val">'.$pv['serial'].'</div>
                        <div style="clear:both; height:10px;"></div>

                        <div class="label">Asset Tag:</div>
                        <div class="val">'.(empty($pv['asset_tag'])? '-':$pv['asset_tag']).'</div>
                        <div style="clear:both; height:10px;"></div>

                        <div class="label">Size:</div>
                        <div class="val">'.(str_replace('#', '', $pv['size'])).'</div>
                        <div style="clear:both; height:10px;"></div>

                        <div class="label">Level:</div>
                        <div class="val">'.$fixed_lists['component_Er_levels'][$pv['Er_level']].'</div>
                        <div style="clear:both; height:10px;"></div>

                        <div class="label">Status:</div>
                        <div class="val">'.$fixed_lists['status'][$pv['status']].'</div>
                        ';

                        if ($a_i<@count($empt['component_index'])) {
                            echo '
                            <div style="clear:both; height:10px;"></div>
                            <div class="mbl_only" style="clear:both; height:15px;"></div>
                            <hr /><br />
                            ';
                        }
                    }
                }
            }
            ?>
        </div>

        <div style="clear:both; height:1px;"></div>
    </td>
    </tr>

    <?php if ($read_only<=0) { ?>
    <tr><td style="padding: 0px !important;">&nbsp;</td></tr>
    <tr>
	   <th colspan="2">SAVE / GENERATE</th>
	</tr>

    <tr>
    <td valign="middle" style="width:35%;"><br />
        <input type="radio" id="generate_pdf" name="generate_option" value="generate_pdf" autocomplete="false" tabindex="<?=$tabindex++?>"
        <?php if (isset($empt['generate_option']) && $empt['generate_option']=='generate_pdf') {echo "checked='checked'";} ?> /> Download Certificate?&nbsp;
        <span class="submsg">//download the PDF file while saving the data?</span>
        <div style="clear:both; height:5px;"></div>
        <div class="mobile_only" style="clear:both; height:10px;"></div>

        <input type="radio" id="email_now" name="generate_option" value="email_now" autocomplete="false" tabindex="<?=$tabindex++?>"
        <?php if (isset($empt['generate_option']) && $empt['generate_option']=='email_now') {echo "checked='checked'";} ?> /> Email now?
        <br class="dsktop_only" />

        <div style="clear:both; height:25px;"></div>
        <input type="submit" class="button" name="sub" value="Submit" style="width:150px;" />
	</td>
	</tr>
    <?php } ?>

</table>
<?php if ($read_only<=0) { ?>
</form>
<div style="clear: both;"></div>

<div style="display: none;">

<?php /** Components elements to be inserted **/ ?>
<div id="component_items_temp">
<div class="items_inserted">
    <input type="hidden" class="index_" name="component_index[]" value="" />

    <div class="rem_div_"><img src="{{asset('assets/images/del2.png')}}" style="cursor: pointer; width:20px;"
    title="remove this row" onclick="$.rem_this_item(this);" /></div>
    <div style="clear:both; height:10px;"></div>

    <div class="label">Type:</div>
    <div class="val">
        <select class="type_" name="component_type[]" style="width:200px;">
        <option value="">Select-</option>
        <?php foreach($fixed_lists['component_types'] as $sk=>$sv) {echo "<option value=\"{$sk}\">{$sv}</option>";} ?>
        </select>
        <span class="req">&nbsp;*</span>
    </div>
    <div style="clear:both; height:10px;"></div>

    <div class="label">Model:</div>
    <div class="val">
        <input type="text" class="model_" name="component_model[]" maxlength="140" style="width:300px;" />
        <span class="req">&nbsp;*</span>
    </div>
    <div style="clear:both; height:10px;"></div>

    <div class="label">Serial:</div>
    <div class="val">
        <input type="text" class="serial_" name="component_serial[]" maxlength="40" style="width:200px;" />
        <span class="req">&nbsp;*</span>
    </div>
    <div style="clear:both; height:10px;"></div>

    <div class="label">Asset Tag:</div>
    <div class="val">
        <input type="text" class="asset_tag_" name="component_asset_tag[]" maxlength="140" style="width:200px;" />
    </div>
    <div style="clear:both; height:10px;"></div>

    <div class="label">Size:</div>
    <div class="val">
        <input type="number" class="size_ margin_below" name="component_size[]" min="0" step="0.01" max="5000" style="width:70px;" />
        <select class="sizeUnit_" name="component_sizeUnit[]" style="width:60px; min-width:0;">
        <option value="TB">TB</option>
        <option value="GB">GB</option>
        <option value="MB">MB</option>
        </select>
        <span class="req">&nbsp;*</span>
    </div>
    <div style="clear:both; height:10px;"></div>

    <div class="label">Er Level:</div>
    <div class="val">
        <select class="Er_level_" name="component_Er_level[]" style="width:200px;">
        <option value="">Select-</option>
        <?php foreach($fixed_lists['component_Er_levels'] as $sk=>$sv) {echo "<option value=\"{$sk}\">{$sv}&nbsp;</option>";} ?>
        </select>
        <span class="req">&nbsp;*</span>
    </div>
    <div style="clear:both; height:10px;"></div>

    <div class="label">Status:</div>
    <div class="val">
        <select class="status_" name="component_status[]" style="width:200px;">
        <option value="">Select-</option>
        <?php foreach($fixed_lists['status'] as $sk=>$sv) {echo "<option value=\"{$sk}\">{$sv}</option>";} ?>
        </select>
        <span class="req">&nbsp;*</span>
    </div>

    <div style="clear:both; height:20px;"></div>
    <div class="sep_1">&nbsp;</div>
</div>
</div>

</div>
<br />

<div style="margin-top:15px;"></div>
<div style="font-weight:bold; margin-bottom:10px; text-decoration: underline;">Please Note:</div>

<ul class="foot_note_opp">
    <li><strong>Please review</strong> the PDF before emailing it to someone or uploading it to a ticket. You may still need some text adjustments to improve the looks</li>
</ul>

<?php if (!empty($pdfk)) { ?>
<script type="text/javascript">
$(document).ready(function() {
document.getElementById('mask_1').style.display='none';
location.href="<?php echo route($cur_route, ['pdfk'=>$pdfk, 'rec_id'=>$rec_id]); ?>";
});
</script>
<?php } ?>

<?php } //end readonly ?>
@endSection
