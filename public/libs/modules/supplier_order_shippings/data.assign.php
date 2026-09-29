<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if((int)$_REQUEST["saveAmts"])
{
   //----------------------------------------------------------------------------------
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "contamts_") !== false && strpos($reqkey, "contamts_") == 0)
      {
         $sord_pos_id      = substr($reqkey, strrpos($reqkey, "_") +1);
         $sord_amount      = getPrice($_REQUEST[$reqkey], 2);
         $sord_kgs_amount  = 0;

         if($sord_amount > 0.00)
         {
            $sql = " select t1.*, t2.sord_type
                     from supplier_order_items t1
                     INNER JOIN supplier_order t2 ON t1.sord_id = t2.id
                     where
                     t1.id = {$sord_pos_id}";
            $sorddata = $CON->select($sql);
            $sorddata = $sorddata[0];

            if((int)$sorddata["sord_type"] == 1 || (int)$sorddata["sord_type"] == 2)
            {
               $sord_kgs_amount  = (float)$sorddata["item_kgs"];
               $amtperc          = $sord_amount / $sorddata["item_amount"] * 100;
               $sord_kgs_amount  = round($sord_kgs_amount / 100 * $amtperc);
            }

            $sql = " delete from supplier_contenedor_items
                     where
                     sord_id     = {$_REQUEST["id"]} and
                     sord_pos_id = {$sord_pos_id}";
            $CON->no_result($sql);

            $sql = " insert into supplier_contenedor_items
                     (sord_id, sord_pos_id, sord_amount, sord_kgs_amount)
                     VALUES
                     ({$_REQUEST["id"]}, {$sord_pos_id}, {$sord_amount}, {$sord_kgs_amount})";
            $CON->no_result($sql);
         }
      }
   }

   getSupplierContenedorOCStr($CON, $_REQUEST["id"]);
   ?>
   <script language="JavaScript">
      location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=basic&id=<?=$_REQUEST["id"]?>';
   </script>
   <?php
   exit;
}

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from supplier_contenedor t1
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
if($_REQUEST["sql_datefrom"] == "")
   $_REQUEST["sql_datefrom"] = date('d.m.Y', time() - (86400 * 60));
if($_REQUEST["sql_dateto"] == "")
   $_REQUEST["sql_dateto"] = date('d.m.Y');

//----------------------------------------------------------------------------------
$_REQUEST["sord_number"]         = trim(addslashes($_REQUEST["sord_number"]));
$sqldate_from                    = getDateFromString($_REQUEST["sql_datefrom"]);
$sqldate_to                      = getDateFromString($_REQUEST["sql_dateto"], false);

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.supp_short
         from supplier_order t1
         LEFT OUTER JOIN supplier t2 ON t1.sord_supplier_id = t2.id
         where
         t1.sord_status        IN (2,3,4) and
         t1.sord_taxes         = 0 and
         t1.sord_company_id    = {$headdata["sord_company_id"]} and
         t1.sord_shop_id       = {$headdata["sord_shop_id"]} and
         t1.sord_crtdat between {$sqldate_from} and {$sqldate_to} ";
         
if($_REQUEST["sord_number"] != "")
   $sql .= " and t1.sord_number like '%{$_REQUEST["sord_number"]}%' ";

$sql .= " order by t1.id desc";
$supporders = $CON->select($sql);

