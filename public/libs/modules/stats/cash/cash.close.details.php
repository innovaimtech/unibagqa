<?php
$_sesmodulename = "cash_close_detail";
unset($_SESSION["STATS"]);

//----------------------------------------------------------------------------------
/*
if($_REQUEST["mode"] == "" && $_SESSION["_INITMODE"] == "ADMIN")
{
   $_MODIFYACT = true;
   $admchg_fancy_params = "/iframe.fancy.php?module=admin_cash_change&id={$_REQUEST["id"]}";
   $admchg_fancy_jspre  = "showFancybox('{$admchg_fancy_params}";
   $admchg_fancy_jssuf  = "', 'iframe', 500, 365, 'false');";
}
*/

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "general")
{
   $sql = " select t1.*
            from cash_close_comments t1
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $startdate = mktime(0, 0, 0, date('m', $headdata["cash_date_close"]), date('d', $headdata["cash_date_close"]), date('Y', $headdata["cash_date_close"]));
   $enddate   = mktime(23, 59, 59, date('m', $headdata["cash_date_close"]), date('d', $headdata["cash_date_close"]), date('Y', $headdata["cash_date_close"]));

   $sql = " select *
            from cash_close_comments
            where
            cash_date_close between {$startdate} and {$enddate} and
            cash_date_close > 0
            order by id asc";
   $closeids = $CON->select($sql);

   $headdata["cash_gastos_pay"]  = 0.00;
   $headdata["cash_gastos_desc"] = "";
   foreach($closeids AS $closeid)
   {
      $_CLOSEIDS .= $closeid["id"].",";
      $_FOLIOS   .= sprintf("%05s", $closeid["id"]).", ";
      
      $headdata["cash_gastos_pay"] += $closeid["cash_gastos_pay"];
      if(trim($closeid["cash_gastos_desc"]) != "")
         $headdata["cash_gastos_desc"] .= $closeid["cash_gastos_desc"]."\n";
   }
   $headdata["cash_gastos_desc"] = trim($headdata["cash_gastos_desc"]);
   $_CLOSEIDS  = substr($_CLOSEIDS, 0, -1);
   $_FOLIOS    = substr($_FOLIOS, 0, -2);
}
//----------------------------------------------------------------------------------
else
{
   $_CLOSEIDS = $_REQUEST["id"];

   $sql = " select t1.*, t2.user_firstname, t2.user_lastname
            from cash_close_comments t1
            LEFT OUTER JOIN user t2                   ON t1.cash_user_id = t2.id
            where
            t1.id IN ({$_CLOSEIDS})";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $startdate = mktime(0, 0, 0, date('m', $headdata["cash_date_close"]), date('d', $headdata["cash_date_close"]), date('Y', $headdata["cash_date_close"]));
   $enddate   = mktime(23, 59, 59, date('m', $headdata["cash_date_close"]), date('d', $headdata["cash_date_close"]), date('Y', $headdata["cash_date_close"]));

   $_FOLIOS = sprintf("%05s", $headdata["id"]);
}

if(date("d.m.Y") == date("d.m.Y", $headdata["cash_date_close"]))
   $_CANDELETE = true;

$sql = " select t1.cash_payment_id, t2.pay_title, SUM(t1.cash_close_calcvalue) 'cash_close_calcvalue',
                SUM(t1.cash_close_real_value) 'cash_close_real_value', SUM(t1.cash_close_diff_value) 'cash_close_diff_value'
         from cash_close_differences t1
         INNER JOIN payments t2 ON t1.cash_payment_id = t2.id
         where
         t1.close_id IN ({$_CLOSEIDS}) and t1.cash_payment_id != 9
         group by 1,2
         order by t2.pay_title";
$payments = $CON->select($sql);

$state = "Finalizado";
$scss  = "#47AE47";
if(!(int)$headdata["cash_date_close"])
{
   $state = "Abierto";
   $scss  = "#E33939";
}

//----------------------------------------------------------------------------------
$sql = " select shop_name
         from company_shops
         where
         id = {$headdata["cash_shop_id"]}";
$shop_name = $CON->select($sql);
$shop_name = $shop_name[0]["shop_name"];

