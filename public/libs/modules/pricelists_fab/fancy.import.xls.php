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

if($_REQUEST["exec"] == "save")
{
   //----------------------------------------------------------------------------------
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
         if((int)$_REQUEST["reempl"])
         {
            $sql = " delete from price_lists_fab_items_prices
                     where
                     pl_id = {$_REQUEST["id"]}";
            $CON->no_result($sql);
            
            $sql = " select *
                     from price_lists_fab_items
                     where
                     pl_id = {$_REQUEST["id"]}";
            $fabitems = $CON->select($sql);
            foreach($fabitems AS $fabitem)
            {
               $sql = " delete from price_lists_fab_items_predefines
                        where
                        header_id = {$fabitem["id"]}";
               $CON->no_result($sql);

               $sql = " delete from price_lists_fab_items
                        where
                        id = {$fabitem["id"]}";
               $CON->no_result($sql);
            }
         }
         
         require_once('../../thirdparty/PHPExcel_1.8.0_doc/Classes/PHPExcel.php');
         require_once('../../thirdparty/PHPExcel_1.8.0_doc/Classes/PHPExcel/Reader/Excel2007.php');

         if($doc_type == "xlsx")
            $objReader = new PHPExcel_Reader_Excel2007();
         elseif($doc_type == "xls")
            $objReader = new PHPExcel_Reader_Excel5();

         $objReader->setReadDataOnly(true);
         $objPHPExcel   = $objReader->load($doc_dir.$doc_name);
         $objWorksheet  = $objPHPExcel->getActiveSheet();
         $rowcc         = $objWorksheet->getHighestRow();

         $sql = " select t1.*
                  from price_lists_fab_amounts t1
                  where
                  t1.pl_id      = {$_REQUEST["id"]} and
                  t1.amt_status  = 1
                  order by t1.amt_val";
         $amounts = $CON->select($sql);
         foreach($amounts AS $amount)
            $_PLAMOUNTS[(int)$amount["amt_val"]] = 1;

         for($x = 18; $x <= 60; $x++)
         {
            $xlsamt = (int)str_replace(".", "", addslashes(trim($objWorksheet->getCellByColumnAndRow($x,1)->getValue())));
            if($xlsamt > 0 && (int)$_PLAMOUNTS[$xlsamt])
               $_PRICECOLS[$x] = $xlsamt;
         }

         $curtime = time();
         $_RES    = Array();
         for($x = 2; $x <= $rowcc; $x++)
         {
            unset($temp);

            $temp["idint"]                = (int)addslashes(trim($objWorksheet->getCellByColumnAndRow(0,$x)->getValue()));
            if((int)$_REQUEST["reempl"])
               $temp["idint"] = 0;
               
            $temp["fab_type"]             = addslashes(trim($objWorksheet->getCellByColumnAndRow(1,$x)->getValue()));
            $temp["item_number_prod"]     = addslashes(trim($objWorksheet->getCellByColumnAndRow(2,$x)->getValue()));
            $temp["item_title"]           = addslashes(trim(mb_convert_encoding($objWorksheet->getCellByColumnAndRow(3,$x)->getValue(), "ISO-8859-1", "UTF-8")));
            $temp["inc_name"]             = addslashes(trim(mb_convert_encoding($objWorksheet->getCellByColumnAndRow(4,$x)->getValue(), "ISO-8859-1", "UTF-8")));
            $temp["fab_desc"]             = addslashes(trim(mb_convert_encoding($objWorksheet->getCellByColumnAndRow(5,$x)->getValue(), "ISO-8859-1", "UTF-8")));
            $temp["fab_med_width"]        = (int)addslashes(trim($objWorksheet->getCellByColumnAndRow(6,$x)->getValue()));
            $temp["fab_med_height"]       = (int)addslashes(trim($objWorksheet->getCellByColumnAndRow(7,$x)->getValue()));
            $temp["fab_med_fuelle"]       = (int)addslashes(trim($objWorksheet->getCellByColumnAndRow(8,$x)->getValue()));
            $temp["fab_min_amt"]          = (int)addslashes(trim($objWorksheet->getCellByColumnAndRow(9,$x)->getValue()));
            $temp["fab_corte_machine"]    = (int)addslashes(trim($objWorksheet->getCellByColumnAndRow(10,$x)->getValue()));
            $temp["fab_roll_width"]       = (int)addslashes(trim($objWorksheet->getCellByColumnAndRow(11,$x)->getValue()));
            $temp["fab_fabric_gr"]        = (int)addslashes(trim($objWorksheet->getCellByColumnAndRow(12,$x)->getValue()));
            $temp["fab_manilla_length"]   = (int)addslashes(trim($objWorksheet->getCellByColumnAndRow(13,$x)->getValue()));
            $temp["fab_print_width"]      = (int)addslashes(trim($objWorksheet->getCellByColumnAndRow(14,$x)->getValue()));
            $temp["fab_print_height"]     = (int)addslashes(trim($objWorksheet->getCellByColumnAndRow(15,$x)->getValue()));
            $temp["fab_noprint_discount"] = (int)addslashes(trim($objWorksheet->getCellByColumnAndRow(16,$x)->getValue()));
            $temp["fab_active"]           = 0;
            
            if(strtoupper(addslashes(trim($objWorksheet->getCellByColumnAndRow(17,$x)->getValue()))) == "X")
               $temp["fab_active"] = 1;

            foreach(array_keys($_PRICECOLS) AS $colidx)
            {
               $xlsamt = (int)str_replace(".", "", addslashes(trim($objWorksheet->getCellByColumnAndRow($colidx,$x)->getValue())));
               if($xlsamt > 0)
               {
                  $temp["_fabamts_{$_PRICECOLS[$colidx]}"] = $xlsamt;
               }
            }
               
            $_RES[] = $temp;
         }
         @unlink($doc_dir.$doc_name);
         $filecheck = true;
      }
      else
         $filecheck = false;
   }
   else
      $filecheck = false;

   $stop = false;
   $_ERR = false;
   for($x = 0; $x < count($_RES) && !$stop; $x++)
   {
      $row = $_RES[$x];

      //---------------------------------------------------------------------------------
      $sql = " select id
               from item
               where
               item_status          > 0 and
               item_fabricate_act   > 0 and
               item_number_prod     = '{$row["item_number_prod"]}'
               order by id desc";
      $fab_item_id = $CON->select($sql);
      $fab_item_id = (int)$fab_item_id[0]["id"];
      $_RES[$x]["_fab_item_id"] = $fab_item_id;
      if(!$fab_item_id)
      {  ?>
         <script language="JavaScript">
            alert("Error: Producto <?=$row["item_number_prod"]?> no existe.");
            parent.location.href = '/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=prices&id=<?=$_REQUEST["id"]?>';
         </script>
         <?php
         $stop = true;
         $_ERR = true;
      }

      //---------------------------------------------------------------------------------
      if(!$stop)
      {
         $sql = " select fabt_status
                  from fabric_types
                  where
                  fabt_status > 0 and
                  fabt_code   = '{$row["fab_type"]}'";
         $fabrictypestatus = $CON->select($sql);
         $fabrictypestatus = (int)$fabrictypestatus[0]["fabt_status"];

         if(!$fabrictypestatus)
         {  ?>
            <script language="JavaScript">
               alert("Error: Tipo de producto <?=$row["fab_type"]?> no existe.");
               parent.location.href = '/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=prices&id=<?=$_REQUEST["id"]?>';
            </script>
            <?php
            $stop = true;
            $_ERR = true;
         }
      }

      //---------------------------------------------------------------------------------
      if(!$stop)
      {
         $sql = " select t1.id
                  from price_lists_fab_increments t1
                  where
                  t1.pl_id       = {$_REQUEST["id"]} and
                  t1.inc_status  = 1 and
                  t1.inc_name    = '{$row["inc_name"]}'";
         $fab_inc_id = $CON->select($sql);
         $fab_inc_id = (int)$fab_inc_id[0]["id"];
         $_RES[$x]["_fab_inc_id"] = $fab_inc_id;
         if(!$fab_inc_id)
         {  ?>
            <script language="JavaScript">
               alert("Error: Incremento <?=$row["inc_name"]?> no existe.");
               parent.location.href = '/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=prices&id=<?=$_REQUEST["id"]?>';
            </script>
            <?php
            $stop = true;
            $_ERR = true;
         }
      }
   }

   //---------------------------------------------------------------------------------
   if(!$_ERR && count($_RES))
   {
      for($x = 0; $x < count($_RES) && !$stop; $x++)
      {
         $row = $_RES[$x];

         $prc_headerid = (int)$row["idint"];
         if((int)$row["idint"])
         {
            $sql = " update price_lists_fab_items
                     set
                     fab_item_id          = {$row["_fab_item_id"]},
                     fab_type             = '{$row["fab_type"]}',
                     fab_desc             = '{$row["fab_desc"]}',
                     fab_med_width        = {$row["fab_med_width"]},
                     fab_med_height       = {$row["fab_med_height"]},
                     fab_med_fuelle       = {$row["fab_med_fuelle"]},
                     fab_min_amt          = {$row["fab_min_amt"]},
                     fab_corte_machine    = {$row["fab_corte_machine"]},
                     fab_roll_width       = {$row["fab_roll_width"]},
                     fab_fabric_gr        = {$row["fab_fabric_gr"]},
                     fab_manilla_length   = {$row["fab_manilla_length"]},
                     fab_print_width      = {$row["fab_print_width"]},
                     fab_print_height     = {$row["fab_print_height"]},
                     fab_noprint_discount = {$row["fab_noprint_discount"]},
                     fab_active           = {$row["fab_active"]},
                     fab_inc_id           = {$row["_fab_inc_id"]}
                     where
                     pl_id = {$_REQUEST["id"]} and
                     id    = {$row["idint"]}";
            $res = $CON->no_result($sql);
         }
         else
         {
            $sql = " insert into price_lists_fab_items
                     (pl_id, fab_item_id, fab_type, fab_desc, fab_med_width, fab_med_height, fab_med_fuelle,
                      fab_min_amt, fab_corte_machine, fab_roll_width, fab_fabric_gr, fab_manilla_length,
                      fab_print_width, fab_print_height, fab_noprint_discount, fab_active, fab_inc_id)
                     VALUES
                     ({$_REQUEST["id"]}, {$row["_fab_item_id"]}, '{$row["fab_type"]}', '{$row["fab_desc"]}', {$row["fab_med_width"]},
                      {$row["fab_med_height"]}, {$row["fab_med_fuelle"]}, {$row["fab_min_amt"]}, {$row["fab_corte_machine"]}, {$row["fab_roll_width"]},
                      {$row["fab_fabric_gr"]}, {$row["fab_manilla_length"]}, {$row["fab_print_width"]}, {$row["fab_print_height"]},
                      {$row["fab_noprint_discount"]}, {$row["fab_active"]}, {$row["_fab_inc_id"]})";
            $res = $CON->no_result($sql);
            if($res)
               $prc_headerid = mysql_insert_id();
         }
         if($res)
         {
            $sql = " delete from price_lists_fab_items_prices
                     where
                     pl_id = {$_REQUEST["id"]} and
                     prc_headerid = {$prc_headerid}";
            $CON->no_result($sql);
               
            foreach(array_keys($_PLAMOUNTS) AS $amtval)
            {
               if((int)$row["_fabamts_{$amtval}"] > 0)
               {
                  $sql = " insert into price_lists_fab_items_prices
                           (prc_headerid, pl_id, prc_amount, prc_price)
                           VALUES
                           ({$prc_headerid}, {$_REQUEST["id"]}, {$amtval}, {$row["_fabamts_{$amtval}"]})";
                  $CON->no_result($sql);
               }
            }
         }
      }
   }

   //---------------------------------------------------------------------------------
   if(!$_ERR)
   {  ?>
      <script language="JavaScript">
         alert("El archivo fue procesado exitosamente.");
         parent.location.href = '/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=prices&id=<?=$_REQUEST["id"]?>';
      </script>
      <?php
   }
   exit;
}

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
      function setSelData(idx)
      {
      }
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<form action="fancy.import.xls.php" method="post" class="fokusfirst" name="xform_xls" autocomplete="off"
onsubmit="return checkform(new Array(this.xls_file))" enctype="multipart/form-data">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="smid" value="<?=$_REQUEST["smid"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="frmname" value="<?=$_REQUEST["frmname"]?>">
<input type="hidden" name="reempl" value="<?=$_REQUEST["reempl"]?>">
<div style="height:3px"></div>
<?=Nifty_printH("box1", "100%")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<tr>
   <td class="content_tbl_header" colspan="2">Subir archivo</td>
</tr>
<tr>
   <td class="content_rowl">Archivo *</td>
   <td class="content_row">
      <input class="text" type="file" name="xls_file" maxlength="100" style="width:200px"
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
</body>
</html>
