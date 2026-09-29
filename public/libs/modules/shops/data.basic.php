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
   
   $_REQUEST["shop_name"]           = trim(addslashes($_REQUEST["shop_name"]));
   $_REQUEST["shop_desc"]           = trim(addslashes($_REQUEST["shop_desc"]));
   $_REQUEST["shop_street"]         = trim(addslashes($_REQUEST["shop_street"]));
   $_REQUEST["shop_cellphone"]      = trim(addslashes($_REQUEST["shop_cellphone"]));
   $_REQUEST["shop_email"]          = trim(addslashes($_REQUEST["shop_email"]));
   $_REQUEST["shop_phone"]          = trim(addslashes($_REQUEST["shop_phone"]));
   $_REQUEST["shop_executive"]      = trim(addslashes($_REQUEST["shop_executive"]));
   $_REQUEST["shop_fax"]            = trim(addslashes($_REQUEST["shop_fax"]));
   $_REQUEST["shop_giro"]           = trim(addslashes($_REQUEST["shop_giro"]));
   $_REQUEST["shop_company_id"]     = (int)$_REQUEST["shop_company_id"];
   $_REQUEST["country"]             = (int)$_REQUEST["country"];
   $_REQUEST["regions"]             = (int)$_REQUEST["regions"];
   $_REQUEST["comunas"]             = (int)$_REQUEST["comunas"];
   $_REQUEST["provincias"]          = (int)$_REQUEST["provincias"];
   $_REQUEST["shop_isremote"]       = (int)$_REQUEST["shop_isremote"];
   $_REQUEST["shop_cash_union"]     = (int)$_REQUEST["shop_cash_union"];
   $_REQUEST["shop_backup_hr"]      = (int)$_REQUEST["shop_backup_hr"];
   $_REQUEST["shop_backup_min"]     = (int)$_REQUEST["shop_backup_min"];
   $_REQUEST["supplier_id_0"]       = (int)$_REQUEST["supplier_id_0"];
   $_REQUEST["cust_id_0"]           = (int)$_REQUEST["cust_id_0"];
   $_REQUEST["shop_rel_custdelivid"]   = (int)$_REQUEST["shop_rel_custdelivid"];
   $_REQUEST["shop_rel_suppdelivid"]   = (int)$_REQUEST["shop_rel_suppdelivid"];

   if($_REQUEST["id"] != "")
   {
      $sql = " update company_shops
               set
               shop_name            = '{$_REQUEST["shop_name"]}',
               shop_desc            = '{$_REQUEST["shop_desc"]}',
               shop_company_id      =  {$_REQUEST["shop_company_id"]},
               shop_street          = '{$_REQUEST["shop_street"]}',
               shop_email           = '{$_REQUEST["shop_email"]}',
               shop_cellphone       = '{$_REQUEST["shop_cellphone"]}',
               shop_phone           = '{$_REQUEST["shop_phone"]}',
               shop_executive       = '{$_REQUEST["shop_executive"]}',
               shop_fax             = '{$_REQUEST["shop_fax"]}',
               shop_countryid       =  {$_REQUEST["country"]},
               shop_regionid        =  {$_REQUEST["regions"]},
               shop_provinciaid     =  {$_REQUEST["provincias"]},
               shop_comunaid        =  {$_REQUEST["comunas"]},
               shop_isremote        =  {$_REQUEST["shop_isremote"]},
               shop_cash_union      =  {$_REQUEST["shop_cash_union"]},
               shop_backup_hr       =  {$_REQUEST["shop_backup_hr"]},
               shop_backup_min      =  {$_REQUEST["shop_backup_min"]},
               shop_giro            = '{$_REQUEST["shop_giro"]}',
               shop_rel_suppid      =  {$_REQUEST["supplier_id_0"]},
               shop_rel_suppdelivid =  {$_REQUEST["shop_rel_suppdelivid"]},
               shop_rel_custid      =  {$_REQUEST["cust_id_0"]},
               shop_rel_custdelivid =  {$_REQUEST["shop_rel_custdelivid"]},
               shop_updusr          =  {$_SESSION["user_id"]},
               shop_upddat          =  {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }
   else
   {
      $sql = " insert into company_shops
               (shop_name, shop_desc, shop_street, shop_countryid, shop_regionid, shop_comunaid, shop_email,
                shop_phone, shop_executive, shop_fax, shop_cellphone, shop_company_id,
                shop_crtusr, shop_crtdat, shop_provinciaid, shop_isremote, shop_cash_union, shop_backup_hr, 
                shop_backup_min, shop_giro)
               VALUES
               ('{$_REQUEST["shop_name"]}', '{$_REQUEST["shop_desc"]}', '{$_REQUEST["shop_street"]}', 
                 {$_REQUEST["country"]}, {$_REQUEST["regions"]}, {$_REQUEST["comunas"]}, '{$_REQUEST["shop_email"]}',
                '{$_REQUEST["shop_phone"]}', '{$_REQUEST["shop_executive"]}', '{$_REQUEST["shop_fax"]}',
                '{$_REQUEST["shop_cellphone"]}', {$_REQUEST["shop_company_id"]}, {$_SESSION["user_id"]}, {$currtme},
                {$_REQUEST["provincias"]}, {$_REQUEST["shop_isremote"]}, {$_REQUEST["shop_cash_union"]},
                {$_REQUEST["shop_backup_hr"]}, {$_REQUEST["shop_backup_min"]}, '{$_REQUEST["shop_giro"]}')";
      $res = $CON->no_result($sql);
      
      if($res)
      {
         $sql = " select MAX(id) 'thisid'
                  from company_shops";
         $thisid = $CON->select($sql);
         ?>
         <script language="JavaScript">
            location.href = 'index.php?mid=564&exec=edit&id=<?=$thisid[0]["thisid"]?>';
         </script>
         <?php
      }
      $savemsg = getSaveMessage($res);
   }
   
}

