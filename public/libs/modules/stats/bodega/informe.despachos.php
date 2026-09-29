<?php
//----------------------------------------------------------------------------------
// Author:        Fernando Garrido Gonzalez
// Copyright:     2022 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_informe.despacho";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";

$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];

   $_SESSION[$_sesmodulename]["sql_chofer"]      = (int)$_REQUEST["sql_chofer"];

   /*
   $_SESSION[$_sesmodulename]["año"] = (int)$_REQUEST["año"];
   $_SESSION[$_sesmodulename]["mes"] = (int)$_REQUEST["mes"];
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   */

   $_SESSION[$_sesmodulename]["sql_pertype"]        = (int)$_REQUEST["sql_pertype"];
   $_SESSION[$_sesmodulename]["sql_item_id"]       = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"]     = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]          = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]       = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_chist"]         = (int)$_REQUEST["sql_chist"];
   $_SESSION[$_sesmodulename]["sql_xystate"]       = (int)$_REQUEST["sql_xystate"];
   /*
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   */
   $_SESSION[$_sesmodulename]["sql_stext"]     = trim($_REQUEST["sql_stext"]);

   $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
   $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);

   $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
   $_SESSION[$_sesmodulename]["sql_ingreso"]   = $_REQUEST["sql_ingreso"];

   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;


}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON);
$shops      = getShops($CON);
$sellers    = getSellers($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_shop"])
{
   $first = false;
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"] && !$first)
      {
         $_SESSION[$_sesmodulename]["sql_shop"] = $shop["id"];
         $first = true;
      }
}
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;
if(!(int)$_SESSION[$_sesmodulename]["sql_chist"])
   $_SESSION[$_sesmodulename]["sql_chist"] = 1;

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

if($_SESSION[$_sesmodulename]["filter_status"] != 4)
{
   if(!is_array($_SESSION[$_sesmodulename]["sql_status"]))
   {
       $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3);
   }
}

$sqlstatstr = "";
foreach($_SESSION[$_sesmodulename]["sql_status"] AS $sqlst)
   $sqlstatstr .= $sqlst.",";

$sqlstatstr = substr($sqlstatstr, 0, -1);


if(!is_array($_SESSION[$_sesmodulename]["sql_ingreso"]))
   $_SESSION[$_sesmodulename]["sql_ingreso"] = Array(0=>1,1=>2);

$sqlstatstr2 = "";
foreach($_SESSION[$_sesmodulename]["sql_ingreso"] AS $sqlst)
   $sqlstatstr2 .= $sqlst.",";

$sqlstatstr2 = substr($sqlstatstr2, 0, -1);

//----------------------------------------------------------------------------------
$datsql = " select d.id                as 'ID'
               , d.mes 	               as 'MES'
               , d.año                 as 'AÑO'
               , d.fecha_ingreso       as 'FECHA'
               , od1.dlv_docnum        as 'NDOCUMENTO'
               , d.tipo_documento      as 'DOCUMENTO'
               , p.descripcion         as 'CANALVENTA'
               , c.cust_company        as 'CLIENTE'
               , item_number_prod      as 'CODIGOS'           
               , d.cantidad_pallet     as 'CANTPALLETS'
               , d.cantidad_cajas      as 'CANTCAJAS'
               , d.codigo_medidacajas1 as 'MEDIDACAJA1'
               , d.codigo_medidacajas2 as 'MEDIDACAJA2'
               , dd.salida             as 'CANTIDAD'
               , trans_name            as 'EMPRESA_TRANSPORTE'
               , tv.transports_vh_patente as 'PATENTE'
               , concat(tc.transports_chofer_nombre,' ',tc.transports_chofer_paterno) as 'CONDUCTOR'
               , tc.transports_chofer_rut as 'RUT'
               , d.hora_ingreso        as 'HORA_ENTRADA'
               , d.hora_salida         as 'HORA_SALIDA'
               , d.sello               as 'SELLO'
               , d.estado              as 'ESTADO'
               , d.observacion         as 'OBSERVACIONES'
               , d.id_ingreso
               , od1.dlv_num
               , od1.dlv_order_id
               , o1.req_number as 'CCNV'
               , tr.transports_rango_codigo as 'Rango'
               , tr.transports_rango_monto as 'Monto' 
           from despacho d
               inner join detalle_despacho dd  on d.id  = dd.id_despacho
               inner join customer c           on c.id  = id_cliente
               left outer join transports_chofer tc on tc.id = d.id_chofer
               left outer join transports_vehiculo tv on tv.id = d.id_patente
               left outer join transports t1 on t1.id = d.id_transporte
               left outer join parametros p on p.tabla = 'CANAL' and p.codigo = c.cust_canal
               left outer join item i on i.id = dd.id_item
               inner join orders_delivery od1 on d.numero_documento = od1.id
               left outer join transports_rango tr on tr.id = od1.dlv_rango_id
               left outer join orders o1 on o1.id = od1.dlv_order_id
         where 0=0 and estado in({$sqlstatstr}) and id_ingreso in({$sqlstatstr2}) ";

