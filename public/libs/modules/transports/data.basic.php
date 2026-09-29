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

   $_REQUEST["trans_name"]        = trim(addslashes($_REQUEST["trans_name"]));
   $_REQUEST["trans_street"]      = trim(addslashes($_REQUEST["trans_street"]));
   $_REQUEST["trans_phone"]       = trim(addslashes($_REQUEST["trans_phone"]));
   $_REQUEST["trans_cellphone"]   = trim(addslashes($_REQUEST["trans_cellphone"]));
   $_REQUEST["trans_fax"]         = trim(addslashes($_REQUEST["trans_fax"]));
   $_REQUEST["trans_email"]       = trim(addslashes($_REQUEST["trans_email"]));
   $_REQUEST["trans_website"]     = trim(addslashes($_REQUEST["trans_website"]));
   $_REQUEST["country"]          = (int)$_REQUEST["country"];
   $_REQUEST["regions"]          = (int)$_REQUEST["regions"];
   $_REQUEST["comunas"]          = (int)$_REQUEST["comunas"];
   $_REQUEST["provincias"]       = (int)$_REQUEST["provincias"];
   $_REQUEST["trans_notes"]      = trim(addslashes($_REQUEST["trans_notes"]));
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $sql = " insert into transports
               (trans_street, trans_phone, trans_fax, trans_email,
                trans_website, trans_cellphone, trans_name,
                trans_countryid, trans_regionid, trans_comunaid, trans_crtusr,
                trans_crtdat, trans_provinciaid, trans_notes)
               VALUES
               ('{$_REQUEST["trans_street"]}',
                '{$_REQUEST["trans_phone"]}', '{$_REQUEST["trans_fax"]}',
                '{$_REQUEST["trans_email"]}', '{$_REQUEST["trans_website"]}',
                '{$_REQUEST["trans_cellphone"]}', '{$_REQUEST["trans_name"]}',
                 {$_REQUEST["country"]}, {$_REQUEST["regions"]}, {$_REQUEST["comunas"]},
                 {$_SESSION["user_id"]}, {$currtme}, {$_REQUEST["provincias"]},
                 '{$_REQUEST["trans_notes"]}')";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'thisid'
                  from transports
                  where
                  trans_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $_REQUEST["id"] = $thisid[0]["thisid"];

         $mid = 652;
         if($_REQUEST["modulo"] == "bodega")
              $mid = 10033;
            
         ?>
         <script language="JavaScript">
            location.href = 'index.php?mid=<?=$mid?>&exec=edit&id=<?=$thisid[0]["thisid"]?>';
         </script>
         <?php

      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update transports
               set
               trans_name         = '{$_REQUEST["trans_name"]}',
               trans_street       = '{$_REQUEST["trans_street"]}',
               trans_phone        = '{$_REQUEST["trans_phone"]}',
               trans_cellphone    = '{$_REQUEST["trans_cellphone"]}',
               trans_fax          = '{$_REQUEST["trans_fax"]}',
               trans_email        = '{$_REQUEST["trans_email"]}',
               trans_website      = '{$_REQUEST["trans_website"]}',
               trans_countryid    =  {$_REQUEST["country"]},
               trans_regionid     =  {$_REQUEST["regions"]},
               trans_provinciaid  =  {$_REQUEST["provincias"]},
               trans_comunaid     =  {$_REQUEST["comunas"]},
               trans_updusr       = {$_SESSION["user_id"]},
               trans_upddat       = {$currtme},
               trans_notes        = '{$_REQUEST["trans_notes"]}'
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $title = $_LANG["MODULE"]["CUST"][0];
   
   $sql = " select t1.*, 
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from transports t1
            LEFT OUTER JOIN user t2 ON t1.trans_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.trans_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $transport = $CON->select($sql);
   $transport = $transport[0];
}
else
{
   $title = $_LANG["MODULE"]["CUST"][1];
   $transport["trans_countryid"] = 81;
}

//----------------------------------------------------------------------------------
$countries  = getCountries($CON);
$regions    = getRegions($CON);
$comunas    = getComunas($CON);
$provincias = getProvincias($CON);

