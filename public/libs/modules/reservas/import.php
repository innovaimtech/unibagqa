<?php
// $sql = "truncate reservas_header";$CON->no_result($sql);
// $sql = "truncate reservas_pos";$CON->no_result($sql);

$_ERRORS = Array();
$_IMPORT = Array();
$currtme = time();
if($_REQUEST["exec"] == "save")
{
   if($_FILES["xls_file"]["name"] != "" &&
      $_FILES["xls_file"]["tmp_name"] != "" &&
      $_FILES["xls_file"]["error"] == 0 &&
      $_FILES["xls_file"]["size"] > 0)
   {
      $doc_type   = strtolower(substr($_FILES["xls_file"]["name"], strrpos($_FILES["xls_file"]["name"], ".") +1));
      $doc_hash   = md5(microtime());
      $doc_name   = "import.{$doc_hash}.{$doc_type}";
      $doc_dir    = "./docs.import/";

      if($doc_type == "xls" || $doc_type == "xlsx" || $doc_type == "csv")
         $res = move_uploaded_file($_FILES["xls_file"]["tmp_name"], "{$doc_dir}{$doc_name}");
      else
         $res = false;

      if($res)
      {

         if($doc_type == "csv")
         {
            $_RDATA = Array();
            $csvdata = explode("\n", file_get_contents("{$doc_dir}{$doc_name}"));
            for($x = 1; $x < count($csvdata); $x++)
            {
               $row = csv_explode(",",  mb_convert_encoding($csvdata[$x], "ISO-8859-1", "UTF-8"));
               foreach(array_keys($row) AS $rowline)
                  $row[$rowline] = str_replace('"', "", $row[$rowline]);

               $temp["res_id"]            = $row[0];
               $temp["res_date"]          = trim(addslashes($row[1]." ".$row[2]));
               $temp["res_custname"]      = trim(addslashes($row[3]));
               $temp["res_city"]          = trim(addslashes($row[7]));
               $temp["res_street"]        = trim(addslashes($row[8]));
               $temp["res_plz"]           = trim(addslashes($row[9]));
               $temp["res_phone"]         = trim(addslashes($row[17]));
               $temp["res_mail"]          = trim(addslashes($row[19]));
               $temp["res_itemdesc"]      = trim(addslashes($row[21]));
               $temp["res_itemcode"]      = trim(addslashes($row[23]));
               $temp["res_itemamt"]       = (int)trim(addslashes($row[24]));
               $temp["res_itemprice"]     = (int)trim(addslashes($row[25]))*$temp["res_itemamt"];
               $temp["res_delivprice"]    = (int)trim(addslashes($row[30]));
               $temp["res_paydesc"]       = trim(addslashes($row[34]));
               $temp["res_state"]         = trim(addslashes($row[35]));
               $temp["res_fulfilled"]     = trim(addslashes($row[36]));
               $temp["res_amount"]        = (int)trim(addslashes($row[38]));

               if($temp["res_id"] != "" && $temp["res_custname"] != "")
                  $_RDATA[$temp["res_id"]][] = $temp;

            }

            foreach(array_keys($_RDATA) AS $res_id)
            {
               $sql = " select count(*) 'cc'
                        from reservas_header
                        where
                        res_status  > 0 and
                        res_type    = 'XLS' and
                        (
                           res_csv_tienda = '{$_REQUEST["res_csv_tienda"]}' or
                           res_csv_tienda = ''
                        ) and
                        res_id      = '{$res_id}'";
               $exists = $CON->select($sql);
               $exists = $exists[0]["cc"];

               if(!(int)$exists)
               {
                  $firstitem = $_RDATA[$res_id][0];

                  $res_paystate = 0;
                  if($firstitem["res_state"] == "paid")
                     $res_paystate = 1;
                  
                  $sql = " insert into reservas_header
                           (res_type, res_id, res_date, res_custname, res_city, res_street, res_plz, res_phone, res_mail,
                            res_delivprice, res_amount, res_paydesc, res_state, res_fulfilled, res_crtdat, res_crtusr, res_paystate,
                            res_csv_tienda)
                           VALUES
                           ('XLS', '{$res_id}', '{$firstitem["res_date"]}', '{$firstitem["res_custname"]}', '{$firstitem["res_city"]}',
                            '{$firstitem["res_street"]}', '{$firstitem["res_plz"]}', '{$firstitem["res_phone"]}', '{$firstitem["res_mail"]}',
                            {$firstitem["res_delivprice"]}, {$firstitem["res_amount"]}, '{$firstitem["res_paydesc"]}', '{$firstitem["res_state"]}',
                            '{$firstitem["res_fulfilled"]}', {$currtme}, {$_SESSION["user_id"]}, {$res_paystate}, '{$_REQUEST["res_csv_tienda"]}')";
                  $xret = $CON->no_result($sql);
                  
                  if($xret)
                  {
                     $pos_header_id = mysql_insert_id();

                     foreach($_RDATA[$res_id] AS $subitem)
                     {
                        $sql = " insert into reservas_pos
                                 (pos_header_id, pos_itemdesc, pos_itemamt, pos_itemprice, pos_code)
                                 VALUES
                                 ({$pos_header_id}, '{$subitem["res_itemdesc"]}', {$subitem["res_itemamt"]},
                                  {$subitem["res_itemprice"]}, '{$subitem["res_itemcode"]}')";
                        $CON->no_result($sql);
                     }
                  }
                  $_IMPORT[] = "Reserva ID <b>{$res_id}</b> fue importado exitosamente.";
               }
               else
                  $_ERRORS[] = "Reserva ID <b>{$res_id}</b> ya existe.";
            }
            
            /*
            $csvdata = explode("\n", file_get_contents("{$doc_dir}{$doc_name}"));
            for($x = 1; $x < count($csvdata); $x++)
            {
               $row = explode(";", mb_convert_encoding($csvdata[$x], "ISO-8859-1", "UTF-8"));

               $res_type         = "CSV";
               $res_id           = $row[0];
               $res_ref          = $row[1];
               $res_newcustomer  = (int)$row[2];
               $res_custname     = trim(addslashes($row[4]));
               $res_amount       = preg_replace("/[^0-9]/", "",$row[6]);
               $res_paydesc      = trim(addslashes($row[7]));
               $res_state        = trim(addslashes($row[8]));
               $res_date         = trim($row[9]);

               if($res_id != "" && $res_ref != "" && $res_amount > 0.00)
               {
                  $sql = " select count(*) 'cc'
                           from reservas_header
                           where
                           res_status  > 0 and
                           res_type    = 'CSV' and
                           res_id      = '{$res_id}' and
                           res_ref     = '{$res_ref}'";
                  $exists = $CON->select($sql);
                  $exists = $exists[0]["cc"];

                  if(!(int)$exists)
                  {
                     $sql = " insert into reservas_header
                              (res_type, res_id, res_ref, res_newcustomer, res_custname, res_status, res_crtdat,
                               res_crtusr, res_amount, res_paydesc, res_state, res_date)
                              VALUES
                              ('CSV', '{$res_id}', '{$res_ref}', {$res_newcustomer}, '{$res_custname}', 1,
                               {$currtme}, {$_SESSION["user_id"]}, {$res_amount}, '{$res_paydesc}', '{$res_state}',
                               '{$res_date}')";
                     $CON->no_result($sql);
                     $_IMPORT[] = "Reserva ID <b>{$res_id}</b>, Referencia <b>{$res_ref}</b> fue importado exitosamente.";
                  }
                  else
                     $_ERRORS[] = "Reserva ID <b>{$res_id}</b>, Referencia <b>{$res_ref}</b> ya existe.";
               }
            }
            */
         }
         elseif($doc_type == "xls" || $doc_type == "xlsx")
         {
            /*
            require_once('./libs/thirdparty/PHPExcel_1.8.0_doc/Classes/PHPExcel.php');
            require_once('./libs/thirdparty/PHPExcel_1.8.0_doc/Classes/PHPExcel/Reader/Excel2007.php');

            if($doc_type == "xlsx")
               $objReader = new PHPExcel_Reader_Excel2007();
            elseif($doc_type == "xls")
               $objReader = new PHPExcel_Reader_Excel5();

            $objReader->setReadDataOnly(true);
            $objPHPExcel   = $objReader->load($doc_dir.$doc_name);
            $objWorksheet  = $objPHPExcel->getActiveSheet();
            $rowcc         = $objWorksheet->getHighestRow();

            $_RDATA = Array();
            for($x = 2; $x <= $rowcc; $x++)
            {
               $idx = addslashes(trim($objWorksheet->getCellByColumnAndRow(0,$x)->getValue()));

               $xtime = (int)utf8_decode(PHPExcel_Shared_Date::ExcelToPHP($objWorksheet->getCellByColumnAndRow(2,$x)->getValue()));
               $xtime  = $xtime - date('Z', $xtime);
               
               $temp["res_id"]            = $idx;
               $temp["res_date"]          = addslashes(trim($objWorksheet->getCellByColumnAndRow(1,$x)->getValue()))." ".date("H:i:s", $xtime);
               $temp["res_custname"]      = mb_convert_encoding(trim(addslashes($objWorksheet->getCellByColumnAndRow(3,$x)->getValue())), "ISO-8859-1", "UTF-8");
               $temp["res_city"]          = mb_convert_encoding(trim(addslashes($objWorksheet->getCellByColumnAndRow(7,$x)->getValue())), "ISO-8859-1", "UTF-8");
               $temp["res_street"]        = mb_convert_encoding(trim(addslashes($objWorksheet->getCellByColumnAndRow(8,$x)->getValue())), "ISO-8859-1", "UTF-8");
               $temp["res_plz"]           = mb_convert_encoding(trim(addslashes(str_replace('"', "", $objWorksheet->getCellByColumnAndRow(9,$x)->getValue()))), "ISO-8859-1", "UTF-8");
               $temp["res_phone"]         = mb_convert_encoding(trim(addslashes(str_replace('"', "", $objWorksheet->getCellByColumnAndRow(17,$x)->getValue()))), "ISO-8859-1", "UTF-8");
               $temp["res_mail"]          = mb_convert_encoding(trim(addslashes(str_replace('"', "", $objWorksheet->getCellByColumnAndRow(19,$x)->getValue()))), "ISO-8859-1", "UTF-8");
               $temp["res_itemdesc"]      = mb_convert_encoding(trim(addslashes($objWorksheet->getCellByColumnAndRow(21,$x)->getValue())), "ISO-8859-1", "UTF-8");
               $temp["res_itemamt"]       = (int)$objWorksheet->getCellByColumnAndRow(24,$x)->getValue();
               $temp["res_itemprice"]     = (int)$objWorksheet->getCellByColumnAndRow(25,$x)->getValue();
               $temp["res_delivprice"]    = (int)$objWorksheet->getCellByColumnAndRow(30,$x)->getValue();
               $temp["res_paydesc"]       = mb_convert_encoding(trim(addslashes(str_replace('"', "", $objWorksheet->getCellByColumnAndRow(34,$x)->getValue()))), "ISO-8859-1", "UTF-8");
               $temp["res_state"]         = mb_convert_encoding(trim(addslashes(str_replace('"', "", $objWorksheet->getCellByColumnAndRow(35,$x)->getValue()))), "ISO-8859-1", "UTF-8");
               $temp["res_fulfilled"]     = mb_convert_encoding(trim(addslashes(str_replace('"', "", $objWorksheet->getCellByColumnAndRow(36,$x)->getValue()))), "ISO-8859-1", "UTF-8");
               $temp["res_amount"]        = (int)$objWorksheet->getCellByColumnAndRow(38,$x)->getValue();

               $_RDATA[$idx][] = $temp;
            }
            
            foreach(array_keys($_RDATA) AS $res_id)
            {
               $sql = " select count(*) 'cc'
                        from reservas_header
                        where
                        res_status  > 0 and
                        res_type    = 'XLS' and
                        res_id      = '{$res_id}'";
               $exists = $CON->select($sql);
               $exists = $exists[0]["cc"];

               if(!(int)$exists)
               {
                  $firstitem = $_RDATA[$res_id][0];
                  $sql = " insert into reservas_header
                           (res_type, res_id, res_date, res_custname, res_city, res_street, res_plz, res_phone, res_mail,
                            res_delivprice, res_amount, res_paydesc, res_state, res_fulfilled, res_crtdat, res_crtusr)
                           VALUES
                           ('XLS', '{$res_id}', '{$firstitem["res_date"]}', '{$firstitem["res_custname"]}', '{$firstitem["res_city"]}',
                            '{$firstitem["res_street"]}', '{$firstitem["res_plz"]}', '{$firstitem["res_phone"]}', '{$firstitem["res_mail"]}',
                            {$firstitem["res_delivprice"]}, {$firstitem["res_amount"]}, '{$firstitem["res_paydesc"]}', '{$firstitem["res_state"]}',
                            '{$firstitem["res_fulfilled"]}', {$currtme}, {$_SESSION["user_id"]})";
                  $xret = $CON->no_result($sql);
                  if($xret)
                  {
                     $pos_header_id = mysql_insert_id();

                     foreach($_RDATA[$res_id] AS $subitem)
                     {
                        $sql = " insert into reservas_pos
                                 (pos_header_id, pos_itemdesc, pos_itemamt, pos_itemprice)
                                 VALUES
                                 ({$pos_header_id}, '{$subitem["res_itemdesc"]}', {$subitem["res_itemamt"]}, {$subitem["res_itemprice"]})";
                        $CON->no_result($sql);
                     }
                  }
                  $_IMPORT[] = "Reserva ID <b>{$res_id}</b> fue importado exitosamente.";
               }
               else
                  $_ERRORS[] = "Reserva ID <b>{$res_id}</b> ya existe.";
            }
            */
         }
      }
      else
         $filecheck = false;
   }
   else
      $filecheck = false;
}
?>
<script language="JavaScript">
function checkuploadxform(xformobj)
{
   if(checkform(new Array(xformobj.xls_file)))
   {
      if(document.getElementById('res_csv_tienda0').checked ||
         document.getElementById('res_csv_tienda1').checked)
         return true;
   }
   return false;
}
</script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Subir archivo CSV</b></td>
   <td align="right" class="content_row_clear"></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" class="fokusfirst" name="xform_xls" autocomplete="off"
