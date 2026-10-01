<?php
/**
 * The settings panes of the template editor. They have the same widgets as
 * views/tpl/_setting_fields.php, in Bootstrap markup and not in a table.
 *
 * Each value that this file prints is kind 1 under the rule in
 * lib/esc.inc.php and goes through epm_esc(): `key`, `value`, `description`
 * and `tooltip` of each row, the tab titles, the template name, product and
 * model, the config file names, and `max_chars`, `rows` and `cols`. The last
 * three are vendor data in attributes.
 *
 * The data-url attribute of the file edit boxes contains $_REQUEST['idsel']
 * and $_REQUEST['custom']. Both are request values, so escape them.
 *
 * $epm_tab_n has no escape. It is a counter that this file increments, never
 * data.
 */
	if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
?>

<form action="config.php?display=endpointman&view=templates" method="post" id="epm_template_edit_form" name="epm_template_edit_form" class="fpbx-submit">
	<?php if (isset($_REQUEST['silent_mode'])) { echo '<input name="silent_mode" id="silent_mode" type="hidden" value="1">'; } ?>
	<input name="id" id="id" type="hidden" value="<?php echo epm_esc($dtemplate['hidden_id']); ?>">
	<input name="custom" id="custom" type="hidden" value="<?php echo epm_esc($dtemplate['hidden_custom']); ?>">
        <input name="command" type="hidden" value="save_template">
	<div class="">
		<div class="row">
			<div class="col-md-6">
	            <div class="row">
             		<div class="col-md-12">
						<div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title"><i class="fa fa-info-circle fa-lg"></i> <?php echo _("Info Template")?></h3>
							</div>
							<div class="panel-body">
                                <table class="table">
                                    <tr>
                                        <td class="col-md-3"><b><?php echo _("Template Name:")?></b></td>
                                        <td class="col-md-9"><?php if ($request['custom'] != 0): ?>Custom Template: Extension <?php echo epm_esc($dtemplate['ext']); ?><?php else: ?><?php echo epm_esc($dtemplate['template_name']); ?><?php endif; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="col-md-3"><b><?php echo _("Product Line:")?></b></td>
                                        <td class="col-md-9"><?php echo epm_esc($dtemplate['product']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="col-md-3"><b><?php echo _("Clone of Model:")?></b></td>
                                    <?php if ($request['custom'] != 0): ?>
                                        <td class="col-md-9"><?php echo epm_esc($dtemplate['model'])?></td>
                                    <?php else: ?>
                                        <td class="col-md-9">
                                        <select class="form-control selectpicker show-tick" data-style="btn-primary" data-live-search-placeholder="Search" data-live-search="true" name="model_list" id="model_list" >
                                            <?php
                                            foreach($dtemplate['models_ava'] as $row) {
                                                echo '<option value="'.epm_esc($row['value']).'" '.(!empty($row['selected']) ? "selected" : "").'>'.epm_esc($row['text']).'</option>';
                                            }
                                            ?>
                                        </select>
										</td>
                                    </tr>
                                    <tr>
                                        <td class="col-md-3"><b><?php echo _("Display:")?></b></td>
                                        <td class="col-md-9">
                                            <select class="form-control selectpicker show-tick" data-style="btn-primary" data-live-search-placeholder="Search" data-live-search="true" name="area_list" id="area_list" onchange="epm_template_edit_select_area_list(this)">
                                                <option value=""></option>
                                                <?php 	
                                                foreach($dtemplate['area_ava'] as $row) {
                                                    echo '<option value="'.epm_esc($row['value']).'" '.(!empty($row['selected']) ? "selected" : "").'>'.epm_esc($row['text']).'</option>';
                                                }
                                                ?>
                                            </select>
                                            <strong><?php echo _('Line settings on this page')?></strong><i><font size="-2"> (Note: This is NOT the number of supported lines on the phone(s))</font></i>
                                        </td>
                                    <?php endif; ?>
                                    </tr>
                                </table>
							</div>
						</div>
					</div>
	           </div>
           </div>
			<div class="col-md-6">
            	<div class="row">
             		<div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h3 class="panel-title"><i class="fa  fa-file-code-o fa-lg"></i> <?php echo _("Settings")?></h3>
                            </div>
                            <div class="panel-body">
                                <button type="button" class="btn btn-primary btn-lg btn-block" data-toggle="modal" data-target="#CfgGlobalTemplate"><i class='fa fa-pencil-square-o'></i> <?php echo _('Edit Global Settings Overrides')?></button>
                            </div>
                        </div>
                	</div>
				</div>
                <div class="row">
             		<div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h3 class="panel-title"><i class="fa  fa-file-code-o fa-lg"></i> <?php echo _("Edit File Configurations")?></h3>
                            </div>
                            <div class="panel-body">
                                <?php 
								
								//$list_all_files = array();
                                //if ($dtemplate['alt'] != 0) {
								//	$list_all_files = array_merge($dtemplate['alt_configs'], $dtemplate['only_configs']);
								//}
								//else {
								//	$list_all_files = $dtemplate['only_configs'];
								//}
                                
								
								$list_all_files = FreePBX::Endpointman()->_get_page('templates')->edit_template_display_files_list($_REQUEST['idsel'],$_REQUEST['custom']);
                                foreach($list_all_files as $row): 
								//config.php?display=endpointman&view=templates&subpage=&custom=0&idsel=41
								$row['idrefbox'] = $row['id']."_".$row['id_d']."_".$row['id_p'];
                                ?>
								<div class="col-md-12">
                                	<div class="input-group input-group-br files_edit_configs" name="boxselect_<?php echo epm_esc($row['idrefbox']); ?>" id="boxselect_<?php echo epm_esc($row['idrefbox']); ?>">
                                        <select 
                                        	class="form-control selectpicker show-tick" 
                                            data-url="ajax.php?module=endpointman&amp;view=templates&amp;command=list_files_edit&amp;idsel=<?php echo epm_esc($_REQUEST['idsel']); ?>&amp;custom=<?php echo epm_esc($_REQUEST['custom']); ?>&amp;namefile=<?php echo epm_esc($row['name']); ?>"
											data-cache="false"
                                            data-id = "id"
											data-label = "name"
                                            data-style = ""
											data-live-search-placeholder = "Search..."
											data-live-search = "true"
                                            name="config_files[<?php echo epm_esc($row['id']); ?>]" 
                                            id="config_files[<?php echo epm_esc($row['id']); ?>]">
                                        </select>
                                        <span class="input-group-btn">
                                            <button class="btn btn-default" type="button" name="bt_boxselect_<?php echo epm_esc($row['idrefbox']); ?>" id="bt_boxselect_<?php echo epm_esc($row['idrefbox']); ?>"><i class='fa fa-files-o'>&nbsp;</i></button>
                                        </span>
                                    </div>
								</div>
                                <?php endforeach; ?> 
                            </div>
                        </div>
	                </div>
                </div>
    		</div>
		</div>
	</div>
	
    
    
    
    
    
    
    
    
    

    
    
    
    
    
    
    
    
    
    
	<div class="">
		<div class="row">
			<div class="col-md-12">
            
				<div class="panel panel-default">
					<div class="panel-heading">
						<h3 class="panel-title"><i class="fa  fa-code"></i> <?php echo _("Example of Variables allowed in boxes")?></h3>
					</div>
					<div class="panel-body">
						<ul class='nobullets'>
							<li><code class='inline'>{$username.line.1}</code> <?php echo _('Device\'s Username (usually the extension)')?></li>
							<li><code class='inline'>{$displayname.line.1}</code> <?php echo _('Device\'s Description in FreePBX (Usually the Full Name)')?></li>
							<li><code class='inline'>{$server_host.line.1}</code> <?php echo _('Server IP For Line 1')?></li>
							<li><code class='inline'>{$server_port.line.1}</code> <?php echo _('Server Port For Line 1')?></li>
							<li><code class='inline'>{$mac}</code> <?php echo _('Device\'s Mac Address')?></li>
							<li><code class='inline'>{$model}</code> <?php echo _('Device\'s Model')?></li>
						</ul>
					</div>
				</div>
                
			</div>
		</div>
	</div>
	
	
	
	
	
    
	<div class="">
		<div class="row">
			<div class="col-md-12">
				<?php
				/*
				 * The tab strip uses the tab plugin of Bootstrap 4.6.1, which FreePBX 17
				 * serves. Each tab label is the title of its group.
				 *
				 * generate_gui_html() returns a string, not the group array, when the
				 * product has no template_data. Print that message.
				 */
				$epm_tabs = $dtemplate['template_editor'];
				if (! is_array($epm_tabs)) {
					// Kind 3 under lib/esc.inc.php, not data. The only string that
					// generate_gui_html() can return here is a literal in TemplatesPage.class.php
					// that ends in <br />.
					echo $epm_tabs;
					$epm_tabs = array();
				}
				$epm_tab_n = 0;
				?>
				<ul class="nav nav-tabs" role="tablist">
				<?php foreach($epm_tabs as $row): $epm_tab_n++; ?>
					<li class="nav-item" role="presentation">
						<a class="nav-link<?php echo $epm_tab_n == 1 ? ' active' : ''; ?>" id="epm-tab-<?php echo $epm_tab_n; ?>-label" href="#epm-tab-<?php echo $epm_tab_n; ?>" data-toggle="tab" role="tab" aria-controls="epm-tab-<?php echo $epm_tab_n; ?>" aria-selected="<?php echo $epm_tab_n == 1 ? 'true' : 'false'; ?>"><?php
						/* Some groups have no title. The lineloop case of
						 * generate_gui_html() names one group and then increments
						 * its counter, so the next group has only `data`. A tab
						 * without a label cannot be clicked, so give it a number. */
						echo isset($row['title']) ? epm_esc($row['title']) : sprintf(_("Section %d"), $epm_tab_n);
						?></a>
					</li>
				<?php endforeach; $epm_tab_n = 0; ?>
				</ul>
				<div class="tab-content">
				<?php foreach($epm_tabs as $row) : $epm_tab_n++; ?> <!-- INI foreach de tabs -->
					<div class="tab-pane<?php echo $epm_tab_n == 1 ? ' active' : ''; ?>" id="epm-tab-<?php echo $epm_tab_n; ?>" role="tabpanel" aria-labelledby="epm-tab-<?php echo $epm_tab_n; ?>-label">
                    	<?php 
						foreach($row['data'] as $srow) 	//INI foreach objetos de cada tab
						{
							switch ($srow['type']) {
								case 'input':
									//INI INPUT-TEXT
									?>
                                    <div class="element-container">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="row">
                                                    <div class="form-group">
                                                        <div class="col-md-3">
                                                            <label class="control-label" for="<?php echo epm_esc($srow['key']); ?>">
                                                            <?php 
                                                            if (! isset($srow['tooltip'])) { echo epm_esc($srow['description']); }
                                                            else { echo '<a href="#" class="info">'.epm_esc($srow['description']).'<span>'.epm_esc($srow['tooltip']).'</span></a>'; }
                                                            ?>
                                                            </label>
                                                            <i class="fa fa-question-circle fpbx-help-icon" data-for="<?php echo epm_esc($srow['key']); ?>"></i>
                                                        </div>
                                                        <div class="col-md-9">
															<input type="text" class="form-control" id="<?php echo epm_esc($srow['key']); ?>" name="<?php echo epm_esc($srow['key']); ?>" placeholder="" value="<?php echo epm_esc($srow['value']); ?>" size="<?php echo epm_esc(isset($srow['max_chars']) ? $srow['max_chars'] : "90"); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <span class="help-block fpbx-help-block" id="<?php echo epm_esc($srow['key']); ?>-help">Texto ayuda ("<?php echo epm_esc($srow['key']); ?>")!</span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
									//END INPUT-TEXT
									break;
									
								case 'textarea':
									//INI TEXTAREA
									?>
                                     <div class="element-container">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="row">
                                                    <div class="form-group">
                                                        <div class="col-md-3">
                                                            <label class="control-label" for="<?php echo epm_esc($srow['key']); ?>">
                                                            <?php 
                                                            if (! isset($srow['tooltip'])) { echo epm_esc($srow['description']); }
                                                            else { echo '<a href="#" class="info">'.epm_esc($srow['description']).'<span>'.epm_esc($srow['tooltip']).'</span></a>'; }
                                                            ?>
                                                            </label>
                                                            <i class="fa fa-question-circle fpbx-help-icon" data-for="<?php echo epm_esc($srow['key']); ?>"></i>
                                                        </div>
                                                        <div class="col-md-9">
	                      									<textarea class="form-control" id="<?php echo epm_esc($srow['key']); ?>" name="<?php echo epm_esc($srow['key']); ?>" rows="<?php echo epm_esc(isset($srow['rows']) ? $srow['rows'] : "2"); ?>" cols="<?php echo epm_esc(isset($srow['cols']) ? $srow['cols'] : "20"); ?>"><?php echo epm_esc($srow['value']); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <span class="help-block fpbx-help-block" id="<?php echo epm_esc($srow['key']); ?>-help">Texto ayuda ("<?php echo epm_esc($srow['key']); ?>")!</span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
									//END TEXTAREA
									break;
									
								case 'radio':
									//INI RADIO
									?>
                                     <div class="element-container">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="row">
                                                    <div class="form-group">
                                                        <div class="col-md-12">
                                                            <label class="control-label" for="<?php echo epm_esc($srow['key']); ?>">
                                                            <?php 
                                                            if (! isset($srow['tooltip'])) { echo epm_esc($srow['description']); }
                                                            else { echo '<a href="#" class="info">'.epm_esc($srow['description']).'<span>'.epm_esc($srow['tooltip']).'</span></a>'; }
                                                            ?>
                                                            </label>
                                                            <i class="fa fa-question-circle fpbx-help-icon" data-for="<?php echo epm_esc($srow['key']); ?>"></i>
                                                            <div class="radioset pull-xs-right">
                                                                <?php
																$z=0;
                                                                foreach($srow['data'] as $lrow) {
                                                                    /*
                                                                    echo '[<label>';
                                                                    echo (! isset($lrow['tooltip']) ? epm_esc($lrow['description']) : '<a href="#" class="info">'.epm_esc($lrow['description']).'<span>'.epm_esc($lrow['tooltip']).'</span></a>').':';
                                                                    //echo '<input type="radio" name="'.$lrow['key'].'" id="'.$lrow['key'].'" value="'.$lrow['value'].'" '.(array_key_exists('checked', $lrow['value']) ? $lrow['checked'] : '').' >';
                                                                    echo '<input type="radio" name="'.epm_esc($lrow['key']).'" id="'.epm_esc($lrow['key']).'" value="'.epm_esc($lrow['value']).'" >';
                                                                    echo '</label>]';
                                                                    
                                                                    <input type="radio" class="form-control" id="addtocdrno" name="addtocdr" value="0" <?php echo ($addtocdr == '1' ? '' : 'CHECKED'); ?>>
                                                                    <label for="addtocdrno"><?php echo _("No")?></label>
                                                                    */
                                                                    echo '<input type="radio" class="form-control" id="'.epm_esc($lrow['key'] . $z).'" name="'.epm_esc($srow['key']).'" value="'.epm_esc($lrow['value']).'" '.(array_key_exists('checked',$lrow)? 'checked' : '').' >';
                                                                    echo '<label for="'.epm_esc($lrow['key'] . $z).'">'.(! isset($lrow['tooltip']) ? epm_esc($lrow['description']) : '<a href="#" class="info">'.epm_esc($lrow['description']).'<span>'.epm_esc($lrow['tooltip']).'</span></a>').'</label>';
                                                                $z++;
																}
                                                                ?>
    
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <span class="help-block fpbx-help-block" id="<?php echo epm_esc($srow['key']); ?>-help">Texto ayuda ("<?php echo epm_esc($srow['key']); ?>")!</span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
									//END RAIDO
									break;
									
								case 'list':
									//INI LIST
									?>
                                    <div class="element-container">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="row">
                                                    <div class="form-group">
                                                        <div class="col-md-3">
                                                            <label class="control-label" for="<?php echo epm_esc($srow['key']); ?>">
                                                            <?php 
                                                            if (! isset($srow['tooltip'])) { echo epm_esc($srow['description']); }
                                                            else { echo '<a href="#" class="info">'.epm_esc($srow['description']).'<span>'.epm_esc($srow['tooltip']).'</span></a>'; }
                                                            ?>
                                                            </label>
                                                            <i class="fa fa-question-circle fpbx-help-icon" data-for="<?php echo epm_esc($srow['key']); ?>"></i>
                                                        </div>
                                                        <div class="col-md-9">
                                                        	<?php
	                    									echo '<select name="'.epm_esc($srow['key']).'" id="'.epm_esc($srow['key']).'" class="form-control selectpicker show-tick" data-style="" data-live-search-placeholder="Search" data-live-search="true">';
															foreach($srow['data'] as $lrow) 
															{
																//echo '<option value="'.$lrow['value'].'" '.(array_key_exists('selected',$lrow['selected'])? $lrow['value'] : '').' >'.$lrow['description'].'</option>';
																//echo '<option value="'.$lrow['value'].'" '.($lrow['value'] == $lrow['selected'] ? 'selected' : '').' >'.$lrow['description'].'</option>';
																echo '<option value="'.epm_esc($lrow['value']).'" '.(array_key_exists('selected',$lrow)? 'selected' : '').' >'.epm_esc($lrow['description']).'</option>';
															}
															echo '</select>';
															?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <span class="help-block fpbx-help-block" id="<?php echo epm_esc($srow['key']); ?>-help">Texto ayuda ("<?php echo epm_esc($srow['key']); ?>")!</span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
									//END LIST
									break;
									
									
								case 'checkbox':
									//INI CHEBOX
									//PENDIENTE UPATEAR.....
									?>
                                    <div class="element-container">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="row">
                                                    <div class="form-group">
                                                        <div class="col-md-3">
                                                            <label class="control-label" for="<?php echo epm_esc($srow['key']); ?>">
                                                            <?php 
                                                            if (! isset($srow['tooltip'])) { echo epm_esc($srow['description']); }
                                                            else { echo '<a href="#" class="info">'.epm_esc($srow['description']).'<span>'.epm_esc($srow['tooltip']).'</span></a>'; }
                                                            ?>
                                                            </label>
                                                            <i class="fa fa-question-circle fpbx-help-icon" data-for="<?php echo epm_esc($srow['key']); ?>"></i>
                                                        </div>
                                                        <div class="col-md-9">
															<input type="checkbox" class="form-control" name="<?php echo epm_esc($srow['key']); ?>" id="<?php echo epm_esc($srow['key']); ?>" value="<?php echo epm_esc($srow['value']); ?>">';
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <span class="help-block fpbx-help-block" id="<?php echo epm_esc($srow['key']); ?>-help">Texto ayuda ("<?php echo epm_esc($srow['key']); ?>")!</span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
									//END CHEKBOX
									break;
									
								case 'break':
									//INI BREAK
									?>
                                     <div class="element-container">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="row">
                                                    <div class="form-group">
                                                        <div class="col-md-12">&nbsp;</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
									//END BREAK
									break;
										
								case 'group':
									//INI group
									?>
                                     <div class="element-container">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="row">
                                                    <div class="form-group">
                                                        <div class="col-md-12">
                                                        	<hr>
															<?php echo '<h3>'.(! isset($srow['tooltip']) ? epm_esc($srow['description']) : '<a href="#" class="info">'.epm_esc($srow['description']).'<span>'.epm_esc($srow['tooltip']).'</span></a>').'</h3><br />'; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
									//END group
									break;
											
								case 'header':
									//INI HEADER
									?>
                                     <div class="element-container">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="row">
                                                    <div class="form-group">
                                                        <div class="col-md-12">
                                                        	<?php echo '<strong>'.(! isset($srow['tooltip']) ? epm_esc($srow['description']) : '<a href="#" class="info">'.epm_esc($srow['description']).'<span>'.epm_esc($srow['tooltip']).'</span></a>').'</strong><br/>'; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
									//END HEADER
									break;
							}

						} //END foreach objetos de cada tabs
						?>
	                    </div>
					<?php endforeach; ?> <!-- END foreach tabs -->
				</div>
    		</div>
	    </div>
    </div>
    
</form>
