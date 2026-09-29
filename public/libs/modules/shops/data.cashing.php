<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       16.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["saveexec"] == "save")
{
   //----------------------------------------------------------------------------------
   $currtme = time();
   $_REQUEST["ca_name"]                = trim(addslashes($_REQUEST["ca_name"]));
   $_REQUEST["ca_ip"]                  = trim(addslashes($_REQUEST["ca_ip"]));
   $_REQUEST["ca_bixolon_name"]        = trim(addslashes($_REQUEST["ca_bixolon_name"]));
   $_REQUEST["ca_bixolon_mountport"]   = trim(addslashes($_REQUEST["ca_bixolon_mountport"]));
   $_REQUEST["ca_bixolon_type"]        = trim(addslashes($_REQUEST["ca_bixolon_type"]));
   $_REQUEST["ca_factura_act"]         = (int)$_REQUEST["ca_factura_act"];
   $_REQUEST["ca_boleta_act"]          = (int)$_REQUEST["ca_boleta_act"];
   $_REQUEST["ca_screen_type"]         = (int)$_REQUEST["ca_screen_type"];

   //----------------------------------------------------------------------------------
   if($_REQUEST["cid"] == "")
   {
      $caip = md5(microtime());
      
      $sql = " insert into company_shops_cashings
               (ca_shop_id, ca_name, ca_ip, 
                ca_bixolon_name, ca_bixolon_mountport, ca_bixolon_type,
                ca_crtusr, ca_crtdat, ca_factura_act, ca_boleta_act, ca_screen_type)
               VALUES
               ({$_REQUEST["id"]}, '{$_REQUEST["ca_name"]}', '{$caip}',
               '{$_REQUEST["ca_bixolon_name"]}', '{$_REQUEST["ca_bixolon_mountport"]}',
               '{$_REQUEST["ca_bixolon_type"]}', {$_SESSION["user_id"]}, {$currtme},
                {$_REQUEST["ca_factura_act"]}, {$_REQUEST["ca_boleta_act"]},
                {$_REQUEST["ca_screen_type"]})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'cid'
                  from company_shops_cashings
                  where
                  ca_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["cid"];
         $_REQUEST["cid"] = $thisid;
      }
   }
   else
   {
      $sql = " update company_shops_cashings
               set
               ca_name              = '{$_REQUEST["ca_name"]}',
               ca_bixolon_name      = '{$_REQUEST["ca_bixolon_name"]}',
               ca_bixolon_mountport = '{$_REQUEST["ca_bixolon_mountport"]}',
               ca_bixolon_type      = '{$_REQUEST["ca_bixolon_type"]}',
               ca_factura_act       =  {$_REQUEST["ca_factura_act"]},
               ca_boleta_act        =  {$_REQUEST["ca_boleta_act"]},
               ca_screen_type       =  {$_REQUEST["ca_screen_type"]},
               ca_updusr            = {$_SESSION["user_id"]},
               ca_upddat            = {$currtme}
               where
               id = {$_REQUEST["cid"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

$sql = " select t1.*,
         t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
         t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
         from company_shops_cashings t1
         LEFT OUTER JOIN user t2 ON t1.ca_updusr = t2.id
         LEFT OUTER JOIN user t3 ON t1.ca_crtusr = t3.id
         where
         t1.id = {$_REQUEST["cid"]} ";
$cashing = $CON->select($sql);
$cashing = $cashing[0];
if(trim($cashing["ca_ip"]) == "" && $_REQUEST["cid"] != "")
{
   $caip = md5(microtime());
   $sql = " update company_shops_cashings
            set
            ca_ip = '{$caip}'
            where
            id = {$_REQUEST["cid"]}";
   $CON->no_result($sql);

   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from company_shops_cashings t1
            LEFT OUTER JOIN user t2 ON t1.ca_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.ca_crtusr = t3.id
            where
            t1.id = {$_REQUEST["cid"]} ";
   $cashing = $CON->select($sql);
   $cashing = $cashing[0];
}
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" name="idx_cond" class="fokusfirst"
<?php
if((int)$_SESSION["LIMITPRIVS"]["user_priv_shop_cajas"])
{  ?>
   onsubmit="return false"
   <?php
}
else
{  ?>
   onsubmit="return checkform(new Array(this.ca_name))"
   <?php
}
?>>
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="saveexec" value="save">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<?=Nifty_printH("box1", "650")?>
<table cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos de caja</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input type="text" class="text" name="ca_name" style="width:350px" value="<?=$cashing["ca_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<?php
if($_REQUEST["cid"] != "")
{  ?>
   <tr>
      <td class="content_rowl">URL acceso</td>
      <td class="content_row">
         <a class="link" href="<?=$_SHOPCONFIG["URL"]?>?caid=<?=$_REQUEST["cid"]?>&auth=<?=$cashing["ca_ip"]?>" target="_blank"><?=$_SHOPCONFIG["URL"]?>?caid=<?=$_REQUEST["cid"]?>&auth=<?=$cashing["ca_ip"]?></a>
      </td>
   </tr>
   <?php
}
?>
<tr style="display:none">
   <td class="content_rowl">Tipo Impresora *</td>
   <td class="content_row">
      <span style="display:none">
      <input type="text" class="text" name="ca_bixolon_name" style="width:228px" value="<?=$cashing["ca_bixolon_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </span>
      <select class="text" style="width:120px" name="ca_bixolon_type"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="" <?php if($cashing["ca_bixolon_type"] == "") echo "selected"?>>SRP350/2PLUS</option>
         <option value="SRP350" <?php if($cashing["ca_bixolon_type"] == "SRP350") echo "selected"?>>SRP350</option>
      </select>
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Tipo Pantalla *</td>
   <td class="content_row">
      <select class="text" style="width:120px" name="ca_screen_type"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="0" <?php if((int)$cashing["ca_screen_type"] == 0) echo "selected"?>>Pantalla normal</option>
         <option value="1" <?php if((int)$cashing["ca_screen_type"] == 1) echo "selected"?>>Pantalla touch</option>
      </select>
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Montar a Puerto *</td>
   <td class="content_row">
      <input type="text" class="text" name="ca_bixolon_mountport" style="width:70px" value="<?=$cashing["ca_bixolon_mountport"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Emissión Boleta</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="ca_boleta_act" checked>
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Emissión Factura</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="ca_factura_act" <?php if((int)$cashing["ca_factura_act"]) echo "checked"?>>
      Activar
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
   <td class="content_row"><?php if($cashing["ca_crtusr"] != "") echo "{$cashing["crt_firstname"]} {$cashing["crt_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
   <td class="content_row"><?php if($cashing["ca_crtusr"] != "") echo displayDate($cashing["ca_crtdat"])?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
   <td class="content_row"><?php if($cashing["ca_updusr"] != "") echo "{$cashing["upd_firstname"]} {$cashing["upd_lastname"]}"?>&nbsp;</td>
</tr>

<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
   <td class="content_row"><?php if($cashing["ca_updusr"] != "") echo displayDate($cashing["ca_upddat"])?>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
if(!(int)$_SESSION["LIMITPRIVS"]["user_priv_shop_cajas"])
{  ?>
   <?=Nifty_printH("boxopt_b", "650")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td align="left" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&subcatexec=cashing", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         if($_REQUEST["cid"] != "")
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subcatexec={$_REQUEST["subcatexec"]}&exec=edit&id={$_REQUEST["id"]}&delcid={$_REQUEST["cid"]}')", "cross-circle-frame");
         ?>
      </td>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.idx_cond)", "disk-black");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}
?>
</form>
<?php
$_SESSION["JSEXEC"] .= "addFormListeners('idx_cond');";
if((int)$_SESSION["LIMITPRIVS"]["user_priv_shop_cajas"])
   $_SESSION["JSEXEC"] .= ";$(':input').attr('disabled','disabled');$(':input').attr('readonly','true');$('select').attr('disabled','disabled');";
?>