if($headdata["sord_status"] > 1)
{  ?>
   <div style="text-align:center;padding:10px;background-color:#CF5959;color:white;font-family:Arial;font-size:12px;text-shadow:none;width:950px">
      El contenedor ya se encuentra en transito.
   </div>
   <?php
}
else
{  ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <tr>
      <td>
         <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
         <input type="hidden" name="subexec" value="search">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
         <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
         <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
         <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
         <input type="hidden" name="saveAmts" value="">
         <?=Nifty_printH("box2", "980")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="130">
            <col>
            <col width="130">
            <col>
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="8">Opciones de búsqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Orden de compra</td>
            <td class="content_row">
               <input name="sord_number" type="text" class="text" style="width:75px"
               value="<?=str_replace("%","*",$_REQUEST["sord_number"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Periodo</td>
            <td class="content_row">
               <input type="text" style="width:70px" id="sql_datefrom" name="sql_datefrom"
               class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
               onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$_REQUEST["sql_datefrom"]?>">
               &nbsp;-&nbsp;
               <input type="text" style="width:70px" id="sql_dateto" name="sql_dateto"
               class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
               onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$_REQUEST["sql_dateto"]?>">
            </td>
            <td class="content_row" align="right">
               <table border="0" cellpadding="0" cellspacing="0" width="270">
               <tr>
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
         <?=Nifty_printH("box1", "980")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="90">
            <col>
            <col width="140">
            <col width="180">
            <col width="110">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader">OC</td>
            <td class="content_tbl_subheader">Proveedor</td>
            <td class="content_tbl_subheader">Fecha creación</td>
            <td class="content_tbl_subheader">Fecha despacho</td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($supporders) && $supporders != false; $x++)
         {
            ?>
            <tr bgcolor="<?=getRowColor(1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row" height="32"><?=$supporders[$x]["sord_number"]?>&nbsp;</td>
               <td class="content_row"><?=$supporders[$x]["supp_short"]?>&nbsp;</td>
               <td class="content_row"><?=date('d.m.Y', $supporders[$x]["sord_crtdat"])?></td>
               <td class="content_row"><?=date('d.m.Y', $supporders[$x]["sord_date"])?></td>
               <td class="content_row">
                  <input type="button" class="button" value="Ver PDF" style="width:100%"
                  onclick="document.all.xframedoc.src = './libs/modules/structure/document_file.php?type=0&id=<?=$supporders[$x]["id"]?>&hash=<?=$supporders[$x]["sord_hash"]?>.pdf&name=<?=$supporders[$x]["sord_number"]?>.pdf&path=../../../docs.supplierorder/'">
               </td>
            </tr>
            <?php
            $posdata = getSupplierOrderPos($CON, $supporders[$x]["id"]);
            for($y = 0; $y < count($posdata) && $posdata != false; $y++)
            {
               $posstat = getSupplierContenedorItemState($CON, $supporders[$x]["id"], $posdata[$y]["id"], $_REQUEST["id"]);
               $pospend = $posdata[$y]["item_amount"] - $posstat;
               ?>
               <tr bgcolor="<?=getRowColor(0)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row" colspan="2">
                     <img src="./images/menu/icons/arrow-turn-000-left.png" style="vertical-align:bottom">
                     <?=$posdata[$y]["item_title"]?>&nbsp;
                  </td>
                  <td class="content_row">Monto: <?=printPrice($posdata[$y]["item_costprice_netto_dsc"],2)?></td>
                  <td class="content_row">
                     Total: <?=printPrice($posdata[$y]["item_amount"],2)?>,
                     Pend: <?=printPrice($pospend,2)?>
                  </td>
                  <td class="content_row" align="center">
                     <input type="checkbox" onclick="$('#contamts_<?=$posdata[$y]["id"]?>').val(''); if(this.checked) $('#contamts_<?=$posdata[$y]["id"]?>').val('<?=printPrice($pospend,2)?>');">
                     <input type="text" class="text" style="width:80px;text-align:center;<?php if($pospend <= 0) echo "background-color:#FFDEDE"?>"
                     name="contamts_<?=$posdata[$y]["id"]?>" id="contamts_<?=$posdata[$y]["id"]?>">
                  </td>
               </tr>
               <?php
            }
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
         <?=Nifty_printF()?>
         <br>
         <?php
         if(count($supporders) && $supporders != false)
         {  ?>
            <?=Nifty_printH("boxopt_b", "980")?>
            <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr>
               <td>&nbsp;</td>
               <td align="right" width="130">
                  <?php
                  printButton("Agregar cantidades al contenedor", "postnav_save", "javascript: deactivateFormChange()", "document.xform_itemsearch.saveAmts.value='1';submitForm(document.xform_itemsearch);", "tick-circle-frame", 250);            ?>
               </td>
            </tr>
            </table>
            <?=Nifty_printF(false)?>
            <?php
         }
         ?>
      </td>
   </tr>
   </table>
   </form>
   <iframe height="0" width="0" frameborder="0" src="" id="xframedoc" name="xframedoc"></iframe>
   <?php
}