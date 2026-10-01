<?php if (!defined('FREEPBX_IS_AUTH')) die('No direct script access allowed'); ?>

<nav class="navbar navbar-default">
	<div class="container-fluid">
    	<div class="navbar-header">
        	<a class="navbar-brand" href="#"><i class="fa fa-file-archive-o"></i></a>
		</div>

        <button type="button" class="navbar-btn btn btn-default" id="button_check_for_updates" name="button_check_for_updates" disabled="false"><i class="fa fa-refresh"></i> <?php echo _("Check for Update"); ?></button>

        <form class="nav navbar-form navbar-right" role="search">
            <div class="form-group">
                <input id="search" type="text" class="form-control" placeholder="<?php echo _("Search Model..."); ?>" />
            </div>
            <button class="btn btn-info btn-lg" type="button"><i class="fa fa-search" aria-hidden="true"></i></button>
        </form>
	</div>
</nav>

<div class="alert alert-info" role="alert">
	<h3><?php echo _("Hidden Packages") ?></h3>
    <div class="input-group">
        <span class="input-group-addon"><?php echo _("Hidden Brands:") ?></span>
        <select
			data-url="ajax.php?module=endpointman&view=packages&command=list_brand_model_hide"
            data-cache="false"
            data-id = ""
			data-label = ""
            data-selected-text-format="count > 4"
            data-size="10"
            data-style=""
            data-live-search-placeholder="<?php echo _("Search") ?>""
            data-live-search="true"
            data-done-button="true"
            class="selectpicker show-tick form-control"
            id="epm_config_manager_select_hidens"
            name="epm_config_manager_select_hidens"
            multiple>
        </select>
        <span class="input-group-addon"><span class="help"><i class="fa fa-question-circle"></i><span style="display: none;"><?php echo _("This list contains all the Brands <br /> that have been hidden.  Clicking on any of them will unhide them.") ?></span></span></span>
    </div>
</div>

<div class="panel panel-primary">
	<div class="panel-heading">
		<h3 class="panel-title"><?php echo _("List Packages Manager") ?></h3>
	</div>
	<div class="panel-body">
		<!-- INI PANEL-BODY -->
		<ul class="list-group" id="epm_config_manager_list_loading">
			<li class="list-group-item text-center bg-info"><i class="fa fa-spinner fa-pulse"></i>&nbsp; <?php echo _("Loading...")?></li>
		</ul>
		<div id="epm_config_manager_all_list_box"></div>
        <!-- END PANEL-BODY -->
	</div>
</div>
