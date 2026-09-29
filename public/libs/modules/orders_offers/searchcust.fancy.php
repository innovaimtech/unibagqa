<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_REQUEST["country"]        = (int)$_REQUEST["country"];
   $_REQUEST["regions"]        = (int)$_REQUEST["regions"];
   $_REQUEST["comunas"]        = (int)$_REQUEST["comunas"];
   $_REQUEST["provincias"]     = (int)$_REQUEST["provincias"];
   $_REQUEST["sql_stext"]      = trim(addslashes(str_replace("*","%",str_replace(".","",$_REQUEST["sql_stext"]))));

   $datsql = " select distinct *
               from customer t1
               where
               t1.cust_status = 1 ";

   //----------------------------------------------------------------------------------
   if($_REQUEST["country"])
      $datsql .= " and t1.cust_countryid = {$_REQUEST["country"]} ";
   if($_REQUEST["regions"])
      $datsql .= " and t1.cust_regionid = {$_REQUEST["regions"]} ";
   if($_REQUEST["comunas"])
      $datsql .= " and t1.cust_comunaid = {$_REQUEST["comunas"]} ";
   if($_REQUEST["provincias"])
      $datsql .= " and t1.cust_provinciaid = {$_REQUEST["provincias"]} ";
   if($_REQUEST["sql_stext"] != "")
      $datsql .= " and (t1.cust_name      like '%{$_REQUEST["sql_stext"]}%' or
                        t1.cust_company   like '%{$_REQUEST["sql_stext"]}%' or
                        REPLACE(t1.cust_rut,'.','') like '{$_REQUEST["sql_stext"]}%') ";

   $datsql .= " order by 2,3 ";
     
   //----------------------------------------------------------------------------------
   $customers  = $CON->select($datsql);
}
   
//----------------------------------------------------------------------------------
$countries  = getCountries($CON);
$regions    = getRegions($CON);
$comunas    = getComunas($CON);
$provincias = getProvincias($CON);

