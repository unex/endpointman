<?php
/**
 * The devices manager page.
 *
 * views/devices.php draws it and assigns each variable below, except
 * disable_help (the constructor of Endpointman) and the four message box
 * variables (DevicesPage::display_message_box).
 *
 * @var array  $list             Managed devices, each with a 'line' list
 * @var array  $brand_ava        Brand <option> rows
 * @var array  $models_ava       Model <option> rows
 * @var array  $display_ext      Free extension <option> rows
 * @var array  $display_templates Template <option> rows (EDIT mode only)
 * @var array  $line_list_edit   Lines of the device being edited
 * @var array  $unmanaged        Devices found by the netmask search
 * @var array  $product_list     Product <option> rows
 * @var array  $model_list       Model <option> rows for the bulk reconfigure
 * @var string $mode             "EDIT" or null
 * @var bool   $no_add           Suppress the add/search forms
 * @var string $netmask          Default search netmask
 *
 * Each value that this page prints is kind 1 under the rule in
 * lib/esc.inc.php and goes through epm_esc(): MAC and IPEI, brand, model and
 * template names, extension numbers and their FreePBX descriptions, the
 * netmask, the addresses of scanned devices, and each <option> value and
 * label. An admin types the extension description in Applications >
 * Extensions, so it is the first field where a hostile value can arrive.
 *
 * The `selected` ternaries print a literal or nothing, so they contain no
 * data and have no escape.
 *
 * Some ids go into a JavaScript argument list in an onclick, for example
 * submit_wtype('edit',<id>). epm_esc() is the correct escape for the
 * attribute. It is the only escape that they need, because each id is an
 * integer. endpointman_mac_list.id and endpointman_line_list.luid are INT
 * columns, and the id of the netmask scan is the array index from
 * views/devices.php. A string there needs a JavaScript escape too.
 */
?>

