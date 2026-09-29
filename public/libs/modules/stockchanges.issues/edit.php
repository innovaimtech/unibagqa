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
   $currtme = time();

   $_REQUEST["stkis_title"]    = trim(addslashes($_REQUEST["stkis_title"]));
   $_REQUEST["stkis_desc"]     = trim(addslashes($_REQUEST["stkis_desc"]));
   $_REQUEST["stkis_negative"] = (int)$_REQUEST["stkis_negative"];

   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $sql = " insert into stockchanges_issues
               (stkis_title, stkis_desc, stkis_negative, stkis_crtusr, stkis_crtdat)
               VALUES
               ('{$_REQUEST["stkis_title"]}', '{$_REQUEST["stkis_desc"]}', {$_REQUEST["stkis_negative"]},
                 {$_SESSION["user_id"]}, {$currtme})";
      $res = $CON->no_result($sql);
      
      if($res)
      {
         $sql = " select MAX(id) 'maxid'
                  from stockchanges_issues";
         $thisid = $CON->select($sql);
         $thisid = (int)$thisid[0]["maxid"];
         $_REQUEST["id"] = $thisid;
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update stockchanges_issues
               set
               stkis_title     = '{$_REQUEST["stkis_title"]}',
               stkis_desc      = '{$_REQUEST["stkis_desc"]}',
               stkis_negative  = {$_REQUEST["stkis_negative"]},
               stkis_updusr    = {$_SESSION["user_id"]},
               stkis_upddat    = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $thisid = $_REQUEST["id"];
   }

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["exec"] == "del")
{
   $currtme = time();
   
   $sql = " update stockchanges_issues
            set
            stkis_status = 0,
            stkis_updusr = {$_SESSION["user_id"]},
            stkis_upddat = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from stockchanges_issues t1
            LEFT OUTER JOIN user t2 ON t1.stkis_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.stkis_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} and
            t1.stkis_status = 1";
   $issue = $CON->select($sql);

   $title = "Cambiar tipo de ajuste";
}
else
   $title = "Agregar tipo de ajuste";
//----------------------------------------------------------------------------------
?>
<form action="index.php" method="post" class="fokusfirst" name="xform_issues"
 onsubmit="return checkform(new Array(this.stkis_title))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<table border="0" cellpadding="0" cellspacing="0" width="650">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de tipo de ajuste</td>
</tr>
<tr>
   <td class="content_row">Nombre *</td>
   <td class="content_row">
      <input name="stkis_title" type="text" class="text" style="width:510px" value="<?=$issue[0]["stkis_title"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row">Tipo *</td>
   <td class="content_row">
      <input type="radio" name="stkis_negative" value="1" <?php if((int)$issue[0]["stkis_negative"] == 1 || $_REQUEST["id"] == "") echo "checked"?>> Negativo
      <input type="radio" name="stkis_negative" value="0" <?php if(!(int)$issue[0]["stkis_negative"] == 1 && $_REQUEST["id"] != "") echo "checked"?>> Positivo
   </td>
</tr>
<tr>
   <td class="content_row" valign="top">Descripción</td>
   <td class="content_row">
      <textarea name="stkis_desc" class="text" style="width:510px;height:130px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$issue[0]["stkis_desc"]?></textarea>
   </td>
</tr>
<?php
if($issue[0]["stkis_crtusr"] != "")
{  ?>
   <tr>
      <td class="content_row">Creado por</td>
      <td class="content_row"><?=$issue[0]["crt_firstname"]?> <?=$issue[0]["crt_lastname"]?>&nbsp;</td>
   </tr>
   <tr>
      <td class="content_row">Creado</td>
      <td class="content_row"><?=displayDate($issue[0]["stkis_crtdat"])?></td>
   </tr>
   <?php
}
if($issue[0]["stkis_updusr"] != "")
{  ?>
   <tr>
      <td class="content_row">Cambiado por</td>
      <td class="content_row"><?=$issue[0]["upd_firstname"]?> <?=$issue[0]["upd_lastname"]?>&nbsp;</td>
   </tr>
   <tr>
      <td class="content_row">Cambiado</td>
      <td class="content_row"><?=displayDate($issue[0]["stkis_upddat"])?></td>
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
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td width="130" align="right" style="padding-right:5px">
         <?php
         if($_REQUEST["id"] != 1 && $_REQUEST["id"] != 2 && $_REQUEST["id"] != 13 && $_REQUEST["id"] != 14 )
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         else
            echo "&nbsp;";
         ?>
      </td>
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_issues)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_issues');" ?>