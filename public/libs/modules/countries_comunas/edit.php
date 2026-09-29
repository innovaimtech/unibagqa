<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   
   $_REQUEST["comuna_name"]   = trim(addslashes($_REQUEST["comuna_name"]));
   $_REQUEST["regions"]       = (int)$_REQUEST["regions"];
   $_REQUEST["provincias"]    = (int)$_REQUEST["provincias"];

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into comunas
               (nombre, id_region, crtusr, crtdat, prov_id)
               VALUES
               ('{$_REQUEST["comuna_name"]}', {$_REQUEST["regions"]}, {$_SESSION["user_id"]}, {$currtme}, {$_REQUEST["provincias"]})";
      $res = $CON->no_result($sql);
   }
   else
   {
      $sql = " update comunas
               set
               nombre    = '{$_REQUEST["comuna_name"]}',
               id_region = {$_REQUEST["regions"]},
               prov_id   = {$_REQUEST["provincias"]},
               updusr    = {$_SESSION["user_id"]},
               upddat    = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $title = "Cambiar comuna";

   $sql = " select t1.*,
                   t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
                   t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from comunas t1
            LEFT OUTER JOIN user t2 ON t1.updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]}";
   $data = $CON->select($sql);
   $data = $data[0];

   $sql = " select *
            from regions
            where
            id = {$data["id_region"]}";
   $selregion = $CON->select($sql);
   $selregion = $selregion[0];

   $sql = " select *
            from country
            where
            id = {$selregion["id_pais"]}";
   $selcountry = $CON->select($sql);
   $selcountry = $selcountry[0];
}
else
{
   $title = "Agregar comuna";
}

$countries  = getCountries($CON);
$regions    = getRegions($CON);
$provincias = getProvincias($CON);

?>
<script language="JavaScript">
   <?php
   generateCountryJS($countries, $regions, $comunas, $provincias);
   ?>
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_country"
 onsubmit="return checkform(new Array(this.comuna_name, this.country, this.regions, this.provincias))">
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
   <col width="">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de comuna</td>
</tr>
<tr>
   <td class="content_rowl">País *</td>
   <td class="content_row">
      <select class="text" style="width:510px" name="country" id="country"
      onchange="setRegions(this.value)"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($countries as $country)
         {  ?>
            <option value="<?=$country["id"]?>"
            <?php if($country["id"] == $selcountry["id"]) echo "selected"?>><?=$country["country_name"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Región *</td>
   <td class="content_row">
      <select class="text" style="width:510px" name="regions" id="regions"
      onchange="setProvincias(this.value);"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         if((int)$selcountry["id"])
         {
            foreach($regions as $region)
            {
               if($region["id_pais"] == $selcountry["id"])
               {  ?>
                  <option value="<?=$region["id"]?>"
                  <?php if($region["id"] == $data["id_region"]) echo "selected"?>><?=$region["name"]?></option>
                  <?php
               }
            }
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Provincia *</td>
   <td class="content_row">
      <select class="text" style="width:510px" name="provincias" id="provincias" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
      onchange="setComunas(this.value)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         if((int)$data["id_region"])
         {
            foreach($provincias as $provincia)
            {
               if($provincia["region_id"] == $data["id_region"])
               {  ?>
                  <option value="<?=$provincia["id"]?>"
                  <?php if($provincia["id"] == $data["prov_id"]) echo "selected"?>><?=$provincia["pro_name"]?></option>
                  <?php
               }
            }
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Comuna *</td>
   <td class="content_row">
      <input name="comuna_name" type="text" class="text" style="width:510px" value="<?=$data["nombre"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Comuna *</td>
   <td class="content_row">
      <select class="text" style="width:280px" name="comunas" id="comunas" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         if((int)$customer["cust_regionid"])
         {
            foreach($comunas as $comuna)
            {
               if($comuna["id_region"] == $customer["cust_regionid"])
               {  ?>
                  <option value="<?=$comuna["id"]?>"
                  <?php if($comuna["id"] == $customer["cust_comunaid"]) echo "selected"?>><?=$comuna["nombre"]?></option>
                  <?php
               }
            }
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$data["crt_firstname"]?> <?=$data["crt_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($data["crtdat"])?></td>
</tr>
<tr>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$data["upd_firstname"]?> <?=$data["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($data["upddat"])?></td>
</tr>
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
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=delete&id={$_REQUEST["id"]}')", "cross-circle-frame");
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_country)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_country');" ?>