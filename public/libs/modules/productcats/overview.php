<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "productcats";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "3";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Prefijo" => "2", "Familia" => "3", "Creado" => "4");

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "delete")
{
   $sql = " update productcats{$_REQUEST["tbl_suffix"]}
            set cat_status = 0
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "update")
   require_once("overview.edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["sql_stext"]      = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["sql_showdels"]   = (int)$_REQUEST["sql_showdels"];
      $_SESSION[$_sesmodulename]["page"]           = 0;
      $_SESSION[$_sesmodulename]["search_active"]  = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   //----------------------------------------------------------------------------------
   $seasql = "";
   $cntsql = " select count(distinct t1.id) 'cc'
               from productcats t1
               where ";
   if((int)$_SESSION[$_sesmodulename]["sql_showdels"])
      $cntsql .= " t1.cat_status = 0 ";
   else
      $cntsql .= " t1.cat_status = 1 ";
               
   $datsql = " select distinct t1.id, t1.cat_prefix, t1.cat_title, t1.cat_crtdat
               from productcats t1
               where ";
   if((int)$_SESSION[$_sesmodulename]["sql_showdels"])
      $datsql .= " t1.cat_status = 0 ";
   else
      $datsql .= " t1.cat_status = 1 ";

   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and (t1.id = ".(int)$_SESSION[$_sesmodulename]["sql_stext"]." or
                        t1.cat_title like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' ) ";

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
   $data = $CON->select($datsql);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen de Familias</b></td>
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
         <?=Nifty_printH("box2", "980")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="100">
            <col>
            <col width="280">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="3">Opciones de búsqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Palabra</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:300px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <input type="checkbox" name="sql_showdels" value="1"
               <?if($_SESSION[$_sesmodulename]["sql_showdels"] == 1) echo "checked"?>>
               Mostrar familias eliminadas
            </td>
            <td class="content_row" align="right">
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
         <tr style="display:none">
            <td class="content_row" colspan="3" align="left">
               <?php
               printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
               $_SESSION["_SUBMITBTN"] = 1;
               ?>
            </td>
         </tr>
         </table>
         <?=Nifty_printF()?>
         </form>
      </td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   ?>
   <tr>
      <td>
         <?=Nifty_printH("box1", "980")?>
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="80">
            <col width="80">
            <col>
            <col width="100">
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($data) && $data != false; $x++)
         {
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=sprintf("%03s", $data[$x]["id"])?></td>
               <td class="content_row"><?=$data[$x]["cat_prefix"]?>&nbsp;</td>
               <td class="content_row"><?=$data[$x]["cat_title"]?>&nbsp;</td>
               <td class="content_row"><?=displayDate($data[$x]["cat_crtdat"])?></td>
               <?php
               if((int)$_SESSION[$_sesmodulename]["sql_showdels"])
               {  ?>
                  <td class="content_row" align="center">
                     <?php
                     printButton("Recuperar", "postnav", "javascript: deactivateFormChange()", "if(askDel('')){location.href='index.php?mid={$_REQUEST["mid"]}&subexec=update&id={$data[$x]["id"]}&tbl_suffix={$_REQUEST["tbl_suffix"]}&reactivate=1'}", "pencil");
                     ?>
                  </td>
                  <?php
               }
               else
               {  ?>
                  <td class="content_row" align="center">
                     <?php
                     printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&subexec=update&id={$data[$x]["id"]}&tbl_suffix={$_REQUEST["tbl_suffix"]}", "", "pencil");
                     ?>
                  </td>
                  <?php
               }
               ?>
            </tr>
            <?php
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="5" align="center">
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
   <?php
}

//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createProdCatOverview($CON, $_SESSION[$_sesmodulename]["sql_stext"], $_SESSION[$_sesmodulename]["sql_showdels"]);

if($pdffile != "")
{
   $doctitle = "Resumen-de-familias-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
?>