<div class="container-fluid">
<?php $this->render('global_header', $this->vars()); ?>
<h2><?php echo _('Device List');?></h2>
<div class="fpbx-container">
<div class="display full-border">
<?php if (isset($show_error_box)) { $this->render('message_box', $this->vars()); } ?>
<script type="text/javascript" charset="utf-8">
   $(function(){
        $("select#brand_edit").change(function(){
            $.ajaxSetup({ cache: false });
            $.getJSON("ajax.php?module=endpointman&view=devices&command=model",{id: $(this).val()}, function(j){
                var options = '';
                for (var i = 0; i < j.length; i++) {
                    options += '<option value="' + j[i].optionValue + '">' + j[i].optionDisplay + '</option>';
                }
                $("#model_new").html(options);
                $('#model_new option:first').attr('selected', 'selected');
                $("#template_list").html('<option></option>');
                $('#template_list option:first').attr('selected', 'selected');
            })
        })
    })
    $(function(){
        $("select#product_select").change(function(){
            $.ajaxSetup({ cache: false });
            $.getJSON("ajax.php?module=endpointman&view=devices&command=template",{id: $(this).val()}, function(j){
                var options = '';
                for (var i = 0; i < j.length; i++) {
                    options += '<option value="' + j[i].optionValue + '">' + j[i].optionDisplay + '</option>';
                }
                $("#template_selector").html(options);
                $('#template_selector option:first').attr('selected', 'selected');
            })
        })
    })
    $(function(){
        $("select#model_select").change(function(){
            $.ajaxSetup({ cache: false });
            $.getJSON("ajax.php?module=endpointman&view=devices&command=mtemplate",{id: $(this).val()}, function(j){
                var options = '';
                for (var i = 0; i < j.length; i++) {
                    options += '<option value="' + j[i].optionValue + '">' + j[i].optionDisplay + '</option>';
                }
                $("#model_template_selector").html(options);
                $('#model_template_selector option:first').attr('selected', 'selected');
            })
        })
    })
    $(function(){
        $("select#model_new").change(function(){
            $.ajaxSetup({ cache: false });
            $.getJSON("ajax.php?module=endpointman&view=devices&command=template2",{id: $(this).val()}, function(j){
                var options = '';
                for (var i = 0; i < j.length; i++) {
                    options += '<option value="' + j[i].optionValue + '">' + j[i].optionDisplay + '</option>';
                }
                $("#template_list").html(options);
                $('#template_list option:first').attr('selected', 'selected');
            }),
            $.ajaxSetup({ cache: false });
            $.getJSON("ajax.php?module=endpointman&view=devices&command=lines",{id: $(this).val()}, function(j){
                var options = '';
                for (var i = 0; i < j.length; i++) {
                    options += '<option value="' + j[i].optionValue + '">' + j[i].optionDisplay + '</option>';
                }
                $("#line_list").html(options);
                $('#line_list option:first').attr('selected', 'selected');
            })
        })
    })

    $(function(){
        $("select#brand_list_selected").change(function(){
            $.ajaxSetup({ cache: false });
            $.getJSON("ajax.php?module=endpointman&view=devices&command=model",{id: $(this).val()}, function(j){
                var options = '';
                for (var i = 0; i < j.length; i++) {
                    options += '<option value="' + j[i].optionValue + '">' + j[i].optionDisplay + '</option>';
                }
                $("#model_list_selected").html(options);
                $('#model_list_selected option:first').attr('selected', 'selected');
            })
        })
    })
    function addTableRow(jQtable){
        jQtable.each(function(){
            var $table = $(this);
            // Number of td's in the last table row
            var n = $('tr:last td', this).length;
            var tds = '<tr>';
            for(var i = 0; i < n; i++){
                tds += '<td>&nbsp;</td>';
            }
            tds += '</tr>';
            if($('tbody', this).length > 0){
                $('tbody', this).append(tds);
            }else {
                $(this).append(tds);
            }
        });
    }
    function add_device() {
        $('#adding').append('<input type="hidden" name="sub_type" value="add"/>');
        document.adding.submit();
    }
    function find_devices() {
        $('#spinner').toggle();
        $('#go').append('<input type="hidden" name="sub_type" value="go"/>');
        document.go.submit();
    }
    function managed_options(type) {
        $('#managed').append('<input type="hidden" name="sub_type" value="'+ type +'"/>');
        document.managed.submit();
    }
    function add_searched_devices() {
        $('#unmanaged').append('<input type="hidden" name="sub_type" value="add_selected_phones"/>');
        document.unmanaged.submit();
    }
    function submit_global(type) {
        $('#globalmanaged').append('<input type="hidden" name="sub_type" value="'+ type +'"/>');
        document.globalmanaged.submit();
    }
    function submit_global2(type) {
        $('#globalmanaged2').append('<input type="hidden" name="sub_type" value="'+ type +'"/>');
        document.globalmanaged2.submit();
    }
    function submit_global3(type) {
        $('#globalmanaged3').append('<input type="hidden" name="sub_type" value="'+ type +'"/>');
        document.globalmanaged3.submit();
    }
    function submit_wtype(type,id) {
        $('#adding').append('<input type="hidden" name="edit_id" value="'+ id +'"/><input type="hidden" name="sub_type" value="'+ type +'"/>');
        document.adding.submit();
    }
    function edit_device(type,id,sub) {
        $('#adding').append('<input type="hidden" name="edit_id" value="'+ id +'"/><input type="hidden" name="sub_type" value="'+ type +'"/><input type="hidden" name="sub_type_sub" value="'+ sub +'"/>');
        document.adding.submit();
    }
    function delete_device(id) {
        if (confirm('Are you sure you want to delete this device?')) {
            submit_wtype('delete_device',id);
        }
    }
    function popitup(url, name, id) {
        if(id != '0') {
            newwindow=window.open(url + '&model_list=' + document.getElementById('model_new').value + '&template_list=' + document.getElementById('template_list').value + '&rand=' + new Date().getTime(),'name2','height=1000,width=950,scrollbars=yes,location=no');
            if (window.focus) {newwindow.focus()}
            return false;
        }
    }

    function submit_stype(type,id) {
        newwindow=window.open('config.php?display=endpointman&quietmode=1&handler=file&file=popup.html.php&module=endpointman&pop_type=edit_specifics&edit_id=' + id + '&rand=' + new Date().getTime(),'name2','height=700,width=750,scrollbars=yes,location=no');
        if (window.focus) {newwindow.focus()}
        return false;
    }
    function togglePhones(action) {
        $('#devList .device').prop('checked', action);
    }
</script>

