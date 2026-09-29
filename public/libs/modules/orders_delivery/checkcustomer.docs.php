<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");
require_once("../../../libs/functions.erp.php");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

//----------------------------------------------------------------------------------
if($_REQUEST["dlvs"] == "1")
{
   if($_REQUEST["reqid"] != "")
   {
      $sql = " select req_cust_id
               from orders
               where
               id = {$_REQUEST["reqid"]}";
      $_REQUEST["custid"] = $CON->select($sql);
      $_REQUEST["custid"] = $_REQUEST["custid"][0]["req_cust_id"];
   }
   
   $sql = " select distinct t1.dlv_num, t2.cust_name, t1.dlv_docnum, t1.dlv_delivery_date,
                   t1.dlv_crtdat, t1.dlv_status, t1.id, t9.req_number
            from orders_delivery t1
            LEFT OUTER JOIN customer t2   ON t1.dlv_cust_id = t2.id
            LEFT OUTER JOIN orders t9     ON t1.dlv_order_id = t9.id
            where
            t1.dlv_mode   <= 2 and
            t1.dlv_status IN (1,2) and
            t1.dlv_cust_id = {$_REQUEST["custid"]}
            order by t1.dlv_delivery_date desc ";
   $invoices = $CON->select($sql);

   if(count($invoices) && $invoices != false)
   {
      ?>
      <?=Nifty_printH("box1", "650")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col>
         <col>
         <col width="25">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Guias de despacho pendientes</td>
      <tr>
         <td class="content_tbl_subheader">Número interno</td>
         <td class="content_tbl_subheader">Guia</td>
         <td class="content_tbl_subheader">Fecha</td>
         <td class="content_tbl_subheader" align="center">Estado</td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($invoices) && $invoices != false; $x++)
      {
         $statimg = "";
         switch((int)$invoices[$x]["dlv_status"])
         {
            case 1: $statimg = "red_active.gif"; break;
            case 2: $statimg = "orange_active.gif"; break;
         }
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row"><?=$invoices[$x]["dlv_num"]?></td>
            <td class="content_row"><?=$invoices[$x]["dlv_docnum"]?>&nbsp;</td>
            <td class="content_row"><?=date('d.m.Y',$invoices[$x]["dlv_delivery_date"])?></td>
            <td class="content_row" align="center">
               <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getShipmentStatus($invoices[$x]["dlv_status"])?>">
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?php
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["invc"] == "1")
{
   $sql = " select distinct t1.id, t1.invc_number, t1.invc_date, t1.invc_status, t2.company_short,
                      t3.shop_name, t4.cust_name, t1.invc_estpay_date, t1.invc_docnumber
               from invoices_sell t1
               LEFT OUTER JOIN company_data t2  ON t1.invc_company_id   = t2.id
               LEFT OUTER JOIN company_shops t3 ON t1.invc_shop_id      = t3.id
               LEFT OUTER JOIN customer t4      ON t1.invc_cust_id      = t4.id
               where
               t1.invc_status IN (1,2) and
               t1.invc_cust_id = {$_REQUEST["custid"]}
               order by t1.invc_date desc";
   $invoices = $CON->select($sql);
   
   if(count($invoices) && $invoices != false)
   {
      ?>
      <?=Nifty_printH("box1", "650")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col>
         <col>
         <col width="25">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Facturas pendientes</td>
      <tr>
         <td class="content_tbl_subheader">Número interno</td>
         <td class="content_tbl_subheader">Factura</td>
         <td class="content_tbl_subheader">Fecha</td>
         <td class="content_tbl_subheader" align="center">Estado</td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($invoices) && $invoices != false; $x++)
      {
         $statimg = "";
         switch((int)$invoices[$x]["invc_status"])
         {
            case 1: $statimg = "red_active.gif"; break;
            case 2: $statimg = "orange_active.gif"; break;
         }
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row"><?=$invoices[$x]["invc_number"]?></td>
            <td class="content_row"><?=$invoices[$x]["invc_docnumber"]?>&nbsp;</td>
            <td class="content_row"><?=date('d.m.Y',$invoices[$x]["invc_date"])?></td>
            <td class="content_row" align="center">
               <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getInvoiceBuyStatus($invoices[$x]["invc_status"])?>">
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?php
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["notes"] == "1")
{
   $sql = " select distinct t1.id, t1.note_number, t1.note_date, t1.note_status, t2.company_short,
                   t3.shop_name, t4.cust_name, t1.note_estpay_date, t1.note_docnumber, t1.note_invcnumber,
                   t1.note_type
            from invoices_notes_sell t1
            LEFT OUTER JOIN company_data t2  ON t1.note_company_id   = t2.id
            LEFT OUTER JOIN company_shops t3 ON t1.note_shop_id      = t3.id
            LEFT OUTER JOIN customer t4      ON t1.note_cust_id      = t4.id
            where
            t1.note_status  IN (1,2) and
            t1.note_cust_id = {$_REQUEST["custid"]}
            order by t1.note_date desc ";
   $invoices = $CON->select($sql);

   if(count($invoices) && $invoices != false)
   {
      ?>
      <?=Nifty_printH("box1", "650")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col width="25">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="6">Notas de Credito / Debito pendientes</td>
      <tr>
         <td class="content_tbl_subheader">Tipo</td>
         <td class="content_tbl_subheader">Número interno</td>
         <td class="content_tbl_subheader">Número</td>
         <td class="content_tbl_subheader">Factura</td>
         <td class="content_tbl_subheader">Fecha</td>
         <td class="content_tbl_subheader" align="center">Estado</td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($invoices) && $invoices != false; $x++)
      {
         $statimg = "";
         switch((int)$invoices[$x]["note_status"])
         {
            case 1: $statimg = "red_active.gif"; break;
            case 2: $statimg = "orange_active.gif"; break;
         }
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row"><?=getInvoiceBuyNoteType($invoices[$x]["note_type"])?></td>
            <td class="content_row"><?=$invoices[$x]["note_number"]?></td>
            <td class="content_row"><?=$invoices[$x]["note_docnumber"]?>&nbsp;</td>
            <td class="content_row"><?=$invoices[$x]["note_invcnumber"]?>&nbsp;</td>
            <td class="content_row"><?=date('d.m.Y',$invoices[$x]["note_date"])?></td>
            <td class="content_row" align="center">
               <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getInvoiceBuyStatus($invoices[$x]["note_status"])?>">
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?php
   }
}

