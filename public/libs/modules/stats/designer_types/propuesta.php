<?php
$_sesmodulename         = "propuesta";
$_sesbasefilterstatus   = "0"; // 1) Ingresadas / Anuladas / Archivadas
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Codigo" => "2", "Fecha" => "3", "Descripción" => "11", "Cliente" => "5", "Creado por" => "6", "Ult. Modificada" => "10", "Estado"=>"9");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);
   
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
     $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
     $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
     $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"]))); // codigo
     $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
     $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
     $_SESSION[$_sesmodulename]["sql_obs"]       = trim(addslashes(str_replace("*","%",$_REQUEST["sql_obs"]))); 
     $_SESSION[$_sesmodulename]["sql_customer"]  = (int)$_REQUEST["sql_customer"];
     $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
     $_SESSION[$_sesmodulename]["page"]          = 0;
     $_SESSION[$_sesmodulename]["search_active"] = 1;
     $_SESSION[$_sesmodulename]["sql_asignado"]   = (int)$_REQUEST["sql_asignado"];
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

if($_SESSION[$_sesmodulename]["filter_status"] != 5)
   if(!is_array($_SESSION[$_sesmodulename]["sql_status"]))
      $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>0,1=>1,2=>2,3=>3,4=>4,5=>5);

//----------------------------------------------------------------------------------
$seasql = "";
$joisql = " LEFT OUTER JOIN company_data t2  ON t1.pro_dis_company_id   = t2.id
            LEFT OUTER JOIN company_shops t3 ON t1.pro_dis_shop_id      = t3.id 
            LEFT OUTER JOIN customer t4 ON t1.pro_dis_custid = t4.id
            LEFT OUTER JOIN user t5 ON t1.pro_dis_user_cr = t5.id
            left outer join pro_dis_items pdi on t1.id =  pdi.pro_dis_items_pro_id
            LEFT OUTER JOIN user t6 ON pdi.pro_dis_items_user_cr = t6.id            
            LEFT OUTER JOIN user t7 ON pdi.pro_dis_asignado = t7.id            
            LEFT OUTER JOIN user t8 ON pdi.pro_dis_items_user_md = t8.id            
            INNER JOIN pro_dis_detalle pdd on pdi.pro_dis_items_id = pdd.pro_dis_detalle_items_id 
            LEFT OUTER JOIN user t9 ON pdd.pro_dis_detalle_user_cr = t9.id ";

$cntsql = " select count(distinct t1.id) 'cc' from pro_dis t1 {$joisql} where 1 = 1 ";

$datsql = " select  /* CABECERA */
                   t1.id
                 , t1.pro_dis_codigo                
                 , t1.pro_dis_fecha_creacion        

                 , case when t1.pro_dis_status = 3 then t1.pro_dis_fecha_actualizacion 
                                          else 0
                   end                                             as pro_dis_fecha_actualizacion
	              , t1.pro_dis_user_cr                              as 'ID Ejecutivo'
                 , concat(t5.user_firstname,' ', t5.user_lastname) as Usuario
	              , t1.pro_dis_custid                               as 'Id Cliente'
                 , t4.cust_company                                 as 'Cliente'
                 , t1.pro_dis_status               
                 , t1.pro_dis_descripcion
                 , t4.cust_name

                 /* ITEMS */

                 , pdi.pro_dis_items_codigo                        as 'Codigo Propuesta'
                 , pdi.pro_dis_items_user_cr                       as 'Usuario Creacion'      
                 , concat(t6.user_firstname,' ', t6.user_lastname) as Usuario2
                 , pdi.pro_dis_items_fecha_cr                      as 'Fecha Creación Propuesta'
	              , pdi.pro_dis_asignado                            as 'Diseñador Asignado'
                 , concat(t7.user_firstname,' ', t7.user_lastname) as Asignado
	              , pdi.pro_dis_items_fecha_md                      as 'Fecha Finalizada'
                 , pdi.pro_dis_items_user_md                       as 'Usuario que Finalizo'
                 , concat(t8.user_firstname,' ', t8.user_lastname) as Finalizado
                 , pdi.pro_dis_items_descripcion                   as 'Descripcion Items'
                 , pdi.pro_dis_items_status                        as 'Estado Items'

                 /* DETALLE */

                 , pdd.pro_dis_detalle_codigo                      as 'Codigo Version'
                 , pdd.pro_dis_detalle_user_cr                     as 'Diseñador que sube Version'   
                 , concat(t9.user_firstname,' ', t9.user_lastname) as FinalizadoVersion
	              , pdd.pro_dis_detalle_fecha_cr                    as 'Fecha que Sube Version'
                 , pdd.pro_dis_detalle_fecha                       as 'Fecha de Finaliza Version'
                 , pdd.pro_dis_detalle_status                      as 'Estado de Versión'
                 , pdd.pro_dis_detalle_id                          as 'Id Versión' 
               from pro_dis t1
                  {$joisql}
               where 1 = 1 ";
   
