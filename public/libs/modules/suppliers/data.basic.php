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

   $_REQUEST["supp_company"]             = trim(addslashes($_REQUEST["supp_company"]));
   $_REQUEST["supp_short"]               = trim(addslashes($_REQUEST["supp_short"]));
   $_REQUEST["supp_street"]              = trim(addslashes($_REQUEST["supp_street"]));
   $_REQUEST["supp_phone"]               = trim(addslashes($_REQUEST["supp_phone"]));
   $_REQUEST["supp_cellphone"]           = trim(addslashes($_REQUEST["supp_cellphone"]));
   $_REQUEST["supp_fax"]                 = trim(addslashes($_REQUEST["supp_fax"]));
   $_REQUEST["supp_email"]               = trim(addslashes($_REQUEST["supp_email"]));
   $_REQUEST["supp_website"]             = trim(addslashes($_REQUEST["supp_website"]));
   $_REQUEST["supp_rut"]                 = trim(addslashes($_REQUEST["supp_rut"]));
   $_REQUEST["country"]                  = (int)$_REQUEST["country"];
   $_REQUEST["regions"]                  = (int)$_REQUEST["regions"];
   $_REQUEST["provincias"]               = (int)$_REQUEST["provincias"];
   $_REQUEST["comunas"]                  = (int)$_REQUEST["comunas"];
   $_REQUEST["supp_delivery_days"]       = (int)$_REQUEST["supp_delivery_days"];
   $_REQUEST["supp_giroid"]              = (int)$_REQUEST["supp_giroid"];
   $_REQUEST["supp_paymentid"]           = (int)$_REQUEST["supp_paymentid"];
   $_REQUEST["supp_marketing_act"]       = (int)$_REQUEST["supp_marketing_act"];
   $_REQUEST["supp_clique_act"]          = (int)$_REQUEST["supp_clique_act"];
   $_REQUEST["supp_aprobrange_disabled"] = (int)$_REQUEST["supp_aprobrange_disabled"];
   $_REQUEST["supp_order_minval"]        = getPrice($_REQUEST["supp_order_minval"],2);
   $_REQUEST["supp_dsc_finance"]         = getPrice($_REQUEST["supp_dsc_finance"],2);
   $_REQUEST["supp_catid"]               = (int)$_REQUEST["supp_catid"];
   $_REQUEST["supp_cod_moneda"]          = trim(addslashes($_REQUEST["supp_cod_moneda"]));
   

   //----------------------------------------------------------------------------------
   if($_REQUEST["supp_dsc_finance_calc"] == "")
      $_REQUEST["supp_dsc_finance_calc"] = "OC";
      
   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $sql = " insert into supplier
               (supp_company, supp_street, supp_phone, supp_fax, supp_email,
                supp_website, supp_notes, supp_cellphone, supp_rut, supp_giroid, supp_short,
                supp_delivery_days, supp_countryid, supp_regionid, supp_comunaid, supp_paymentid,
                supp_order_minval, supp_dsc_finance, supp_dsc_finance_calc, supp_marketing_act,
                supp_crtusr, supp_crtdat, supp_provinciaid, supp_clique_act, supp_catid, supp_cod_moneda)
               VALUES
               ('{$_REQUEST["supp_company"]}', '{$_REQUEST["supp_street"]}', '{$_REQUEST["supp_phone"]}', '{$_REQUEST["supp_fax"]}',
                '{$_REQUEST["supp_email"]}', '{$_REQUEST["supp_website"]}', '{$_REQUEST["supp_notes"]}',
                '{$_REQUEST["supp_cellphone"]}', '{$_REQUEST["supp_rut"]}', {$_REQUEST["supp_giroid"]},
                '{$_REQUEST["supp_short"]}', {$_REQUEST["supp_delivery_days"]},
                 {$_REQUEST["country"]}, {$_REQUEST["regions"]}, {$_REQUEST["comunas"]}, {$_REQUEST["supp_paymentid"]},
                 {$_REQUEST["supp_order_minval"]}, {$_REQUEST["supp_dsc_finance"]}, '{$_REQUEST["supp_dsc_finance_calc"]}', 
                 {$_REQUEST["supp_marketing_act"]}, {$_SESSION["user_id"]}, {$currtme}, {$_REQUEST["provincias"]},
                 {$_REQUEST["supp_clique_act"]},{$_REQUEST["supp_catid"]},'{$_REQUEST["supp_cod_moneda"]}')";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'thisid'
                  from supplier
                  where
                  supp_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);

         if((int)$_SESSION["user_type"] == 1)
         {
            $sql = " update supplier
                     set
                     supp_aprobrange_disabled = {$_REQUEST["supp_aprobrange_disabled"]}
                     where
                     id = {$thisid[0]["thisid"]}";
            $CON->no_result($sql);
         }

         ?>
         <script language="JavaScript">
            location.href = 'index.php?mid=497&exec=edit&id=<?=$thisid[0]["thisid"]?>';
         </script>
         <?php
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update supplier
               set
               supp_company            = '{$_REQUEST["supp_company"]}',
               supp_short              = '{$_REQUEST["supp_short"]}',
               supp_street             = '{$_REQUEST["supp_street"]}',
               supp_phone              = '{$_REQUEST["supp_phone"]}',
               supp_cellphone          = '{$_REQUEST["supp_cellphone"]}',
               supp_fax                = '{$_REQUEST["supp_fax"]}',
               supp_email              = '{$_REQUEST["supp_email"]}',
               supp_website            = '{$_REQUEST["supp_website"]}',
               supp_rut                = '{$_REQUEST["supp_rut"]}',
               supp_delivery_days      =  {$_REQUEST["supp_delivery_days"]},
               supp_countryid          =  {$_REQUEST["country"]},
               supp_regionid           =  {$_REQUEST["regions"]},
               supp_provinciaid        =  {$_REQUEST["provincias"]},
               supp_comunaid           =  {$_REQUEST["comunas"]},
               supp_giroid             =  {$_REQUEST["supp_giroid"]},
               supp_paymentid          =  {$_REQUEST["supp_paymentid"]},
               supp_order_minval       =  {$_REQUEST["supp_order_minval"]},
               supp_dsc_finance        =  {$_REQUEST["supp_dsc_finance"]},
               supp_dsc_finance_calc   = '{$_REQUEST["supp_dsc_finance_calc"]}',
               supp_marketing_act      =  {$_REQUEST["supp_marketing_act"]},
               supp_clique_act         =  {$_REQUEST["supp_clique_act"]},
               supp_updusr             =  {$_SESSION["user_id"]},
               supp_catid              =  {$_REQUEST["supp_catid"]},
               supp_cod_moneda         =  '{$_REQUEST["supp_cod_moneda"]}',
               supp_upddat             =  {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      
      if((int)$_SESSION["user_type"] == 1)
      {
         $sql = " update supplier
                  set
                  supp_aprobrange_disabled = {$_REQUEST["supp_aprobrange_disabled"]}
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
   }


   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   // set title
   $title = $_LANG["MODULE"]["SUPP"][0];
   
   $sql = " select t1.*, 
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from supplier t1
            LEFT OUTER JOIN user t2 ON t1.supp_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.supp_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $supplier = $CON->select($sql);
   $supplier = $supplier[0];
}
else
{
   $title = $_LANG["MODULE"]["SUPP"][1];
   $supplier["supp_countryid"] = 81;
}

