<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "customers";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("RUT" => "3", "Nombre" => "2", "Dirección" => "4");

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "" && $_REQUEST["exec"] == "edit" && $_REQUEST["registerback"] != "")
   $_SESSION[$_sesmodulename]["registerback"] = $_REQUEST["registerback"];
if($_REQUEST["id"] == "")
   $_SESSION[$_sesmodulename]["registerback"] = "";

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if($_REQUEST["exec"] == "edit")
   require_once("overview.edit.php");
else
{
   if($_SESSION[$_sesmodulename]["country"] == 0)
      $_SESSION[$_sesmodulename]["country"] = 81;
      
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["country"]        = (int)$_REQUEST["country"];
      $_SESSION[$_sesmodulename]["regions"]        = (int)$_REQUEST["regions"];
      $_SESSION[$_sesmodulename]["comunas"]        = (int)$_REQUEST["comunas"];
      $_SESSION[$_sesmodulename]["provincias"]     = (int)$_REQUEST["provincias"];
      $_SESSION[$_sesmodulename]["cust_sellerid"]  = (int)$_REQUEST["cust_sellerid"];
      $_SESSION[$_sesmodulename]["sql_cust_type"]  = trim(addslashes($_REQUEST["sql_cust_type"]));
      $_SESSION[$_sesmodulename]["sql_stext"]      = trim(addslashes(str_replace("*","%",str_replace(".","",$_REQUEST["sql_stext"]))));
      $_SESSION[$_sesmodulename]["page"]           = 0;
      $_SESSION[$_sesmodulename]["search_active"]  = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
   {
      $currtme = time();
      
      $sql = " update customer
               set
               cust_status = 0,
               cust_updusr = {$_SESSION["user_id"]},
               cust_upddat = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $cntsql = " select count(distinct t1.id) 'cc'
               from customer t1
               where
               t1.cust_status = 1";
               
   $datsql = " select distinct t1.id, t1.cust_name, t1.cust_rut, t1.cust_street
               from customer t1
               where
               t1.cust_status = 1 ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["country"])
      $seasql .= " and t1.cust_countryid = {$_SESSION[$_sesmodulename]["country"]} ";
   if($_SESSION[$_sesmodulename]["regions"])
      $seasql .= " and t1.cust_regionid = {$_SESSION[$_sesmodulename]["regions"]} ";
   if($_SESSION[$_sesmodulename]["provincias"])
      $seasql .= " and t1.cust_provinciaid = {$_SESSION[$_sesmodulename]["provincias"]} ";
   if($_SESSION[$_sesmodulename]["comunas"])
      $seasql .= " and t1.cust_comunaid = {$_SESSION[$_sesmodulename]["comunas"]} ";
   if($_SESSION[$_sesmodulename]["cust_sellerid"])
      $seasql .= " and t1.cust_sellerid = {$_SESSION[$_sesmodulename]["cust_sellerid"]} ";
   if($_SESSION[$_sesmodulename]["sql_cust_type"] != "")
      $seasql .= " and t1.cust_type = '{$_SESSION[$_sesmodulename]["sql_cust_type"]}' ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and (t1.cust_company   like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t1.cust_name      like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        REPLACE(t1.cust_rut,'.','')       like '{$_SESSION[$_sesmodulename]["sql_stext"]}%') ";

   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;
   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
   $datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

   //----------------------------------------------------------------------------------
   $customers = $CON->select($datsql);
   
   //----------------------------------------------------------------------------------
   $countries  = getCountries($CON);
   $regions    = getRegions($CON);
   $provincias = getProvincias($CON);
   $comunas    = getComunas($CON);
   $sellers    = getSellers($CON);

   ?>
   <script language="JavaScript">
   <?php
   generateCountryJS($countries, $regions, $comunas, $provincias);
   ?>
   </script>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["CUST"][32]?></b></td>
      <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($itemcount); else echo $savemsg;?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <tr id="idx_tr_custsearch">
      <td>
         <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
         <input type="hidden" name="subexec" value="search">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
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
            <td class="content_rowl">Nombre/RUT</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:375px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
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
                     <?php if($country["id"] == $_SESSION[$_sesmodulename]["country"]) echo "selected"?>><?=$country["country_name"]?></option>
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
                  if((int)$_SESSION[$_sesmodulename]["country"])
                  {
                     foreach($regions as $region)
                     {
                        if($region["id_pais"] == $_SESSION[$_sesmodulename]["country"])
                        {  ?>
                           <option value="<?=$region["id"]?>"
                           <?php if($region["id"] == $_SESSION[$_sesmodulename]["regions"]) echo "selected"?>><?=$region["name"]?></option>
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
                  if((int)$_SESSION[$_sesmodulename]["regions"])
                  {
                     foreach($provincias as $provincia)
                     {
                        if($provincia["region_id"] == $_SESSION[$_sesmodulename]["regions"])
                        {  ?>
                           <option value="<?=$provincia["id"]?>"
                           <?php if($provincia["id"] == $_SESSION[$_sesmodulename]["provincias"]) echo "selected"?>><?=$provincia["pro_name"]?></option>
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
                  if((int)$_SESSION[$_sesmodulename]["provincias"])
                  {
                     foreach($comunas as $comuna)
                     {
                        if($comuna["prov_id"] == $_SESSION[$_sesmodulename]["provincias"])
                        {  ?>
                           <option value="<?=$comuna["id"]?>"
                           <?php if($comuna["id"] == $_SESSION[$_sesmodulename]["comunas"]) echo "selected"?>><?=$comuna["nombre"]?></option>
                           <?php
                        }
                     }
                  }
                  ?>
               </select>
            </td>
            <td class="content_rowl" valign="top">Vendedor</td>
            <td class="content_row" valign="top">
               <select class="text" style="width:375px" name="cust_sellerid" id="cust_sellerid" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($sellers as $seller)
                  {  ?>
                     <option value="<?=$seller["id"]?>"
                     <?php if($seller["id"] == $_SESSION[$_sesmodulename]["cust_sellerid"]) echo "selected"?>><?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?></option>
                     <?php
                  }
                  ?>
               </select>
            </td>
         </tr>
         <tr>
            <td class="content_row" align="right" colspan="4">
               <table border="0" cellpadding="0" cellspacing="0" width="270">
               <tr>
                  <td align="right">
                     <?php
                     if((int)$_SESSION[$_sesmodulename]["search_active"])
                        printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                     ?>
                  </td>
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
   <?php
   printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
   ?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="110">
      <col>
      <col>
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
      <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
      <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
      <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["CUST"][40]?></td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($customers) && $customers != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$customers[$x]["cust_rut"]?>&nbsp;</td>
         <td class="content_row"><?=$customers[$x]["cust_name"]?></td>
         <td class="content_row"><nobr><?=$customers[$x]["cust_street"]?>&nbsp;</nobr></td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$customers[$x]["id"]}", "", "pencil"); 
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
   </table>
   <?php
   printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
   ?>
   <?=Nifty_printF()?>
   <br>
   <?php
      
}
?>