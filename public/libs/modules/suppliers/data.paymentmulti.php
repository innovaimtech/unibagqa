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
   $sql = " delete from supplier_productcat_payment
            where
            sup_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "payid_") !== false && strpos($reqkey, "payid_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         if((int)$_REQUEST[$reqkey])
         {
            $sql = " insert into supplier_productcat_payment
                     (sup_id, cat_id, pay_id)
                     VALUES
                     ({$_REQUEST["id"]}, {$idx}, {$_REQUEST[$reqkey]})";
            $CON->no_result($sql);
         }
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select distinct t1.id, t1.cat_title
         from productcats t1
         INNER JOIN item_productcats t2   ON t1.id = t2.cat_id
         INNER JOIN item t3               ON t2.item_id = t3.id
         INNER JOIN item_suppliers t4     ON t3.id = t4.item_id
         where
         t1.cat_status  = 1 and
         t3.item_status = 1 and
         t4.supplier_id = {$_REQUEST["id"]}
         order by t1.id";
$cats = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from payments
         where
         pay_status > 0
         order by pay_title";
$payments = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from supplier_productcat_payment
         where
         sup_id = {$_REQUEST["id"]}";
$seldata = $CON->select($sql);

foreach($seldata AS $selrow)
   $_SELIDS[$selrow["cat_id"]][$selrow["pay_id"]] = 1;
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_itemprices">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="40">
   <col>
   <col width="400">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Resumen de formas de pago por familia</td>
</tr>
<tr>
   <td class="content_tbl_subheader" colspan="2">Familia</td>
   <td class="content_tbl_subheader">Forma de Pago</td>
</tr>
<?php
for($x = 0; $x < count($cats) && $cats != false; $x++)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row"><?=sprintf("%03s", $cats[$x]["id"])?></td>
      <td class="content_row"><?=$cats[$x]["cat_title"]?></td>
      <td class="content_row">
         <select class="text" name="payid_<?=$cats[$x]["id"]?>" style="width:400px">
            <option value="">SEGUN DATOS BASICOS</option>
            <?php
            for($y = 0; $y < count($payments) && $payments != false; $y++)
            {  ?>
               <option value="<?=$payments[$y]["id"]?>" <?php if((int)$_SELIDS[$cats[$x]["id"]][$payments[$y]["id"]]) echo "selected"?>><?=$payments[$y]["pay_title"]?></option>
               <?php
            }
            ?>
         </select>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_itemprices');" ?>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemprices)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>