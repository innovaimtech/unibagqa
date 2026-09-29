<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "parametros";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Tabla" => "2","Descripcion" => "3");

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if($_REQUEST["exec"] == "edit")
   require_once("data.basic.tabla.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["tabla"]          = $_REQUEST["tabla"];
      $_SESSION[$_sesmodulename]["sql_stext"]      = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["page"]           = 0;
      $_SESSION[$_sesmodulename]["search_active"]  = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
   {
      $currtme = time();

      $sql = " select count(*) as contador from parametros where tabla = {$_REQUEST["tabla"]}";
      $val = $CON->no_result($sql);
      if( $val[0]["contador"] > 0)
      {
         $savemsg = "<b class='msg_save_err'>Tabla contiene registros, no puede ser eliminada</b>";
      }
      else
      {
         $sql = " delete from parametros where id = {$_REQUEST["id"]} ";
         $res = $CON->no_result($sql);
         $savemsg = getSaveMessage($res);
      }
   }

   //----------------------------------------------------------------------------------
   $cntsql = " select count(distinct t1.id) 'cc'
               from parametros t1";
               
   $datsql = " select t1.id, t1.tabla, t1.codigo, t1.descripcion
               from parametros t1 
               where t1.tabla = 'TABLA' ";
               
   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["tabla"])
      $seasql .= " and t1.codigo = '{$_SESSION[$_sesmodulename]["tabla"]}' ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and (t1.descripcion like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%') ";
   
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
     
   $parametros  = $CON->select($datsql);

   //----------------------------------------------------------------------------------
   // Carga tablas registradas
   
   $sql = "select id
             ,codigo as tabla
             ,Descripcion
           from parametros
              where tabla = 'TABLA'
              order by tabla";
   $tablas = $CON->select($sql);
     
   //----------------------------------------------------------------------------------
  
   ?>
   <script language="JavaScript">
   <?php
   ?>
   </script>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Resumen de Tablas</b></td>
      <td align="right"><?=$savemsg?></td>
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
         <?=Nifty_printH("box2", "822")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="100">
            <col width="100">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Opciones de Busqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Tabla</td>
            <td class="content_row">
               <select class="text" style="width:280px" name="tabla" id="tabla" 
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                     foreach($tablas as $tabla)
                     {
                        ?>
                           <option value="<?=$tabla["tabla"]?>"
                           <?php if($tabla["tabla"] == $_SESSION[$_sesmodulename]["tabla"]) echo "selected"?>><?=$tabla["Descripcion"]?></option>
                        <?php
                     }
                  ?>
               </select>
            </td>
         </tr>   
         <tr>
            <td class="content_rowl">Descripcion</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:280px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr>
            <td class="content_row" align="left" colspan="2">
               <table border="0" cellpadding="0" cellspacing="0" width="270">
               <tr>
                  <td align="left">
                     <?php
                     printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                     ?>
                  </td>
                  <td align="left">
                     <?php
                     if((int)$_SESSION[$_sesmodulename]["search_active"])
                        printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
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
   <?=Nifty_printH("box1", "822")?>
   <?php
   /*printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);*/
   ?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="50">
      <col width="100">
      <col width="50">
      <col width="50">
   </colgroup>
   <tr>
      <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
      <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
      <td class="content_tbl_subheader" align="center" colspan="2"><?=$_LANG["MODULE"]["CUST"][40]?></td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($parametros) && $parametros != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><nobr><?=$parametros[$x]["codigo"]?></nobr></td>
         <td class="content_row"><nobr><?=$parametros[$x]["descripcion"]?></nobr></td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$parametros[$x]["id"]}", "", "pencil");
            ?>
         </td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$parametros[$x]["id"]}')", "cross-circle-frame");
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
   /* printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]); */
   ?>
   <?=Nifty_printF()?>
   <?=Nifty_printH("box1", "822")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
            <col width="100">
            <col width="100">
      </colgroup>
      <tr>
         <td class="content_row" align="left" colspan="2">
            <table border="0" cellpadding="0" cellspacing="0" width="270">
               <tr>
                  <td align="left">
                     <?php
                     printButton("Nuevo", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id=0", "", "plus");
                     ?>
                  </td>
               </tr>
            </table>
         </td>
      </tr>
   </table>
   <?=Nifty_printF()?>
   <?php
}
?>