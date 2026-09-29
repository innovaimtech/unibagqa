<?php
//----------------------------------------------------------------------------------
if(!(int)$_REQUEST["xyear"])
   $_REQUEST["xyear"] = date("Y");

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "metaluvi_") !== false && strpos($reqkey, "metaluvi_") == 0)
      {
         $idxarr              = explode("_", $reqkey);
         $meta_shopid         = (int)$idxarr[1];
         $meta_month          = (int)$idxarr[2];
         $meta_year           = (int)$_REQUEST["xyear"];
         $meta_amount_lu_vi   = getPrice($_REQUEST[$reqkey]);
         $meta_amount_sa_do   = getPrice($_REQUEST["metasado_{$meta_shopid}_{$meta_month}"]);

         $sql = " delete from metas_config
                  where
                  meta_shopid   = {$meta_shopid} and
                  meta_month    = {$meta_month} and
                  meta_year     = {$meta_year}";
         $CON->no_result($sql);

         $sql = " insert into metas_config
                  (meta_shopid, meta_month, meta_year, meta_amount_lu_vi, meta_amount_sa_do)
                  VALUES
                  ({$meta_shopid}, {$meta_month}, {$meta_year}, {$meta_amount_lu_vi}, {$meta_amount_sa_do})";
         $CON->no_result($sql);
      }
   }
   $savemsg = getSaveMessage(true);
}

//----------------------------------------------------------------------------------
$sql = " select *
         from metas_config
         where
         meta_year = {$_REQUEST["xyear"]}";
$metas = $CON->select($sql);
foreach($metas AS $meta)
{
   $_METAS_LUVI[$meta["meta_shopid"]][$meta["meta_year"]][$meta["meta_month"]] = $meta["meta_amount_lu_vi"];
   $_METAS_SADO[$meta["meta_shopid"]][$meta["meta_year"]][$meta["meta_month"]] = $meta["meta_amount_sa_do"];
}

//----------------------------------------------------------------------------------
$sql_datefrom  = mktime(0, 0, 0, 1, 1, $_REQUEST["xyear"]);
$sql_dateto    = mktime(23, 59, 59, 12, 31, $_REQUEST["xyear"]);
$datsql = " select t1.invc_shop_id, t1.invc_date, t1.invc_total_brutto
            from invoices_sell t1
            where
            t1.invc_status       > 1 and
            t1.invc_date         between {$sql_datefrom} and {$sql_dateto}
            UNION ALL
            select t1.invc_shop_id, t1.invc_date, t1.invc_total_brutto
            from invoices_sell_bol t1
            where
            t1.invc_status       > 1 and
            t1.invc_date         between {$sql_datefrom} and {$sql_dateto} and
            t1.invc_resv_id     <= 0";
$trans = $CON->select($datsql);
foreach($trans AS $tran)
{
   $idx1 = $tran["invc_shop_id"];
   $idx2 = (int)date("Y", $tran["invc_date"]);
   $idx3 = (int)date("m", $tran["invc_date"]);

   $dayidx = (int)date("N", $tran["invc_date"]);

   if($dayidx >= 1 && $dayidx <= 5)
      $_TRANS_LUVI[$idx1][$idx2][$idx3] += $tran["invc_total_brutto"];
   else
      $_TRANS_SADO[$idx1][$idx2][$idx3] += $tran["invc_total_brutto"];
}

//----------------------------------------------------------------------------------
$datsql = " select t1.note_shop_id, t1.note_date, t1.note_type, t1.note_total_brutto
            from invoices_notes_sell t1
            where
            t1.note_status       > 1 and
            t1.note_date         between {$sql_datefrom} and {$sql_dateto}  ";
