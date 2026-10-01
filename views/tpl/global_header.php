<?php
/**
 * The header of the devices manager. devices_manager includes it.
 *
 * The file prints no variables. Each value below is a literal or a _() call,
 * also the two background-image URLs.
 */
?>
<html>
    <head>
        <title>PBX Endpoint Configuration Manager</title>
        <style>
            .tooltip {
                display:none;
                background:transparent url('assets/endpointman/images/black_arrow.png');
                font-size:12px;
                height:70px;
                width:160px;
                padding:25px;
                color:#fff;
            }
            #spinner
            {
                display:none;
                width:200px;
                height: 200px;
                position: fixed;
                top: 40%;
                left: 55%;
                background:url('assets/endpointman/images/ajax-loader.gif') no-repeat center #fff;
                text-align:center;
                padding:10px;
                font:normal 16px Tahoma, Geneva, sans-serif;
                margin-left: -50px;
                margin-top: -50px;
                z-index:2;
                overflow: auto;
                background-color:#f8f8ff;
                border: 1px solid #aaaaff;
            }
        </style>
    </head>
    <body face="Arial">
        <div id="spinner"></div>
        <h2><?php echo _('End Point Configuration Manager')?></h2>
