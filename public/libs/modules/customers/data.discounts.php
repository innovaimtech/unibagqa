<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   //----------------------------------------------------------------------------------
   $sql = " delete from dscsell_customer_productcats_payment
            where
            dct_cust_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "dct_allprice_") !== false && strpos($reqkey, "dct_allprice_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         $dct_alltype      = (int)$_REQUEST["dct_alltype_{$idx}"];
         $dct_allprice     = getPrice($_REQUEST["dct_allprice_{$idx}"],2);
         $selcatid         = (int)$_REQUEST["selcatid_{$idx}"];
         $payid            = (int)$_REQUEST["payid_{$idx}"];
         $dct_voldeact     = (int)$_REQUEST["dct_voldeact_{$idx}"];

         if($selcatid && $payid)
         {
            unset($paysql);
            foreach(array_keys($_REQUEST) AS $reqkey)
            {
               if(strpos($reqkey, "dct_scale_discount_{$idx}_") !== false && strpos($reqkey, "dct_scale_discount_{$idx}_") == 0)
               {
                  $idx2 = substr($reqkey, strrpos($reqkey, "_") +1);
                  $paysql["DSC{$idx2}"] = getPrice($_REQUEST["dct_scale_discount_{$idx}_{$idx2}"],2);
                  $paysql["TYP{$idx2}"] = (int)$_REQUEST["dct_scale_type_{$idx}_{$idx2}"];
                  $paysql["MOD{$idx2}"]  = (int)$_REQUEST["dct_scale_mode_{$idx}_{$idx2}"];
               }
            }
            $sql = " insert into dscsell_customer_productcats_payment
                     (dct_cust_id, dct_cat_id, dct_payid, dct_scale_discount1, dct_scale_discount2, dct_scale_discount3,
                      dct_scale_discount4, dct_scale_type1, dct_scale_type2, dct_scale_type3, dct_scale_type4,
                      dct_scale_mode1, dct_scale_mode2, dct_scale_mode3, dct_scale_mode4, dct_voldeact)
                     VALUES
                     ({$_REQUEST["id"]}, {$selcatid}, {$payid}, {$paysql["DSC1"]}, {$paysql["DSC2"]}, {$paysql["DSC3"]},
                      {$paysql["DSC4"]}, {$paysql["TYP1"]}, {$paysql["TYP2"]}, {$paysql["TYP3"]},
                      {$paysql["TYP4"]}, {$paysql["MOD1"]}, {$paysql["MOD2"]}, {$paysql["MOD3"]},
                      {$paysql["MOD4"]}, {$dct_voldeact})";
            $CON->no_result($sql);

            if(trim($_REQUEST["dct_allprice_{$idx}"]) != "")
            {
               $sql = " select count(*) 'cc'
                        from dscsell_customer_productcats_base
                        where
                        dct_cust_id = {$_REQUEST["id"]} and
                        dct_cat_id  = {$selcatid}";
               $baseexists = $CON->select($sql);
               $baseexists = (int)$baseexists[0]["cc"];

               if($baseexists)
               {
                  $sql = " update dscsell_customer_productcats_base
                           set
                           dct_allprice   = {$dct_allprice},
                           dct_alltype    = {$dct_alltype},
                           dct_active     = 1
                           where
                           dct_cust_id = {$_REQUEST["id"]} and
                           dct_cat_id  = {$selcatid}";
                  $CON->no_result($sql);
               }
               else
               {
                  $sql = " insert into dscsell_customer_productcats_base
                           (dct_cust_id, dct_cat_id, dct_active, dct_alltype, dct_allprice)
                           VALUES
                           ({$_REQUEST["id"]}, {$selcatid}, 1, {$dct_alltype}, {$dct_allprice})";
                  $CON->no_result($sql);
               }
            }
            else
            {
               $sql = " update dscsell_customer_productcats_base
                        set
                        dct_allprice   = 0.00,
                        dct_alltype    = 0,
                        dct_active     = 0
                        where
                        dct_cust_id = {$_REQUEST["id"]} and
                        dct_cat_id  = {$selcatid}";
               $CON->no_result($sql);
            }
         }
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from dscsell_customer_productcats_notes
            where
            dct_cust_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "note_selcatid_") !== false && strpos($reqkey, "note_selcatid_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         $selcatid            = (int)$_REQUEST["note_selcatid_{$idx}"];
         $dct_scale_discount  = getPrice($_REQUEST["note_dct_scale_discount_{$idx}"],2);
         $dct_scale_type      = (int)$_REQUEST["note_dct_scale_type_{$idx}"];
         if($selcatid > 0 && $dct_scale_discount > 0.00)
         {
            $sql = " insert into dscsell_customer_productcats_notes
                     (dct_cust_id, dct_cat_id,  dct_scale_discount, dct_scale_type)
                     VALUES
                     ({$_REQUEST["id"]}, {$selcatid}, {$dct_scale_discount}, {$dct_scale_type})";
            $CON->no_result($sql);
         }
      }
   }
   $savemsg = getSaveMessage(true);
}

