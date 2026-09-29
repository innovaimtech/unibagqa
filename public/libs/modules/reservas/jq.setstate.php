<?php
//----------------------------------------------------------------------------------
require_once("../../classes/page.php");
require_once("../../classes/mysql.php");
require_once("../../config.php");


//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../lang/es.php");
require_once("../../functions.php");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
usleep(200000);
$_REQUEST["xid"]     = (int)$_REQUEST["xid"];
$_REQUEST["xstate"]  = (int)$_REQUEST["xstate"];
$currtme             = time();

//----------------------------------------------------------------------------------
if($_REQUEST["xtype"] == "pay")
{
   $sql = " update reservas_header
            set
            res_paystate   = {$_REQUEST["xstate"]},
            res_upddat     = {$currtme},
            res_updusr     = {$_SESSION["user_id"]}
            where
            id = {$_REQUEST["xid"]}";
   $CON->no_result($sql);
}
elseif($_REQUEST["xtype"] == "dlv")
{
   $sql = " update reservas_header
            set
            res_dlvstate   = {$_REQUEST["xstate"]},
            res_upddat     = {$currtme},
            res_updusr     = {$_SESSION["user_id"]}
            where
            id = {$_REQUEST["xid"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
$sql = " select res_paystate, res_dlvstate
         from reservas_header
         where
         id = {$_REQUEST["xid"]}";
$resstates = $CON->select($sql);
$resstates = $resstates[0];

if((int)$resstates["res_paystate"] && (int)$resstates["res_dlvstate"])
{
   $sql = " update reservas_header
            set
            res_status = 2
            where
            id = {$_REQUEST["xid"]}";
   $CON->no_result($sql);
}
else
{
   $sql = " update reservas_header
            set
            res_status = 1
            where
            id = {$_REQUEST["xid"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["xtype"] == "retiro")
{
   $sql = " update reservas_header
            set
            res_retiroshopid  = {$_REQUEST["xstate"]},
            res_upddat        = {$currtme},
            res_updusr        = {$_SESSION["user_id"]}
            where
            id = {$_REQUEST["xid"]}";
   $CON->no_result($sql);
}
?>