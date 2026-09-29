<?php
//----------------------------------------------------------------------------------
// Author:        FERNANDO GARRIDO GONXS
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

   $sql = " select od.id
                 , od.dlv_num
                 , od.dlv_docnum
                 , c.cust_company
                 , od.dlv_cod_despacho
                 , od.dlv_delivery_date
               from orders_delivery od
                  inner join customer c on c.id = od.dlv_cust_id
               where od.dlv_docnum > 0
                  and od.dlv_cod_despacho in(0,1)
                  and od.dlv_status not in(3,5)
                  and od.dlv_itf_trndat > 0 ";

   $invoices = $CON->select($sql);

   if(count($invoices) && $invoices != false)
   {
      ?>
      <?=Nifty_printH("box1", "650", 0)?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col>
         <col>
         <col>
         <col width="25">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="5">Guias de despacho pendientes</td>
      <tr>
         <td class="content_tbl_subheader">Número interno</td>
         <td class="content_tbl_subheader">Guia</td>
         <td class="content_tbl_subheader">Cliente</td>
         <td class="content_tbl_subheader">Fecha</td>
         <td class="content_tbl_subheader" align="center">Estado</td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($invoices) && $invoices != false; $x++)
      {
            $statimg = "";
            switch((int)$invoices[$x]["dlv_cod_despacho"])
            {
               case 1: $statimg = "green_active.gif"; break;
               case 2: $statimg = "blue_active.gif"; break;
               case 3: $statimg = "red_active.gif"; break;
               case 4: $statimg = "purple_active.gif"; break;
            }
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row"><?=$invoices[$x]["dlv_num"]?></td>
            <td class="content_row"><?=$invoices[$x]["dlv_docnum"]?>&nbsp;</td>
            <td class="content_row"><?=$invoices[$x]["cust_company"]?>&nbsp;</td>
            <td class="content_row"><?=date('d.m.Y',$invoices[$x]["dlv_delivery_date"])?></td>
            <td class="content_row" align="center">
               <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getEstadoDeDesapacho($invoices[$x]["dlv_cod_despacho"])?>">
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
if($_REQUEST["dlvs"] == "2")
{

   if($_REQUEST["reqid"] != "")
   {
      $sql = " select req_cust_id
               from orders
               where
               id = {$_REQUEST["reqid"]}";
      $_REQUEST["custid"] = $CON->select($sql);
      $_REQUEST["custid"] = (int)$_REQUEST["custid"][0]["req_cust_id"];
   }
   
   $sql = " select od.id
                 , od.dlv_num
                 , t1.invc_docnumber
                 , c.cust_company
                 , od.dlv_cod_despacho
                 , od.dlv_status
                 , t1.invc_status
                 , dlv_delivery_date
               from orders_delivery od
                  inner join customer c on c.id = od.dlv_cust_id
                  inner join invoices_sell t1 on t1.invc_number = od.dlv_num
                  where od.dlv_cod_despacho in(0,1)
               and t1.invc_status in(0,2,3) 
               and t1.invc_docnumber != 'PENDIENTE_SII' ";


   $invoices = $CON->select($sql);

   if(count($invoices) && $invoices != false)
   {
      ?>
      <?=Nifty_printH("box1", "650", 0)?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col>
         <col>
         <col width="25">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="5">Facturas Pendientes de Despachos</td>
      <tr>
         <td class="content_tbl_subheader">Número interno</td>
         <td class="content_tbl_subheader">Guia</td>
         <td class="content_tbl_subheader">Cliente</td>
         <td class="content_tbl_subheader">Fecha</td>
         <td class="content_tbl_subheader" align="center">Estado</td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($invoices) && $invoices != false; $x++)
      {
         $statimg = "";
         switch((int)$invoices[$x]["dlv_cod_despacho"])
         {
            case 1: $statimg = "green_active.gif"; break;
            case 2: $statimg = "blue_active.gif"; break;
            case 3: $statimg = "red_active.gif"; break;
            case 4: $statimg = "purple_active.gif"; break;
         }
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row"><?=$invoices[$x]["dlv_num"]?></td>
            <td class="content_row"><?=$invoices[$x]["invc_docnumber"]?>&nbsp;</td>
            <td class="content_row"><?=$invoices[$x]["cust_company"]?>&nbsp;</td>
            <td class="content_row"><?=date('d.m.Y',$invoices[$x]["dlv_delivery_date"])?></td>
            <td class="content_row" align="center">
               <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getEstadoDeDesapacho($invoices[$x]["dlv_status"])?>">
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