//----------------------------------------------------------------------------------
$sql = " select *
         from payments
         where
         pay_status > 0
         order by pay_title";
$payments = $CON->select($sql);

$sql = " select t1.*, t2.pay_title, t3.cat_title, t4.dct_active, t4.dct_vol_active, t4.dct_allprice, t4.dct_alltype
         from dscsell_customer_productcats_payment t1
         LEFT OUTER JOIN payments t2                           ON t1.dct_payid = t2.id
         LEFT OUTER JOIN productcats t3                        ON t1.dct_cat_id = t3.id
         LEFT OUTER JOIN dscsell_customer_productcats_base t4  ON ( t1.dct_cust_id = t4.dct_cust_id and t1.dct_cat_id = t4.dct_cat_id )
         where
         t1.dct_cust_id = {$_REQUEST["id"]}
         order by t1.dct_cat_id asc";
$posdata = $CON->select($sql);

foreach($posdata AS $row)
   $catidstr .= $row["dct_cat_id"].",";
$catidstr = substr($catidstr, 0, -1);

//----------------------------------------------------------------------------------
$sql = " select id, cat_title
         from productcats
         where
         cat_status = 1 ";
if($catidstr != "")
   $sql .= " and id NOT IN ({$catidstr}) ";
$sql .= " order by id";
$selcats = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " delete from dscsell_customer_productcats_base
         where
         dct_cust_id = {$_REQUEST["id"]} ";
if($catidstr != "")
   $sql .= " and dct_cat_id NOT IN ({$catidstr})";
$CON->no_result($sql);

//----------------------------------------------------------------------------------
$sql = " delete from dscsell_customer_productcats_value
         where
         dct_cust_id = {$_REQUEST["id"]} ";
if($catidstr != "")
   $sql .= " and dct_cat_id NOT IN ({$catidstr})";
$CON->no_result($sql);

//----------------------------------------------------------------------------------
$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

if($rowcount <= 6)
   $rowcount = 10;
else
   $rowcount += 5;

//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function setxFamily(valid, rowid)
   {
      var val = parseInt(valid);
      var obj = document.getElementById('selcatid_' +rowid);
      for(var x = 0; x < obj.options.length; x++)
      {
         if(obj.options[x].value == val)
            obj.selectedIndex = x;
      }
   }

   function setxNoteFamily(valid, rowid)
   {
      var val = parseInt(valid);
      var obj = document.getElementById('note_selcatid_' +rowid);
      for(var x = 0; x < obj.options.length; x++)
      {
         if(obj.options[x].value == val)
            obj.selectedIndex = x;
      }
   }

   function setAllSelVal(xval, xtext)
   {
      $('select[name^="payid_"]').val(xval);
      $('.textinp').html(xtext);
   }