//----------------------------------------------------------------------------------
   
if($_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.pro_dis_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.pro_dis_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if($_SESSION[$_sesmodulename]["sql_stext"] != "")
   $seasql .= " and t1.pro_dis_codigo like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' ";
if($_SESSION[$_sesmodulename]["sql_obs"] != "")
   $seasql .= " and t1.pro_dis_descripcion like '%{$_SESSION[$_sesmodulename]["sql_obs"]}%' ";
if($_SESSION[$_sesmodulename]["sql_customer"])
   $seasql .= " and t1.pro_dis_custid = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if($_SESSION[$_sesmodulename]["sql_asignado"])
   $seasql .= " and pdd.pro_dis_detalle_user_cr = {$_SESSION[$_sesmodulename]["sql_asignado"]} ";
/* if($_SESSION["user_type"]==2)
   $seasql .= " and t1.pro_dis_user_cr = {$_SESSION["user_id"]} ";*/
      

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
{
   if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
      $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
   if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
      $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

   $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
   $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);
   $seasql .= " and t1.pro_dis_fecha_creacion between {$sqldate_from} and {$sqldate_to} ";
}

//----------------------------------------------------------------------------------
 
   
if($_SESSION[$_sesmodulename]["filter_status"] != 5)
{
   foreach($_SESSION[$_sesmodulename]["sql_status"] AS $seastat)
   {
      $seastatstr .= $seastat.",";
   }
   $seastatstr = substr($seastatstr, 0, -1);
   $seasql .= " and pro_dis_status IN ({$seastatstr}) ";
}

//----------------------------------------------------------------------------------
$cntsql   .= $seasql;
$datsql   .= $seasql;
$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"];

//----------------------------------------------------------------------------------

$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

$datsql .= " order by t1.id , pro_dis_items_pro_id , pdd.pro_dis_detalle_id ";

$sords = $CON->select($datsql);
  
// echo($datsql);

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON);
   
//----------------------------------------------------------------------------------

$sql = "select u.id
             , concat(user_firstname,' ',user_lastname) as usuario_asignado
         from user u
            inner join  user_group ug on ug.user_id = u.id and ug.group_id = 20
         where u.user_status > 0";