//----------------------------------------------------------------------------------
$countries  = getCountries($CON);
$regions    = getRegions($CON);
$comunas    = getComunas($CON);
$provincias = getProvincias($CON);
$giros      = getGiros($CON);
$monedas    = $CON->select("select * from parametros where tabla = 'MONEDA'");
$payments   = $CON->select("select * from payments where pay_status > 0 order by pay_title");
$custcats   = $CON->select("select t1.id, t1.cat_name, t1.cat_crtdat from customer_cats t1 where t1.cat_status > 0 order by t1.cat_name");
//----------------------------------------------------------------------------------------

if($_REQUEST["id"] != "")
   $title = $_LANG["MODULE"]["SUPP"][0];
else
   $title = $_LANG["MODULE"]["SUPP"][1];
   
//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function suppformcheck(obj)
   {
      var rutchk = Rut(obj.supp_rut, obj.supp_rut.value);
      if(!rutchk)
         return false;

      if(document.getElementById('country').value == '81')
         var frmchk = checkform(new Array(obj.supp_rut, obj.supp_company, obj.supp_short, obj.supp_street, obj.country, obj.regions, obj.provincias, obj.comunas, obj.supp_giroid, obj.supp_catid, obj.supp_cod_moneda));
      else
         var frmchk = checkform(new Array(obj.supp_rut, obj.supp_company, obj.supp_short, obj.supp_street, obj.country, obj.supp_giroid, obj.supp_catid, obj.supp_cod_moneda));
         
      if(!frmchk)
         return false;
      else
      {
         if(obj.supp_email.value != '')
         {
            if(!avzCheckEmail(obj.supp_email.value))
            {
               alert('Email no valido.');
               return false;
            }
         }
      }

      obj.supp_phone.value       = obj.supp_phone.value.replace(/\D/g,'');
      obj.supp_cellphone.value   = obj.supp_cellphone.value.replace(/\D/g,'');

      if((obj.supp_phone.value != '' && obj.supp_phone.value.length < 9) ||
         (obj.supp_cellphone.value != '' && obj.supp_cellphone.value.length < 9))
      {
         alert('Telefono/Celular no valido.');
         return false;
      }

      return true;
   }
   <?php
   generateCountryJS($countries, $regions, $comunas, $provincias);
   ?>
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_cust" onsubmit="return suppformcheck(this)">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<table cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed">
<colgroup>
   <col width="500" valign="top">
   <col width="15">
   <col valign="top">
