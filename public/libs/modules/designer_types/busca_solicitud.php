<?php
//----------------------------------------------------------------------------------
$_sesmodulename         = "buscador";
$_sesbasefilterstatus   = "1"; // 1) Solicitada / 2) Aceptado / 3) Rechazado / 4) Eliminada
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Numero" => "1","Cliente" =>"3","Solicitante"=>"4","Fecha Solicitante"=>"5","Revisor"=>"6","Fecha Revisión"=>"7","Estado"=>"8");
$_SESSION[$_sesmodulename]["sql_status"] = $_SESSION["sql_status"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);
$_REQUEST["id"] = (int)$_REQUEST["id"];
$_REQUEST["id_item"] = (int)$_REQUEST["id_item"];
$_REQUEST["fab_design_imagehash"] = $_REQUEST["fab_design_imagehash"];
$_REQUEST["req_data_id"] = (int)$_REQUEST["req_data_id"];
if($_REQUEST["exec"] == "edit")
{
   require_once("aprueba.menu.php");
}
else
{
   if($_REQUEST["subexec"] == "aprueba")
   {

      echo("ingreso por {$_REQUEST["req_data_id"]} acá");

   }

   if($_REQUEST["subexec"] == "rechaza")
   {

      $CON->no_result("insert into mensajes(texto) values('entro por req_data_id  buscar_9 ({$_REQUEST["req_data_id"]}), ({$_REQUEST["req_id_propuesta"]}) , ({$_REQUEST["req_id_item"]}) ') ");
      echo("ingreso por {$_REQUEST["req_data_id"]} acá rechazo");

   }

   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["sql_numero"]      = (int)$_REQUEST["sql_numero"];
      $_SESSION[$_sesmodulename]["sql_customer"]    = (int)$_REQUEST["sql_customer"];
      $_SESSION[$_sesmodulename]["sql_solicitado"]  = (int)$_REQUEST["sql_solicitado"];
      $_SESSION[$_sesmodulename]["sql_revisado"]    = (int)$_REQUEST["sql_revisado"];

      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);

      $_SESSION[$_sesmodulename]["sql_status"]      = $_REQUEST["sql_status"];
      $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));

      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   if($_SESSION[$_sesmodulename]["filter_status"] != 4)
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]))
         $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3);

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " LEFT OUTER JOIN orders   t2 ON t2.req_numero_aprob = t1.id
               LEFT OUTER JOIN customer t4 ON t4.id = t2.req_cust_id
               LEFT OUTER JOIN user     t5 ON t5.id = t1.apro_id_user_sol
               LEFT OUTER JOIN user     t6 ON t6.id = t1.apro_id_user_rev" ;

   $cntsql = " select count(distinct t1.id) 'cc'
               from aprobacion_dis t1
               {$joisql}
               where
               1 = 1 ";

   $datsql = " select t2.id
                     ,t2.req_number 
	                  ,t4.cust_rut
                     ,t4.cust_name
                     ,concat(t5.user_lastname ,' ',t5.user_firstname) as solicitante
                     ,t1.apro_fec_solicitud
                     ,concat(t6.user_lastname ,' ',t6.user_firstname) as revisor
                     ,t1.apro_fec_resolucion
                     ,t1.apro_estado
               from aprobacion_dis t1
               {$joisql}
               where 1 = 1 ";
   
    if($_SESSION[$_sesmodulename]["sql_revisado"])
       $seasql .= " and t1.apro_id_user_rev = {$_SESSION[$_sesmodulename]["sql_revisado"]} ";

   if($_SESSION[$_sesmodulename]["sql_solicitado"])
       $seasql .= " and t1.apro_id_user_sol = {$_SESSION[$_sesmodulename]["sql_solicitado"]} ";

   if($_SESSION[$_sesmodulename]["sql_customer"])
        $seasql .= " and t1.pro_dis_custid = {$_SESSION[$_sesmodulename]["sql_customer"]} ";

   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
   {
      $seasql .= " and
                    (
                       t2.req_number     like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                       t4.cust_name      like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                    ) ";
   }

   //----------------------------------------------------------------------------------

   if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
   {
      if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
         $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
      if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
         $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

      $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
      $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);

      $seasql .= " and t1.apro_fec_solicitud between {$sqldate_from} and {$sqldate_to} ";
   }   
 
   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["filter_status"] != 4)
   {
      $seastatstr = "";
      foreach($_SESSION[$_sesmodulename]["sql_status"] AS $seastat)
         $seastatstr .= $seastat.",";
      $seastatstr = substr($seastatstr, 0, -1);
      $seasql .= " and apro_estado IN ({$seastatstr}) ";
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
   $sql = "select u.id
               , concat(user_firstname,' ',user_lastname) as usuario_asignado
            from user u
               inner join  user_group ug on ug.user_id = u.id and ug.group_id = 20
               where u.user_status > 0";
   $asignados = $CON->select($sql);   
   //-------------------------------------------------------------
   $sql = "select distinct u.id
                , concat(user_firstname,' ',user_lastname) as usuario_asignado
            from user u
               inner join user_group ug on ug.user_id = u.id and ug.group_id not in(30,31)
               where u.user_status > 0";
   $usuarios = $CON->select($sql);
   
   //----------------------------------------------------------------------------------
   function getAprobacionDiseñoStatus($stat, $formated = false)
   {
      if($formated)
      {
         switch($stat)
         {
            case 1: return "<b class='msg_save_err'>Solicitado</b>"; break;
            case 2: return "<b class='msg_save_ok'>Aceptado</b>"; break;
            case 3: return "<b style='color:purple'>Rechazado</b>"; break;
         }
      }
      else
      {
         switch($stat)
         {
            case 1: return "Solicitado"; break;
            case 2: return "Aceptado"; break;
            case 3: return "Rechazado"; break;
         }
      }
   }
   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
         <input type="hidden" name="subexec" value="search">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
         <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
         <input type="hidden" name="id_item" value="<?=$_REQUEST["id_item"]?>">
         <input type="hidden" name="deldesignimg" value="">
         <input type="hidden" name="fab_design_imagehash" id="fab_design_imagehash" value="">
         <input type="hidden" name="req_data_id" id="req_data_id" value="<?=$_REQUEST["req_data_id"]?>"">
         <input type="hidden" name="req_id_propuesta" id="req_id_propuesta" value="<?=$_REQUEST["req_id_propuesta"]?>">
         <input type="hidden" name="req_id_item" id="req_id_item" value="<?=$_REQUEST["req_id_item"]?>">
         <input type="hidden" name="fab_design_name" id="fab_design_name" value="<?=$_REQUEST["fab_design_name"]?>">

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
            <td class="content_rowl">Número de C.C.</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:375px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Cliente</td>
            <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename)?></td>
         </tr>
         <tr>
            <td class="content_rowl">Fecha Solicitud</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
            <td class="content_rowl">Solicitante</td>
            <td class="content_row">
               <select class="text" style="width:330px" name="sql_solicitado" id="sql_solicitado" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                     foreach($usuarios as $usuario)
                     {?>
                        <option value="<?=$usuario["id"]?>"
                           <?php if($usuario["id"] == $_SESSION[$_sesmodulename]["sql_solicitado"]) echo "selected"?>><?=$usuario["usuario_asignado"]?>
                        </option><?php
                     }
               ?>
               </select>
            </td>
         </tr>  
         <tr>
            <?php
               if($_SESSION[$_sesmodulename]["filter_status"] != 4)
               {  ?>
                     <td class="content_rowl">Estado</td>
                     <td class="content_row" colspan="1">
                        <?php
                          for($x = 1; $x <= 3; $x++)
                          {  
                             ?>
                             <input type="checkbox" name="sql_status[]" value="<?=$x?>"
                             <?php 
                                if(array_search($x, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?> <?=getAprobacionDiseñoStatus($x, true)?>
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
            <td class="content_rowl">Revisado</td>
            <td class="content_row">
               <select class="text" style="width:330px" name="sql_revisado" id="sql_revisado" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                  foreach($asignados as $asignado)
                  {?>
                     <option value="<?=$asignado["id"]?>"
                        <?php if($asignado["id"] == $_SESSION[$_sesmodulename]["sql_revisado"]) echo "selected"?>><?=$asignado["usuario_asignado"]?>
                     </option><?php
                  }
               ?>
               </select>
            </td>

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
            <col width="80">
            <col>
            <col>
            <col>
            <col width="80">
            <col>
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
            <td class="content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($sords) && $sords != false; $x++)
         {
            $statimg = "";
            switch((int)$sords[$x]["apro_estado"])
            {
               case 1: $statimg = "green_active.gif"; break;
               case 2: $statimg = "orange_active.gif"; break;
               case 3: $statimg = "red_active.gif"; break;
               case 4: $statimg = "purple_active.gif"; break;
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$sords[$x]["req_number"]?></td>
               <!--<td class="content_row"><nobr><?=$sords[$x]["cust_rut"]?></nobr></td>-->
               <td class="content_row"><?=$sords[$x]["cust_name"]?></td>
               <td class="content_row"><nobr><?=$sords[$x]["solicitante"]?>&nbsp;</nobr></td>
               <td class="content_row"><?=date('d.m.Y',$sords[$x]["apro_fec_solicitud"])?>&nbsp;</td>
               <td class="content_row"><nobr><?=$sords[$x]["revisor"]?>&nbsp;</nobr></td>
               <td class="content_row"><?=!empty($sords[$x]["apro_fec_resolucion"]) ? date('d.m.Y', $sords[$x]["apro_fec_resolucion"]) : "" ?>&nbsp;</td>
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getAprobacionDiseñoStatus($sords[$x]["apro_estado"])?>">
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$sords[$x]["id"]}", "", "pencil");
                  ?>
               </td>
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
   </form>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
}