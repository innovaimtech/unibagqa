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
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting(0);

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
$sql = " select distinct assign_month, assign_year
         from turnos_config_assign";
$assmonths = $CON->select($sql);
foreach($assmonths AS $assmonth)
   $_ASSMONTHS[$assmonth["assign_year"]][$assmonth["assign_month"]] = 1;
   
$sql = " select *
         from turnos_types
         where
         type_status > 0
         order by type_name";
$ttypes = $CON->select($sql);
foreach($ttypes AS $ttype)
   $_TTYPES[$ttype["type_name_short"]] = $ttype;
   
if($_REQUEST["exec"] == "save")
{
   if($_FILES["xls_file"]["name"] != "" &&
      $_FILES["xls_file"]["tmp_name"] != "" &&
      $_FILES["xls_file"]["error"] == 0 &&
      $_FILES["xls_file"]["size"] > 0)
   {
      $doc_type   = strtolower(substr($_FILES["xls_file"]["name"], strrpos($_FILES["xls_file"]["name"], ".") +1));
      $doc_hash   = md5(microtime());
      $doc_name   = "{$_REQUEST["id"]}.import.{$doc_hash}.{$doc_type}";
      $doc_dir    = "../../../docs.tmp/";

      if($doc_type == "xls" || $doc_type == "xlsx")
         $res = move_uploaded_file($_FILES["xls_file"]["tmp_name"], "{$doc_dir}{$doc_name}");
      else
         $res = false;

      if($res)
      {
         require_once('../../../libs/thirdparty/phpexcel_1.7.6/Classes/PHPExcel.php');
         require_once('../../../libs/thirdparty/phpexcel_1.7.6/Classes/PHPExcel/Reader/Excel2007.php');

         if($doc_type == "xlsx")
            $objReader = new PHPExcel_Reader_Excel2007();
         elseif($doc_type == "xls")
            $objReader = new PHPExcel_Reader_Excel5();

         $objReader->setReadDataOnly(true);
         $objPHPExcel   = $objReader->load($doc_dir.$doc_name);
         $objWorksheet  = $objPHPExcel->getActiveSheet();
         $rowcc         = $objWorksheet->getHighestRow();
         $colcc         = $objWorksheet->getHighestColumn();

         $intcolcc = 0;
         for($x = "A"; $x != $colcc; $x++)
            $intcolcc++;


         $rowidx = 3;
         $colidx = 3;
         for($x = $colidx; $x <= $intcolcc; $x++)
         {
            $daystr = trim($objWorksheet->getCellByColumnAndRow($x,$rowidx)->getValue());
            if($daystr != "")
            {
               $dayarr = explode(".", $daystr);

               $_COLDATES[$x]["DAYSTR"] = $daystr;
               $_COLDATES[$x]["DAYARR"] = $dayarr;
            }
         }
            
         for($y = 5; $y <= $rowcc; $y++)
         {
            $rowidx  = $y;

            $idsvals  = trim($objWorksheet->getCellByColumnAndRow(0,$rowidx)->getValue());
            $idsvals  = explode("_", $idsvals);
            $plantaid = (int)$idsvals[0];
            $turnoid  = (int)$idsvals[1];
            if((int)$turnoid && (int)$plantaid)
            {
               $colidx = 3;
               for($x = $colidx; $x <= $intcolcc; $x++)
               {
                  $daydata = $_COLDATES[$x];
                  $cellval = trim($objWorksheet->getCellByColumnAndRow($x,$rowidx)->getValue());
                  $ttypeid = (int)$_TTYPES[$cellval]["id"];

                  $_VALID = false;
                  if(((int)$ttypeid || $cellval == "") && $daydata["DAYSTR"] != "")
                     $_VALID = true;

                  if($_VALID)
                  {
                     $cfg_datestr   = $daydata["DAYSTR"];
                     $cfg_datestamp = mktime(15, 0, 0, $daydata["DAYARR"][1], $daydata["DAYARR"][0], $daydata["DAYARR"][2]);

                     if(!(int)$_ASSMONTHS[(int)date("Y", $cfg_datestamp)][(int)date("m", $cfg_datestamp)])
                     {
                        $sql = " delete from turnos_config
                                 where
                                 cfg_turno_id  = {$turnoid} and
                                 cfg_datestr   = '{$cfg_datestr}' and
                                 cfg_planta_id = {$plantaid} ";
                        $CON->no_result($sql);

                        if((int)$ttypeid)
                        { 
                           $sql = " insert into turnos_config
                                    (cfg_planta_id, cfg_turno_id, cfg_turno_type_id, cfg_datestr, cfg_datestamp)
                                    VALUES
                                    ({$plantaid}, {$turnoid}, {$ttypeid}, '{$cfg_datestr}', {$cfg_datestamp})";
                           $CON->no_result($sql);
                        }
                     }
                  }
                  else
                  {
                     $dspcol = $x+1;
                     $_ERRSTR .= "{$y}:{$dspcol},";
                  }
               }
            }
         }

         @unlink($doc_dir.$doc_name);
         $filecheck = true;
      }
      else
         $filecheck = false;
   }
   else
      $filecheck = false;

   if($_ERRSTR != "")
   {
      $_ERRSTR = substr($_ERRSTR, 0, -1);
      $msgs = "ADVERTENCIA: ERRORES EN LINEA/COL {$_ERRSTR}";
      ?>
      <script language="JavaScript">
         alert('<?=substr($msgs, 0, 125)?>');
         parent.document.xform_itemsearch.subexec.value='search';
         parent.document.xform_itemsearch.submit();
      </script>
      <?php
   }
   else
   {  ?>
      <script language="JavaScript">
         alert('IMPORTACION EJECUTADA EXITOSAMENTE.');
         parent.document.xform_itemsearch.subexec.value='search';
         parent.document.xform_itemsearch.submit();
      </script>
      <?php
   }
   exit;
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
<script language="JavaScript">
   <?php
   if($_REQUEST["exec"] == "save")
   {  ?>
      
      <?php
   }
   ?>
</script>
<table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
<tr>
   <td align="center" valign="top" height="100%">
      <form action="upload.xls.fancy.php" method="post" class="fokusfirst" name="xform_xls" autocomplete="off"
      onsubmit="return checkform(new Array(this.xls_file))" enctype="multipart/form-data">
      <input type="hidden" name="exec" value="save">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="smid" value="<?=$_REQUEST["smid"]?>">
      <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
      <input type="hidden" name="frmname" value="<?=$_REQUEST["frmname"]?>">
      <br>
      <?=Nifty_printH("box1", "99%")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_header" colspan="2">Subir archivo</td>
      </tr>
      <tr>
         <td class="content_rowl" width="100">Archivo XLSX *</td>
         <td class="content_row">
            <input class="text" type="file" name="xls_file" maxlength="100" style="width:120px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?=Nifty_printH("boxopt_b", "99%")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear" align="right">
            <?php
            printButton("Subir", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_xls)", "tick-circle-frame", 200);
            ?>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
</table>
</body>
</html>