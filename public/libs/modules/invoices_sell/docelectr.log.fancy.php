<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/classes/invc.npgspf.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "facturaelectronica")
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from document_signature_detail
            where
            id_doc = {$_REQUEST["id"]} and
            dsd_companyid = {$_REQUEST["company_id"]} and 
            dsd_type = 33
            order by id ASC";
   $headdata = $CON->select($sql);
}
elseif($_REQUEST["mode"] == "credito")
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from document_signature_detail
            where
            id_doc = {$_REQUEST["id"]} and
            dsd_companyid = {$_REQUEST["company_id"]} and 
            dsd_type = 61
            order by id ASC";
   $headdata = $CON->select($sql);
}
elseif($_REQUEST["mode"] == "debito")
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from document_signature_detail
            where
            id_doc = {$_REQUEST["id"]} and
            dsd_companyid = {$_REQUEST["company_id"]} and 
            dsd_type = 56
            order by id ASC";
   $headdata = $CON->select($sql);
}
elseif($_REQUEST["mode"] == "guias")
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from document_signature_detail
            where
            id_doc = {$_REQUEST["id"]} and
            dsd_companyid = {$_REQUEST["company_id"]} and 
            dsd_type = 52
            order by id ASC";
   $headdata = $CON->select($sql);
}

//----------------------------------------------------------------------------------
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script language="Javascript">
      <?php
      require_once("../../../libs/jscripts/sourcen.php");
      ?>
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<?=Nifty_printH("box1", "490")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="140">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos: Log Actividad</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Fecha</td>
   <td class="content_tbl_subheader">Estado</td>
</tr>
<?php
for($x = 0; $x < count($headdata) && $headdata !== false; $x++)
{  ?>
   <tr>
      <td class="content_row"><?=$headdata[$x]["dsd_date"]?></td>
      <td class="content_row"><?=$headdata[$x]["dsd_status"]?></td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
</body>
</html>