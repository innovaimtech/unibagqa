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
   
   $_REQUEST["company_name"]           = trim(addslashes($_REQUEST["company_name"]));
   $_REQUEST["company_short"]          = trim(addslashes($_REQUEST["company_short"]));
   $_REQUEST["company_rut"]            = trim(addslashes($_REQUEST["company_rut"]));
   $_REQUEST["company_street"]         = trim(addslashes($_REQUEST["company_street"]));
   $_REQUEST["company_cellphone"]      = trim(addslashes($_REQUEST["company_cellphone"]));
   $_REQUEST["company_email"]          = trim(addslashes($_REQUEST["company_email"]));
   $_REQUEST["company_phone"]          = trim(addslashes($_REQUEST["company_phone"]));
   $_REQUEST["company_executive"]      = trim(addslashes($_REQUEST["company_executive"]));
   $_REQUEST["company_fax"]            = trim(addslashes($_REQUEST["company_fax"]));
   $_REQUEST["company_website"]        = trim(addslashes($_REQUEST["company_website"]));

   $_REQUEST["country"]          = (int)$_REQUEST["country"];
   $_REQUEST["regions"]          = (int)$_REQUEST["regions"];
   $_REQUEST["provincias"]       = (int)$_REQUEST["provincias"];
   $_REQUEST["comunas"]          = (int)$_REQUEST["comunas"];

   $_REQUEST["company_numformat_invoice"]       = trim(addslashes($_REQUEST["company_numformat_invoice"]));
   $_REQUEST["company_numformat_offer"]         = trim(addslashes($_REQUEST["company_numformat_offer"]));
   $_REQUEST["company_numformat_order"]         = trim(addslashes($_REQUEST["company_numformat_order"]));
   $_REQUEST["company_numformat_transfer"]      = trim(addslashes($_REQUEST["company_numformat_transfer"]));
   $_REQUEST["company_numformat_stockchange"]   = trim(addslashes($_REQUEST["company_numformat_stockchange"]));
   $_REQUEST["company_numformat_shipment"]      = trim(addslashes($_REQUEST["company_numformat_shipment"]));
   $_REQUEST["company_numformat_stockcount"]    = trim(addslashes($_REQUEST["company_numformat_stockcount"]));
   $_REQUEST["company_numformat_invoicebuy"]    = trim(addslashes($_REQUEST["company_numformat_invoicebuy"]));
   $_REQUEST["company_numformat_storehouse"]    = trim(addslashes($_REQUEST["company_numformat_storehouse"]));

   $_REQUEST["company_numformat_shipmentsell"]        = trim(addslashes($_REQUEST["company_numformat_shipmentsell"]));
   $_REQUEST["company_numformat_invoicesell"]         = trim(addslashes($_REQUEST["company_numformat_invoicesell"]));
   $_REQUEST["company_numformat_invoicesellnotecred"] = trim(addslashes($_REQUEST["company_numformat_invoicesellnotecred"]));
   $_REQUEST["company_numformat_invoicesellnotedeb"]  = trim(addslashes($_REQUEST["company_numformat_invoicesellnotedeb"]));
   $_REQUEST["company_numformat_invoicebuynotecred"]  = trim(addslashes($_REQUEST["company_numformat_invoicebuynotecred"]));
   $_REQUEST["company_numformat_invoicebuynotedeb"]   = trim(addslashes($_REQUEST["company_numformat_invoicebuynotedeb"]));

   $_REQUEST["company_numcounter_invoice"]             = (int)$_REQUEST["company_numcounter_invoice"];
   $_REQUEST["company_numcounter_offer"]               = (int)$_REQUEST["company_numcounter_offer"];
   $_REQUEST["company_numcounter_order"]               = (int)$_REQUEST["company_numcounter_order"];
   $_REQUEST["company_numcounter_transfer"]            = (int)$_REQUEST["company_numcounter_transfer"];
   $_REQUEST["company_numcounter_stockchange"]         = (int)$_REQUEST["company_numcounter_stockchange"];
   $_REQUEST["company_numcounter_shipment"]            = (int)$_REQUEST["company_numcounter_shipment"];
   $_REQUEST["company_numcounter_stockcount"]          = (int)$_REQUEST["company_numcounter_stockcount"];
   $_REQUEST["company_numcounter_invoicebuy"]          = (int)$_REQUEST["company_numcounter_invoicebuy"];
   $_REQUEST["company_numcounter_storehouse"]          = (int)$_REQUEST["company_numcounter_storehouse"];
   $_REQUEST["company_numcounter_stockprod"]           = (int)$_REQUEST["company_numcounter_stockprod"];
   $_REQUEST["company_numcounter_shipmentsell"]        = (int)$_REQUEST["company_numcounter_shipmentsell"];
   $_REQUEST["company_numcounter_invoicesell"]         = (int)$_REQUEST["company_numcounter_invoicesell"];
   $_REQUEST["company_numcounter_invoicesellnotecred"] = (int)$_REQUEST["company_numcounter_invoicesellnotecred"];
   $_REQUEST["company_numcounter_invoicesellnotedeb"]  = (int)$_REQUEST["company_numcounter_invoicesellnotedeb"];
   $_REQUEST["company_numcounter_invoicebuynotecred"]  = (int)$_REQUEST["company_numcounter_invoicebuynotecred"];
   $_REQUEST["company_numcounter_invoicebuynotedeb"]   = (int)$_REQUEST["company_numcounter_invoicebuynotedeb"];

   $_REQUEST["company_iva"]                            = (int)$_REQUEST["company_iva"];
   $_REQUEST["company_simbolo_moneda"]                 = trim(addslashes($_REQUEST["company_simbolo_moneda"]));
   $_REQUEST["company_num_decimales"]                  = (int)$_REQUEST["company_num_decimales"];
   $_REQUEST["company_ctacte"]                         = trim(addslashes($_REQUEST["company_ctacte"]));
   $_REQUEST["company_banco"]                          = trim(addslashes($_REQUEST["company_banco"]));


   if($_REQUEST["id"] != "")
   {
      $sql = " update company_data
               set
               company_name                            = '{$_REQUEST["company_name"]}',
               company_short                           = '{$_REQUEST["company_short"]}',
               company_street                          = '{$_REQUEST["company_street"]}',
               company_email                           = '{$_REQUEST["company_email"]}',
               company_cellphone                       = '{$_REQUEST["company_cellphone"]}',
               company_phone                           = '{$_REQUEST["company_phone"]}',
               company_executive                       = '{$_REQUEST["company_executive"]}',
               company_fax                             = '{$_REQUEST["company_fax"]}',
               company_website                         = '{$_REQUEST["company_website"]}',
               company_rut                             = '{$_REQUEST["company_rut"]}',
               company_countryid                       =  {$_REQUEST["country"]},
               company_regionid                        =  {$_REQUEST["regions"]},
               company_provinciaid                     =  {$_REQUEST["provincias"]},
               company_comunaid                        =  {$_REQUEST["comunas"]},
               company_updusr                          =  {$_SESSION["user_id"]},
               company_upddat                          =  {$currtme},
               company_numformat_invoice               = '{$_REQUEST["company_numformat_invoice"]}',
               company_numformat_order                 = '{$_REQUEST["company_numformat_order"]}',
               company_numformat_storehouse            = '{$_REQUEST["company_numformat_storehouse"]}',
               company_numformat_stockchange           = '{$_REQUEST["company_numformat_stockchange"]}',
               company_numformat_shipment              = '{$_REQUEST["company_numformat_shipment"]}',
               company_numformat_stockcount            = '{$_REQUEST["company_numformat_stockcount"]}',
               company_numformat_invoicebuy            = '{$_REQUEST["company_numformat_invoicebuy"]}',
               company_numformat_shipmentsell          = '{$_REQUEST["company_numformat_shipmentsell"]}',
               company_numformat_invoicesell           = '{$_REQUEST["company_numformat_invoicesell"]}',
               company_numformat_invoicesellnotecred   = '{$_REQUEST["company_numformat_invoicesellnotecred"]}',
               company_numformat_invoicesellnotedeb    = '{$_REQUEST["company_numformat_invoicesellnotedeb"]}',
               company_numformat_invoicebuynotecred    = '{$_REQUEST["company_numformat_invoicebuynotecred"]}',
               company_numformat_invoicebuynotedeb     = '{$_REQUEST["company_numformat_invoicebuynotedeb"]}',
               company_numformat_offer                 = '{$_REQUEST["company_numformat_offer"]}',
               company_numcounter_invoice              = {$_REQUEST["company_numcounter_invoice"]},
               company_numcounter_order                = {$_REQUEST["company_numcounter_order"]},
               company_numcounter_storehouse           = {$_REQUEST["company_numcounter_storehouse"]},
               company_numcounter_stockchange          = {$_REQUEST["company_numcounter_stockchange"]},
               company_numcounter_shipment             = {$_REQUEST["company_numcounter_shipment"]},
               company_numcounter_stockcount           = {$_REQUEST["company_numcounter_stockcount"]},
               company_numcounter_invoicebuy           = {$_REQUEST["company_numcounter_invoicebuy"]},
               company_numcounter_shipmentsell         = {$_REQUEST["company_numcounter_shipmentsell"]},
               company_numcounter_invoicesell          = {$_REQUEST["company_numcounter_invoicesell"]},
               company_numcounter_invoicesellnotecred  = {$_REQUEST["company_numcounter_invoicesellnotecred"]},
               company_numcounter_invoicesellnotedeb   = {$_REQUEST["company_numcounter_invoicesellnotedeb"]},
               company_numcounter_invoicebuynotecred   = {$_REQUEST["company_numcounter_invoicebuynotecred"]},
               company_numcounter_invoicebuynotedeb    = {$_REQUEST["company_numcounter_invoicebuynotedeb"]},
               company_numcounter_offer                = {$_REQUEST["company_numcounter_offer"]},
               company_iva                             = {$_REQUEST["company_iva"]},
               company_simbolo_moneda                  = '{$_REQUEST["company_simbolo_moneda"]}',
               company_num_decimales                   = {$_REQUEST["company_num_decimales"]},
               company_ctacte                          = '{$_REQUEST["company_ctacte"]}',
               company_banco                           = '{$_REQUEST["company_banco"]}'
               where
               id = {$_REQUEST["id"]}" ;
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }
   else
   {
      $sql = " insert into company_data
               (company_name, company_street, company_email, company_countryid, company_regionid, company_comunaid,
                company_phone, company_executive, company_fax, company_website, company_short, company_cellphone,
                company_rut, company_numformat_invoice, company_numformat_order, company_numformat_storehouse,
                company_numformat_stockchange, company_numformat_shipment, company_numformat_stockcount,
                company_numformat_invoicebuy, company_numformat_shipmentsell, company_numformat_invoicesell,
                company_numformat_invoicesellnotecred, company_numformat_invoicesellnotedeb, company_numformat_invoicebuynotecred,
                company_numformat_invoicebuynotedeb, company_numformat_offer, company_crtusr, company_crtdat, company_provinciaid,
                company_iva,company_simbolo_moneda,company_num_decimales,company_ctacte,company_banco)
               VALUES
               ('{$_REQUEST["company_name"]}', '{$_REQUEST["company_street"]}', '{$_REQUEST["company_email"]}',
                 {$_REQUEST["country"]}, {$_REQUEST["regions"]}, {$_REQUEST["comunas"]},
                '{$_REQUEST["company_phone"]}', '{$_REQUEST["company_executive"]}', '{$_REQUEST["company_fax"]}',
                '{$_REQUEST["company_website"]}', '{$_REQUEST["company_short"]}', '{$_REQUEST["company_cellphone"]}',
                '{$_REQUEST["company_rut"]}', '{$_REQUEST["company_numformat_invoice"]}', '{$_REQUEST["company_numformat_order"]}',
                '{$_REQUEST["company_numformat_storehouse"]}', '{$_REQUEST["company_numformat_stockchange"]}',
                '{$_REQUEST["company_numformat_shipment"]}', '{$_REQUEST["company_numformat_stockcount"]}',
                '{$_REQUEST["company_numformat_invoicebuy"]}', '{$_REQUEST["company_numformat_shipmentsell"]}',
                '{$_REQUEST["company_numformat_invoicesell"]}', '{$_REQUEST["company_numformat_invoicesellnotecred"]}',
                '{$_REQUEST["company_numformat_invoicesellnotedeb"]}', '{$_REQUEST["company_numformat_invoicebuynotecred"]}',
                '{$_REQUEST["company_numformat_invoicebuynotedeb"]}', '{$_REQUEST["company_numformat_offer"]}',
                 {$_SESSION["user_id"]}, {$currtme}, {$_REQUEST["provincias"]}, {$_REQUEST["company_iva"]},
                '{$_REQUEST["company_simbolo_moneda"]}',{$_REQUEST["company_num_decimales"]},'{$_REQUEST["company_ctacte"]}','{$_REQUEST["company_banco"]}')";

      $res = $CON->no_result($sql);
      
      if($res)
      {
         $sql = " select MAX(id) 'thisid'
                  from company_data";
         $thisid = $CON->select($sql);

         ?>
         <script language="JavaScript">
            location.href = 'index.php?mid=561&exec=edit&id=<?=$thisid[0]["thisid"]?>';
         </script>
         <?php
      }
   }
   
}

