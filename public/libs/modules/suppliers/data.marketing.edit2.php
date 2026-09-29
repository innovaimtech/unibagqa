<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["ccom"] == "save")
{
   $currtme = time();

   $_REQUEST["mark_name"]              = trim(addslashes($_REQUEST["mark_name"]));
   $_REQUEST["mark_notes_check"]       = (int)$_REQUEST["mark_notes_check"];
   $_REQUEST["mark_year"]              = (int)$_REQUEST["mark_year"];
   $_REQUEST["mark_period"]            = trim(addslashes($_REQUEST["mark_period"]));

   //----------------------------------------------------------------------------------
   if($_REQUEST["cid"] == "")
   {
      $sql = " insert into supplier_marketing_head
               (supp_id, mark_name, mark_type, mark_notes_check, mark_year, mark_period, 
                mark_crtusr, mark_crtdat)
               VALUES
               ({$_REQUEST["id"]}, '{$_REQUEST["mark_name"]}', 'META', 
                {$_REQUEST["mark_notes_check"]}, {$_REQUEST["mark_year"]}, '{$_REQUEST["mark_period"]}',
                {$_SESSION["user_id"]}, {$currtme})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'thisid'
                  from supplier_marketing_head
                  where
                  supp_id = {$_REQUEST["id"]}";
         $thisid = $CON->select($sql);
         $_REQUEST["cid"] = $thisid[0]["thisid"];
      }
   }
   else
   {
      $sql = " update supplier_marketing_head
               set
               mark_name            = '{$_REQUEST["mark_name"]}',
               mark_notes_check     =  {$_REQUEST["mark_notes_check"]},
               mark_period          = '{$_REQUEST["mark_period"]}',
               mark_year            =  {$_REQUEST["mark_year"]},
               mark_updusr          =  {$_SESSION["user_id"]},
               mark_upddat          =  {$currtme}
               where
               id = {$_REQUEST["cid"]}";
      $res = $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from supplier_marketing_metas
            where
            dct_head_id    = {$_REQUEST["cid"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " delete from supplier_marketing_limits
            where
            dct_head_id    = {$_REQUEST["cid"]}";
   $CON->no_result($sql);
   
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "dct_scale_discount_") !== false && strpos($reqkey, "dct_scale_discount_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $dct_scale_pricefrom = getPrice($_REQUEST["dct_scale_amtfrom_{$idx}"],2);
         $dct_scale_priceto   = getPrice($_REQUEST["dct_scale_amtto_{$idx}"],2);
         $dct_scale_discount  = getPrice($_REQUEST["dct_scale_discount_{$idx}"],2);

         if($dct_scale_pricefrom > 0.00 || $dct_scale_priceto > 0.00)
         {
            $sql = " insert into supplier_marketing_metas
                     (dct_head_id, dct_pos, dct_scale_amtfrom, dct_scale_amtto, dct_scale_discount)
                     VALUES
                     ({$_REQUEST["cid"]}, {$poscounter}, {$dct_scale_pricefrom}, {$dct_scale_priceto}, {$dct_scale_discount})";
            $CON->no_result($sql);

            $poscounter++;
         }
      }
   }

   $poscounter = 1;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "ldct_scale_amt_") !== false && strpos($reqkey, "ldct_scale_amt_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $dct_scale_pricefrom = getPrice($_REQUEST["ldct_scale_amt_{$idx}"]);

         $sql = " insert into supplier_marketing_limits
                  (dct_head_id, dct_month, dct_scale_amt)
                  VALUES
                  ({$_REQUEST["cid"]}, {$poscounter}, {$dct_scale_pricefrom})";
         $CON->no_result($sql);

         $poscounter++;
      }
   }

   

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["cid"] != "")
{
   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from supplier_marketing_head t1
            LEFT OUTER JOIN user t2 ON t1.mark_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.mark_crtusr = t3.id
            where
            t1.id      = {$_REQUEST["cid"]} and
            t1.supp_id = {$_REQUEST["id"]}";
   $data = $CON->select($sql);
   $data = $data[0];

   $sql = " select *
            from supplier_marketing_metas
            where
            dct_head_id  = {$_REQUEST["cid"]}
            order by dct_pos asc";
   $volpos = $CON->select($sql);

   $sql = " select *
            from supplier_marketing_limits
            where
            dct_head_id  = {$_REQUEST["cid"]}
            order by dct_month asc";
   $lpos = $CON->select($sql);
}
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_supplier"
onsubmit="return checkform(new Array(this.mark_name))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="addtype2">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="ccom" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<table cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed" border="0">
<colgroup>
   <col width="450" valign="top">
   <col width="15">
   <col width="250" valign="top">
   <col width="15">
   <col width="250" valign="top">