//----------------------------------------------------------------------------------
// print formular
//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function custformcheck(obj)
   {
      if(document.getElementById('country').value == '81')
         var frmchk = checkform(new Array(obj.trans_name, obj.country, obj.regions, obj.provincias, obj.comunas));
      else
         var frmchk = checkform(new Array(obj.trans_name, obj.country));
         
      if(!frmchk)
         return false;

      return true;
   }
   
   <?php
   generateCountryJS($countries, $regions, $comunas, $provincias);
   ?>
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_cust" onsubmit="return custformcheck(this)">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="modulo" value="<?=$_REQUEST["modulo"]?>">
<table cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed">
<colgroup>
   <col width="404" valign="top">
   <col width="15">
   <col width="404" valign="top">
</colgroup>
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos de transportista I</td>
      </tr>
      <tr>
         <td class="content_rowl">Nombre *</td>
         <td class="content_row">
            <input name="trans_name" type="text" class="text" style="width:280px" value="<?=$transport["trans_name"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Dirección *</td>
         <td class="content_row">
            <input name="trans_street" type="text" class="text" style="width:280px" value="<?=$transport["trans_street"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">País *</td>
         <td class="content_row" width="130">
            <select class="text" style="width:280px" name="country" id="country"
            onchange="setRegions(this.value)"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($countries as $country)
               {  ?>
                  <option value="<?=$country["id"]?>"
                  <?php if($country["id"] == $transport["trans_countryid"]) echo "selected"?>><?=$country["country_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Región *</td>
         <td class="content_row" width="130">
            <select class="text" style="width:280px" name="regions" id="regions"
            onchange="setProvincias(this.value);"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$transport["trans_countryid"])
               {
                  foreach($regions as $region)
                  {
                     if($region["id_pais"] == $transport["trans_countryid"])
                     {  ?>
                        <option value="<?=$region["id"]?>"
                        <?php if($region["id"] == $transport["trans_regionid"]) echo "selected"?>><?=$region["name"]?></option>
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
            <select class="text" style="width:280px" name="provincias" id="provincias" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setComunas(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$transport["trans_regionid"])
               {
                  foreach($provincias as $provincia)
                  {
                     if($provincia["region_id"] == $transport["trans_regionid"])
                     {  ?>
                        <option value="<?=$provincia["id"]?>"
                        <?php if($provincia["id"] == $transport["trans_provinciaid"]) echo "selected"?>><?=$provincia["pro_name"]?></option>
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
            <select class="text" style="width:280px" name="comunas" id="comunas" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$transport["trans_provinciaid"])
               {
                  foreach($comunas as $comuna)
                  {
                     if($comuna["prov_id"] == $transport["trans_provinciaid"])
                     {  ?>
                        <option value="<?=$comuna["id"]?>"
                        <?php if($comuna["id"] == $transport["trans_comunaid"]) echo "selected"?>><?=$comuna["nombre"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Email</td>
         <td class="content_row">
            <input name="trans_email" type="text" class="text" style="width:280px" value="<?=$transport["trans_email"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Telefono</td>
         <td class="content_row">
            <input name="trans_phone" type="text" class="text" style="width:280px" value="<?=$transport["trans_phone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Celular</td>
         <td class="content_row">
            <input name="trans_cellphone" type="text" class="text" style="width:280px" value="<?=$transport["trans_cellphone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Fax</td>
         <td class="content_row">
            <input name="trans_fax" type="text" class="text" style="width:280px" value="<?=$transport["trans_fax"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Sitio web</td>
         <td class="content_row">
            <input name="trans_website" type="text" class="text" style="width:280px" value="<?=$transport["trans_website"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
   </td>
   <td></td>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos de transportista II</td>
      </tr>
      <tr>
         <td class="content_rowl" valign="top">Comentarios</td>
         <td class="content_row">
            <textarea name="trans_notes" class="text" style="width:280px; height:225px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$transport["trans_notes"]?></textarea>
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
         <td class="content_row"><?php if($transport["trans_crtusr"] != "") echo "{$transport["crt_firstname"]} {$transport["crt_lastname"]}"?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
         <td class="content_row"><?php if($transport["trans_crtusr"] != "") echo displayDate($transport["trans_crtdat"])?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
         <td class="content_row"><?php if($transport["trans_updusr"] != "") echo "{$transport["upd_firstname"]} {$transport["upd_lastname"]}"?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
         <td class="content_row"><?php if($transport["trans_updusr"] != "") echo displayDate($transport["trans_upddat"])?>&nbsp;</td>
      </tr>
      </table>
      <?=Nifty_printF()?>
   </td>
</tr>
</table>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td align="left" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_cust)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_cust');" ?>