</script>
<form action="index.php" method="post" name="xform_itemprices">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="50">
   <col width="300">
   <col>
   <col>
   <col>
   <col width="90">
   <col width="90">
   <col width="70">
   <col width="70">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="9">Resumen de descuentos por familia</td>
</tr>
<tr>
   <td class="content_tbl_subheader content_row_os" valign="bottom" align="center">Codigo</td>
   <td class="content_tbl_subheader content_row_os" valign="bottom">
      <select name="temppayid" id="temppayid" class="text" style="width:270px;"
      onchange='setAllSelVal(this.value, this.options[this.selectedIndex].text)'
      onblur="markfield(this,1);"
      onmousedown="markfield(this,0)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($payments AS $payment)
         {  ?>
            <option value="<?=$payment["id"]?>" <?php if($payment["id"] == $posdata[0]["dct_payid"]) echo "selected"?>><?=$payment["pay_title"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_tbl_subheader content_row_os">Descuento 1</td>
   <td class="content_tbl_subheader content_row_os">Descuento 2</td>
   <td class="content_tbl_subheader content_row_os">Descuento 3</td>
   <td class="content_tbl_subheader content_row_os">Descuento 4</td>
   <td class="content_tbl_subheader content_row_os" valign="bottom" align="center">Descuento<br>global</td>
   <td class="content_tbl_subheader content_row_os" valign="bottom" align="center">Desc/Vol<br>Desact.</td>
   <td class="content_tbl_subheader content_row_os" valign="bottom" align="center">Descuento<br>monto</td>
</tr>
<?php
for($x = 0; $x < $rowcount; $x++)
{
   
   
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row_os" align="center">
         <?php
         if((int)$posdata[$x]["dct_cat_id"])
         {  ?>
            <input type="button" class="buttonred" value="x" style="width:20px"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
            onclick="if(askDel('')) { document.xform_itemprices.selcatid_<?=$x?>.options.length=0; submitForm(document.xform_itemprices); }">
            <?php
         }
         else
         {  ?>
            <input type="text" class="text" name="catid_<?=$x?>" id="catid_<?=$x?>" style="width:50px;text-align:center"
            onfocus="markfield(this,0)" onblur="markfield(this,1);setxFamily(this.value, '<?=$x?>')">
            <?php
            if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "catid_{$x}";
         }
         if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "selcatid_{$x}";
         ?>
      </td>
      <td class="content_row_os">
         <select name="selcatid_<?=$x?>" id="selcatid_<?=$x?>" class="text" style="width:270px"
         onblur="markfield(this,1);removeSelStyle(this);"
         onfocus="<?php if(!(int)$posdata[$x]["dct_cat_id"]) echo "addSelStyle(this);" ?>"
         onmousedown="markfield(this,0)">
            <?php
            if((int)$posdata[$x]["dct_cat_id"])
            {  ?>
               <option value="<?=$posdata[$x]["dct_cat_id"]?>"><?=sprintf("%03s", $posdata[$x]["dct_cat_id"])?> <?=$posdata[$x]["cat_title"]?></option>
               <?php
               $hasItems = true;
            }
            else
            {  ?>
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($selcats AS $selcat)
               {  ?>
                  <option value="<?=$selcat["id"]?>" <?php if($selcat["id"] == $posdata[$x]["dct_cat_id"]) echo "selected"?>><?=sprintf("%03s", $selcat["id"])?> <?=$selcat["cat_title"]?></option>
                  <?php
               }
            }
            ?>
         </select>
      </td>
      <td class="content_row_os" id="" style="display:none">
         <select name="payid_<?=$x?>" id="payid_<?=$x?>" class="text" style="width:200px;display:none"
         <?php if(!$x) echo "onchange='setAllSelVal(this.value, this.options[this.selectedIndex].text)'"?>
         onblur="markfield(this,1);"
         onmousedown="markfield(this,0)">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($payments AS $payment)
            {  ?>
               <option value="<?=$payment["id"]?>" <?php if($payment["id"] == $posdata[$x]["dct_payid"]) echo "selected"?>><?=$payment["pay_title"]?></option>
               <?php
            }
            ?>
         </select>
         <div class="textinp" style='display:none'></div>
      </td>
      <?php
      for($y = 1; $y <= 4; $y ++)
      {
         if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "dct_scale_discount_{$x}_{$y}";
         ?>
         <td class="content_row_os">
            <nobr>
            <input type="checkbox" name="dct_scale_mode_<?=$x?>_<?=$y?>"
            title="Activado = Nota adjunta, Desactivado = Factura" value="1"
            <?php if((int)$posdata[$x]["dct_scale_mode{$y}"]) echo "checked"?>>
            
            <input name="dct_scale_discount_<?=$x?>_<?=$y?>" id="dct_scale_discount_<?=$x?>_<?=$y?>"
            type="text" class="text" style="width:40px;text-align:center"
            value="<?php
            if($posdata[$x]["dct_scale_discount{$y}"] > 0.00)
               echo printPrice($posdata[$x]["dct_scale_discount{$y}"],2);
            ?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">

            <select class="text" style="width:35px;display:none" name="dct_scale_type_<?=$x?>_<?=$y?>" id="dct_scale_type_<?=$x?>_<?=$y?>"
            onblur="markfield(this,1)" onmousedown="markfield(this,0)">
               <option value="0" selected>%</option>
               <option value="1"><?=$_SESSION["_CONF"]["conf_currency"]?></option>
            </select>
            %
            </nobr>
         </td>
         <?php
      }
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "dct_allprice_{$x}";
      ?>
      <td class="content_row_os" align="center">
         <nobr>
         <input name="dct_allprice_<?=$x?>" id="dct_allprice_<?=$x?>" type="text" class="text" style="width:35px;text-align:center"
         value="<?php if((int)$posdata[$x]["dct_active"]) echo printPrice($posdata[$x]["dct_allprice"],2)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <select class="text" style="width:35px;display:none" name="dct_alltype_<?=$x?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
            <option value="0" <?php if(!(int)$posdata[$x]["dct_alltype"]) echo "selected" ?>>%</option>
            <option value="1" <?php if((int)$posdata[$x]["dct_alltype"]) echo "selected" ?>><?=$_SESSION["_CONF"]["conf_currency"]?></option>
         </select>
         %
         </nobr>
      </td>
      <td class="content_row_os" align="center">
         <input type="checkbox" name="dct_voldeact_<?=$x?>" value="1"
         <?php if((int)$posdata[$x]["dct_voldeact"]) echo "checked"?>>
      </td>
      <td class="content_row_os" align="center">
         <?php
         if((int)$posdata[$x]["dct_cat_id"])
         {
            if((int)$posdata[$x]["dct_vol_active"])
               $css = "postnav_save";
            else
               $css = "postnav";
            printButton("Editar", $css, "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=discountsedit&id={$_REQUEST["id"]}&catid={$posdata[$x]["dct_cat_id"]}", "", "", 60);
         }
         else
            echo "&nbsp;";
         ?>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td width="130" align="right">
      <?php
      printButton("Guardar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemprices)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
$sql = " select t1.*, t3.cat_title
         from dscsell_customer_productcats_notes t1
         LEFT OUTER JOIN productcats t3  ON t1.dct_cat_id = t3.id
         where
         t1.dct_cust_id = {$_REQUEST["id"]}
         order by t1.dct_cat_id asc";
$posdata = $CON->select($sql);

foreach($posdata AS $row)
   $catidstr2 .= $row["dct_cat_id"].",";
$catidstr2 = substr($catidstr2, 0, -1);

//----------------------------------------------------------------------------------
$sql = " select id, cat_title
         from productcats
         where
         cat_status = 1 ";
if($catidstr2 != "")
   $sql .= " and id NOT IN ({$catidstr2}) ";
$sql .= " order by id";
$selcats2 = $CON->select($sql);

//----------------------------------------------------------------------------------
$rowcount = 9000;
if($posdata != false && count($posdata))
   $rowcount = 9000 + count($posdata);

if($rowcount <= 9006)
   $rowcount = 9010;
else
   $rowcount += 5;
?>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="50">
   <col width="300">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="8">Resumen de descuentos por familia con nota de credito adicional</td>
</tr>
<?php
$gc = 0;
for($x = 9000; $x < $rowcount; $x++)
{
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row_os" align="center">
         <?php
         if((int)$posdata[$gc]["dct_cat_id"])
         {  ?>
            <input type="button" class="buttonred" value="x" style="width:20px"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
            onclick="if(askDel('')) { document.xform_itemprices.note_selcatid_<?=$x?>.options.length=0; submitForm(document.xform_itemprices); }">
            <?php
         }
         else
         {  ?>
            <input type="text" class="text" name="note_catid_<?=$x?>" id="note_catid_<?=$x?>" style="width:50px;text-align:center"
            onfocus="markfield(this,0)" onblur="markfield(this,1);setxNoteFamily(this.value, '<?=$x?>')">
            <?php
            if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "note_catid_{$x}";
         }
         
         if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "note_selcatid_{$x}";
         ?>
      </td>
      <td class="content_row_os">
         <select name="note_selcatid_<?=$x?>" id="note_selcatid_<?=$x?>" class="text" style="width:270px"
         onblur="markfield(this,1);removeSelStyle(this);"
         onfocus="<?php if(!(int)$posdata[$gc]["dct_cat_id"]) echo "addSelStyle(this);" ?>"
         onmousedown="markfield(this,0)">
            <?php
            if((int)$posdata[$gc]["dct_cat_id"])
            {  ?>
               <option value="<?=$posdata[$gc]["dct_cat_id"]?>"><?=sprintf("%03s", $posdata[$gc]["dct_cat_id"])?> <?=$posdata[$gc]["cat_title"]?></option>
               <?php
               $hasItems = true;
            }
            else
            {  ?>
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($selcats2 AS $selcat)
               {  ?>
                  <option value="<?=$selcat["id"]?>" <?php if($selcat["id"] == $posdata[$gc]["dct_cat_id"]) echo "selected"?>><?=sprintf("%03s", $selcat["id"])?> <?=$selcat["cat_title"]?></option>
                  <?php
               }
            }
            if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "note_dct_scale_discount_{$x}";
            ?>
         </select>
      </td>
      <td class="content_row_os">
         <nobr>
         <input name="note_dct_scale_discount_<?=$x?>" id="note_dct_scale_discount_<?=$x?>"
         type="text" class="text" style="width:40px;text-align:center"
         value="<?php
         if($posdata[$gc]["dct_scale_discount"] > 0.00)
            echo printPrice($posdata[$gc]["dct_scale_discount"],2);
         ?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">

         <select class="text" style="width:35px;display:none" name="note_dct_scale_type_<?=$x?>" id="note_dct_scale_type_<?=$x?>"
         onblur="markfield(this,1)" onmousedown="markfield(this,0)">
            <option value="0" selected>%</option>
            <option value="1"><?=$_SESSION["_CONF"]["conf_currency"]?></option>
         </select>
         %
         </nobr>
      </td>
   </tr>
   <?php
   $gc++;
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td width="130" align="right">
      <?php
      printButton("Guardar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemprices)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php
if($hasItems)
{
   $_SESSION["JSEXEC"] .= ";setAllSelVal(document.getElementById('payid_0').value, document.getElementById('payid_0').options[document.getElementById('payid_0').selectedIndex].text);";
}