if($_REQUEST["id"] != "")
{
   $title = "Cambiar sucursal";
   
   $sql = " select t1.*,
                   t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
                   t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from company_shops t1
            LEFT OUTER JOIN user t2 ON t1.shop_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.shop_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]}";
   $shop = $CON->select($sql);
   $shop = $shop[0];
}
else
{
   $title = "Agregar sucursal";
   $shop["shop_countryid"] = 81;
}

//----------------------------------------------------------------------------------
$countries  = getCountries($CON);
$regions    = getRegions($CON);
$comunas    = getComunas($CON);
$provincias = getProvincias($CON);

//----------------------------------------------------------------------------------
$sql = " select id, company_short
         from company_data
         where
         company_status = 1
         order by company_short";
$selcompanies = $CON->select($sql);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function shopformcheck(obj)
   {
      if(document.getElementById('country').value == '81')
         var frmchk = checkform(new Array(obj.shop_company_id, obj.shop_name, obj.country, obj.regions, obj.provincias, obj.comunas));
      else
         var frmchk = checkform(new Array(obj.shop_company_id, obj.shop_name, obj.country));
         
      if(!frmchk)
         return false;

      return true;
   }

   <?php
   generateCountryJS($countries, $regions, $comunas, $provincias);
   ?>
</script>
<form action="index.php" method="post" name="xform_cdata" class="fokusfirst" onsubmit="return shopformcheck(this)">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<table border="0" cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed">
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
         <col width="110">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos de sucursal I</td>
      </tr>
      <tr>
         <td class="content_rowl" height="29">Empresa *</td>
         <td class="content_row">
            <?php
            if($_REQUEST["id"] != "")
            {  ?>
               <input type="hidden" name="shop_company_id" value="<?=$shop["shop_company_id"]?>">
               <?php
               foreach($selcompanies AS $selcompany)
                  if($selcompany["id"] == $shop["shop_company_id"])
                     echo $selcompany["company_short"];
            }
            else
            {  ?>
               <select class="text" style="width:100%" name="shop_company_id"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($selcompanies AS $selcompany)
                  {  ?>
                     <option value="<?=$selcompany["id"]?>"><?=$selcompany["company_short"]?></option>
                     <?php
                  }
                  ?>
               </select>
               <?php
            }
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Nombre *</td>
         <td class="content_row">
            <input name="shop_name" type="text" class="text" style="width:100%" value="<?=$shop["shop_name"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Gerente general</td>
         <td class="content_row">
            <input name="shop_executive" type="text" class="text" style="width:100%" value="<?=$shop["shop_executive"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Dirección</td>
         <td class="content_row">
            <input name="shop_street" type="text" class="text" style="width:100%" value="<?=$shop["shop_street"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">País *</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="country" id="country"
            onchange="setRegions(this.value)"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($countries as $country)
               {  ?>
                  <option value="<?=$country["id"]?>"
                  <?php if($country["id"] == $shop["shop_countryid"]) echo "selected"?>><?=$country["country_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Región *</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="regions" id="regions"
            onchange="setProvincias(this.value);"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$shop["shop_countryid"])
               {
                  foreach($regions as $region)
                  {
                     if($region["id_pais"] == $shop["shop_countryid"])
                     {  ?>
                        <option value="<?=$region["id"]?>"
                        <?php if($region["id"] == $shop["shop_regionid"]) echo "selected"?>><?=$region["name"]?></option>
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
            <select class="text" style="width:100%" name="provincias" id="provincias" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setComunas(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$shop["shop_regionid"])
               {
                  foreach($provincias as $provincia)
                  {
                     if($provincia["region_id"] == $shop["shop_regionid"])
                     {  ?>
                        <option value="<?=$provincia["id"]?>"
                        <?php if($provincia["id"] == $shop["shop_provinciaid"]) echo "selected"?>><?=$provincia["pro_name"]?></option>
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
            <select class="text" style="width:100%" name="comunas" id="comunas" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$shop["shop_provinciaid"])
               {
                  foreach($comunas as $comuna)
                  {
                     if($comuna["prov_id"] == $shop["shop_provinciaid"])
                     {  ?>
                        <option value="<?=$comuna["id"]?>"
                        <?php if($comuna["id"] == $shop["shop_comunaid"]) echo "selected"?>><?=$comuna["nombre"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Es cliente</td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="55">
               <col>
            </colgroup>
            <tr>
               <td width="5">
                  <input type="text" class="text" style="width:50px" onfocus="markfield(this,0)" name="cust_search" id="cust_search" value=""
                  onblur="markfield(this,1); if(this.value != '') document.all.idxifrsrc.src='./libs/modules/orders/searchcust.php?rowcount=0' +'&search=' +this.value;">
               </td>
               <td>
                  <select class="text" name="cust_id_0" id="cust_id_0" style="width:306px"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     if((int)$shop["shop_rel_custid"])
                     {
                        $sql = " select *
                                 from customer
                                 where
                                 id = {$shop["shop_rel_custid"]}";
                        $custdata = $CON->select($sql);
                        $custdata = $custdata[0];
                        ?>
                        <option value="<?=$custdata["id"]?>"><?=$custdata["cust_company"]?></option>
                        <?php
                     }
                     ?>
                  </select>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
   <td></td>
   <td valign="top">
      <?=Nifty_printH("box2", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos de sucursal II</td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Tienda</td>
         <td class="content_row">
            <?php
            if((int)$_REQUEST["id"])
            {  ?>
               <input type="checkbox" value="1" <?php if((int)$shop["shop_isremote"]) echo "checked"?>
               <?php if((int)$_REQUEST["id"]) echo "disabled"?>>
               Activar Tienda/Sucursal remoto
               <input type="hidden" value="<?php if((int)$shop["shop_isremote"]) echo "1"; else echo "0"?>" name="shop_isremote">
               <?php
            }
            else
            {  ?>
               <input type="checkbox" value="1" name="shop_isremote" <?php if((int)$shop["shop_isremote"]) echo "checked"?>>
               Activar Tienda/Sucursal remoto
               <?php
            }
            ?>
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl" valign="top">Cajas/Ventas</td>
         <td class="content_row">
            <input type="radio" value="0" name="shop_cash_union" <?php if((int)$shop["shop_cash_union"] == 0) echo "checked"?>> Caja y Venta separado
            <br>
            <input type="radio" value="1" name="shop_cash_union" <?php if((int)$shop["shop_cash_union"] == 1) echo "checked"?>> Caja y Venta unido
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Giro Facturación</td>
         <td class="content_row">
            <input name="shop_giro" type="text" class="text" style="width:100%" value="<?=$shop["shop_giro"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Agendamiento respaldo</td>
         <td class="content_row">
            <input type="text" class="text" name="shop_backup_hr" style="width:60px;text-align:center"
            value="<?=sprintf("%02s", $shop["shop_backup_hr"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"> Hora
            <input type="text" class="text" name="shop_backup_min" style="width:60px;text-align:center"
            value="<?=sprintf("%02s", $shop["shop_backup_min"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"> Min
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Email</td>
         <td class="content_row">
            <input name="shop_email" type="text" class="text" style="width:100%" value="<?=$shop["shop_email"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Teléfono</td>
         <td class="content_row">
            <input name="shop_phone" type="text" class="text" style="width:100%" value="<?=$shop["shop_phone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Celular</td>
         <td class="content_row">
            <input name="shop_cellphone" type="text" class="text" style="width:100%" value="<?=$shop["shop_cellphone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Fax</td>
         <td class="content_row">
            <input name="shop_fax" type="text" class="text" style="width:100%" value="<?=$shop["shop_fax"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl" valign="top"><?=$_LANG["MODULE"]["CUST"][13]?></td>
         <td class="content_row">
            <textarea name="shop_desc" class="text" style="width:100%; height:58px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$shop["shop_desc"]?></textarea>
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Es proveedor</td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td width="160">
                  <input type="text" class="text" style="width:50px" onfocus="markfield(this,0)" name="supplier_search" value=""
                  onblur="markfield(this,1); if(this.value != '') document.all.idxifrsrc.src='./libs/modules/items/searchsupplier.php?rowcount=0' +'&search=' +this.value;">
               </td>
               <td>
                  <select class="text" name="supplier_id_0" style="width:190px"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     if((int)$shop["shop_rel_suppid"])
                     {
                        $sql = " select *
                                 from supplier
                                 where
                                 id = {$shop["shop_rel_suppid"]}";
                        $supdata = $CON->select($sql);
                        $supdata = $supdata[0];
                        ?>
                        <option value="<?=$supdata["id"]?>"><?=$supdata["supp_company"]?></option>
                        <?php
                     }
                     ?>
                  </select>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="29">Creado por</td>
         <td class="content_row"><?=$shop["crt_firstname"]?> <?=$shop["crt_lastname"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl" height="29">Creado</td>
         <td class="content_row"><?=displayDate($shop["shop_crtdat"])?></td>
      </tr>
      <tr>
         <td class="content_rowl" height="29">Cambiado por</td>
         <td class="content_row"><?=$shop["upd_firstname"]?> <?=$shop["upd_lastname"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl" height="29">Cambiado</td>
         <td class="content_row"><?=displayDate($shop["shop_upddat"])?></td>
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_cdata)", "disk-black");
      ?>
   </td>
</tr>
</table>
</form>
<?=Nifty_printF(false)?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_cdata');" ?>