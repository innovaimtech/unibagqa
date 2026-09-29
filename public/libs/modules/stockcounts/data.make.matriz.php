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

session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
unset($_SESSION["_CONF"]);

$sql = " select *
         from config_system";
$conf = $CON->select($sql);
$conf = $conf[0];
foreach(array_keys($conf) AS $ckey)
   $_SESSION["_CONF"][$ckey] = trim($conf[$ckey]);

$sql = " select lang_filename
         from config_lang
         where
         id = {$conf["conf_lang"]}";
$lang = $CON->select($sql);
$_SESSION["_CONF"]["conf_lang_filename"] = $lang[0]["lang_filename"];

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

if($_REQUEST["mode"] == "preview")
{  ?>
   <html>
   <head>
      <title>Prevista impresión</title>
      <style type="text/css">
      html { height: 100%; }
      <?php $_SESSION["_PAGE"]->printStyle() ?>
      </style>
   </head>
   <body class="page_content" style="background-color:#EEEEEE">
   <center>
   <br>
   <?=Nifty_printH("box1", "860")?>
   <table border="0" cellpadding="3" cellspacing="0" width="100%">
   <tr>
      <td class="content_tbl_header">Prevista impresión</td>
   </tr>
   <tr>
      <td class="content_row_clear"><pre><font style="font-size:10px"><br><?php
}

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
$sql = " select *
         from company_data
         where
         id = {$stc["stc_companyid"]}";
$company = $CON->select($sql);
$company = $company[0];
$sql = " select *
         from company_shops
         where
         id = {$stc["stc_shopid"]}";
$shop = $CON->select($sql);
$shop = $shop[0];

//----------------------------------------------------------------------------------
$x = 0;
$headerposx1   = 2;
$headerwidth1  = 9;
$headerposx2   = 12;
$headerwidth2  = 53;
$headerposx3   = 66;
$headerwidth3  = 11;
$headerposx4   = 78;
$headerwidth4  = 40;

$currtme = time();
$data[$x]["COL1"]["DATA"]     = "RECUENTO";
$data[$x]["COL1"]["XPOS"]     = $headerposx1;
$data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
$data[$x]["COL2"]["DATA"]     = $stc["stc_num"];
$data[$x]["COL2"]["XPOS"]     = $headerposx2;
$data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
$data[$x]["COL3"]["DATA"]     = "FECHA";
$data[$x]["COL3"]["XPOS"]     = $headerposx3;
$data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
$data[$x]["COL4"]["DATA"]     = displaydate($currtme);
$data[$x]["COL4"]["XPOS"]     = $headerposx4;
$data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
$x++;
$data[$x]["COL1"]["DATA"]     = "NOMBRE";
$data[$x]["COL1"]["XPOS"]     = $headerposx1;
$data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
$data[$x]["COL2"]["DATA"]     = "{$stclists["lst_name"]}";
$data[$x]["COL2"]["XPOS"]     = $headerposx2;
$data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
$data[$x]["COL3"]["DATA"]     = "ARTICULOS";
$data[$x]["COL3"]["XPOS"]     = $headerposx3;
$data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
$data[$x]["COL4"]["DATA"]     = "{$stclists["itemcount"]}";
$data[$x]["COL4"]["XPOS"]     = $headerposx4;
$data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
$x++;
$data[$x]["COL1"]["DATA"]     = "EMPRESA";
$data[$x]["COL1"]["XPOS"]     = $headerposx1;
$data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
$data[$x]["COL2"]["DATA"]     = $company["company_short"];
$data[$x]["COL2"]["XPOS"]     = $headerposx2;
$data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
$data[$x]["COL3"]["DATA"]     = "SUCURSAL";
$data[$x]["COL3"]["XPOS"]     = $headerposx3;
$data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
$data[$x]["COL4"]["DATA"]     = $shop["shop_name"];
$data[$x]["COL4"]["XPOS"]     = $headerposx4;
$data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
$x++;