if($_REQUEST["inpobj"] == "")
   $_REQUEST["inpobj"] = "cust_search";
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script language="Javascript">
      <?php
      require_once("../../../libs/jscripts/sourcen.php");
      generateCountryJS($countries, $regions, $comunas, $provincias);
      ?>
      function setSelData(idx)
      {
         var xform = parent.document.form_reqpos;
         <?php
         foreach($customers AS $customer)
         {
            ?>if(idx == '<?=$customer["id"]?>') {
               xform.req_cust_company.value='<?=str_replace("'","",$customer["cust_company"])?>';
               xform.req_cust_rut.value='<?=str_replace("'","",$customer["cust_rut"])?>';
               xform.req_cust_street.value='<?=str_replace("'","",$customer["cust_street"])?>';
               xform.req_cust_phone.value='<?=str_replace("'","",$customer["cust_phone"])?>';
               xform.country.value='<?=$customer["cust_countryid"]?>';
               xform.req_cust_fax.value='<?=str_replace("'","",$customer["cust_fax"])?>';
               xform.regions.value='<?=$customer["cust_regionid"]?>';
               parent.setProvincias('<?=$customer["cust_regionid"]?>');
               xform.provincias.value='<?=$customer["cust_provinciaid"]?>';
               parent.setComunas('<?=$customer["cust_provinciaid"]?>');
               xform.req_cust_email.value='<?=str_replace("'","",$customer["cust_email"])?>';
               xform.comunas.value='<?=$customer["cust_comunaid"]?>';
               xform.req_paymentid.value='<?=$customer["cust_paymentid"]?>';
               <?php
               if((int)$customer["cust_plfabid"])
               {  ?>
                  xform.req_plid_fab.value = '<?=$customer["cust_plfabid"]?>';
                  <?php
               }
               else
               {  ?>
                  xform.req_plid_fab.options.selectedIndex = 1;
                  <?php
               }
               ?>
            }
            <?php
         }
         ?>
         parent.$.fancybox.close();
      }
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <script type="text/javascript" src="/libs/jscripts/jquery.table_navigation.js"></script>
   <style type="text/css">
   tr.selected {background-color: <?=$_SESSION["_PAGE"]->getEffectVal("js_content_hover")?>;}
   </style>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="<?php if(count($customers) == 0 || $customers == false) echo "document.xform_itemsearch.sql_stext.focus()"?>">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="searchcust.fancy.php" method="post" name="xform_itemsearch" class="fokusfirst">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="destobj" value="<?=$_REQUEST["destobj"]?>">
      <input type="hidden" name="inpobj" value="<?=$_REQUEST["inpobj"]?>">
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="100">
         <col width="385">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Palabra</td>
         <td class="content_row">
            <input name="sql_stext" type="text" class="text" style="width:375px"
            value="<?=str_replace("%","*",$_REQUEST["sql_stext"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_rowl">País</td>
         <td class="content_row">
            <select class="text" style="width:375px" name="country" id="country"
            onchange="setRegions(this.value)"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($countries as $country)
               {  ?>
                  <option value="<?=$country["id"]?>"
                  <?php if($country["id"] == $_REQUEST["country"]) echo "selected"?>><?=$country["country_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Región</td>
         <td class="content_row">
            <select class="text" style="width:375px" name="regions" id="regions"
            onchange="setProvincias(this.value);"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$_REQUEST["country"])
               {
                  foreach($regions as $region)
                  {
                     if($region["id_pais"] == $_REQUEST["country"])
                     {  ?>
                        <option value="<?=$region["id"]?>"
                        <?php if($region["id"] == $_REQUEST["regions"]) echo "selected"?>><?=$region["name"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Provincia</td>
         <td class="content_row">
            <select class="text" style="width:375px" name="provincias" id="provincias" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setComunas(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$_REQUEST["regions"])
               {
                  foreach($provincias as $provincia)
                  {
                     if($provincia["region_id"] == $_REQUEST["regions"])
                     {  ?>
                        <option value="<?=$provincia["id"]?>"
                        <?php if($provincia["id"] == $_REQUEST["provincias"]) echo "selected"?>><?=$provincia["pro_name"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Comuna</td>
         <td class="content_row">
            <select class="text" style="width:375px" name="comunas" id="comunas" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$_REQUEST["provincias"])
               {
                  foreach($comunas as $comuna)
                  {
                     if($comuna["prov_id"] == $_REQUEST["provincias"])
                     {  ?>
                        <option value="<?=$comuna["id"]?>"
                        <?php if($comuna["id"] == $_REQUEST["comunas"]) echo "selected"?>><?=$comuna["nombre"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
         <td class="content_row" align="right" colspan="2">
            <table border="0" cellpadding="0" cellspacing="0" width="270">
            <tr>
               <td align="right">&nbsp;</td>
               <td align="right">
                  <?php
                  printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
                  $_SESSION["_SUBMITBTN"] = 1;
                  ?>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
      </form>
   </td>
</tr>
</table>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="90">
   <col>
   <col>
   <col width="85">
</colgroup>
<thead>
<tr>
   <td class="content_tbl_subheader">RUT</td>
   <td class="content_tbl_subheader">Nombre</td>
   <td class="content_tbl_subheader">Dirección</td>
   <td class="content_tbl_subheader" align="center">Opciones</td>
</tr>
</thead>
<tbody>
<?php
//----------------------------------------------------------------------------------
for($x = 0; $x < count($customers) && $customers != false; $x++)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" class="viewable_records">
      <td class="content_row"><?=$customers[$x]["cust_rut"]?>&nbsp;</td>
      <td class="content_row"><?=$customers[$x]["cust_name"]?>&nbsp;</td>
      <td class="content_row"><?=$customers[$x]["cust_street"]?>&nbsp;</nobr></td>
      <td class="content_row" align="center">
         <?php
         printButton("Seleccionar", "postnav_save", "javascript: deactivateFormChange()\" class=\"activation", "setSelData('{$customers[$x]["id"]}')", "tick-circle-frame");
         ?>
      </td>
   </tr>
   <?php
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row" colspan="4" align="center" style="height:40px">
         <b class="msg_save_err"><?=$_LANG["FORM"]["MESSAGE"][5]?></b>
      </td>
   </tr>
   <?php
}
?>
</tbody>
</table>
<?=Nifty_printF()?>
<script type="text/javascript">
jQuery.tableNavigation({
   table_selector: 'table.navigateable',
   row_selector: 'table.navigateable tbody tr.viewable_records',
   selected_class: 'selected',
   activation_selector: 'a.activation',
   bind_key_events_to_links: true,
   focus_links_on_select: true,
   select_event: 'click',
   activate_event: 'dblclick',
   activation_element_activate_event: 'click',
   scroll_overlap: 20,
   cookie_name: null,
   focus_tables: true,
   focused_table_class: 'focused',
   jump_between_tables: false,
   disabled: false,
   on_activate: null,
   on_select: null
});
</script>
</body>
</html>