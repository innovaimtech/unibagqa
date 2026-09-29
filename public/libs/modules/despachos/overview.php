<?php
//----------------------------------------------------------------------------------
// Author:        FERNANDO GARRIDO GONZALEZ
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
$_REQUEST["id_despacho"] = (int)$_REQUEST["id_despacho"];
//----------------------------------------------------------------------------------
$sql = " select *
         from user
         where
         id = {$_SESSION["user_id"]}";
$userdata = $CON->select($sql);
$userdata = $userdata[0];

//----------------------------------------------------------------------------------
$_sesmodulename         = "invoicessell";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Id"=>"1", "Numero" => "2", "Doc"=> "3","Fecha Ingreso"=>"4", "Cliente"=>"5", "Transporte" => "6", "Chofer" => "7",  "Ingreso" => "8", "Estado"=>"9");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);
   
if($_REQUEST["exec"] == "edit")
{
   require_once("overview.edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $sql_item = explode("#", $_REQUEST["item_id"]);
      
      $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_chofer"]    = (int)$_REQUEST["sql_chofer"];
      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
      $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
      $_SESSION[$_sesmodulename]["sql_ingreso"]   = $_REQUEST["sql_ingreso"];
      $_SESSION[$_sesmodulename]["sql_mes"]       = $_REQUEST["sql_mes"];
      $_SESSION[$_sesmodulename]["sql_año"]       = $_REQUEST["sql_año"];
      $_SESSION[$_sesmodulename]["sql_stext"]     = $_REQUEST["sql_stext"];
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   //----------------------------------------------------------------------------------
   
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

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


  if(!is_array($_SESSION[$_sesmodulename]["sql_ingreso"]) ||
     (array_search(1,$_SESSION[$_sesmodulename]["sql_ingreso"]) === false &&
      array_search(2,$_SESSION[$_sesmodulename]["sql_ingreso"]) === false))
   $_SESSION[$_sesmodulename]["sql_ingreso"] = Array(0=>1,1=>2);

   foreach($_SESSION[$_sesmodulename]["sql_ingreso"] AS $sqlst)
      $sqlstatstr2 .= $sqlst.",";
   $sqlstatstr2 = substr($sqlstatstr, 0, -1);
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "anu")
   {
      $sql = " update despacho set estado = 3 where id = {$_REQUEST["id_despacho"]} ";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   if($_REQUEST["subexec"] == "del")
   {
      $sql = " update despacho set estado = 9 where id = {$_REQUEST["id_despacho"]} ";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }


   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "abrir")
   {
      $sql = " update despacho set estado = 1 where id = {$_REQUEST["id_despacho"]} ";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "archivar")
   {
      $sql = " update despacho set estado = 2 where id = {$_REQUEST["id_despacho"]} ";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }
   

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " left outer join transports t1 on t1.id = d1.id_transporte
               left outer join transports_chofer tc1 on tc1.id = d1.id_chofer ";

   $cntsql = " select count(distinct t1.id) 'cc'
                 from despacho d1
                 {$joisql}
                where d1.estado IN ({$sqlstatstr}) ";

   $datsql = " select d1.id
                     ,d1.fecha_ingreso
                     ,d1.mes
                     ,d1.año
                     ,d1.id_transporte
                     ,t1.trans_name
                     ,d1.id_chofer
                     ,concat(tc1.transports_chofer_nombre,' ',tc1.transports_chofer_paterno) as nombre_chofer
                     ,d1.estado
                     ,d1.tipo_documento
                     ,d1.numero_documento
                     ,d1.empresa
                     ,d1.sucursal
                     ,c1.cust_company
                     ,od1.dlv_docnum
                     ,od1.dlv_num
                     ,d1.id_ingreso
                  from despacho d1
                       {$joisql}
                     inner join customer c1 on d1.id_cliente = c1.id
                     inner join orders_delivery od1 on d1.numero_documento = od1.id
                  where d1.estado IN ({$sqlstatstr}) ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and d1.empresa = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and d1.sucursal = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_chofer"])
      $seasql .= " and d1.id_chofer = {$_SESSION[$_sesmodulename]["sql_chofer"]} ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and ( d1.id      like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                         d1.numero_documento   like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' ) ";
                         
   if($_SESSION[$_sesmodulename]["sql_año"] != "" && $_SESSION[$_sesmodulename]["sql_año"] != "" )
      $seasql .= " and d1.año = {$_SESSION[$_sesmodulename]["sql_año"]}
                   and d1.mes = {$_SESSION[$_sesmodulename]["sql_mes"]} ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
   {
      if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
         $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
      if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
         $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

      $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
      $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);

      $seasql .= " and d1.fecha_ingreso between {$sqldate_from} and {$sqldate_to} ";
   }

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["filter_status"] != 5)
   {
      $seastatstr = "";
      foreach($_SESSION[$_sesmodulename]["sql_status"] AS $seastat)
         $seastatstr .= $seastat.",";
      $seastatstr = substr($seastatstr, 0, -1);
      $seasql .= " and d1.estado IN ({$seastatstr}) ";
   }

   if($_SESSION[$_sesmodulename]["filter_status"] != 3)
   {
      $seastatstr2 = "";
      foreach($_SESSION[$_sesmodulename]["sql_ingreso"] AS $seastat)
         $seastatstr2 .= $seastat.",";
      $seastatstr2 = substr($seastatstr2, 0, -1);
      $seasql .= " and d1.id_ingreso IN ({$seastatstr2}) ";
   }

   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;
   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
   $datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";


 // echo("  --------->  ".$datsql);

   //----------------------------------------------------------------------------------
   $invoices = $CON->select($datsql);
  
   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON, false, true);
   $customers  = getCustomers($CON);

   $sql      = " select * from transports_chofer where transports_chofer_status > 0";
   $choferes = $CON->select($sql);

   //----------------------------------------------------------------------------------
   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
      <?php
   printJSsetCompanyShop($shops);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen de Despachos/Rechazos</b></td>
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
         <input type="hidden" name="id_despacho" value="<?=$_REQUEST["id_despacho"]?>">
         <?=Nifty_printH("box2", "980", 0)?>
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
            <td class="content_rowl">Periodo</td>
            <td class="content_row">
                  <select class="text" style="width:150px" name="sql_mes" id="sql_mes"
                        onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                        <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                        <option value="1" <?if(1==$_REQUEST["sql_mes"]) echo "selected"?>>ENERO</option>
                        <option value="2" <?if(2==$_REQUEST["sql_mes"]) echo "selected"?>>FEBRERO</option>
                        <option value="3" <?if(3==$_REQUEST["sql_mes"]) echo "selected"?>>MARZO</option>
                        <option value="4" <?if(4==$_REQUEST["sql_mes"]) echo "selected"?>>ABRIL</option>
                        <option value="5" <?if(5==$_REQUEST["sql_mes"]) echo "selected"?>>MAYO</option>
                        <option value="6" <?if(6==$_REQUEST["sql_mes"]) echo "selected"?>>JUNIO</option>
                        <option value="7" <?if(7==$_REQUEST["sql_mes"]) echo "selected"?>>JULIO</option>
                        <option value="8" <?if(8==$_REQUEST["sql_mes"]) echo "selected"?>>AGOSTO</option>
                        <option value="9" <?if(9==$_REQUEST["sql_mes"]) echo "selected"?>>SEPTIEMBRE</option>
                        <option value="10" <?if(10==$_REQUEST["sql_mes"]) echo "selected"?>>OCTUBRE</option>
                        <option value="11" <?if(11==$_REQUEST["sql_mes"]) echo "selected"?>>NOVIEMBRE</option>
                        <option value="12" <?if(12==$_REQUEST["sql_mes"]) echo "selected"?>>DICIEMBRE</option>
                  </select>
                  <input type="number" class="text" id="sql_año" name="sql_año" style="width:70px" value="<?=$_REQUEST["sql_año"]?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)" 

                  >
            <td class="content_rowl">Sucursal</td>
            <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
         </tr>
         <tr>
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
            <td class="content_rowl">Fecha Ingreso</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
         </tr>
         <tr>
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
            <td class="content_rowl">Tipo de Movimiento</td>
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
            <td class="content_row"></td>
            <td class="content_row"></td>
            <td class="content_row" align="right" colspan="2">
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
         <?=Nifty_printF(false)?>
         </form>
      </td>
   </tr>
   <tr>
      <td>
         <?=Nifty_printH("box1", "980", 0)?>
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="75">
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="25">
            <col width="25">
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader"><nobr><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></nobr></td>
            <td class="content_tbl_subheader"><nobr><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></nobr></td>
            <td class="content_tbl_subheader"><nobr><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></nobr></td>
            <td class="content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
            <td class="content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 8)?></td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($invoices) && $invoices != false; $x++)
         {
            // $_SESSION[$_sesmodulename]["FLW"][$invoices[$x]["id"]]["L"] = (int)$invoices[($x -1)]["id"];
            // $_SESSION[$_sesmodulename]["FLW"][$invoices[$x]["id"]]["N"] = (int)$invoices[($x +1)]["id"];
            
            $statimg = "";
            switch((int)$invoices[$x]["estado"])
            {
               case 1: $statimg = "green_active.gif"; break;
               case 2: $statimg = "red_active.gif"; break;
               case 3: $statimg = "gray.gif"; break;
            }

            $statimg2 = "";
            switch((int)$invoices[$x]["id_ingreso"])
            {
               case 1: $statimg2 = "blue_active.gif"; break;
               case 2: $statimg2 = "yellow_active.gif"; break;
            }


            if(ltrim($invoices[$x]["tipo_documento"]) == "FA")
            {
               $sql = " select * from invoices_sell where invc_number = '{$invoices[$x]["dlv_num"]}' ";
               $factura = $CON->select($sql);
               $invoices[$x]["dlv_docnum"] = $factura[0]["invc_docnumber"];
            }

            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row"><?=$invoices[$x]["id"];?></td>
               <td class="content_row"><?=$invoices[$x]["dlv_docnum"];?></td>
               <td class="content_row"><?=$invoices[$x]["tipo_documento"]?>&nbsp;</td>
               <td class="content_row"><?=date('d.m.Y',$invoices[$x]["fecha_ingreso"])?></td>               
               <td class="content_row"><?=$invoices[$x]["cust_company"]?></td>
               <td class="content_row"><?=$invoices[$x]["trans_name"]?></td>
               <td class="content_row"><?=$invoices[$x]["nombre_chofer"]?>: <?=$invoices[$x]["shop_name"]?></td>
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg2?>" title="Estado: <?=getEstadoDeDesapachoRechazo($invoices[$x]["id_ingreso"])?>">
               </td>
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getEstadoDeDesapacho($invoices[$x]["estado"])?>">
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id_despacho={$invoices[$x]["id"]}&id={$invoices[$x]["numero_documento"]}", "", "pencil");
                  ?>
               </td>
            </tr>
            <?php
         }

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
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <?=Nifty_printF()?>
         <br>
      </td>
   </tr>
   </table>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
}