//----------------------------------------------------------------------------------
// ITEMS
//----------------------------------------------------------------------------------
$itemposx1     = 2;
$itemwidth1    = 9;
$itemposx2     = 12;
$itemwidth2    = 45;
$itemposx3     = 48;
$itemwidth3    = 9;
$itemposx4     = 58;
$itemwidth4    = 19;
$itemposx5     = 80;
$itemwidth5    = 8;
$itemposx6     = 89;
$itemwidth6    = 7;
$itemposx7     = 97;
$itemwidth7    = 25;

//----------------------------------------------------------------------------------
$data[$x]["COL1"]["DATA"]     = " ";
$x++;
$data[$x]["COL1"]["DATA"]     = "NUMERO";
$data[$x]["COL1"]["XPOS"]     = $itemposx1;
$data[$x]["COL1"]["WIDTH"]    = $itemwidth1;
$data[$x]["COL2"]["DATA"]     = "ARTICULO";
$data[$x]["COL2"]["XPOS"]     = $itemposx2;
$data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
$data[$x]["COL3"]["DATA"]     = "UNIDAD";
$data[$x]["COL3"]["XPOS"]     = $itemposx3;
$data[$x]["COL3"]["WIDTH"]    = $itemwidth3;
$data[$x]["COL4"]["DATA"]     = "CODIGO PROV";
$data[$x]["COL4"]["XPOS"]     = $itemposx4;
$data[$x]["COL4"]["WIDTH"]    = $itemwidth4;
$data[$x]["COL5"]["DATA"]     = "ACTUAL";
$data[$x]["COL5"]["XPOS"]     = $itemposx5;
$data[$x]["COL5"]["WIDTH"]    = $itemwidth5;
$data[$x]["COL6"]["DATA"]     = "REAL";
$data[$x]["COL6"]["XPOS"]     = $itemposx6;
$data[$x]["COL6"]["WIDTH"]    = $itemwidth6;
$data[$x]["COL7"]["DATA"]     = "OBSERVACIONES";
$data[$x]["COL7"]["XPOS"]     = $itemposx7;
$data[$x]["COL7"]["WIDTH"]    = $itemwidth7;
$counter = $x;
for($x = 0; $x < count($stclistitems) && $stclistitems != false; $x++)
{
   $data[$counter]["COL1"]["DATA"]     = $stclistitems[$x]["item_number_prod"];
   $data[$counter]["COL1"]["XPOS"]     = $itemposx1;
   $data[$counter]["COL1"]["WIDTH"]    = $itemwidth1;

   $data[$counter]["COL2"]["DATA"]     = $stclistitems[$x]["item_title"];
   $data[$counter]["COL2"]["XPOS"]     = $itemposx2;
   $data[$counter]["COL2"]["WIDTH"]    = $itemwidth2;

   $data[$counter]["COL3"]["DATA"]     = $stclistitems[$x]["unit_name"];
   $data[$counter]["COL3"]["XPOS"]     = $itemposx3;
   $data[$counter]["COL3"]["WIDTH"]    = $itemwidth3;

   $data[$counter]["COL4"]["DATA"]     = $stclistitems[$x]["item_code"];
   $data[$counter]["COL4"]["XPOS"]     = $itemposx4;
   $data[$counter]["COL4"]["WIDTH"]    = $itemwidth4;

   $data[$counter]["COL5"]["DATA"]     = printPrice($stclistitems[$x]["item_amount_stock"], 2);
   $data[$counter]["COL5"]["XPOS"]     = $itemposx5;
   $data[$counter]["COL5"]["WIDTH"]    = $itemwidth5;

   $counter++;
}

printRAWTable($data, $form, $mode);
formFeed();

if($_REQUEST["mode"] == "preview")
{
   ?></font></pre></td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("boxopt_b", "860")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "javascript: void(0);window.close()", "", "arrow-180", 230);
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130">
         <?php
         if($_SESSION["user_printer_name"] != "")
            printButton("Ejecutar impresion punto matriz", "postnav_save", "javascript: void(0)", "opener.document.getElementById('idxifrsrc').src = './libs/modules/stockcounts/printraw.exec.php?id={$_REQUEST["id"]}&lstpos={$_REQUEST["lstpos"]}';window.close()", "script", 230);
         else
            echo "<b class=msg_save_err>Para imprimir: Ingresa nombre de la impresora en el perfil del usuario</b>";
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   </center>
   <br>
   </body>
   </html>
   <?php
}
?>