//----------------------------------------------------------------------------------
$sql = " select SUM(bill_amount1) 'bill_amount1', SUM(bill_amount2) 'bill_amount2',
                SUM(bill_amount3) 'bill_amount3', SUM(bill_amount4) 'bill_amount4',
                SUM(bill_amount5) 'bill_amount5', SUM(bill_amount6) 'bill_amount6',
                SUM(bill_value1) 'bill_value1', SUM(bill_value2) 'bill_value2',
                SUM(bill_value3) 'bill_value3', SUM(bill_value4) 'bill_value4',
                SUM(bill_value5) 'bill_value5', SUM(bill_value6) 'bill_value6',
                SUM(bill_total) 'bill_total'
         from cash_close_money
         where
         close_id IN ({$_CLOSEIDS})";
$money = $CON->select($sql);
$money = $money[0];
?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td class="aula_content_module">
      <?=Nifty_printH("box2", "980")?>
      <table cellpadding="6" cellspacing="0" width="100%" border="0" class="aula_listtbl">
      <colgroup>
         <col width="125">
         <col width="340">
         <col width="125">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Cierre de caja</td>
      </tr>
      <tr>
         <td class="content_rowl">Folio</td>
         <td class="content_row"><?=$_FOLIOS?></td>
         <td class="content_rowl">Estado</td>
         <td class="content_row" style="color:<?=$scss?>"><?=$state?></td>
      </tr>
      <?php
      if($_REQUEST["mode"] == "")
      {  ?>
         <tr>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row"><?=$shop_name?>&nbsp;</td>
            <td class="content_rowl">Cajero</td>
            <td class="content_row"><?=$headdata["user_firstname"]?>&nbsp;<?=$headdata["user_lastname"]?></td>
         </tr>
         <tr>
            <td class="content_rowl">Apertura</td>
            <td class="content_row"><?=displayDate($headdata["cash_date"])?></td>
            <td class="content_rowl">Cierre</td>
            <td class="content_row"><?=displayDate($headdata["cash_date_close"])?></td>
         </tr>
            <td class="content_rowl">Comentario apertura</td>
            <td class="content_row"><?=$headdata["cash_open_comments"]?>&nbsp;</td>
            <td class="content_rowl">Comentario cierre</td>
            <td class="content_row"><?=$headdata["cash_close_comments"]?>&nbsp;</td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <table cellpadding="0" cellspacing="0" width="980" border="0" style="margin-top:20px">
      <colgroup>
         <col width="450">
         <col width="15">
         <col>
      </colgroup>
      <tr>
         <td valign="top">
            <?=Nifty_printH("box2", "450")?>
            <table cellpadding="6" cellspacing="0" width="100%" border="0" class="aula_listtbl" style="margin-top:0px">
            <colgroup>
               <col>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <col width="20">
                  <?php
               }
               ?>
               <col width="70">
               <col width="70">
               <col width="70">
            </colgroup>
            <tr>
               <td class="content_tbl_header">Medio de pago</td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_tbl_header" align="center">&nbsp;</td>
                  <?php
               }
               ?>
               <td class="content_tbl_header" align="right">Sistema</td>
               <td class="content_tbl_header" align="right">Cajero</td>
               <td class="content_tbl_header" align="right">Diferencia</td>
            </tr>
            <?php
            $x = 0;
            $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val1"] = "FOLIO";
            $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val2"] = $_FOLIOS;
            $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val3"] = "ESTADO";
            $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val4"] = $state;
            $x++;
            if($_REQUEST["mode"] == "")
            {
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val1"] = "SUCURSAL";
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val2"] = $shop_name;
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val3"] = "CAJERO";
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val4"] = $headdata["user_firstname"]." ".$headdata["user_lastname"];
               $x++;
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val1"] = "APERTURA";
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val2"] = displayDate($headdata["cash_date"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val3"] = "CIERRE";
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val4"] = displayDate($headdata["cash_date_close"]);
               $x++;
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val1"] = "COMENTARIO APERTURA";
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val2"] = $headdata["cash_open_comments"];
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val3"] = "COMENTARIO CIERRE";
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val4"] = $headdata["cash_close_comments"];
               $x++;
            }
            else
            {
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val1"] = "CIERRE";
               $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val2"] = date('d.m.Y', $headdata["cash_date_close"]);
               $x++;
            }

            $x = 0;
            foreach($payments AS $payment)
            {  ?>
               <tr>
                  <td class="content_rowl"><?=$payment["pay_title"]?></td>
                  <?php
                  if($_MODIFYACT && $payment["cash_payment_id"] == 1)
                  {  ?>
                     <td class="content_row_osl" align="center">&nbsp;</td>
                     <?php
                  }
                  elseif($_MODIFYACT && $payment["cash_payment_id"] != 1)
                  {  ?>
                     <td class="content_row_osl" align="center">
                        <?php
                        printButtonMicroSmall("", "postnav_blue", "javascript:void(0)", $admchg_fancy_jspre."&exec=payment&payid={$payment["cash_payment_id"]}".$admchg_fancy_jssuf, "fa-pencil", 20);
                        ?>
                     </td>
                     <?php
                  }
                  ?>
                  <td class="content_row" align="right"><?=printPrice($payment["cash_close_calcvalue"])?></td>
                  <td class="content_row" align="right"><?=printPrice($payment["cash_close_real_value"])?></td>
                  <td class="content_row" align="right"><?=printPrice($payment["cash_close_diff_value"])?></td>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val1"] = $payment["pay_title"];
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val2"] = printPrice($payment["cash_close_calcvalue"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val3"] = printPrice($payment["cash_close_real_value"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val4"] = printPrice($payment["cash_close_diff_value"]);
               $x++;

               $calc_total += $payment["cash_close_calcvalue"];
               $calc_real  += $payment["cash_close_real_value"];
               $calc_diff  += $payment["cash_close_diff_value"];
            }

            $calc_real += $headdata["cash_gastos_pay"];
            $calc_diff += $headdata["cash_gastos_pay"];

            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val1"] = "GASTOS\n{$headdata["cash_gastos_desc"]}";
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val2"] = printPrice(0);
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val3"] = printPrice($headdata["cash_gastos_pay"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val4"] = printPrice($headdata["cash_gastos_pay"]);
            $x++;
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val1"] = "TOTAL";
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val2"] = printPrice($calc_total);
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val3"] = printPrice($calc_real);
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val4"] = printPrice($calc_diff);
            $x++;
            ?>
            <tr>
               <td class="content_rowl" valign="top">GASTOS<br><?=nl2br($headdata["cash_gastos_desc"])?></td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_row_osl" align="center">
                     <?php
                     printButtonMicroSmall("", "postnav_blue", "javascript:void(0)", "showFancybox('/iframe.fancy.php?module=admin_cash_change_gastos&id={$_REQUEST["id"]}', 'iframe', 500, 365, 'false');", "fa-pencil", 20);
                     ?>
                  </td>
                  <?php
               }
               ?>
               <td class="content_row" valign="top" align="right">0</td>
               <td class="content_row" valign="top" align="right"><?=printPrice($headdata["cash_gastos_pay"])?></td>
               <td class="content_row" valign="top" align="right"><?=printPrice($headdata["cash_gastos_pay"])?></td>
            </tr>
            <tr>
               <td class="content_rowl"><b>TOTAL</b></td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_row_osl" align="center">&nbsp;</td>
                  <?php
               }
               ?>
               <td class="content_row content_tbl_subheader" align="right"><b><?=printPrice($calc_total)?></b></td>
               <td class="content_row content_tbl_subheader" align="right"><b><?=printPrice($calc_real)?></b></td>
               <td class="content_row content_tbl_subheader" align="right"><b><?=printPrice($calc_diff)?></b></td>
            </tr>
            <tr>
               <td class="content_rowl"><b>FONDO INICIAL</b></td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_row_osl" align="center">&nbsp;</td>
                  <?php
               }
               ?>
               <td class="content_row content_tbl_subheader" align="right"><b>&nbsp;</b></td>
               <td class="content_row content_tbl_subheader" align="right"><b><?=printPrice($headdata["cash_open_amount"])?></b></td>
               <td class="content_row content_tbl_subheader" align="right"><b>&nbsp;</b></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val1"] = "FONDO INICIAL";
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val2"] = "";
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val3"] = printPrice($headdata["cash_open_amount"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val4"] = "";
            $x++;
            ?>
            <tr>
               <td class="content_rowl"><b>DEPOSITO CAJA FUERTE</b></td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_row_osl" align="center">&nbsp;</td>
                  <?php
               }
               ?>
               <td class="content_row content_tbl_subheader" align="right"><b>&nbsp;</b></td>
               <td class="content_row content_tbl_subheader" align="right"><b><?=printPrice($headdata["cash_cajafuerte_amount"])?></b></td>
               <td class="content_row content_tbl_subheader" align="right"><b>&nbsp;</b></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val1"] = "DEPOSITO CAJA FUERTE";
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val2"] = "";
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val3"] = printPrice($headdata["cash_cajafuerte_amount"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val4"] = "";
            $x++;
            ?>
            <tr>
               <td class="content_rowl"><b>DEPOSITO VENTA DISTRIBUIDOR</b></td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_row_osl" align="center">&nbsp;</td>
                  <?php
               }
               ?>
               <td class="content_row content_tbl_subheader" align="right"><b>&nbsp;</b></td>
               <td class="content_row content_tbl_subheader" align="right"><b><?=printPrice($headdata["cash_distrib_amount"])?></b></td>
               <td class="content_row content_tbl_subheader" align="right"><b>&nbsp;</b></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val1"] = "DEPOSITO VENTA DISTRIBUIDOR";
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val2"] = "";
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val3"] = printPrice($headdata["cash_distrib_amount"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x]["val4"] = "";
            $x++;
            ?>
            </table>
            <?=Nifty_printF()?>
            <br>
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td align="center" width="33%" style="padding-right:5px">
                  <?php
                  printButton("Imprimir XLS", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&id={$_REQUEST["id"]}&exec=details&printxls=1&mode={$_REQUEST["mode"]}", "", "document-excel", "100%");
                  ?>
               </td>
               <td align="center" width="34%" style="padding-right:5px">
                  <?php
                  printButton("Imprimir PDF", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&id={$_REQUEST["id"]}&exec=details&printpdf=1&mode={$_REQUEST["mode"]}", "", "document-pdf", "100%");
                  ?>
               </td>
            </tr>
            </table>
         </td>
         <td>&nbsp;</td>
         <td valign="top">
            <?php
            $money_calc = $money["bill_value1"] + $money["bill_value2"] + $money["bill_value3"] + $money["bill_value4"] + $money["bill_value5"] + $money["bill_value6"];
            ?>
            <?=Nifty_printH("box2", "100%")?>
            <table cellpadding="6" cellspacing="0" width="100%" border="0" class="aula_listtbl" style="margin-top:0px">
            <colgroup>
               <col>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <col width="20">
                  <?php
               }
               ?>
               <col width="105">
            </colgroup>
            <tr>
               <td class="content_tbl_header">Efectivo</td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_tbl_header" align="center">&nbsp;</td>
                  <?php
               }
               ?>
               <td class="content_tbl_header" align="center">Cantidad</td>
               <td class="content_tbl_header" align="right">Monto</td>
            </tr>
            <tr>
               <td class="content_rowl">Billetes de 20.000</td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_rowl" align="center">
                     <?php
                     printButtonMicroSmall("", "postnav_blue", "javascript:void(0)", $admchg_fancy_jspre."&exec=bill&btype=bill_amount1".$admchg_fancy_jssuf, "fa-pencil", 20);
                     ?>
                  </td>
                  <?php
               }
               ?>
               <td class="content_row" align="center"><?=printPrice($money["bill_amount1"])?></td>
               <td class="content_row" align="right"><?=printPrice($money["bill_value1"])?></td>
            </tr>
            <tr>
               <td class="content_rowl">Billetes de 10.000</td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_rowl" align="center">
                     <?php
                     printButtonMicroSmall("", "postnav_blue", "javascript:void(0)", $admchg_fancy_jspre."&exec=bill&btype=bill_amount2".$admchg_fancy_jssuf, "fa-pencil", 20);
                     ?>
                  </td>
                  <?php
               }
               ?>
               <td class="content_row" align="center"><?=printPrice($money["bill_amount2"])?></td>
               <td class="content_row" align="right"><?=printPrice($money["bill_value2"])?></td>
            </tr>
            <tr>
               <td class="content_rowl">Billetes de 5.000</td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_tbl_subheader" align="center">
                     <?php
                     printButtonMicroSmall("", "postnav_blue", "javascript:void(0)", $admchg_fancy_jspre."&exec=bill&btype=bill_amount3".$admchg_fancy_jssuf, "fa-pencil", 20);
                     ?>
                  </td>
                  <?php
               }
               ?>
               <td class="content_row" align="center"><?=printPrice($money["bill_amount3"])?></td>
               <td class="content_row" align="right"><?=printPrice($money["bill_value3"])?></td>
            </tr>
            <tr>
               <td class="content_rowl">Billetes de 2.000</td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_tbl_subheader" align="center">
                     <?php
                     printButtonMicroSmall("", "postnav_blue", "javascript:void(0)", $admchg_fancy_jspre."&exec=bill&btype=bill_amount4".$admchg_fancy_jssuf, "fa-pencil", 20);
                     ?>
                  </td>
                  <?php
               }
               ?>
               <td class="content_row" align="center"><?=printPrice($money["bill_amount4"])?></td>
               <td class="content_row" align="right"><?=printPrice($money["bill_value4"])?></td>
            </tr>
            <tr>
               <td class="content_rowl">Billetes de 1.000</td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_tbl_subheader" align="center">
                     <?php
                     printButtonMicroSmall("", "postnav_blue", "javascript:void(0)", $admchg_fancy_jspre."&exec=bill&btype=bill_amount5".$admchg_fancy_jssuf, "fa-pencil", 20);
                     ?>
                  </td>
                  <?php
               }
               ?>
               <td class="content_row" align="center"><?=printPrice($money["bill_amount5"])?></td>
               <td class="content_row" align="right"><?=printPrice($money["bill_value5"])?></td>
            </tr>
            <tr>
               <td class="content_rowl">Monedas</td>
               <?php
               if($_MODIFYACT)
               {  ?>
                  <td class="content_tbl_subheader" align="center">
                     <?php
                     printButtonMicroSmall("", "postnav_blue", "javascript:void(0)", $admchg_fancy_jspre."&exec=bill&btype=bill_value6".$admchg_fancy_jssuf, "fa-pencil", 20);
                     ?>
                  </td>
                  <?php
               }
               ?>
               <td class="content_rowl" align="center">&nbsp;</td>
               <td class="content_row" align="right"><?=printPrice($money["bill_value6"])?></td>
            </tr>
            <tr>
               <td class="content_rowl content_tbl_subheader" colspan="<?php if($_MODIFYACT) echo "3"; else echo "2"?>"><b>TOTAL</b></td>
               <td class="content_row content_tbl_subheader" align="right"><b><?=printPrice($money_calc)?></b></td>
            </tr>
            </table>
            <?=Nifty_printF()?>
            <?php
            $x = 0;
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val1"] = "Billetes de 20.000";
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val2"] = printPrice($money["bill_amount1"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val3"] = printPrice($money["bill_value1"]);
            $x++;
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val1"] = "Billetes de 10.000";
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val2"] = printPrice($money["bill_amount2"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val3"] = printPrice($money["bill_value2"]);
            $x++;
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val1"] = "Billetes de 5.000";
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val2"] = printPrice($money["bill_amount3"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val3"] = printPrice($money["bill_value3"]);
            $x++;
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val1"] = "Billetes de 2.000";
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val2"] = printPrice($money["bill_amount4"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val3"] = printPrice($money["bill_value4"]);
            $x++;
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val1"] = "Billetes de 1.000";
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val2"] = printPrice($money["bill_amount5"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val3"] = printPrice($money["bill_value5"]);
            $x++;
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val1"] = "Monedas";
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val2"] = " ";
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val3"] = printPrice($money["bill_value6"]);
            $x++;
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val1"] = "TOTAL";
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val2"] = " ";
            $_SESSION["STATS"][$_sesmodulename]["DATA3"][$x]["val3"] = printPrice($money_calc);
            $x++;

            $doctype_calc = ($_BOLETA_PAY_TOTAL + $_FACTURA_PAY_TOTAL + $_FACTURA_PAY_OTHERTOTAL - $_NOTAS_PAY_TOTAL) - $_PAY_BONUS_OLD;

            if($_CANDELETE && $_REQUEST["mid"] == 1014)
            {
               echo "<br>";
               printButton($_LANG["FORM"]["BUTTON"][2]." cierre de caja", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
            }
            ?>
         </td>
      </tr>
      </table>
   </td>
</tr>
</table>
<br><br><br>
<?php
//----------------------------------------------------------------------------------
if((int)$_REQUEST["printxls"])
  $xlsfile = xls_createStatsCashClose($CON);

//----------------------------------------------------------------------------------
if((int)$_REQUEST["printpdf"])
  $pdffile = doc_createStatsCashClose($CON);

if($xlsfile != "")
{
   $doctitle = "Detalle-Cierre-Caja-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
if($pdffile != "")
{
   $doctitle = "Detalle-Cierre-Caja-".time().".pdf";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>