$notes = $CON->select($datsql);
foreach($notes AS $note)
{
   $idx1 = $note["note_shop_id"];
   $idx2 = (int)date("Y", $note["note_date"]);
   $idx3 = (int)date("m", $note["note_date"]);

   $dayidx = (int)date("N", $note["note_date"]);
   
   if((int)$note["note_type"] == 1)
   {
      if($dayidx >= 1 && $dayidx <= 5)
         $_TRANS_LUVI[$idx1][$idx2][$idx3] -= $note["note_total_brutto"];
      else
         $_TRANS_SADO[$idx1][$idx2][$idx3] -= $note["note_total_brutto"];
   }
   else
   {
      if($dayidx >= 1 && $dayidx <= 5)
         $_TRANS_LUVI[$idx1][$idx2][$idx3] += $note["note_total_brutto"];
      else
         $_TRANS_SADO[$idx1][$idx2][$idx3] += $note["note_total_brutto"];
   }
}
?>
<form action="index.php" method="post" class="fokusfirst" name="xform_payment">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<table border="0" cellpadding="3" cellspacing="0" width="99%">
<tr>
   <td height="30" width="350"><b class="content_header">Metas: Lunes - Viernes</b></td>
   <td align="center"><?=$savemsg?></td>
   <td align="right" width="120">
      <select class="text" name="xyear"
      onchange="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&xyear=' +this.value">
         <?php
         for($x = date("Y")+1; $x >= date("Y")-10; $x--)
         {  ?>
            <option value="<?=$x?>" <?php if($x == $_REQUEST["xyear"]) echo "selected"?>><?=$x?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <?php
   for($x = 1; $x <= 12; $x++)
   {  ?>
      <col width="80">
      <col width="1">
      <?php
   }
   ?>
</colgroup>
<tr>
   <td class="content_tbl_header content_row_os" rowspan="2">Sucursal</td>
   <?php
   for($x = 1; $x <= 12; $x++)
   {  ?>
      <td class="content_tbl_header content_row_os" colspan="2" align="center" style="border-left:3px double #333333"><?=sprintf("%02s", $x)?>-<?=$_REQUEST["xyear"]?></td>
      <?php
   }
   ?>
</tr>
<tr>
   <?php
   for($x = 1; $x <= 12; $x++)
   {  ?>
      <td class="content_tbl_header content_row_os" align="center" style="border-left:3px double #333333">Meta</td>
      <td class="content_tbl_header content_row_os" align="center">%</td>
      <?php
   }
   ?>
</tr>
<?php
//----------------------------------------------------------------------------------
$sql = " select *
         from company_data
         where
         company_status = 1
         order by company_short";
$companies = $CON->select($sql);

foreach($companies AS $company)
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from company_shops t1
            where
            t1.shop_status = 1 and
            t1.shop_company_id = {$company["id"]}
            order by t1.shop_name";
   $shops = $CON->select($sql);

   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($shops) && $shops != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row_os"><?=$shops[$x]["shop_name"]?>&nbsp;</td>
         <?php
         for($y = 1; $y <= 12; $y++)
         {
            $meta_amount   = $_METAS_LUVI[$shops[$x]["id"]][$_REQUEST["xyear"]][$y];
            $sales_amount  = (float)$_TRANS_LUVI[$shops[$x]["id"]][$_REQUEST["xyear"]][$y];
            $perc          = round($sales_amount / $meta_amount * 100);

            $css = "background-color:#DF6262;color:white";
            if($perc >= 50 && $perc < 100)
               $css = "background-color:#EEA300;color:white";
            if($perc >= 100)
               $css = "background-color:#5AC562;color:white";
            ?>
            <td class="content_row_os" style="border-left:3px double #333333">
               <input type="text" class="text" style="width:100%;text-align:center"
               name="metaluvi_<?=$shops[$x]["id"]?>_<?=$y?>"
               value="<?php if($meta_amount > 0.00) echo printPrice($meta_amount);?>">
            </td>
            <td class="content_row_os" align="center" width="1" style="<?=$css?>">
               <?=(int)$perc?>%
            </td>
            <?php
         }
         ?>
      </tr>
      <?php
   }
}
?>
</table>
<?=Nifty_printF()?>
<br>
<table border="0" cellpadding="3" cellspacing="0" width="99%">
<tr>
   <td height="30"><img src="./images/menu/icons/currency.png" style="vertical-align:bottom"><b class="content_header" style="padding-left:5px">Metas: Sabado - Domingo</b></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <?php
   for($x = 1; $x <= 12; $x++)
   {  ?>
      <col width="80">
      <col width="1">
      <?php
   }
   ?>
</colgroup>
<tr>
   <td class="content_tbl_header content_row_os" rowspan="2">Sucursal</td>
   <?php
   for($x = 1; $x <= 12; $x++)
   {  ?>
      <td class="content_tbl_header content_row_os" colspan="2" align="center" style="border-left:3px double #333333"><?=sprintf("%02s", $x)?>-<?=$_REQUEST["xyear"]?></td>
      <?php
   }
   ?>
</tr>
<tr>
   <?php
   for($x = 1; $x <= 12; $x++)
   {  ?>
      <td class="content_tbl_header content_row_os" align="center" style="border-left:3px double #333333">Meta</td>
      <td class="content_tbl_header content_row_os" align="center">%</td>
      <?php
   }
   ?>
</tr>
<?php
//----------------------------------------------------------------------------------
$sql = " select *
         from company_data
         where
         company_status = 1
         order by company_short";
$companies = $CON->select($sql);

foreach($companies AS $company)
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from company_shops t1
            where
            t1.shop_status = 1 and
            t1.shop_company_id = {$company["id"]}
            order by t1.shop_name";
   $shops = $CON->select($sql);

   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($shops) && $shops != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row_os"><?=$shops[$x]["shop_name"]?>&nbsp;</td>
         <?php
         for($y = 1; $y <= 12; $y++)
         {
            $meta_amount   = $_METAS_SADO[$shops[$x]["id"]][$_REQUEST["xyear"]][$y];
            $sales_amount  = (float)$_TRANS_SADO[$shops[$x]["id"]][$_REQUEST["xyear"]][$y];
            $perc          = round($sales_amount / $meta_amount * 100);

            $css = "background-color:#DF6262;color:white";
            if($perc >= 50 && $perc < 100)
               $css = "background-color:#EEA300;color:white";
            if($perc >= 100)
               $css = "background-color:#5AC562;color:white";
            ?>
            <td class="content_row_os" style="border-left:3px double #333333">
               <input type="text" class="text" style="width:100%;text-align:center"
               name="metasado_<?=$shops[$x]["id"]?>_<?=$y?>"
               value="<?php if($meta_amount > 0.00) echo printPrice($meta_amount);?>">
            </td>
            <td class="content_row_os" align="center" width="1" style="<?=$css?>">
               <?=(int)$perc?>%
            </td>
            <?php
         }
         ?>
      </tr>
      <?php
   }
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "99%")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <?php
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_payment)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<br><br>
<?php $_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");'; ?>