</colgroup>
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="120">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["SUPP"][2]?> I</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][19]?></td>
         <td class="content_row">
            <input name="supp_rut" type="text" class="text" style="width:100px" value="<?=$supplier["supp_rut"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr> 
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][3]?></td>
         <td class="content_row">
            <input name="supp_company" type="text" class="text" style="width:370px" value="<?=$supplier["supp_company"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][4]?></td>
         <td class="content_row">
            <input name="supp_short" type="text" class="text" style="width:370px" value="<?=$supplier["supp_short"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][6]?></td>
         <td class="content_row">
            <input name="supp_street" type="text" class="text" style="width:370px" value="<?=$supplier["supp_street"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Comuna *</td>
         <td class="content_row">
            <select class="text" style="width:370px" name="comunas" id="comunas" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="jqUnibagSetComuna(this.value, 'supplier')">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               $allcomunas = getAllComunas($CON);
               foreach($allcomunas AS $comuna)
               {  ?>
                  <option value="<?=$comuna["id"]?>"
                  <?php if($comuna["id"] == $supplier["supp_comunaid"]) echo "selected"?>><?=$comuna["nombre"]?></option>
                  <?php
               }
               /*
               if((int)$supplier["supp_provinciaid"])
               {
                  foreach($comunas as $comuna)
                  {
                     if($comuna["prov_id"] == $supplier["supp_provinciaid"])
                     {  ?>
                        <option value="<?=$comuna["id"]?>"
                        <?php if($comuna["id"] == $supplier["supp_comunaid"]) echo "selected"?>><?=$comuna["nombre"]?></option>
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
            <select class="text" style="width:370px" name="provincias" id="provincias" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setComunas(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$supplier["supp_regionid"])
               {
                  foreach($provincias as $provincia)
                  {
                     if($provincia["region_id"] == $supplier["supp_regionid"])
                     {  ?>
                        <option value="<?=$provincia["id"]?>"
                        <?php if($provincia["id"] == $supplier["supp_provinciaid"]) echo "selected"?>><?=$provincia["pro_name"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Region *</td>
         <td class="content_row" width="130">
            <select class="text" style="width:370px" name="regions" id="regions"
            onchange="setProvincias(this.value);" 
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$supplier["supp_countryid"])
               {
                  foreach($regions as $region)
                  {
                     if($region["id_pais"] == $supplier["supp_countryid"])
                     {  ?>
                        <option value="<?=$region["id"]?>"
                        <?php if($region["id"] == $supplier["supp_regionid"]) echo "selected"?>><?=$region["name"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Pais *</td>
         <td class="content_row" width="130">
            <select class="text" style="width:370px" name="country" id="country"
            onchange="setRegions(this.value); ; updateCurrencyByCountry(this.value)"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($countries as $country)
               {  ?>
                  <option value="<?=$country["id"]?>"
                  <?php if($country["id"] == $supplier["supp_countryid"]) echo "selected"?>><?=$country["country_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Moneda</td>
          <td class="content_row" width="130">
            <select class="text" style="width:370px" name="supp_cod_moneda" id="supp_cod_moneda"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if ($supplier["supp_countryid"] == 81 || $_REQUEST["supp_countryid"] == 81) {
                   $supplier["supp_cod_moneda"] = "CL";
               }
               foreach($monedas as $money)
               {  ?>
                  <option value="<?=$money["codigo"]?>"
                  <?php if($money["codigo"] == $supplier["supp_cod_moneda"]) echo "selected"?>><?=$money["descripcion"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][14]?></td>
         <td class="content_row"><?php if($supplier["supp_crtusr"] != "") echo "{$supplier["crt_firstname"]} {$supplier["crt_lastname"]}"?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][15]?></td>
         <td class="content_row"><?php if($supplier["supp_crtusr"] != "") echo displayDate($supplier["supp_crtdat"])?>&nbsp;</td>
      </tr>
      </table>
      <?=Nifty_printF()?>
   </td>
   <td></td>
   <td valign="top">
      <?=Nifty_printH("box2", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="140">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["SUPP"][2]?> II</td>
      </tr>
      <tr>
         <td class="content_rowl">Giro *</td>
         <td class="content_row">
            <select class="text" style="width:305px" name="supp_giroid" id="supp_giroid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($giros as $giro)
               {  ?>
                  <option value="<?=$giro["id"]?>"
                  <?php if($giro["id"] == $supplier["supp_giroid"]) echo "selected"?>><?=$giro["giro_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Rubro *</td>
         <td class="content_row">
            <select class="text" style="width:305px" name="supp_catid" id="supp_catid" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($custcats as $custcat)
               {  ?>
                  <option value="<?=$custcat["id"]?>"
                  <?php if($custcat["id"] == $supplier["supp_catid"]) echo "selected"?>><?=$custcat["cat_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>      
      <tr>
         <td class="content_rowl">Forma de pago</td>
         <td class="content_row">
            <select class="text" style="width:305px" name="supp_paymentid" id="supp_paymentid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($payments as $payment)
               {  ?>
                  <option value="<?=$payment["id"]?>"
                  <?php if($payment["id"] == $supplier["supp_paymentid"]) echo "selected"?>><?=$payment["pay_title"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">OC Monto minimo <?=$_SESSION["_CONF"]["conf_currency"]?></td>
         <td class="content_row">
            <input name="supp_order_minval" type="text" class="text" style="width:80px"
            value="<?=printPrice($supplier["supp_order_minval"],2)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td> 
      </tr>
      <tr style="display:none">
         <td class="content_rowl" valign="top">Descuento financiero</td>
         <td class="content_row" valign="top">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td class="content_row_clear" valign="top">
                  <input name="supp_dsc_finance" type="text" class="text" style="width:80px"
                  value="<?=printPrice($supplier["supp_dsc_finance"],2)?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"> %
               </td>
               <td class="content_row_clear">
                  <input type="radio" name="supp_dsc_finance_calc" value="OC" <?php if($supplier["supp_dsc_finance_calc"] == "OC") echo "checked"?>> Calcular en OC<br>
                  <input type="radio" name="supp_dsc_finance_calc" value="NC" <?php if($supplier["supp_dsc_finance_calc"] == "NC") echo "checked"?>> Generar Nota de Credito
               </td>
            </tr>
            </table>
         </td> 
      </tr>
      <tr>
         <td class="content_rowl">Despacho</td>
         <td class="content_row">
            <input name="supp_delivery_days" type="text" class="text" style="width:80px;text-align:center"
            value="<?=$supplier["supp_delivery_days"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"> Dias

            &nbsp;&nbsp;&nbsp;
            <input type="checkbox" name="supp_aprobrange_disabled" value="1"
            <?if((int)$supplier["supp_aprobrange_disabled"]) echo "checked"?>>
            Desactivar aprobacion OC
         </td> 
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][9]?></td>
         <td class="content_row">
            <input name="supp_email" type="text" class="text" style="width:305px" value="<?=$supplier["supp_email"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][10]?></td>
         <td class="content_row">
            <input name="supp_phone" type="text" class="text" style="width:305px" value="<?=$supplier["supp_phone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][18]?></td>
         <td class="content_row">
            <input name="supp_cellphone" type="text" class="text" style="width:305px" value="<?=$supplier["supp_cellphone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][11]?></td>
         <td class="content_row">
            <input name="supp_fax" type="text" class="text" style="width:305px" value="<?=$supplier["supp_fax"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Solicitar clique</td>
         <td class="content_row">
            <input type="checkbox" name="supp_clique_act" value="1"
            <?if((int)$supplier["supp_clique_act"]) echo "checked"?>>
         </td>
      </tr>
      <!--
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][12]?></td>
         <td class="content_row">
            <input name="supp_website" type="text" class="text" style="width:305px" value="<?=$supplier["supp_website"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      -->
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][16]?></td>
         <td class="content_row"><?php if($supplier["supp_updusr"] != "") echo "{$supplier["upd_firstname"]} {$supplier["upd_lastname"]}"?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["SUPP"][17]?></td>
         <td class="content_row"><?php if($supplier["supp_updusr"] != "") echo displayDate($supplier["supp_upddat"])?>&nbsp;</td>
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
   {
      if($_SESSION[$_sesmodulename]["registerback"] != "")
      {
         $backdata = explode("-", $_SESSION[$_sesmodulename]["registerback"]);
         ?>
         <td align="left" width="130">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$backdata[0]}&exec=edit&id={$backdata[1]}", "", "arrow-180");
            ?>
         </td>
         <?php
      }
      else
      {  ?>
         <td align="left" width="130">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
            ?>
         </td>
         <?php
      }
      ?>
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
<script language="JavaScript">
<?php
if((int)$supplier["supp_marketing_act"])
{  ?>
   $('#idx_menu_marketing').show();
   <?php
}
else
{  ?>
   $('#idx_menu_marketing').hide();
   <?php
}
?>
</script>
<div id="idx_comunaout" style="display:none"></div>