<h3><?php echo ($mode != 'EDIT') ? _('Add') : _('Edit'); ?> <?php echo _('Device')?></h3>
<form name="adding" id="adding" action='config.php?display=endpointman&view=devices' method='POST' />
<table id="devAdd" data-toolbar="#toolbar-all" data-pagination="false" data-show-columns="false" data-show-toggle="false" data-search="false" data-show-refresh="false" data-detail-formatter="device" data-minimum-count-columns="1" data-show-pagination-switch="false" data-id-field="id" data-page-list="[5,10, 25, 50, 100, ALL]" data-show-footer="false" data-side-pagination="device" class="table table-striped table-hover">
  <thead>
    <tr>
        <th width="13%" align='center'><?php echo _('MAC Address')?></th>
        <th width="13%" align='center'><?php echo _('IPEI (DECT Handset)')?></th>
        <th width="13%" align='center'><?php echo _('Brand')?></th>
        <th width="10%" align='center'><?php echo _('Model')?></th>
        <th width="10%" align='center'><?php echo _('Line')?></th>
        <th width="19%" align='center'><?php echo _('Extension Number')?></th>
        <th width="15%" align='center'><?php echo _('Template')?></th>
        <th width="6%"></th>
        <th width="6%"></th>
    </tr>
  </thead>
  <tbody>
<?php if ($no_add == FALSE) { ?>
    <tr>
    <td align='center'>
<?php if ($mode == 'EDIT') { ?>
        <?php echo epm_esc($mac); ?>
<?php } else { ?>
        <input name='mac' type='text' tabindex='1' size="17" maxlength="17">
<?php } ?></td>
    <td align='center'>
<?php if ($mode != 'EDIT') { ?>
        <input name='ipei' type='text' tabindex='1' size="17" maxlength="17">
<?php } ?></td>
    <td align='center'>  <label>
<?php if ($mode == 'EDIT') { ?>
            <?php echo epm_esc($name); ?>
<?php } else { ?>
            <select name="brand_list" id="brand_edit">
<?php foreach ($brand_ava as $row) { ?>
                <option value="<?php echo epm_esc($row['value']); ?>" <?php echo isset($row['selected']) ? 'selected' : ''; ?>><?php echo epm_esc($row['text']); ?></option>
<?php } ?>
            </select></label>
<?php } ?>
    </td>
    <td align='center'>
        <label>
<?php if ($mode == 'EDIT') { ?>
            <input name="display" type="hidden" value="epm_devices">
            <select name="model_list" id="model_new">
<?php foreach ($models_ava as $row) { ?>
                <option value="<?php echo epm_esc($row['value']); ?>" <?php echo !empty($row['selected']) ? 'selected' : ''; ?>><?php echo epm_esc($row['text']); ?></option>
<?php } ?>
            </select>
<?php } else { ?>
            <select name="model_list" id="model_new"><option></option></select>
<?php } ?>
        </label></td>
<?php if ($mode == 'EDIT') { ?>
    <td align='center'></td>
    <td align='center'></td>
<?php } else { ?>
    <td align='center'>
        <label>
            <select name="line_list" id="line_list" >
                <option></option>
            </select>
        </label></td>
    <td align='center'>
        <select name="ext_list" id="select">
<?php foreach ($display_ext as $row) { ?>
            <option value="<?php echo epm_esc($row['value']); ?>"><?php echo epm_esc($row['text']); ?></option>
<?php } ?>
        </select>
        </label>
    </td>
<?php } ?>
    <td align='center'>  <div id="demo"><select name="template_list" id="template_list">
<?php foreach ((isset($display_templates) ? $display_templates : array()) as $row) { ?>
                <option value="<?php echo epm_esc($row['value']); ?>" <?php echo isset($row['selected']) ? 'selected' : ''; ?>><?php echo epm_esc($row['text']); ?></option>
<?php } ?>
            </select>
            <a href="#" onclick="return popitup('config.php?display=endpointman&view=templates&subpage=editor&custom=<?php echo epm_esc($custom); ?>&idsel=<?php echo epm_esc($template_id); ?>', 'Template Editor', '<?php echo epm_esc($template_id); ?>')"><i class='fa fa-pencil'></i></a></div>
        </label></td>
    <td align='center'>
<?php if ($mode == 'EDIT') { ?>
        <button type='submit' name='button_save' onclick="edit_device('edit',<?php echo epm_esc($edit_id); ?>,'button_save');"><i class='fa fa-floppy-o blue'></i> <?php echo _('Save')?></button>
        <td></td>
<?php } else { ?>
        <button type='button' name='button_add' onclick="add_device();"><i class='fa fa-plus success'></i>&nbsp;<?php echo _('Add')?></button>
<?php } ?>
    </td>
<?php if ($mode != 'EDIT') { ?>   <td align='center'><button type='reset'><i class='fa fa-undo red'></i> <?php echo _('Reset')?></td><?php } ?>
</tr>
<?php foreach ((isset($line_list_edit) ? $line_list_edit : array()) as $line) { ?>
<tr>
    <td align='center' width='2%'>&nbsp;</td>
    <td align='center'><input name='ipei_<?php echo epm_esc($line['luid']); ?>' value='<?php echo epm_esc($line['ipei']); ?>' type='text' tabindex='1' size="17" maxlength="17"></td>
    <td align='center'></td>
    <td align='center'></td>
    <td align='center'>
        <label>
            <select name="line_list_<?php echo epm_esc($line['luid']); ?>" id="line_list" >
<?php foreach ($line['line_list'] as $row) { ?>
                <option value="<?php echo epm_esc($row['value']); ?>" <?php echo isset($row['selected']) ? 'selected' : ''; ?>><?php echo epm_esc($row['text']); ?></option>
<?php } ?>
            </select>
        </label></td>
    <td align='center'>
        <select name="ext_list_<?php echo epm_esc($line['luid']); ?>" id="select">
<?php foreach ($line['reg_list'] as $row) { ?>
            <option value="<?php echo epm_esc($row['value']); ?>" <?php echo isset($row['selected']) ? 'selected' : ''; ?>><?php echo epm_esc($row['text']); ?></option>
<?php } ?>
        </select>
        </label>
    </td>
    <td align='center'></td>
    <td align='center'><?php if (!isset($disabled_delete_line)) { ?><div id="demo"><a href="#" onclick="edit_device('edit',<?php echo epm_esc($line['luid']); ?>,'delete');"><i class="red fa fa-times" title="Delete Line from Device to the Left"></i></div><?php } ?></a></td>
</tr>
<?php } ?>
<?php if ($mode == 'EDIT') { ?>
<tr>
    <td align='center'>&nbsp;</td>
    <td align='center'></td>
    <td align='center'></td>
    <td align='center'></td>
    <td align='center'>&nbsp;</td>
    <td align='center'>&nbsp;</td>
    <td align='center'></td>
    <td align='center'><div id="demo"><a href="#" onclick="edit_device('edit',<?php echo epm_esc($edit_id); ?>,'add_line_x');"><i class="green fa fa-plus" title="Add a Line to the device currently being edited"></i></a></div></td>
</tr>
<?php } ?>
<?php } ?>
  </tbody>