$asignados = $CON->select($sql);   
//-------------------------------------------------------------

   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <?php
   printJSsetCompanyShop($shops);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen de Propuestas</b></td>
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
         <input type="hidden" name="printxls" value="0">         
         <?=Nifty_printH("box2", "980", 0)?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="100">
            <col>
            <col width="100">
            <col width="385">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Filtros de busqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Codigo</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:375px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Empresa</td>
            <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Descripcion</td>
            <td class="content_row">
               <input name="sql_obs" type="text" class="text" style="width:375px"
               value="<?=$_SESSION[$_sesmodulename]["sql_obs"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Cliente</td>
            <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename)?></td>
         </tr>
         <tr>
            <td class="content_rowl">Asignado</td>
            <td class="content_row">
               <select class="text" style="width:330px" name="sql_asignado" id="sql_asignado" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                     foreach($asignados as $asignado)
                     {?>
                        <option value="<?=$asignado["id"]?>"
                           <?php if($asignado["id"] == $_SESSION[$_sesmodulename]["sql_asignado"]) echo "selected"?>><?=$asignado["usuario_asignado"]?>
                        </option><?php
                     }
               ?>
               </select>
            </td>
         </tr>
         <tr>
            <?php
               if($_SESSION[$_sesmodulename]["filter_status"] != 5)
               {  ?>
                     <td class="content_rowl">Estado</td>
                     <td class="content_row" colspan="3">
                        <?php
                        for($x = 0; $x <= 4; $x++)
                        {  ?>
                           <input type="checkbox" name="sql_status[]" value="<?=$x?>"
                           <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?>><?=getPropuestaStatus($x, true)?>
                           <?php
                        }
                        ?>
                     </td>
                  <?php
               }
               else
               {  ?>
                     <td class="content_row" colspan="2">&nbsp;</td>
                  <?php
               }
            ?>
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
                        if(count($sords) > 0 && $sords != false)
                        {
                           printButton("Generar XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                           $_SESSION["_SUBMITBTN"] = 1;
                        }
                        ?>
                     </td>
                     <td></td>               
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
         <?=Nifty_printH("box1", "980", 0)?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <tr>
            <td class="content_rowl content_row_os" align="center" colspan="4">Propuesta</td>
            <td class="content_row"                 align="center" colspan="4">Items</td>
            <td class="content_rowl content_row_os" align="center" colspan="6">Versiones</td>
            <td class="content_row"                 align="center" colspan="2">Cierre de Propuesta</td>
         </tr>
         <tr>
            <!-- /* PROPUESTAS */ -->
            <td class="content_rowl content_row_os" align="center">Código</td>
            <td class="content_rowl content_row_os" align="center">Cliente</td>
            <td class="content_rowl content_row_os" align="center">Creado por</td>
            <td class="content_rowl content_row_os" align="center">Estado</td>            

            <!-- /* ITEMS */ -->
            <td class="content_rowl content_row_os" align="center"><nobr>Codigo Propuesta</nobr></td>
            <td class="content_rowl content_row_os" align="center">Referencia Pedido</td>                                    
            <td class="content_rowl content_row_os" align="center"><nobr>Fecha Propuesta</nobr></td>
            <td class="content_rowl content_row_os" align="center"><nobr>Hora Propuesta</nobr></td>
	         <!--  
               <td class="content_rowl content_row_os" align="center"><nobr>Diseñador Asignado</nobr></td>
               <td class="content_rowl content_row_os" align="center"><nobr>Fecha Termino</nobr></td>
               <td class="content_rowl content_row_os" align="center"><nobr>Hora Termino</nobr></td> 
               <td class="content_rowl content_row_os" align="center"><nobr>Usuario que Finalizo</nobr></td>
               <td class="content_rowl content_row_os" align="center"><nobr>Descripcion Items</nobr></td>
               <td class="content_rowl content_row_os" align="center"><nobr>Estado Items</nobr></td> 
            -->

            <!-- /* DETALLE */ --> 
            <td class="content_rowl content_row_os" align="center"><nobr>Codigo Version</nobr></td>
            <td class="content_rowl content_row_os" align="center"><nobr>Diseñador que sube Version</nobr></td>
	         <td class="content_rowl content_row_os" align="center"><nobr>Fecha que Sube Version</nobr></td>
            <td class="content_rowl content_row_os" align="center"><nobr>Hora que Sube Version</nobr></td>
            <td class="content_rowl content_row_os" align="center"><nobr>Fecha de Finaliza Version</nobr></td>
            <td class="content_rowl content_row_os" align="center"><nobr>Hora de Finaliza Version</nobr></td>
            
            <td class="content_rowl content_row_os" align="center"><nobr>Fecha Termino</nobr></td>
            <td class="content_rowl content_row_os" align="center"><nobr>Hora Termino</nobr></td>
         </tr>
         <?php
         
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($sords) && $sords != false; $x++)
         {
            $statimg = "";
            switch((int)$sords[$x]["pro_dis_status"])
            {
               case 0: $statimg = "Ingresada"; break;
               case 1: $statimg = "Solicitada"; break;
               case 2: $statimg = "En Proceso"; break;
               case 3: $statimg = "Terminada"; break;
               case 4: $statimg = "Anulada"; break;
            }

            $statimg2 = "";
            switch((int)$sords[$x]["Estado Items"])
            {
               case 0: $statimg2 = "Ingresada"; break;
               case 1: $statimg2 = "Solicitada"; break;
               case 2: $statimg2 = "En Proceso"; break;
               case 3: $statimg2 = "Terminada"; break;
               case 4: $statimg2 = "Anulada"; break;
            }
            
            
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <!-- /* PROPUESTAS */ -->
               <?php $fec_actualizacion = $sords[$x]["pro_dis_fecha_actualizacion"]; ?>               

               <td class="content_row"><nobr><?=$sords[$x]["pro_dis_codigo"]?></nobr></td>
               <td class="content_row"><nobr><?=$sords[$x]["cust_name"]?></nobr></td>
               <td class="content_row"><nobr><?=$sords[$x]["Usuario"]?></nobr></td>
               <td class="content_row"><?=$statimg?>&nbsp;</td>               

               <!-- /* ITEMS */ -->
               <td class="content_row"><nobr><?=$sords[$x]["Codigo Propuesta"]?></nobr></td>
               <td class="content_row"><nobr><?=$sords[$x]["Descripcion Items"]?></nobr></td>
               <td class="content_row"><nobr><?=date('d/m/Y',$sords[$x]["Fecha Creación Propuesta"])?></nobr></td>
               <td class="content_row"><nobr><?=date('h:i',$sords[$x]["Fecha Creación Propuesta"])?></nobr></td>

               <!-- /* VERISONES */ --> 
               <td class="content_row"><nobr><?=$sords[$x]["Codigo Version"]?></nobr></td>
               <td class="content_row"><nobr><?=$sords[$x]["FinalizadoVersion"]?></nobr></td>   
               <?php $fec_sube_verison = $sords[$x]["Fecha que Sube Version"]; ?>
               <td class="content_row"><?=!empty($fec_sube_verison) && is_numeric($fec_sube_verison) ? date('d/m/Y', $fec_sube_verison) : "" ?></td>
               <td class="content_row"><?=!empty($fec_sube_verison) && is_numeric($fec_sube_verison) ? date('H:i', $fec_sube_verison) : "" ?></td>
               <?php $fec_fin_ver = $sords[$x]["Fecha de Finaliza Version"]; ?>
               <td class="content_row"><?=!empty($fec_fin_ver) && is_numeric($fec_fin_ver) ? date('d/m/Y', $fec_fin_ver) : "" ?></td>
               <td class="content_row"><?=!empty($fec_fin_ver) && is_numeric($fec_fin_ver) ? date('H:i', $fec_fin_ver) : "" ?></td>
               <td class="content_row"><?=!empty($fec_actualizacion) && is_numeric($fec_actualizacion) ? date('d/m/Y', $fec_actualizacion) : "" ?></td>
               <td class="content_row"><?=!empty($fec_actualizacion) && is_numeric($fec_actualizacion) ? date('H:i', $fec_actualizacion) : "" ?></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["pro_dis_codigo"]           = $sords[$x]["pro_dis_codigo"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_name"]                = $sords[$x]["cust_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Usuario"]                  = $sords[$x]["Usuario"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Codigo Propuesta"]         = $sords[$x]["Codigo Propuesta"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Descripcion Items"]        = $sords[$x]["Descripcion Items"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Fecha Creación Propuesta"] = $sords[$x]["Fecha Creación Propuesta"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Codigo Version"]           = $sords[$x]["Codigo Version"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FinalizadoVersion"]        = $sords[$x]["FinalizadoVersion"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fec_sube_verison"]         = $fec_sube_verison;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fec_fin_ver"]              = $fec_fin_ver;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fec_actualizacion"]        = $fec_actualizacion;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Estado"]                   = $statimg;
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="9" align="center">
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
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
   if($_REQUEST["printxls"])
       $xlsfile = xls_createStatsPropuestas($CON);

   if($xlsfile != "")
   {
      $doctitle = "ReportePropuestas-".time().".xls";
      $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
      ?>
      <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
      <?php
   }
