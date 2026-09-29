<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "suppcont";
$_sesbasefilterstatus   = "1"; // 1) Ingresadas / Anuladas / Archivadas
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Codigo" => "2", "Fecha" => "3", "Descripción" => "11", "Cliente" => "5", "Creado por" => "6", "Ult. Modificada" => "10", "Estado"=>"9");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
// , "Estado" => "3"
//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);
   
if($_REQUEST["exec"] == "edit")
{
   require_once("overview.edit.sol.php");
}
else
{
   if($_REQUEST["exec"]=="del" || $_REQUEST["subexec"]=="del")
   {
     
      $sql = "select count(*) as contador from pro_dis pd
                                 inner join pro_dis_items on pro_dis_items_pro_id = pd.id
                                 inner join pro_dis_detalle on pro_dis_detalle_items_id = pro_dis_items_id
                              where id = {$sords[$x]["id"]}";
      $puntero = $CON->select($sql);
      $puntero = $puntero[0]["contador"];
      if($puntero > 0)
      {
          echo "<script>alert('No puede cancelar Solicitud, tiene Versiones de diseños ingresadas');</script>";
      }
      else   
      {
         
         $sql = "select pro_dis_items_status, pro_dis_asigandos from pro_dis_items where pro_dis_items_pro_id = {$_REQUEST["id"]} ";


         $sql = " update pro_dis set pro_dis_status = 0
                                , pro_dis_asignado = 0
                     where id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
   }
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

   if($_SESSION[$_sesmodulename]["filter_status"] != 4)
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]))
         $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3);

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " LEFT OUTER JOIN company_data t2  ON t1.pro_dis_company_id   = t2.id
               LEFT OUTER JOIN company_shops t3 ON t1.pro_dis_shop_id      = t3.id 
               LEFT OUTER JOIN customer t4 ON t1.pro_dis_custid = t4.id
               LEFT OUTER JOIN user t5 ON t1.pro_dis_user_cr = t5.id
               LEFT OUTER JOIN user t6 ON t1.pro_dis_asignado = t6.id" ;

   $cntsql = " select count(distinct t1.id) 'cc'
               from pro_dis t1
               {$joisql}
               where
               1 = 1 ";

   $datsql = " select distinct t1.id
                             , t1.pro_dis_codigo  
                             , t1.pro_dis_fecha_creacion 
                             , t1.pro_dis_fecha_actualizacion
                             , t1.pro_dis_observa
                             , t4.cust_name
                             , concat(t5.user_firstname , ' ' , t5.user_lastname ) as Usuario
                             , t2.company_short 
                             , t3.shop_name 
                             , t1.pro_dis_status
                             , concat(t6.user_firstname , ' ' , t6.user_lastname ) as Asignado
                             , t1.pro_dis_descripcion
               from pro_dis t1
               {$joisql}
               where
               t1.pro_dis_status > 0 ";
   
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
      $seasql .= " and t1.pro_dis_asignado = {$_SESSION[$_sesmodulename]["sql_asignado"]} ";

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
   if($_SESSION[$_sesmodulename]["filter_status"] != 4)
   {
      $seastatstr = "";
      foreach($_SESSION[$_sesmodulename]["sql_status"] AS $seastat)
         $seastatstr .= $seastat.",";
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

   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
   $datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

   //----------------------------------------------------------------------------------
   
   $sords = $CON->select($datsql);
   
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
         <?=Nifty_printH("box2", "980", 0)?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="100">
            <col>
            <col width="100">
            <col width="385">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Opciones de busqueda</td>
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
            <?php
               if($_SESSION[$_sesmodulename]["filter_status"] != 4)
               {  ?>
                     <td class="content_rowl">Estado</td>
                     <td class="content_row" colspan="3">
                        <?php
                        for($x = 1; $x <= 4; $x++)
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
            <td class="content_row" colspan="2">&nbsp;</td>
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
            <col width="120">
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
            <td class="content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
            <td class="content_tbl_subheader" colspan="2" align="center">Opciones</td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($sords) && $sords != false; $x++)
         {
            $statimg = "";
            switch((int)$sords[$x]["pro_dis_status"])
            {
               case 0: $statimg = "yellow_active.gif"; break;
               case 1: $statimg = "orange_active.gif"; break;
               case 2: $statimg = "red_active.gif"; break;
               case 3: $statimg = "green_active.gif"; break;
               case 4: $statimg = "purple_active.gif"; break;
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$sords[$x]["pro_dis_codigo"]?></td>
               <td class="content_row"><?=date('d.m.Y',$sords[$x]["pro_dis_fecha_creacion"])?>&nbsp;</td>
               <td class="content_row"><?=$sords[$x]["pro_dis_descripcion"]?>&nbsp;</td>
               <td class="content_row"><?=$sords[$x]["cust_name"]?>&nbsp;</td>
               <td class="content_row"><?=$sords[$x]["Usuario"]?>&nbsp;</td>
               <td class="content_row"><?=date('d.m.Y',$sords[$x]["pro_dis_fecha_actualizacion"])?>&nbsp;</td>
               <!--<td class="content_row"><?=$sords[$x]["Asignado"]?>&nbsp;</td>-->
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getOrderStatus($sords[$x]["req_status"])?>">
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$sords[$x]["id"]}", "", "");
                  ?>
               </td>
               <td class="content_row" align="center">
                  <?php
                     $sql = "select count(*) as contador from pro_dis pd
                                 inner join pro_dis_items on pro_dis_items_pro_id = pd.id
                                 inner join pro_dis_detalle on pro_dis_detalle_items_id = pro_dis_items_id
                              where id = {$sords[$x]["id"]}";
                     $puntero = $CON->select($sql);
                     $puntero = $puntero[0]["contador"];
                     if($puntero == 0)
                         printButton("Anular", "postnav",    "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=del&id={$sords[$x]["id"]}')", "");
                     else
                         printButton("Anular", "postnav",    "javascript: deactivateFormChange()", "alert('No puede Anular, propuesta({$sords[$x]["id"]}) tiene versiones ingresadas')", "");                        
                  ?>
               </td
            </tr>
            <?php
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