</table>
</form>

<?php if ($searched == 1) { ?>
<table width='90%' align='center'>
    <tr>
        <td align='center'>&nbsp;</td>
        <td align='center'>&nbsp;</td>
        <td align='center'>&nbsp;</td>
        <td colspan="3" align='center'><h3><?php echo _('Unmanaged Extensions')?></h3></td>
        <td align='center'>&nbsp;</td>
        <td align='center'>&nbsp;</td>
        <td align='center'>&nbsp;</td>
    </tr>
<?php if (is_array($unmanaged)) { ?>
    <form id="unmanaged" action='' method='POST'>
<?php foreach ($unmanaged as $device) { ?>
        <input name="mac_<?php echo epm_esc($device['id']); ?>" type="hidden" value="<?php echo epm_esc($device['mac_strip']); ?>">
        <input name="brand_<?php echo epm_esc($device['id']); ?>" type="hidden" value="<?php echo epm_esc($device['brand_id']); ?>">
        <tr id="<?php echo epm_esc($device['mac_strip']); ?>">
            <td align='center' width='20'><input type="checkbox" name="add[]" value="<?php echo epm_esc($device['id']); ?>"></td>
            <td align='center' width='148'><?php echo epm_esc($device['mac_strip']); ?><br />(<?php echo epm_esc($device['ip']); ?>)</td>
            <td width="188" align='center'><?php echo epm_esc($device['brand']); ?></td>
            <td width="216" align='center'>
                <select name="model_list_<?php echo epm_esc($device['id']); ?>">
<?php foreach ($device['list'] as $row) { ?>
                    <option value="<?php echo epm_esc($row['id']); ?>"><?php echo epm_esc($row['model']); ?></option>
<?php } ?>
                </select></td>
            <td width="141" align='center'>
            </td>
            <td width="276" align='center'>
                <select name="ext_list_<?php echo epm_esc($device['id']); ?>" id="ext">
<?php foreach ($display_ext as $row) { ?>
                    <option value="<?php echo epm_esc($row['value']); ?>"><?php echo epm_esc($row['text']); ?></option>
<?php } ?>
                </select></td>
            <td align='center' width='220'>&nbsp;</td>
            <td align='center' width='154'></td>
            <td align='center' width='73'>&nbsp;</td>
        </tr>
<?php } ?>
        <tr>
        <table width="90%" border="0" cellspacing="0" cellpadding="0">
            <tr>
                <td><center><input type="submit" name="button_add_selected_phones" onclick="add_searched_devices();" value="<?php echo _('Add Selected Phones')?>"><br /><input type="checkbox" name="reboot_sel">Reboot Phones</center></td>
            </tr>
        </table>
        </tr>
    </form>
<?php } ?>
</table>
<?php } ?>
<br/>
<br/>
<form id="managed" action='config.php?display=endpointman&view=devices' method='POST'>
<h3><?php echo _('Current Managed Extensions')?></h3>

