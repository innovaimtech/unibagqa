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
   $_REQUEST["mark_perc"]              = getPrice($_REQUEST["mark_perc"],4);
   $_REQUEST["mark_notes_check"]       = (int)$_REQUEST["mark_notes_check"];
   $_REQUEST["mark_year"]              = (int)$_REQUEST["mark_year"];
   $_REQUEST["mark_period"]            = trim(addslashes($_REQUEST["mark_period"]));

   //----------------------------------------------------------------------------------
   if($_REQUEST["cid"] == "")
   {
      $sql = " insert into supplier_marketing_head
               (supp_id, mark_name, mark_type, mark_perc, mark_notes_check, mark_year, mark_period, 
                mark_crtusr, mark_crtdat)
               VALUES
               ({$_REQUEST["id"]}, '{$_REQUEST["mark_name"]}', 'SIMPLE', {$_REQUEST["mark_perc"]},
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
               mark_perc            =  {$_REQUEST["mark_perc"]},
               mark_notes_check     =  {$_REQUEST["mark_notes_check"]},
               mark_period          = '{$_REQUEST["mark_period"]}',
               mark_year            =  {$_REQUEST["mark_year"]},
               mark_updusr          =  {$_SESSION["user_id"]},
               mark_upddat          =  {$currtme}
               where
               id = {$_REQUEST["cid"]}";
      $res = $CON->no_result($sql);
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
}
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_supplier"
onsubmit="return checkform(new Array(this.mark_name, this.mark_perc))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="addtype1">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="ccom" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "450")?>
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
   <td class="content_rowl">Porcentaje *</td>
   <td class="content_row">
      <input name="mark_perc" type="text" class="text" style="width:50px"
      value="<?=printPrice($data["mark_perc"],4)?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"> %
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
<?=Nifty_printH("boxopt_b", "450")?>
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
</form>
</center>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_supplier');" ?>