onsubmit="return checkuploadxform(this)" enctype="multipart/form-data">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "650")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<tr>
   <td class="content_tbl_header" colspan="2">Subir archivo</td>
</tr>
<tr>
   <td class="content_rowl" width="80">Archivo *</td>
   <td class="content_row">
      <input class="text" type="file" name="xls_file" maxlength="255" style="width:400px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top" width="80">Tienda WIX *</td>
   <td class="content_row">
      <input type="radio" value="www.nisim.cl" name="res_csv_tienda" id="res_csv_tienda0">www.nisim.cl<br>
      <input type="radio" value="www.revitalash.cl" name="res_csv_tienda" id="res_csv_tienda1">www.revitalash.cl
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<tr>
   <td class="content_row_clear" align="right">
      <?php
      printButton("Subir", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_xls)", "tick-circle-frame", 120);
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php
if(count($_ERRORS) > 0)
{  ?>
   <?=Nifty_printH("box1", "650")?>
   <table border="0" cellpadding="3" cellspacing="0" width="100%">
   <tr>
      <td class="content_tbl_header" style="background-color:#DE6F6F;color:white;text-shadow:none">Errores de importación</td>
   </tr>
   <?php
   $x = 0;
   foreach($_ERRORS AS $err)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <td class="content_row_os"><?=$err?></td>
      </tr>
      <?php
      $x++;
   }
   ?>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?php
}
if(count($_IMPORT) > 0)
{  ?>
   <?=Nifty_printH("box1", "650")?>
   <table border="0" cellpadding="3" cellspacing="0" width="100%">
   <tr>
      <td class="content_tbl_header" style="background-color:#74B174;color:white;text-shadow:none">Éxito de importación</td>
   </tr>
   <?php
   $x = 0;
   foreach($_IMPORT AS $imp)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <td class="content_row_os"><?=$imp?></td>
      </tr>
      <?php
      $x++;
   }
   ?>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}