//----------------------------------------------------------------------------------

if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and d.empresa   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and d.sucursal = {$_SESSION[$_sesmodulename]["sql_shop"]} ";

if((int)$_SESSION[$_sesmodulename]["sql_chofer"])
   $seasql .= " and d1.id_chofer  = {$_SESSION[$_sesmodulename]["sql_chofer"]} ";

if((int)$_REQUEST["año"])
   $seasql .= " and d.año  = {$_REQUEST["año"]} ";

if((int)$_REQUEST["mes"])
   $seasql .= " and d.mes  = {$_REQUEST["mes"]} ";

$datsql .= $seasql;

if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
{
   if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
      $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
   if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
      $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

   $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
   $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);

   $seasql .= " and d.fecha_ingreso between {$sqldate_from} and {$sqldate_to} ";
}

$datsql .= $seasql;

$datsql .= " order by d.fecha_ingreso desc";

$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
         array_push($selshops, $shop);
}

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_chofer"])
{
   $sql = " select cust_name
            from customer
            where
            id = {$_SESSION[$_sesmodulename]["sql_chofer"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = $custdata[0]["cust_name"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = "TODO";

//----------------------------------------------------------------------------------
$sql      = " select * from transports_chofer where transports_chofer_status > 0";
$choferes = $CON->select($sql);

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

printJSsetCompanyShop($shops);
?>
<script language="JavaScript">
function setCompanyShop(companyidx)
{
   var obj = document.all.sql_shop;
   obj.options.length = 1;
   document.all.sql_storehouse.options.length = 1;
   <?php
   foreach($shops AS $shop)
   {  ?>
      if(companyidx == '<?=$shop["shop_company_id"]?>')
      {
         var newIndex   = obj.options.length;
         var newOpt     = new Option('<?=addslashes($shop["shop_name"])?>');
         newOpt.value   = '<?=$shop["id"]?>';
         obj.options[newIndex] = newOpt;
      }
      <?php
   }
   ?>
}
function setCompanyShopStorehouse(shopidx)
{
}
</script>

<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Reporte Despacho Realizados</b></td>
   <td align="right" class="content_row_clear"></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst"
      onsubmit="return checkform(new Array(this.sql_company))">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <?=Nifty_printH("box2", "980", 0)?>
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
         <td class="content_rowl">Número</td>
         <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:375px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Fecha Ingreso</td>
         <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
         
         <td class="content_rowl">Sucursal</td>
         <td class="content_row">
            <select class="text" name="sql_shop" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setCompanyShopStorehouse(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($selshops AS $selshop)
               {  ?>
                  <option value="<?=$selshop["id"]?>"
                  <?php if($selshop["id"] == $_SESSION[$_sesmodulename]["sql_shop"]) echo "selected"?>><?=$selshop["shop_name"]?>
                  </option><?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Periodo</td>
            <td class="content_row">
                  <select class="text" style="width:150px" name="mes" id="mes"
                        onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                        <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                        <option value="1" <?if(1==$_REQUEST["mes"]) echo "selected"?>>ENERO</option>
                        <option value="2" <?if(2==$_REQUEST["mes"]) echo "selected"?>>FEBRERO</option>
                        <option value="3" <?if(3==$_REQUEST["mes"]) echo "selected"?>>MARZO</option>
                        <option value="4" <?if(4==$_REQUEST["mes"]) echo "selected"?>>ABRIL</option>
                        <option value="5" <?if(5==$_REQUEST["mes"]) echo "selected"?>>MAYO</option>
                        <option value="6" <?if(6==$_REQUEST["mes"]) echo "selected"?>>JUNIO</option>
                        <option value="7" <?if(7==$_REQUEST["mes"]) echo "selected"?>>JULIO</option>
                        <option value="8" <?if(8==$_REQUEST["mes"]) echo "selected"?>>AGOSTO</option>
                        <option value="9" <?if(9==$_REQUEST["mes"]) echo "selected"?>>SEPTIEMBRE</option>
                        <option value="10" <?if(10==$_REQUEST["mes"]) echo "selected"?>>OCTUBRE</option>
                        <option value="11" <?if(11==$_REQUEST["mes"]) echo "selected"?>>NOVIEMBRE</option>
                        <option value="12" <?if(12==$_REQUEST["mes"]) echo "selected"?>>DICIEMBRE</option>
                  </select>

                  <input type="number" class="text" name="año" id="año" style="width:70px" value="<?=$_REQUEST["año"]?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <td class="content_rowl">Chofer</td>
         <td class="content_row"> 
               <select class="text" name="sql_chofer" id="sql_chofer" style="width:300px" onmousedown="markfield(this,0)" 
                  onblur="markfield(this,1)" onchange="setChofer(this.value)"" >
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($choferes as $chofere)
                  {  
                     ?>
                        <option value="<?=$chofere["id"]?>"
                        <?php if($chofere["id"] == $_REQUEST["sql_chofer"]) echo "selected"?>><?=$chofere["transports_chofer_nombre"].' '.$chofere["transports_chofer_paterno"]?></option>
                     <?php
                  }
                  ?>  
               </select>
            </td>

      </tr>
      <tr>
         <?php
         if($_SESSION[$_sesmodulename]["filter_status"] != 5)
         {?>
            <td class="content_rowl">Estado</td>
            <?php
            if($_SESSION[$_sesmodulename]["filter_status"] != 3)
            { 
                ?>
               <td class="content_row">
                <?php
                for($x = 1; $x <= 3; $x++)
                {  ?>
                   <input type="checkbox" name="sql_status[]" value="<?=$x?>"
                   <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?>><?=getEstadoDeDesapacho($x, true)?>
                   <?php
                }
            }
            ?>
            </td>
            <?php
         }
         ?>
         <td class="content_rowl">Tipo de Ingreso</td>
         <td class="content_row">
             <?php
             for($x = 1; $x <= 2; $x++)
             {  ?>
                 <input type="checkbox" name="sql_ingreso[]" value="<?=$x?>"
                 <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_ingreso"]) !== false) echo "checked"?>><?=getEstadoDeDesapachoRechazo($x, true)?>
                 <?php
             }
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="132">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($items) > 0 && $items != false)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left"></td>
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
      <?=Nifty_printH("box1", "99%",0)?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
      </colgroup>
      <tr>
         <td class="content_tbl_header content_row_os">ID</td>
         <td class="content_tbl_header content_row_os">Periodo</td>
         <td class="content_tbl_header content_row_os">Fecha</td>
         <td class="content_tbl_header content_row_os">N° Documento</td>
         <td class="content_tbl_header content_row_os">Tipo Documento</td>
         <td class="content_tbl_header content_row_os">Canal de Venta</td>
         <td class="content_tbl_header content_row_os">CC / NV</td>
         <td class="content_tbl_header content_row_os">Cliente</td>
         <td class="content_tbl_header content_row_os">Codigo</td>
         <td class="content_tbl_header content_row_os">Cantidad Pallets</td>
         <td class="content_tbl_header content_row_os">Cantidad Cajas</td>
         <td class="content_tbl_header content_row_os">Medida Caja1</td>
         <td class="content_tbl_header content_row_os">Medida Caja2</td>
         <td class="content_tbl_header content_row_os">Cantidad</td>
         <td class="content_tbl_header content_row_os">Empresa de Transporte</td>
         <td class="content_tbl_header content_row_os">Patente</td>
         <td class="content_tbl_header content_row_os">Conductor</td>
         <td class="content_tbl_header content_row_os">RUT</td>
         <td class="content_tbl_header content_row_os">Hora Entrada</td>
         <td class="content_tbl_header content_row_os">Hora Salida</td>
         <td class="content_tbl_header content_row_os">Valor Neto</td>
         <td class="content_tbl_header content_row_os">Rango</td>
         <td class="content_tbl_header content_row_os">N° de Sello</td>
         <td class="content_tbl_header content_row_os">Estado</td>
         <td class="content_tbl_header content_row_os">Tipo Ingreso</td>
         <td class="content_tbl_header content_row_os">Observación</td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         
         $statimg = "";
         switch((int)$items[$x]["ESTADO"])
         {
              case 1: $statimg = "Ingresado"; break;
              case 2: $statimg = "Archivado"; break;
              case 3: $statimg = "Anulada"; break;
         }

         $statimg2 = "";
         switch((int)$items[$x]["id_ingreso"])
         {
            case 1: $statimg2 = "Despacho"; break;
            case 2: $statimg2 = "Rechazo"; break;
         }


         $mes_palabra = "";
         switch((int)$items[$x]["MES"])
         {
              case 1: $mes_palabra = "ENERO"; break;
              case 2: $mes_palabra = "FEBRERO"; break;
              case 3: $mes_palabra = "MARZO"; break;
              case 4: $mes_palabra = "ABRIL"; break;
              case 5: $mes_palabra = "MAYO"; break;
              case 6: $mes_palabra = "JUNIO"; break;
              case 7: $mes_palabra = "JULIO"; break;
              case 8: $mes_palabra = "AGOSTO"; break;
              case 9: $mes_palabra = "SEPTIEMBRE"; break;
              case 10: $mes_palabra = "OCTUBRE"; break;
              case 11: $mes_palabra = "NOVIEMBRE"; break;
              case 12: $mes_palabra = "DICIEMBRE"; break;
         }


         if(ltrim($items[$x]["DOCUMENTO"]) == "FA")
         {
            $sql = " select * from invoices_sell where invc_number = '{$items[$x]["dlv_num"]}' ";
            $factura = $CON->select($sql);
            $items[$x]["NDOCUMENTO"] = $factura[0]["invc_docnumber"];
            $items[$x]["CCNV"]  = $factura[0]["invc_docnumber"];

            $sql = " select * from transports_rango where id = {$factura[0]["invc_rango_id"]} ";
            $rango = $CON->select($sql);
            $items[$x]["Rango"] = $rango[0]["transports_rango_codigo"];
            $items[$x]["Monto"] = $rango[0]["transports_rango_monto"];
         }


         $sql   = " select * from parametros where tabla = 'MEDIDACAJA' and codigo = '{$items[$x]["MEDIDACAJA1"]}' ";
         $caja1 = $CON->select($sql);
         $caja1 = $caja1[0];

         $sql   = " select * from parametros where tabla = 'MEDIDACAJA' and codigo = '{$items[$x]["MEDIDACAJA2"]}' ";
         $caja2 = $CON->select($sql);
         $caja2 = $caja2[0];

         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><nobr><?=$items[$x]["ID"]?></td>
            <td class="content_row_os"><nobr><?=$mes_palabra.'/'.$items[$x]["AÑO"]?></nobr></td>
            <td class="content_row_os"><nobr><?=date("d.m.Y", $items[$x]["FECHA"])?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["NDOCUMENTO"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["DOCUMENTO"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["CANALVENTA"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["CCNV"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["CLIENTE"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["CODIGOS"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["CANTPALLETS"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["CANTCAJAS"]?></td>
            <td class="content_row_os"><nobr><?=$caja1["descripcion"]?></td>
            <td class="content_row_os"><nobr><?=$caja2["descripcion"]?></td>
            <td class="content_row_os"><nobr><?=printPrice($items[$x]["CANTIDAD"])?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["EMPRESA_TRANSPORTE"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$items[$x]["PATENTE"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["CONDUCTOR"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["RUT"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["HORA_ENTRADA"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["HORA_SALIDA"]?></td>
            <td class="content_row_os"><nobr><?=printPrice($items[$x]["Monto"])?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["Rango"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["SELLO"]?></td>
            <td class="content_row_os"><nobr><?=$statimg?></td>
            <td class="content_row_os"><nobr><?=$statimg2?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["OBSERVACIONES"]?></td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ID"]                  = $items[$x]["ID"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Periodo"]             = $mes_palabra.'/'.$items[$x]["AÑO"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Fecha"]               = $items[$x]["FECHA"];                 
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Documento"]           = $items[$x]["NDOCUMENTO"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TipoDocumento"]       = $items[$x]["DOCUMENTO"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CanaldeVenta"]        = $items[$x]["CANALVENTA"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Cliente"]             = $items[$x]["CLIENTE"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Codigo"]              = $items[$x]["CODIGOS"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CantidadPallets"]     = $items[$x]["CANTPALLETS"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CantidadCajas"]       = $items[$x]["CANTCAJAS"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MedidaCaja1"]         = $caja1["descripcion"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MedidaCaja2"]         = $caja2["descripcion"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Cantidad"]            = printPrice($items[$x]["CANTIDAD"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["EmpresadeTransporte"] = $items[$x]["EMPRESA_TRANSPORTE"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Patente"]             = $items[$x]["PATENTE"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Conductor"]           = $items[$x]["CONDUCTOR"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["RUT"]                 = $items[$x]["RUT"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["HoraEntrada"]         = $items[$x]["HORA_ENTRADA"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["HoraSalida"]          = $items[$x]["HORA_SALIDA"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Sello"]               = $items[$x]["SELLO"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Estado"]              = $statimg;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Ingreso"]             = $statimg2;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Observación"]         = $items[$x]["OBSERVACIONES"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CCNV"]                = $items[$x]["CCNV"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["monto"]               = printPrice($items[$x]["Monto"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["rango"]               = $items[$x]["Rango"];

      }
      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="25" align="center">
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
$_SESSION["JSEXEC"] .= ";$('#obitpanel').html('');";

//----------------------------------------------------------------------------------
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsDespachos($CON);
if($xlsfile != "")
{
   $doctitle = "Reporte-de-Despachos".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
