<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       12.04.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
require_once("../../../libs/thirdparty/pdfClassesAndFonts/class.ezpdf.php");
//----------------------------------------------------------------------------------
require_once("../../../libs/classes/menu.php");
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");
require_once("../../../libs/functions.php");
require_once("../../../libs/functions.pdf.php");

//----------------------------------------------------------------------------------
session_start();
require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
$sql = " select *
         from stockcounts
         where
         id  = {$_REQUEST["id"]}";
$stc = $CON->select($sql);
$stc = $stc[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*, count(t2.stc_lst_posid) 'itemcount'
         from stockcounts_lists t1
         INNER JOIN stockcounts_lists_items t2 ON t1.stc_id = t2.stc_id and t1.lst_pos = t2.stc_lst_posid
         where
         t1.stc_id  = {$_REQUEST["id"]} and
         t1.lst_pos = {$_REQUEST["lstpos"]}
         group by t1.stc_id, t1.lst_pos";
$stclists = $CON->select($sql);
$stclists = $stclists[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.item_number_prod, t2.item_title, t2.id 'itemid', t3.unit_name, t4.item_code
         from stockcounts_lists_items t1
         LEFT OUTER JOIN item           t2 ON t1.item_id = t2.id
         LEFT OUTER JOIN item_units     t3 ON t2.item_unit = t3.id
         LEFT OUTER JOIN item_suppliers t4 ON ( t1.item_id = t4.item_id and t4.item_supp_act = 1 )
         where
         t1.stc_id        = {$_REQUEST["id"]} and
         t1.stc_lst_posid = {$_REQUEST["lstpos"]}
         order by t1.stc_lst_posid, t1.item_pos";
$stclistitems = $CON->select($sql);

//----------------------------------------------------------------------------------
$pdfname = date("d.m.Y")."_Recuento_inventario.pdf";

//----------------------------------------------------------------------------------
global $_LANG;
global $_sesmodulename;

//----------------------------------------------------------------------------------
header('Content-Type: application/pdf;');
header("Content-Disposition: attachment; filename={$pdfname}");

//----------------------------------------------------------------------------------
$currtme = time();

//----------------------------------------------------------------------------------
$pdf = new Cezpdf("LETTER");
$pdf->selectFont("../../../libs/thirdparty/pdfClassesAndFonts/fonts/Helvetica.afm");
$pdf->ezSetMargins(60, 35, 35, 35);

//----------------------------------------------------------------------------------
$attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
              "xpos" => "left", "showLines" => 2, "fontSize" => 7,
              "rowGap" => 2, "colGap" => 3, "cols" => Array(
              "NÚMERO"        => Array("width" => "40", "justification"   => "left"),
              "ARTÍCULO"      => Array("width" => "180", "justification"  => "left"),
              "UNIDAD"        => Array("width" => "35", "justification"   => "center"),
              "CODIGO PROV"   => Array("width" => "65",  "justification"  => "left"),
              "ACTUAL"        => Array("width" => "75",  "justification"  => "center"),
              "RES"           => Array("width" => "35",  "justification"  => "center"),
              "REAL"          => Array("width" => "35",  "justification"  => "center"),
              "OBSERVACIONES" => Array("width" => "90", "justification"  => "left")));

//----------------------------------------------------------------------------------
$counter = 0;
for($x = 0; $x < count($stclistitems) && $stclistitems != false; $x++)
{
   $shareamount = getStockShared($CON, $stclistitems[$x]["itemid"], "item", $stc["stc_shopid"]);
   
   $data[$counter]["NÚMERO"]        = $stclistitems[$x]["item_number_prod"];
   $data[$counter]["ARTÍCULO"]      = $stclistitems[$x]["item_title"];
   $data[$counter]["UNIDAD"]        = $stclistitems[$x]["unit_name"];
   $data[$counter]["CODIGO PROV"]   = $stclistitems[$x]["item_code"];
   $data[$counter]["ACTUAL"]        = printPrice($stclistitems[$x]["item_amount_stock"], 10);
   $data[$counter]["RES"]           = printPrice($shareamount, 10);
   $data[$counter]["REAL"]          = " ";
   $data[$counter]["OBSERVACIONES"] = " ";

   $counter++;
}
$pdf->ezTable($data,$type,$dummy,$attr);

//----------------------------------------------------------------------------------
$HEADER["ATTR"] = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 8,
                "showLines" => 0, "rowGap" => 1, "colGap" => 0,
                "cols" => Array (
                "x1" => Array("width" => "60", "justification" => "left"),
                "x2" => Array("width" => "215", "justification" => "left"),
                "x3" => Array("width" => "60", "justification" => "left"),
                "x4" => Array("width" => "220", "justification" => "left")));

//----------------------------------------------------------------------------------
$pcounter = 0;

//----------------------------------------------------------------------------------
$HEADER["DATA"][$pcounter]["x1"] = "<b>INVENTARIO</b>";
$HEADER["DATA"][$pcounter]["x2"] = "RECUENTO: {$stc["stc_num"]}";

//----------------------------------------------------------------------------------
$HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
$HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
$pcounter++;

//----------------------------------------------------------------------------------
$HEADER["DATA"][$pcounter]["x1"] = "<b>EMPRESA</b>";

$sql = " select *
         from company_data
         where
         id = {$stc["stc_companyid"]}";
$company = $CON->select($sql);
$company = $company[0];
$HEADER["DATA"][$pcounter]["x2"] = $company["company_short"];

//----------------------------------------------------------------------------------
$HEADER["DATA"][$pcounter]["x3"] = "<b>SUCURSAL</b>";

$sql = " select *
         from company_shops
         where
         id = {$stc["stc_shopid"]}";
$shop = $CON->select($sql);
$shop = $shop[0];
$HEADER["DATA"][$pcounter]["x4"] = $shop["shop_name"];

$pcounter++;

//----------------------------------------------------------------------------------
$HEADER["DATA"][$pcounter]["x1"] = "<b>NOMBRE</b>";
$HEADER["DATA"][$pcounter]["x2"] = "{$stclists["lst_name"]}";

//----------------------------------------------------------------------------------
$HEADER["DATA"][$pcounter]["x3"] = "<b>OBS.</b>";
$HEADER["DATA"][$pcounter]["x4"] = "{$stc["stc_annotation"]}";

$pcounter++;

if((int)$stc["stc_ubicid"])
{
   $sql = " select t1.id, t1.ubi_name, t1.ubi_crtdat
            from ubicacion t1
            where
            id = {$stc["stc_ubicid"]}";
   $ubicdesc = $CON->select($sql);
   $ubicdesc = $ubicdesc[0]["ubi_name"];

   $HEADER["DATA"][$pcounter]["x1"] = "<b>UBICACIÓN</b>";
   $HEADER["DATA"][$pcounter]["x2"] = "{$ubicdesc}";
}


//----------------------------------------------------------------------------------
$pdf = printPDFFooter($CON, $pdf, NULL, "inventory", $HEADER);

//----------------------------------------------------------------------------------
echo $pdf->output();
?>