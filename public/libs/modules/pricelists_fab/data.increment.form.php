<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------


if($_REQUEST["ccom"] == "save")
{
   $currtme = time();

   $_REQUEST["inc_name"] = trim(addslashes($_REQUEST["inc_name"]));
   /*
   $_REQUEST["aum_prc_flexo_2sides"]   = getPrice($_REQUEST["aum_prc_flexo_2sides"]);
   $_REQUEST["aum_prc_flexo_1sides"]   = getPrice($_REQUEST["aum_prc_flexo_1sides"]);
   $_REQUEST["aum_prc_seri_2sides"]    = getPrice($_REQUEST["aum_prc_seri_2sides"]);
   $_REQUEST["aum_prc_seri_1sides"]    = getPrice($_REQUEST["aum_prc_seri_1sides"]);

   {$_REQUEST["aum_prc_flexo_2sides"]}, {$_REQUEST["aum_prc_flexo_1sides"]},
   {$_REQUEST["aum_prc_seri_2sides"]}, {$_REQUEST["aum_prc_seri_1sides"]}
   */

   if((int)$_REQUEST["cid"])
   {
      $sql = " update price_lists_fab_increments
               set
               inc_name = '{$_REQUEST["inc_name"]}'
               where
               id = {$_REQUEST["cid"]}";
      $res = $CON->no_result($sql);
      
      $savemsg = getSaveMessage($res);
   }
   else
   {
      $sql = " insert into price_lists_fab_increments
               (pl_id, inc_name)
               VALUES
               ({$_REQUEST["id"]}, '{$_REQUEST["inc_name"]}')";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
      if($res)
         $_REQUEST["cid"] = mysql_insert_id();
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from price_lists_fab_increments_pos
            where
            pos_incid = {$_REQUEST["cid"]}";
   $CON->no_result($sql);

   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "aum_prc_flexo_2") !== false && strpos($reqkey, "aum_prc_flexo_2") == 0)
      {
         $idxarr     = explode("_", $reqkey);
         $prc_amount = (int)$idxarr[4];
         
         $aum_prc_flexo_2sides   = getPrice($_REQUEST["aum_prc_flexo_2sides_{$prc_amount}"]);
         $aum_prc_flexo_1sides   = getPrice($_REQUEST["aum_prc_flexo_1sides_{$prc_amount}"]);
         $aum_prc_seri_2sides    = getPrice($_REQUEST["aum_prc_seri_2sides_{$prc_amount}"]);
         $aum_prc_seri_1sides    = getPrice($_REQUEST["aum_prc_seri_1sides_{$prc_amount}"]);

         if($prc_amount > 0.00)
         {
            $sql = " insert into price_lists_fab_increments_pos
                     (pos_amount, pos_incid, pl_id, aum_prc_flexo_2sides, aum_prc_flexo_1sides, aum_prc_seri_2sides, aum_prc_seri_1sides)
                     VALUES
                     ({$prc_amount}, {$_REQUEST["cid"]}, {$_REQUEST["id"]}, {$aum_prc_flexo_2sides}, {$aum_prc_flexo_1sides},
                      {$aum_prc_seri_2sides}, {$aum_prc_seri_1sides})";
            $CON->no_result($sql);
         }
      }
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["cid"] != "")
{
   $sql = " select *
            from price_lists_fab_increments
            where
            id = {$_REQUEST["cid"]}";
   $data = $CON->select($sql);
   $data = $data[0];

   $sql = " select *
            from price_lists_fab_increments_pos
            where
            pos_incid = {$_REQUEST["cid"]}";
   $posdata = $CON->select($sql);
   foreach($posdata AS $posdatarow)
   {
      $_RES[(int)$posdatarow["pos_amount"]] = $posdatarow;
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from price_lists_fab_amounts t1
         where
         t1.pl_id      = {$_REQUEST["id"]} and
         t1.amt_status  = 1
         order by t1.amt_val";
$amounts = $CON->select($sql);
   
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_customer" onsubmit="return checkform(new Array(this.inc_name))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="ccom" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="add_pos" value="<?=$data["add_pos"]?>">
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos Básicos</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row" colspan="4">
      <input name="inc_name" type="text" class="text" style="width:100%"
      value="<?=$data["inc_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl content_row_os">Cantidad</td>
   <td class="content_rowl content_row_os" align="center">Incremento x color Flexo ambos lados</td>
   <td class="content_rowl content_row_os" align="center">Incremento x color Flexo un lado</td>
<!--    <td class="content_rowl content_row_os" align="center">Incremento x color Seri ambos lados</td> -->
   <td class="content_rowl content_row_os" align="center">Incremento x color Seri un lado</td>
</tr>
<?php
for($x = 0; $x < count($amounts) && $amounts != false; $x++)
{
   $amount = $amounts[$x];
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os"><?=printPrice($amounts[$x]["amt_val"])?>&nbsp;</td>
      <td class="content_row_os">
         <input type="text" class="text" name="aum_prc_flexo_2sides_<?=(int)$amount["amt_val"]?>" style="width:100%"
         value="<?if((int)$_RES[(int)$amount["amt_val"]]["aum_prc_flexo_2sides"]) echo printPrice($_RES[(int)$amount["amt_val"]]["aum_prc_flexo_2sides"])?>">
      </td>
      <td class="content_row_os">
         <input type="text" class="text" name="aum_prc_flexo_1sides_<?=(int)$amount["amt_val"]?>" style="width:100%"
         value="<?if((int)$_RES[(int)$amount["amt_val"]]["aum_prc_flexo_1sides"]) echo printPrice($_RES[(int)$amount["amt_val"]]["aum_prc_flexo_1sides"])?>">
      </td>
      <!--
      <td class="content_row_os">
         <input type="text" class="text" name="aum_prc_seri_2sides_<?=(int)$amount["amt_val"]?>" style="width:100%"
         value="<?if((int)$_RES[(int)$amount["amt_val"]]["aum_prc_seri_2sides"]) echo printPrice($_RES[(int)$amount["amt_val"]]["aum_prc_seri_2sides"])?>">
      </td>
      -->
      <td class="content_row_os">
         <input type="text" class="text" name="aum_prc_seri_1sides_<?=(int)$amount["amt_val"]?>" style="width:100%"
         value="<?if((int)$_RES[(int)$amount["amt_val"]]["aum_prc_seri_1sides"]) echo printPrice($_RES[(int)$amount["amt_val"]]["aum_prc_seri_1sides"])?>">
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130" valign="top">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&subcatexec=increment", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["cid"] != "")
   {  ?>
      <td align="right" width="130" style="padding-right:5px" valign="top">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=increment&id={$_REQUEST["id"]}&clearData={$_REQUEST["cid"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   ?>
   <td align="right" width="130" valign="top">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_customer)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_customer');" ?>