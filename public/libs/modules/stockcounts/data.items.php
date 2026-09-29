<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "add")
{
   require_once("data.storehouses.edit.php");
}
else
{
   $sql = " select t1.*, count(t2.stc_lst_posid) 'itemcount'
            from stockcounts_lists t1
            INNER JOIN stockcounts_lists_items t2 ON t1.stc_id = t2.stc_id and t1.lst_pos = t2.stc_lst_posid
            where
            t1.stc_id = {$_REQUEST["id"]}
            group by t1.stc_id, t1.lst_pos
            order by t1.lst_pos";
   $stclists = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from stockcounts t1
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];
   ?>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="60">
      <col>
      <col width="60">
      <col width="80">
      <col width="80">
      <col width="80">
      <col width="80">
      <col width="80">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="9">Resumen de listas de artículos</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader" align="center">Artículos</td>
      <td class="content_tbl_subheader" align="center">Ingresado</td>
      <td class="content_tbl_subheader" align="center">Estado</td>
      <td class="content_tbl_subheader" align="center" colspan="5">Opciones</td>
   </tr>
   <?php
   for($x = 0; $x < count($stclists) && $stclists != false; $x++)
   {
      unset($resamt);
      $sql = " select t1.item_finished, count(*) 'cc'
               from stockcounts_lists_items t1
               where
               t1.stc_id         = {$_REQUEST["id"]} and
               t1.stc_lst_posid  = {$stclists[$x]["lst_pos"]}
               group by t1.item_finished";
      $finishedamounts = $CON->select($sql);
      foreach($finishedamounts AS $finishedamount)
         $resamt[$finishedamount["item_finished"]] = $finishedamount["cc"];
      $amtperc = $resamt[1] / ($resamt[1] + $resamt[0]) * 100;
      
      $statimg = "";
      switch((int)$stclists[$x]["lst_status"])
      {
         case 1: $statimg = "red_active.gif"; break;
         case 2: $statimg = "green_active.gif"; break;
      }
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$stclists[$x]["lst_name"]?>&nbsp;</td>
         <td class="content_row" align="center"><?=(int)$stclists[$x]["itemcount"]?>&nbsp;</td>
         <td class="content_row" align="center"><?=printPrice($amtperc, 2)?> %</td>
         <td class="content_row" align="center">
            <img class="select" src="./images/content/<?=$statimg?>">
         </td>
         <?php
         if($headdata["stc_status"] == 1)
         {  ?>
            <td class="content_row" align="center" colspan="5"><b class="msg_save_err">Aprobación pendiente</b></td>
            <?php
         }
         else
         {
            if($stclists[$x]["lst_status"] == 1)
            {  ?>
               <td class="content_row" align="center">&nbsp;</td>
               <td class="content_row" align="center" colspan="2">
                  <?php
                  printButton("Imprimir", "postnav", "javascript: deactivateFormChange()", "document.all.idx_ifr_getdocx.src = './libs/modules/stockcounts/data.make.pdf.php?id={$_REQUEST["id"]}&lstpos={$stclists[$x]["lst_pos"]}'", "document-pdf");
                  ?>
               </td>
               <td class="content_row" align="center" colspan="2">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=storehouses&id={$_REQUEST["id"]}&lstpos={$stclists[$x]["lst_pos"]}", "", "pencil");
                  ?>
               </td>
               <?php
            }
            else
            {  ?>
               <td class="content_row" align="center">
                  <?php
                  printButton("Mostrar", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=storehouses&id={$_REQUEST["id"]}&lstpos={$stclists[$x]["lst_pos"]}", "", "pencil");
                  ?>
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton("Result.", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=items&id={$_REQUEST["id"]}&printStatRep=1&lst_pos={$stclists[$x]["lst_pos"]}&execPDF=1", "", "document-pdf", 80);
                  ?>
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton("Result.", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=items&id={$_REQUEST["id"]}&printStatRep=1&lst_pos={$stclists[$x]["lst_pos"]}&execExcel=1", "", "document-excel", 80);
                  ?>
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton("R/Con Stock", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=items&id={$_REQUEST["id"]}&printStatRep=1&lst_pos={$stclists[$x]["lst_pos"]}&execPDF=1&sql_stockmode=1", "", "document-pdf", 80);
                  ?>
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton("R/Con Stock", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=items&id={$_REQUEST["id"]}&printStatRep=1&lst_pos={$stclists[$x]["lst_pos"]}&execExcel=1&sql_stockmode=1", "", "document-excel", 80);
                  ?>
               </td>
               <?php
            }
         }
         ?>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="8" align="center" valign="middle" height="30">
            <b class="msg_save_err">No hay datos disponibles</b>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("boxopt_b", "980")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>&nbsp;</td>
      <td align="right" width="130">
         <?php
         if($headdata["stc_status"] == 2)
            printButton("Imprimir", "postnav", "javascript: deactivateFormChange()", "document.all.idx_ifr_getdocx.src = './libs/modules/stockcounts/data.make.ov.pdf.php?id={$_REQUEST["id"]}'", "document-pdf");
         ?>
      </td>
   </tr>
   </table>
   <?php
   if($rdlo == "")
      $_SESSION["JSEXEC"] .= "addFormListeners('form_shppos');";
   ?>
   <?=Nifty_printF(false)?>
   <iframe style="width:1px;height:1px;display:none" id="idx_ifr_getdocx" src=""></iframe>
   <?php
   if((int)$_REQUEST["printStatRep"])
   {  ?>
      <div style="display:none">
      <?php
      
      unset($_SESSION["STATS"]["stock_stockcounts"]);
      
      $_REQUEST["orderBy"] = "1";
      if($headdata["stc_order_mode"] == 2)
         $_REQUEST["orderBy"] = "2";
   
      $_SESSION["stock_stockcounts"]["orderSort"] = "asc";
      $_REQUEST["orderSort"]     = "asc";
      $_REQUEST["subexec"]       = "search";
      $_REQUEST["printpdf"]      = (int)$_REQUEST["execPDF"];
      $_REQUEST["printxls"]      = (int)$_REQUEST["execExcel"];
      $_REQUEST["sql_company"]   = $headdata["stc_companyid"];
      $_REQUEST["sql_stcmode"]   = 1;
      $_REQUEST["sql_stcnum"]    = $headdata["stc_num"];
      $_REQUEST["sql_dspmode"]   = 1;
      require_once("./libs/modules/stats/stock/item.stockcounts.php");
      ?>
      </div>
      <?php
   }
}