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

   $_REQUEST["delivery_name"]    = trim(addslashes($_REQUEST["delivery_name"]));
   $_REQUEST["delivery_street"]  = trim(addslashes($_REQUEST["delivery_street"]));
   $_REQUEST["delivery_desc"]    = trim(addslashes($_REQUEST["delivery_desc"]));
   $_REQUEST["country"]          = (int)$_REQUEST["country"];
   $_REQUEST["regions"]          = (int)$_REQUEST["regions"];
   $_REQUEST["provincias"]       = (int)$_REQUEST["provincias"];
   $_REQUEST["comunas"]          = (int)$_REQUEST["comunas"];

   if($_REQUEST["cid"] != "")
   {
      $sql = " update customer_deliveryaddr
               set
               delivery_name        = '{$_REQUEST["delivery_name"]}',
               delivery_desc        = '{$_REQUEST["delivery_desc"]}',
               delivery_street      = '{$_REQUEST["delivery_street"]}',
               delivery_countryid   =  {$_REQUEST["country"]},
               delivery_regionid    =  {$_REQUEST["regions"]},
               delivery_provinciaid =  {$_REQUEST["provincias"]},
               delivery_comunaid    =  {$_REQUEST["comunas"]}
               where
               id       = {$_REQUEST["cid"]} and
               cust_id  = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }
   else
   {
      $sql = " insert into customer_deliveryaddr
               (cust_id, delivery_name, delivery_street, delivery_countryid, delivery_regionid,
                delivery_comunaid, delivery_provinciaid, delivery_desc)
               VALUES
               ({$_REQUEST["id"]}, '{$_REQUEST["delivery_name"]}', '{$_REQUEST["delivery_street"]}',
                {$_REQUEST["country"]}, {$_REQUEST["regions"]}, {$_REQUEST["comunas"]}, 
                {$_REQUEST["provincias"]}, '{$_REQUEST["delivery_desc"]}')";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'thisid'
                  from customer_deliveryaddr
                  where
                  cust_id = {$_REQUEST["id"]}";
         $thisid = $CON->select($sql);
         ?>
         <script language="JavaScript">
            location.href = 'index.php?mid=628&exec=edit&subcatexec=delivaddr&id=<?=$_REQUEST["id"]?>&subexec=add&cid=<?=$thisid[0]["thisid"]?>';
         </script>
         <?php
      }
   }

   $savemsg = getSaveMessage($res);
   
   $sql = " update customer
            set
            cust_updusr = {$_SESSION["user_id"]},
            cust_upddat = {$currtme}
            where
            id          = {$_REQUEST["id"]}";      
   $CON->no_result($sql);
}

if($_REQUEST["cid"] != "")
{
   $sql = " select *
            from customer_deliveryaddr
            where
            id       = {$_REQUEST["cid"]} and
            cust_id  = {$_REQUEST["id"]}";
   $data = $CON->select($sql);
   $data = $data[0];
}
else
{
   $sql = " select cust_company
            from customer
            where
            id = {$_REQUEST["id"]}";

   $data["delivery_name"] = $CON->select($sql);
   $data["delivery_name"] = $data["delivery_name"][0]["cust_company"];
   $data["delivery_countryid"] = 81;
}

//----------------------------------------------------------------------------------
$countries  = getCountries($CON);
$regions    = getRegions($CON);
$comunas    = getComunas($CON);
$provincias = getProvincias($CON);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function delivformcheck(obj)
   {
      if(document.getElementById('country').value == '81')
         var frmchk = checkform(new Array(obj.delivery_name, obj.delivery_street, obj.country, obj.regions, obj.provincias, obj.comunas));
      else
         var frmchk = checkform(new Array(obj.delivery_name, obj.delivery_street, obj.country));
         
      if(!frmchk)
         return false;

      return true;
   }
   
   <?php
   generateCountryJS($countries, $regions, $comunas, $provincias);
   ?>
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" class="fokusfirst" name="xform_cust" onsubmit="return delivformcheck(this)">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="ccom" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "540")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Dirección del despacho</td>
</tr>
<tr>
   <td class="content_rowl">Empresa / Nombre *</td>
   <td class="content_row">
      <input name="delivery_name" type="text" class="text" style="width:390px" value="<?=$data["delivery_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Dirección *</td>
   <td class="content_row">
      <input name="delivery_street" type="text" class="text" style="width:390px" value="<?=$data["delivery_street"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Comuna *</td>
   <td class="content_row">
      <select class="text" style="width:390px" name="comunas" id="comunas" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
      onchange="jqUnibagSetComuna(this.value, 'customer')">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         $allcomunas = getAllComunas($CON);
         foreach($allcomunas AS $comuna)
         {  ?>
            <option value="<?=$comuna["id"]?>"
            <?php if($comuna["id"] == $data["delivery_comunaid"]) echo "selected"?>><?=$comuna["nombre"]?></option>
            <?php
         }
         /*
         if((int)$data["delivery_provinciaid"])
         {
            foreach($comunas as $comuna)
            {
               if($comuna["prov_id"] == $data["delivery_provinciaid"])
               {  ?>
                  <option value="<?=$comuna["id"]?>"
                  <?php if($comuna["id"] == $data["delivery_comunaid"]) echo "selected"?>><?=$comuna["nombre"]?></option>
                  <?php
               }
            }
         }
         */
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Provincia *</td>
   <td class="content_row">
      <select class="text" style="width:390px" name="provincias" id="provincias" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
      onchange="setComunas(this.value)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         if((int)$data["delivery_regionid"])
         {
            foreach($provincias as $provincia)
            {
               if($provincia["region_id"] == $data["delivery_regionid"])
               {  ?>
                  <option value="<?=$provincia["id"]?>"
                  <?php if($provincia["id"] == $data["delivery_provinciaid"]) echo "selected"?>><?=$provincia["pro_name"]?></option>
                  <?php
               }
            }
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Región *</td>
   <td class="content_row" width="130">
      <select class="text" style="width:390px" name="regions" id="regions"
      onchange="setProvincias(this.value)"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         if((int)$data["delivery_countryid"])
         {
            foreach($regions as $region)
            {
               if($region["id_pais"] == $data["delivery_countryid"])
               {  ?>
                  <option value="<?=$region["id"]?>"
                  <?php if($region["id"] == $data["delivery_regionid"]) echo "selected"?>><?=$region["name"]?></option>
                  <?php
               }
            }
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">País *</td>
   <td class="content_row" width="130">
      <select class="text" style="width:390px" name="country" id="country"
      onchange="setRegions(this.value)"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($countries as $country)
         {  ?>
            <option value="<?=$country["id"]?>"
            <?php if($country["id"] == $data["delivery_countryid"]) echo "selected"?>><?=$country["country_name"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Observaciones</td>
   <td class="content_row">
      <textarea name="delivery_desc" class="text" style="width:390px;height:52px"><?=stripslashes($data["delivery_desc"])?></textarea>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "540")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&subcatexec=delivaddr", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["cid"] != "")
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=delivaddr&id={$_REQUEST["id"]}&clearData={$_REQUEST["cid"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_cust)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<div id="idx_comunaout" style="display:none"></div>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_cust');" ?>