</colgroup>
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="120">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Configuración de rebate</td>
      </tr>
      <tr>
         <td class="content_rowl">Nombre *</td>
         <td class="content_row">
            <input name="mark_name" type="text" class="text" style="width:300px" value="<?=$data["mark_name"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Periodo</td>
         <td class="content_row">
            <select class="text" style="width:300px" name="mark_period" id="mark_period"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="MENSUAL"    <?php if($data["mark_period"] == "MENSUAL") echo "selected"?>>MENSUAL</option>
               <option value="TRIMENSUAL" <?php if($data["mark_period"] == "TRIMENSUAL") echo "selected"?>>TRIMENSUAL</option>
               <option value="SEMESTRAL"  <?php if($data["mark_period"] == "SEMESTRAL") echo "selected"?>>SEMESTRAL</option>
               <option value="ANUAL"      <?php if($data["mark_period"] == "ANUAL") echo "selected"?>>ANUAL</option>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Año</td>
         <td class="content_row">
            <select class="text" name="mark_year" id="mark_year"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <?php
               $startyear  = date('Y') -3;
               $endyear    = date('Y') +2;
               for($x = $startyear; $x <= $endyear; $x++)
               {
                  ?>
                  <option value="<?=$x?>"
                  <?php if($x == $data["mark_year"]) echo "selected" ?>><?=$x?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Notas de credito</td>
         <td class="content_row">
            <input name="mark_notes_check" type="checkbox" value="1"
            <?php if((int)$data["mark_notes_check"]) echo "checked"?>>Descontar
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][14]?></td>
         <td class="content_row"><?php if($data["mark_crtusr"] != "") echo "{$data["crt_firstname"]} {$data["crt_lastname"]}"?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][15]?></td>
         <td class="content_row"><?php if($data["mark_crtusr"] != "") echo displayDate($data["mark_crtdat"])?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][16]?></td>
         <td class="content_row"><?php if($data["mark_updusr"] != "") echo "{$data["upd_firstname"]} {$data["upd_lastname"]}"?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][17]?></td>
         <td class="content_row"><?php if($data["mark_updusr"] != "") echo displayDate($data["mark_upddat"])?>&nbsp;</td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      <br>
      <?=Nifty_printH("boxopt_b", "100%")?>
      <table border="0" cellspacing="0" cellpadding="0" width="100%">
      <tr>
         <td align="left" width="130">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&subcatexec=marketing", "", "arrow-180");
            ?>
         </td>
         <td>&nbsp;</td>
         <?php
         if($_REQUEST["cid"] != "")
         {  ?>
            <td align="right" width="130" style="padding-right:5px">
               <?php
               printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=marketing&id={$_REQUEST["id"]}&clearData={$_REQUEST["cid"]}')", "cross-circle-frame");
               ?>
            </td>
            <?php
         }
         ?>
         <td align="right" width="130">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_supplier)", "disk-black");
            ?>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
   <td class="content_row_clear">&nbsp;</td>
   <td valign="top">
      <?=Nifty_printH("box1", "250")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col width="110">
         <col width="110">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="5">Configuración rangos</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader">Rango</td>
         <td class="content_tbl_subheader">De %</td>
         <td class="content_tbl_subheader">Hasta %</td>
         <td class="content_tbl_subheader">Rebate %</td>
      </tr>
      <?php
      for($x = 0; $x < 12; $x++)
      {
         if((float)$volpos[$x]["dct_scale_amtfrom"] > 0.00 || (float)$volpos[$x]["dct_scale_amtto"] > 0.00)
         {
            $dsp_pricefrom    = printPrice($volpos[$x]["dct_scale_amtfrom"],2);
            $dsp_priceto      = printPrice($volpos[$x]["dct_scale_amtto"],2);
            $dsp_discount     = printPrice($volpos[$x]["dct_scale_discount"],2);
         }
         else
         {
            $dsp_pricefrom    = "";
            $dsp_priceto      = "";
            $dsp_discount     = "";
         }
         ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_row">#<?=($x + 1)?></td>
            <td class="content_row">
               <input name="dct_scale_amtfrom_<?=$x?>" type="text" class="text" style="width:60px" value="<?=$dsp_pricefrom?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_row">
               <input name="dct_scale_amtto_<?=$x?>" type="text" class="text" style="width:60px" value="<?=$dsp_priceto?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_row">
               <input name="dct_scale_discount_<?=$x?>" type="text" class="text" style="width:60px" value="<?=$dsp_discount?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <?php
      }  ?>
      </table>
      <?=Nifty_printF(false)?>
   </td>
   <td class="content_row_clear">&nbsp;</td>
   <td valign="top">
      <?=Nifty_printH("box1", "250")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Configuración metas</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader">Mes</td>
         <td class="content_tbl_subheader">Meta $</td>
      </tr>
      <?php
      for($x = 1; $x <= 12; $x++)
      {
         if((float)$lpos[($x-1)]["dct_scale_amt"] > 0.00)
         {
            $dsp_pricefrom = printPrice($lpos[($x-1)]["dct_scale_amt"],0);
         }
         else
         {
            $dsp_pricefrom = "";
         }
         ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_row"><?=$_LANG["MODULE"]["CAL"][($x-1)]?></td>
            <td class="content_row">
               $ <input name="ldct_scale_amt_<?=$x?>" type="text" class="text" style="width:100px" value="<?=$dsp_pricefrom?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <?php
      }  ?>
      </table>
      <?=Nifty_printF(false)?>
   </td>
</tr>
</table>
</form>
</center>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_supplier');" ?>