<button type="button" id="selecter"   style="zoom: 0.8" onclick="togglePhones(true)"  ><i class="info fa fa-check-square-o"       id="toggle_all_phones_on"  title="Click to Select All Phones"  ></i> Select All</button>
<button type="button" id="deselecter" style="zoom: 0.8" onclick="togglePhones(false)" ><i class="info fa fa-square-o" id="toggle_all_phones_off" title="Click to Deselect All Phones"></i> Deselect All</button>

<table id='devList'
    data-toolbar="#toolbar-all" data-toggle="table" data-pagination="true"
    data-show-columns="true"
    data-show-toggle="true"
    data-search="true"
    data-show-refresh="true"
    data-detail-formatter="device"
    data-minimum-count-columns="1"
    data-show-pagination-switch="true"
    data-id-field="id"
    data-page-list="[5,10, 25, 50, 100, ALL]"
    data-show-footer="false"
    data-side-pagination="device"
    class="table table-striped">

    <thead>
        <tr>
            <th data-sortable="false"><?php echo _(" ")?></th>
            <th data-sortable="true"><?php echo _("MAC Address")?></th>
            <th data-sortable="true"><?php echo _("IPEI")?></th>
            <th data-sortable="true"><?php echo _("Brand")?></th>
            <th data-sortable="true"><?php echo _("Model")?></th>
            <th data-sortable="false"><?php echo _("Line")?></th>
            <th data-sortable="true"><?php echo _("Extension")?></th>
            <th data-sortable="true"><?php echo _("Template")?></th>
            <th ><?php echo _("Actions")?></th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($list as $device) { ?>
<?php     foreach ($device['line'] as $line) { ?>
<?php         // Merge the device row and the line row. Line values win, and
              // most cells read from the merged row.
              $row = array_merge($device, $line); ?>
        <tr>
            <td align='center' width="7%"><i class="fa fa-power-off fa-lg <?php if ($row['status']['status'] === TRUE) { echo "green"; } else { echo "red"; } ?>" title="<?php echo epm_esc($row['status']['ip']);?>:<?php echo epm_esc($row['status']['port']);?>"></i><input type="checkbox" class="device" name="selected[]" value="<?php echo epm_esc($row['ext']);?>"></td>
            <td align='center' width='13%'><?php echo epm_esc($row['mac']);?></td>
            <td align='center' width='13%'><?php echo epm_esc($row['ipei']);?></td>
            <td width="13%" align='center'><?php echo epm_esc($row['name']);?></td>
            <td width="10%" align='center'><?php echo epm_esc($row['model']);?></td>
            <td width="10%" align='center'><?php echo epm_esc($line['line']);?></td>
            <td width="19%" align='center'><?php echo epm_esc($line['ext']);?> - <?php echo epm_esc($line['description']);?></td>
            <td align='center' width='15%'><a href="#" onclick="submit_stype('edit',<?php echo epm_esc($row['id']);?>);"><?php echo epm_esc($row['template_name']);?></a></td>
            <td align='center' width='6%'><div id="demo"><a href="#" onclick="submit_wtype('edit',<?php echo epm_esc($row['id']);?>);"><i class='fa fa-edit' alt='<?php echo _('Edit')?>' title="Edit phone"></i></a></div></td>
        </tr>
<?php     } ?>
<?php } ?>
    </tbody>
</table>