if($_REQUEST["id"] != "")
{
   $sql = " select t1.*,
                   t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
                   t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from company_data t1
            LEFT OUTER JOIN user t2 ON t1.company_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.company_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]}";
   $company = $CON->select($sql);
   $company = $company[0];
}
else
   $company["company_countryid"] = 81;

//----------------------------------------------------------------------------------
$countries  = getCountries($CON);
$regions    = getRegions($CON);
$comunas    = getComunas($CON);
$provincias = getProvincias($CON);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function compformcheck(obj)
   {
      if(document.getElementById('country').value == '81')
      {
         var rutchk = Rut(obj.company_rut, obj.company_rut.value);
         if(!rutchk)
            return false;
         
         var frmchk = checkform(new Array(obj.company_rut, obj.company_name, obj.company_short, obj.country, obj.regions, obj.provincias, obj.comunas));
      }
      else
         var frmchk = checkform(new Array(obj.company_rut, obj.company_name, obj.company_short, obj.country));
         
      if(!frmchk)
         return false;

      return true;
   }
   
   <?php
   generateCountryJS($countries, $regions, $comunas, $provincias);
   ?>
</script>
<form action="index.php" method="post" name="xform_cdata" class="fokusfirst" onsubmit="return compformcheck(this)">
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
         <td class="content_tbl_header" colspan="2">Datos de empresa</td>
      </tr>
      <tr>
         <td class="content_rowl">Razón Social *</td>
         <td class="content_row">
            <input name="company_name" type="text" class="text" style="width:100%" value="<?=$company["company_name"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Empresa *</td>
         <td class="content_row">
            <input name="company_short" type="text" class="text" style="width:100%" value="<?=$company["company_short"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">RUT *</td>
         <td class="content_row">
            <input name="company_rut" type="text" class="text" style="width:100px" value="<?=$company["company_rut"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Gerente general</td>
         <td class="content_row">
            <input name="company_executive" type="text" class="text" style="width:100%" value="<?=$company["company_executive"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Dirección</td>
         <td class="content_row">
            <input name="company_street" type="text" class="text" style="width:100%" value="<?=$company["company_street"]?>"
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
                  <?php if($country["id"] == $company["company_countryid"]) echo "selected"?>><?=$country["country_name"]?></option>
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
               if((int)$company["company_countryid"])
               {
                  foreach($regions as $region)
                  {
                     if($region["id_pais"] == $company["company_countryid"])
                     {  ?>
                        <option value="<?=$region["id"]?>"
                        <?php if($region["id"] == $company["company_regionid"]) echo "selected"?>><?=$region["name"]?></option>
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
               if((int)$company["company_regionid"])
               {
                  foreach($provincias as $provincia)
                  {
                     if($provincia["region_id"] == $company["company_regionid"])
                     {  ?>
                        <option value="<?=$provincia["id"]?>"
                        <?php if($provincia["id"] == $company["company_provinciaid"]) echo "selected"?>><?=$provincia["pro_name"]?></option>
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
               if((int)$company["company_provinciaid"])
               {
                  foreach($comunas as $comuna)
                  {
                     if($comuna["prov_id"] == $company["company_provinciaid"])
                     {  ?>
                        <option value="<?=$comuna["id"]?>"
                        <?php if($comuna["id"] == $company["company_comunaid"]) echo "selected"?>><?=$comuna["nombre"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Impuesto (IVA)</td>
         <td class="content_row">
            <input name="company_iva" type="text" class="text" style="width:100px" value="<?=$company["company_iva"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Simbolo de Moneda</td>
         <td class="content_row">
            <input name="company_simbolo_moneda" type="text" class="text" style="width:100px" value="<?=$company["company_simbolo_moneda"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Numero de Decimales</td>
         <td class="content_row">
            <input name="company_num_decimales" type="text" class="text" style="width:100px" value="<?=$company["company_num_decimales"]?>"
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
         <col width="110">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos de empresa</td>
      </tr>
      <tr>
         <td class="content_rowl">Email</td>
         <td class="content_row">
            <input name="company_email" type="text" class="text" style="width:100%" value="<?=$company["company_email"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Teléfono</td>
         <td class="content_row">
            <input name="company_phone" type="text" class="text" style="width:100%" value="<?=$company["company_phone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Celular</td>
         <td class="content_row">
            <input name="company_cellphone" type="text" class="text" style="width:100%" value="<?=$company["company_cellphone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Fax</td>
         <td class="content_row">
            <input name="company_fax" type="text" class="text" style="width:100%" value="<?=$company["company_fax"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Sitio web</td>
         <td class="content_row">
            <input name="company_website" type="text" class="text" style="width:100%" value="<?=$company["company_website"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Banco</td>
         <td class="content_row">
            <input name="company_banco" type="text" class="text" style="width:100%" value="<?=$company["company_banco"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Cuenta Corriente</td>
         <td class="content_row">
            <input name="company_ctacte" type="text" class="text" style="width:100%" value="<?=$company["company_ctacte"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="29">Creado por</td>
         <td class="content_row"><?=$company["crt_firstname"]?> <?=$company["crt_lastname"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl" height="29">Creado</td>
         <td class="content_row"><?=displayDate($company["company_crtdat"])?></td>
      </tr>
      <tr>
         <td class="content_rowl" height="29">Cambiado por</td>
         <td class="content_row"><?=$company["upd_firstname"]?> <?=$company["upd_lastname"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl" height="29">Cambiado</td>
         <td class="content_row"><?=displayDate($company["company_upddat"])?></td>
      </tr>
      </table>
      <?=Nifty_printF()?>
   </td>
   <td valign="top" style="display:none">
      <?=Nifty_printH("box2", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="180">
         <col width="80">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="3">Números correlativos</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader">Tipo de transacción</td>
         <td class="content_tbl_subheader">Formato</td>
         <td class="content_tbl_subheader" align="center">Número correlativo</td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Venta: Cotización</b></td>
         <td class="content_row">
            <input name="company_numformat_offer" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_offer"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_offer" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_offer"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Venta: Confirmación de compra</b></td>
         <td class="content_row">
            <input name="company_numformat_invoice" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_invoice"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_invoice" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_invoice"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Venta: Guia de despacho</b></td>
         <td class="content_row">
            <input name="company_numformat_shipmentsell" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_shipmentsell"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_shipmentsell" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_shipmentsell"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Venta: Factura</b></td>
         <td class="content_row">
            <input name="company_numformat_invoicesell" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_invoicesell"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_invoicesell" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_invoicesell"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Venta: Nota de credito</b></td>
         <td class="content_row">
            <input name="company_numformat_invoicesellnotecred" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_invoicesellnotecred"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_invoicesellnotecred" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_invoicesellnotecred"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Venta: Nota de debito</b></td>
         <td class="content_row">
            <input name="company_numformat_invoicesellnotedeb" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_invoicesellnotedeb"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_invoicesellnotedeb" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_invoicesellnotedeb"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Compra: Orden de compra</b></td>
         <td class="content_row">
            <input name="company_numformat_order" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_order"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_order" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_order"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Compra: Guia de despacho</b></td>
         <td class="content_row">
            <input name="company_numformat_shipment" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_shipment"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_shipment" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_shipment"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Compra: Factura</b></td>
         <td class="content_row">
            <input name="company_numformat_invoicebuy" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_invoicebuy"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_invoicebuy" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_invoicebuy"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Compra: Nota de credito</b></td>
         <td class="content_row">
            <input name="company_numformat_invoicebuynotecred" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_invoicebuynotecred"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_invoicebuynotecred" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_invoicebuynotecred"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Compra: Nota de debito</b></td>
         <td class="content_row">
            <input name="company_numformat_invoicebuynotedeb" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_invoicebuynotedeb"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_invoicebuynotedeb" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_invoicebuynotedeb"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Inventario: Traspaso</b></td>
         <td class="content_row">
            <input name="company_numformat_storehouse" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_storehouse"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_storehouse" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_storehouse"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Inventario: Ajuste stock</b></td>
         <td class="content_row">
            <input name="company_numformat_stockchange" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_stockchange"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_stockchange" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_stockchange"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b>Inventario: Recuento</b></td>
         <td class="content_row">
            <input name="company_numformat_stockcount" type="text" class="text" style="width:80px" value="<?=$company["company_numformat_stockcount"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="center">
            <input name="company_numcounter_stockcount" type="text" class="text" style="width:80px;text-align:center" value="<?=(int)$company["company_numcounter_stockcount"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
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
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_cdata');" ?>