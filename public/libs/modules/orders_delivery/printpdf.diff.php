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
require_once("../../../libs/thirdparty/pdfClassesAndFonts/class.ezpdf.php");

//----------------------------------------------------------------------------------
global $_LANG;
global $_sesmodulename;


//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cust_name, t3.req_number, t3.req_order_shipped, t4.company_short, t5.shop_name, t8.trans_name, t2.cust_notes, t9.pay_title,
                t6.user_firstname 'upd_firstname', t6.user_lastname 'upd_lastname',
                t7.user_firstname 'crt_firstname', t7.user_lastname 'crt_lastname'
         from orders_delivery t1
         INNER JOIN customer t2              ON t1.dlv_cust_id       = t2.id
         LEFT OUTER JOIN orders t3           ON t1.dlv_order_id      = t3.id
         LEFT OUTER JOIN company_data t4     ON t1.dlv_company_id    = t4.id
         LEFT OUTER JOIN company_shops t5    ON t1.dlv_shop_id       = t5.id
         LEFT OUTER JOIN user t6             ON t1.dlv_updusr        = t6.id
         LEFT OUTER JOIN user t7             ON t1.dlv_crtusr        = t7.id
         LEFT OUTER JOIN transports t8       ON t1.dlv_transportid   = t8.id
         LEFT OUTER JOIN payments t9         ON t1.dlv_paymentid     = t9.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre
         from customer t1
         LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
         where
         t1.id = {$headdata["dlv_cust_id"]}";
$customer = $CON->select($sql);
$customer = $customer[0];

//----------------------------------------------------------------------------------
$pdf = new Cezpdf("LETTER");
$pdf->selectFont("../../../libs/thirdparty/pdfClassesAndFonts/fonts/Helvetica.afm");
$pdf->ezSetMargins(35, 35, 35, 35);

//----------------------------------------------------------------------------------
$attr = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 8,
                "showLines" => 0, "rowGap" => 1, "colGap" => 3,
                "cols" => Array (
                "x1" => Array("width" => "60", "justification" => "left"),
                "x2" => Array("width" => "265", "justification" => "left"),
                "x3" => Array("width" => "60", "justification" => "left"),
                "x4" => Array("width" => "180", "justification" => "left")));

//----------------------------------------------------------------------------------
$pcounter = 0;
$currtme  = time();

//----------------------------------------------------------------------------------
$data[$pcounter]["x1"] = "<b>GUIA</b>";
$data[$pcounter]["x2"] = "{$headdata["dlv_docnum"]}";
$data[$pcounter]["x3"] = "<b>GUIA INT</b>";
$data[$pcounter]["x4"] = "{$headdata["dlv_num"]}";
$pcounter++;

$data[$pcounter]["x1"] = "<b>CLIENTE</b>";
$data[$pcounter]["x2"] = $customer["cust_name"];
$data[$pcounter]["x3"] = "<b>RUT</b>";
$data[$pcounter]["x4"] = $customer["cust_rut"];
$pcounter++;

$data[$pcounter]["x1"] = "<b>VENTA</b>";
$data[$pcounter]["x2"] = "{$headdata["req_number"]}";
$data[$pcounter]["x3"] = "<b>FECHA</b>";
$data[$pcounter]["x4"] = displaydate($currtme);
$pcounter++;

$pdf->ezTable($data,$type,$dummy,$attr);
$pdf->ezText(" ");

unset($data);
//----------------------------------------------------------------------------------
$attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
              "xpos" => "left", "showLines" => 2, "fontSize" => 8,
              "rowGap" => 1, "colGap" => 3, "cols" => Array(
              "NÚMERO"        => Array("width" => "50", "justification"   => "left"),
              "ARTÍCULO"      => Array("width" => "210", "justification"  => "left"),
              "UNIDAD"        => Array("width" => "60", "justification"   => "center"),
              "ORDEN"         => Array("width" => "45",  "justification"  => "center"),
              "DESP"          => Array("width" => "45",  "justification"  => "center"),
              "PEND"          => Array("width" => "45", "justification"  => "center"),
              "OBSERVACIONES" => Array("width" => "110", "justification"  => "center")));

//----------------------------------------------------------------------------------
$posdata = getOrderDeliveryPos($CON, $_REQUEST["id"], "prodnumber");
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

$counter = 0;
for($x = 0; $x < $rowcount; $x++)
{
   if($posdata[$x]["item_amount"] > 0 && $posdata[$x]["item_amount_shipped"] < $posdata[$x]["item_amount"])
   {
      $data[$counter]["NÚMERO"]        = $posdata[$x]["item_number_prod"];
      $data[$counter]["ARTÍCULO"]      = $posdata[$x]["item_title"];
      $data[$counter]["UNIDAD"]        = $posdata[$x]["unit_name"];
      $data[$counter]["ORDEN"]         = printPrice($posdata[$x]["item_amount"],2);
      $data[$counter]["DESP"]          = printPrice($posdata[$x]["item_amount_shipped"],2);
      $data[$counter]["PEND"]          = printPrice($posdata[$x]["item_amount"] - $posdata[$x]["item_amount_shipped"],2);
      $data[$counter]["OBSERVACIONES"] = " ";
      $counter++;

   }
}
$pdf->ezTable($data,$type,$dummy,$attr);

//----------------------------------------------------------------------------------
//----------------------------------------------------------------------------------
$pdfname = "Borrador-Guia-{$headdata["dlv_docnum"]}.pdf";

header('Content-Type: application/pdf;');
header("Content-Disposition: attachment; filename={$pdfname}");
echo $pdf->output();
?>