<br/>
<h4><?php echo _('Selected Phone(s) Options')?></h4>
<p><button style="width: 100px" type="submit" style="vertical-align:middle" name="button_delete_selected_phones" onclick="managed_options('delete_selected_phones');"><i class="fa fa-trash-o red"></i> <?php echo _('Delete')?></button> <?php echo ('Delete Selected Phones')?></p>
<p><button style="width: 100px" type="submit" name="button_rebuild_selected" onclick="managed_options('rebuild_selected_phones');"><i class="fa fa-refresh green"></i> <?php echo _('Rebuild')?></button> <?php echo _('Rebuild Configs for Selected Phones')?> (<label><input type="checkbox" name="reboot"><font size="-1">Reboot Phones</font></label>)</p>
<p><button style="width: 100px" style="vertical-align:middle" type="submit" name="button_update_phones" onclick="managed_options('change_brand');"><i class='blue fa fa-random'></i> <?php echo _('Update')?></button>
<?php echo _('Change Selected Phones to')?>&nbsp;
<select name="brand_list_selected" id="brand_list_selected"><option><?php echo _('Brand')?></option><?php foreach ($brand_ava as $row) { ?><option value="<?php echo epm_esc($row['value']); ?>" <?php echo isset($row['selected']) ? 'selected' : ''; ?>><?php echo epm_esc($row['text']); ?></option><?php } ?></select> <select name="model_list_selected" id="model_list_selected"><option><?php echo _('Model')?></option></select> (<label><input type="checkbox" name="reboot_change"><font size="-1">Reboot Phones</font></label>)</p>
</form>

<h4><?php echo _('Global Phone Options')?></h4>
<?php if ($no_add == FALSE) { ?>
<p>
<form id='go' action='config.php?display=endpointman&view=devices' method='POST'>
  <button style="width: 100px" type="Submit" name="button_go" id="button_go" onclick="find_devices();"><i class='fa fa-search blue'></i> <?php echo _('Search')?></button>
  <?php echo _('Search for new devices in netmask')?>
  <input name="netmask" type="text" value="<?php echo epm_esc($netmask); ?>">
  (<label><input name="nmap" type="checkbox" value="1" checked><font size="-1"><?php echo _('Use NMAP')?></font></label>)
</form>
</p>
<?php } ?>

<p><form action='' name='globalmanaged' id='globalmanaged' method='POST'>
<button style="width: 100px" type='Submit' name='button_rebuild_configs_for_all_phones' onclick="submit_global('rebuild_configs_for_all_phones');"><i class='fa fa-refresh green'></i> <?php echo _('Rebuild')?></button> <?php echo _('Rebuild Configs for All Phones')?>&nbsp;(<label><input type="checkbox" name="reboot"><font size="-1">Reboot Phones</font></label>)
</form></p>

<p><form action='' name='globalmanaged2' id='globalmanaged2' method='POST'>
<button style="width: 100px" type='Submit' name='button_reboot_this_brand' onclick="submit_global2('reboot_brand');"><i class='fa fa-power-off red'></i> <?php echo _('Reboot')?></button> <?php echo _('Reboot This Brand')?> <select name="rb_brand"><?php foreach ($brand_ava as $row) { ?><option value="<?php echo epm_esc($row['value']); ?>"><?php echo epm_esc($row['text']); ?></option><?php } ?></select>
</form></p>

<p><form action='' name='globalmanaged3' id='globalmanaged3' method='POST'>
<button style="width: 100px" type="submit" name="button_rebuild_reboot" onclick="submit_global3('rebuild_reboot');"><i class="fa fa-random blue"></i> <?php echo _('Configure')?></button>
<?php echo _('Reconfigure all')?> (products) <select name="product_select" id="product_select"><?php foreach ($product_list as $row) { ?><option value="<?php echo epm_esc($row['value']); ?>"><?php echo epm_esc($row['text']); ?></option><?php } ?></select> <?php echo _('with')?>
<label><select name="template_selector" id="template_selector"><option></option></select></label>
(<label><input type="checkbox" name="reboot"><font size='-1'>Reboot Phones</font></label>)
</form></p>

<p><form action='' name='globalmanaged4' id='globalmanaged4' method='POST'>
<button style="width: 100px" type="submit" name="button_rebuild_reboot" onclick="submit_global3('mrebuild_reboot');"><i class="fa fa-random blue"></i> <?php echo _('Configure')?></button>
<?php echo _('Reconfigure all')?> (models) <select name="model_select" id="model_select"><?php foreach ($model_list as $row) { ?><option value="<?php echo epm_esc($row['value']); ?>"><?php echo epm_esc($row['text']); ?></option><?php } ?></select> <?php echo _('with')?>
<label><select name="model_template_selector" id="model_template_selector"><option></option></select></label>
(<label><input type="checkbox" name="reboot"><font size='-1'>Reboot Phones</font></label>)
</form></p>

</form>

<?php $this->render('global_footer', $this->vars()); ?>
</div>
</div>
</div>
