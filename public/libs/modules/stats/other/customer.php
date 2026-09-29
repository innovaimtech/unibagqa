<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
//----------------------------------------------------------------------------------
$_sesmodulename         = "customer";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Rut" => "1", "Nombre" => "2", "Dirección" => "3", "Region" => "4",
                               "Comuna" => "5", "Vendedor" => "6,7", "Teléfono" => "8", "Email" => "9",
                               "Rubro" => "21", "Canal" => 10);

unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_seller"]      = (int)$_REQUEST["sql_seller"];
   $_SESSION[$_sesmodulename]["country"]         = (int)$_REQUEST["country"];
   $_SESSION[$_sesmodulename]["regions"]         = (int)$_REQUEST["regions"];
   $_SESSION[$_sesmodulename]["provincias"]      = (int)$_REQUEST["provincias"];
   $_SESSION[$_sesmodulename]["comunas"]         = (int)$_REQUEST["comunas"];
   $_SESSION[$_sesmodulename]["sql_nadj"]        = (int)$_REQUEST["sql_nadj"];
   $_SESSION[$_sesmodulename]["sql_custcatid"]   = (int)$_REQUEST["sql_custcatid"];
   $_SESSION[$_sesmodulename]["sql_cust_type"]   = trim(addslashes($_REQUEST["sql_cust_type"]));
   $_SESSION[$_sesmodulename]["sql_cust_presu"]  = trim(addslashes($_REQUEST["sql_cust_presu"]));
   $_SESSION[$_sesmodulename]["page"]            = 0;
   $_SESSION[$_sesmodulename]["search_active"]   = 1;
}

//----------------------------------------------------------------------------------
$countries  = getCountries($CON);
$regions    = getRegions($CON);
$provincias = getProvincias($CON);
$comunas    = getComunas($CON);
$sellers    = getSellers($CON);

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 100);

//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$cntsql = " select count(distinct t1.id) 'cc'
            from customer t1
            {$joisql}
            where
            t1.cust_status = 1 ";





//----------------------------------------------------------------------------------
$cntsql .= $seasql;

$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"];

//----------------------------------------------------------------------------------
$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

// Prepara los parámetros desde la sesión
$countryid     = $_SESSION[$_sesmodulename]["country"]        ?: null;
$regionid      = $_SESSION[$_sesmodulename]["regions"]        ?: null;
$provinciaid   = $_SESSION[$_sesmodulename]["provincias"]     ?: null;
$comunaid      = $_SESSION[$_sesmodulename]["comunas"]        ?: null;
$sellerid      = $_SESSION[$_sesmodulename]["sql_seller"]     ?: null;
$catid         = $_SESSION[$_sesmodulename]["sql_custcatid"]  ?: null;
$discount_spec = $_SESSION[$_sesmodulename]["sql_nadj"]       ?: null;
$cust_type     = $_SESSION[$_sesmodulename]["sql_cust_type"]  ?: null;
$presupuesto   = $_SESSION[$_sesmodulename]["sql_cust_presu"] ?: null;

// Construye la llamada al SP
$spcall = "CALL sp_get_customers(" .
          ($countryid     !== null ? $countryid     : "NULL") . "," .
          ($regionid      !== null ? $regionid      : "NULL") . "," .
          ($provinciaid   !== null ? $provinciaid   : "NULL") . "," .
          ($comunaid      !== null ? $comunaid      : "NULL") . "," .
          ($sellerid      !== null ? $sellerid      : "NULL") . "," .
          ($catid         !== null ? $catid         : "NULL") . "," .
          ($discount_spec !== null ? $discount_spec : "NULL") . "," .
          ($cust_type     !== null ? "'$cust_type'" : "NULL") . "," .
          ($presupuesto   !== null ? "'$presupuesto'" : "NULL") .
          ")";

// Ejecuta con tu método heredado
$clients = $CON->select($spcall);
// $clients = $CON->select("CALL sp_get_customers2()");
//----------------------------------------------------------------------------------
// $clients = $CON->select($datsql);
?>
<script language="JavaScript">
   <?generateCountryJS($countries, $regions, $comunas, $provincias)?>
