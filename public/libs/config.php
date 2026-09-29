<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_CONFIG["_MODUS"] = "TEST";

$_CONFIG["TEST"]["ERROR_REPORTING"] = 0;
$_CONFIG["LIVE"]["ERROR_REPORTING"] = 0;

//----------------------------------------------------------------------------------
$_CONFIG["TEST"]["DATABASE"]["HOST"] = "149.50.129.154";
$_CONFIG["TEST"]["DATABASE"]["NAME"] = "unibag_unibag";
$_CONFIG["TEST"]["DATABASE"]["USER"] = "unibag_unibag";
$_CONFIG["TEST"]["DATABASE"]["PASS"] = "ccsadfsdM1Ffd12";

$_CONFIG["_PROD_SUPERVISOR_ROLEID"] = "31";
$_CONFIG["_REPORTS_DEFAULT_STHID"]  = 1;

$_CONFIG["DAEMON_STATE_GUI_INTERVAL"]     = 5000;
$_CONFIG["SYNC_STATE_GUI_INTERVAL"]       = 5000;
$_CONFIG["PROCESS_SII_DOCS"]              = 1;
$_CONFIG["SII_ONLINE"]                    = 30;
$_CONFIG["SII_DOWNLOAD"]                  = 1;
$_CONFIG["SII_ITF_DISABLE"]               = 1;

//----------------------------------------------------------------------------------
$_CONFIG["DAEMONS"][0]["PNAME"]     = "unibagsrv.php";
$_CONFIG["DAEMONS"][0]["PDESC"]     = "Unibag-Demonio-Principal";
$_CONFIG["DAEMONS"][0]["SECONDARY"] = false;

//----------------------------------------------------------------------------------
$_CONFIG["DAEMONS"][5]["PNAME"]     = "unibagsrv-persistent-sii.php";
$_CONFIG["DAEMONS"][5]["PDESC"]     = "Unibag-Procesar-Sii";
$_CONFIG["DAEMONS"][5]["SECONDARY"] = false;

//----------------------------------------------------------------------------------
// BODEGA PARA PRODUCCION, SACAR STOCK
//----------------------------------------------------------------------------------
$_CONFIG["_PRODSTHS"]["_ISSUEID"]   = 1; //MOTIVO AJUSTE NEGATIVO 

//----------------------------------------------------------------------------------
$_CONFIGTRANTYPES["invoicesbuy"]       = true;
$_CONFIGTRANTYPES["shipment"]          = true;
$_CONFIGTRANTYPES["stockchangeup"]     = true;
$_CONFIGTRANTYPES["transup"]           = true;
$_CONFIGTRANTYPES["sthup"]             = true;
$_CONFIGTRANTYPES["invoicesnotessell"] = true;
$_CONFIGTRANTYPES["invoicesnotesselb"] = false;
$_CONFIGTRANTYPES["invoice"]           = false;
$_CONFIGTRANTYPES["invoicebol"]        = false;
$_CONFIGTRANTYPES["sthdown"]           = false;
$_CONFIGTRANTYPES["stockchangedown"]   = false;
$_CONFIGTRANTYPES["ordersdelivery"]    = false;
$_CONFIGTRANTYPES["transdown"]         = false;
$_CONFIGTRANTYPES["invoicesnotessbuy"] = false;

$_CONFIGTRANTYPESNAMES["invoicesbuy"]       = "Compra (+)";
$_CONFIGTRANTYPESNAMES["shipment"]          = "Compra (+)";
$_CONFIGTRANTYPESNAMES["stockchangeup"]     = "Ajuste (+)";
$_CONFIGTRANTYPESNAMES["transup"]           = "Traspaso (+)";
$_CONFIGTRANTYPESNAMES["sthup"]             = "Traspaso (+)";
$_CONFIGTRANTYPESNAMES["invoice"]           = "Venta (-)";
$_CONFIGTRANTYPESNAMES["invoicebol"]        = "Boleta (-)";
$_CONFIGTRANTYPESNAMES["sthdown"]           = "Traspaso (-)";
$_CONFIGTRANTYPESNAMES["stockchangedown"]   = "Ajuste (-)";
$_CONFIGTRANTYPESNAMES["ordersdelivery"]    = "Venta (-)";
$_CONFIGTRANTYPESNAMES["transdown"]         = "Traspaso (-)";
$_CONFIGTRANTYPESNAMES["invoicesnotessell"] = "Nota/credito/V (+)";
$_CONFIGTRANTYPESNAMES["invoicesnotesselb"] = "Nota/debito/V (-)";
$_CONFIGTRANTYPESNAMES["invoicesnotessbuy"] = "Nota/credito/C (-)";

$_CONFIG["TELA_COLOR_CHARACTID"]       = 17;
$_CONFIG["FLEX_TINTA_COLOR_CHARACTID"] = 22;
$_CONFIG["FLEX_TINTA_COLOR_CHARACTID_2"]  = 25;
$_CONFIG["SERI_TINTA_COLOR_CHARACTID"] = 21;
$_CONFIG["SERI_TINTA_COLOR_CHARACTID_2"]  = 21;
$_CONFIG["TELA_CATID"]                    = 6;
$_CONFIG["PINTURAS_CATID"]                = 2;
$_CONFIG["TELA_MATERIAL_CHARACTID"]       = 18;

$_CONFIG["WORDPRESS_HOST"]             = "localhost";
$_CONFIG["WORDPRESS_DB"]               = "unibag_wp843";
$_CONFIG["WORDPRESS_USER"]             = "unibag_wp843";
$_CONFIG["WORDPRESS_PASSWORD"]         = "2Sphq7.[E6";
$_CONFIG["WORDPRESS_COMPID"]           = 20010;
$_CONFIG["WORDPRESS_SHOPID"]           = 30010;
$_CONFIG["WORDPRESS_STHID"]            = 1;

//----------------------------------------------------------------------------------
define("TTF_DIR","../../thirdparty/jpgraph-1.26/fonts/");
?>