</script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Clientes</b></td>
   <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($itemcount); else echo $savemsg;?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="100">
         <col width="300">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Vendedor</td>
         <td class="content_row">
            <select class="text" style="width:375px" name="sql_seller" id="sql_seller"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($sellers as $seller)
               {  ?>
                  <option value="<?=$seller["id"]?>" <?php if($seller["id"] == $_SESSION[$_sesmodulename]["sql_seller"]) echo "selected"?>>
                     <?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
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
         <td class="content_rowl">Rubro</td>
         <td class="content_row">
            <?php
            $sql = " select t1.id, t1.cat_name, t1.cat_crtdat
                     from customer_cats t1
                     where
                     t1.cat_status > 0
                     order by t1.cat_name";
            $custcats = $CON->select($sql);
            ?>
            <select class="text" style="width:375px" name="sql_custcatid" id="sql_custcatid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($custcats as $custcat)
               {  ?>
                  <option value="<?=$custcat["id"]?>" <?php if($custcat["id"] == $_SESSION[$_sesmodulename]["sql_custcatid"]) echo "selected"?>>
                     <?=$custcat["cat_name"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>      
      <tr>
         <td class="content_rowl" valign="top">Tipo</td>
         <td class="content_row" valign="top">
            <select name="sql_cust_type" style="width:375px">
               <option value="" <?php if ($_SESSION[$_sesmodulename]["sql_cust_type"] == "") echo "selected"; ?>>Todos</option>
               <option value="Concesión" <?php if ($_SESSION[$_sesmodulename]["sql_cust_type"] == "Concesión") echo "selected"; ?>>Concesión</option>
               <option value="Distribuidor" <?php if ($_SESSION[$_sesmodulename]["sql_cust_type"] == "Distribuidor") echo "selected"; ?>>Distribuidor</option>
               <option value="Cliente final" <?php if ($_SESSION[$_sesmodulename]["sql_cust_type"] == "Cliente final") echo "selected"; ?>>Cliente final</option>
               <option value="Paciente oncológico" <?php if ($_SESSION[$_sesmodulename]["sql_cust_type"] == "Paciente oncológico") echo "selected"; ?>>Paciente oncológico</option>
               <option value="Cliente frecuente" <?php if ($_SESSION[$_sesmodulename]["sql_cust_type"] == "Cliente frecuente") echo "selected"; ?>>Cliente frecuente</option>
               <option value="Preferencial" <?php if ($_SESSION[$_sesmodulename]["sql_cust_type"] == "Preferencial") echo "selected"; ?>>Preferencial</option>
            </select>
         </td>     
         <td class="content_rowl" height="32">Cliente en presupuesto</td>
         <td class="content_row">
            <nobr><input type="radio" name="sql_cust_presu" value="" <?php if ($_SESSION[$_sesmodulename]["sql_cust_presu"] == "") echo "checked"; ?>>Todos</nobr>
            <nobr><input type="radio" name="sql_cust_presu" value="1" <?php if ($_SESSION[$_sesmodulename]["sql_cust_presu"] == "1") echo "checked"; ?>>SI</nobr>
            <nobr><input type="radio" name="sql_cust_presu" value="0" <?php if ($_SESSION[$_sesmodulename]["sql_cust_presu"] == "0") echo "checked"; ?>>NO</nobr>
         </td>
         <!--
         <td class="content_rowl" valign="top">Tipo</td>
         <td class="content_row" valign="top" colspan="3">
            <nobr><input type="radio" value="" name="sql_cust_type" <?php if($_SESSION[$_sesmodulename]["sql_cust_type"] == "") echo "checked"?>> Todos</nobr>
            <nobr><input type="radio" value="Concesión" name="sql_cust_type" <?php if($_SESSION[$_sesmodulename]["sql_cust_type"] == "Concesión") echo "checked"?>> Concesión</nobr>
            <nobr><input type="radio" value="Distribuidor" name="sql_cust_type" <?php if($_SESSION[$_sesmodulename]["sql_cust_type"] == "Distribuidor") echo "checked"?>> Distribuidor</nobr>
            <nobr><input type="radio" value="Cliente final" name="sql_cust_type" <?php if($_SESSION[$_sesmodulename]["sql_cust_type"] == "Cliente final") echo "checked"?>> Cliente final</nobr>
            <nobr><input type="radio" value="Paciente oncológico" name="sql_cust_type" <?php if($_SESSION[$_sesmodulename]["sql_cust_type"] == "Paciente oncológico") echo "checked"?>> Paciente oncológico</nobr>
            <nobr><input type="radio" value="Cliente frecuente" name="sql_cust_type" <?php if($_SESSION[$_sesmodulename]["sql_cust_type"] == "Cliente frecuente") echo "checked"?>> Cliente frecuente</nobr>
            <nobr><input type="radio" value="Preferencial" name="sql_cust_type" <?php if($_SESSION[$_sesmodulename]["sql_cust_type"] == "Preferencial") echo "checked"?>> Preferencial</nobr>
         </td>
         -->
      </tr>

      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width='132'>
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if($itemcount > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if($itemcount > 0)
                  {
                     printButton("Generar XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
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
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box1", "99%")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="80">
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
         <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
         <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
         <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
         <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
         <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 8)?></td>
         <td class="content_tbl_header">Subrubro</td>
         <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 9)?></td>
         <td class="content_tbl_header">Forma de Pago</td>
         <td class="content_tbl_header">Monto Crédito</td>
         <td class="content_tbl_header">Creación</td>
         <td class="content_tbl_header">Ult. Cotización</td>
         <td class="content_tbl_header">$ Ult. Cotización</td>
         <td class="content_tbl_header">Ult. C.C.</td>
         <td class="content_tbl_header">$ Ult. C.C.</td>         
         <td class="content_tbl_header">Presupuesto</td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      $statsRows = array();
      for($x = 0; $x < count($clients) && $clients != false; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><nobr><?=$clients[$x]["cust_rut"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$clients[$x]["cust_company"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$clients[$x]["cust_street"]?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=($_SESSION["user_type"] == 1 ? $clients[$x]["cust_phone"] : " ")?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=($_SESSION["user_type"] == 1 ? $clients[$x]["cust_email"]: " ")?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=$clients[$x]["name"]?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=$clients[$x]["nombre"]?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=$clients[$x]["user_firstname"]?>&nbsp;<?=$clients[$x]["user_lastname"]?></nobr></td>
            <td class="content_row_os"><nobr><?=($_SESSION["user_type"] == 1 ? $clients[$x]["cat_name"]: " ")?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=$clients[$x]["sub_cat_name"]?>&nbsp;</nobr></td>                        
            <td class="content_row_os"><nobr><?=$clients[$x]["canal"]?>&nbsp;</nobr></td>            
            <td class="content_row_os"><nobr><?=$clients[$x]["pay_title"]?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=printPrice($clients[$x]["cust_pricetolerance"])?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=empty($clients[$x]["cust_crtdat"])?'':date("d/m/Y",$clients[$x]["cust_crtdat"]);?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=empty($clients[$x]["ultima_cotizacion"])?'':date("d/m/Y",$clients[$x]["ultima_cotizacion"]);?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=printPrice($clients[$x]["monto_cotizacion"])?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=empty($clients[$x]["req_crtdat"])?'':date("d/m/Y",$clients[$x]["req_crtdat"])?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=printPrice($clients[$x]["req_total_netto"])?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=($clients[$x]["cust_presupuesto"] == 1) ? 'SI' : 'NO'?>&nbsp;</nobr></td>
         </tr>
         <?php
         if($_REQUEST["printpdf"] || $_REQUEST["printxls"])
         {
            $row = array();
            $row["cust_rut"]            = $clients[$x]["cust_rut"];
            $row["cust_company"]        = $clients[$x]["cust_company"];
            $row["cust_name"]           = $clients[$x]["cust_name"];
            $row["cust_street"]         = $clients[$x]["cust_street"];
            $row["cust_cellphone"]      = ($_SESSION["user_type"] == 1 ? $clients[$x]["cust_cellphone"] : "");
            $row["cust_fax"]            = ($_SESSION["user_type"] == 1 ? $clients[$x]["cust_fax"] : "");
            $row["cust_website"]        = $clients[$x]["cust_website"];
            $row["pro_name"]            = $clients[$x]["pro_name"];
            $row["cust_pricetolerance"] = printPrice($clients[$x]["cust_pricetolerance"],0);
            $row["cust_convenio_act"]   = $clients[$x]["cust_convenio_act"];
            $row["giro_name"]           = $clients[$x]["giro_name"];
            $row["trans_name"]          = $clients[$x]["trans_name"];
            $plTitle = $clients[$x]["pl_title"];
            if($plTitle == "")
               $plTitle = "PRECIOS BASICOS";
            $row["pl_title"]           = $plTitle;
            $row["pay_title"]          = $clients[$x]["pay_title"];
            $row["name"]               = $clients[$x]["name"];
            $row["nombre"]             = $clients[$x]["nombre"];
            $row["username"]           = $clients[$x]["user_firstname"]." ".$clients[$x]["user_lastname"];
            $row["cust_phone"]         = $clients[$x]["cust_phone"];
            $row["cust_email"]         = $clients[$x]["cust_email"];
            $row["cat_name"]           = $clients[$x]["cat_name"];
            $row["canal"]              = $clients[$x]["canal"];
            $row["creacion"]           = $clients[$x]["cust_crtdat"];
            $row["ultima_cotizacion"]  = $clients[$x]["ultima_cotizacion"];
            $row["monto_cotizacion"]   = $clients[$x]["monto_cotizacion"];
            $row["req_crtdat"]         = $clients[$x]["req_crtdat"];
            $row["req_total_netto"]    = $clients[$x]["req_total_netto"];
            $row["presupuesto"]        = ($clients[$x]["cust_presupuesto"] == 1) ? 'SI' : 'NO';
            $row["subrubro"]          = $clients[$x]["sub_cat_name"];
            $statsRows[] = $row;
         }
      }

      if($_REQUEST["printpdf"] || $_REQUEST["printxls"])
         $_SESSION["STATS"][$_sesmodulename]["DATA"] = $statsRows;

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="8" align="center">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsCustomers($CON);

if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsCustomers($CON);

if($pdffile != "")
{
   $doctitle = "Lista-clientes.pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
?>
<?php
if($xlsfile != "")
{
   $doctitle = "Lista-clientes.xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>