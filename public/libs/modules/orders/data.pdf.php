<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
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


require_once("../../../libs/functions.php");

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

$_SESSION["_CONF"]["conf_shop_lang"] = Array();
$sql = " select id
         from config_lang
         where
         lang_shop_active = 1";
$shoplangs = $CON->select($sql);
foreach($shoplangs AS $shoplang)
   array_push($_SESSION["_CONF"]["conf_shop_lang"], $shoplang["id"]);

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from orders t1
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$currtme    = time();
$hash       = md5(microtime());
$filedir    = "../../../docs.print/";
$doctype    = "ncorder";
$filename   = "{$filedir}{$_REQUEST["id"]}-{$doctype}-{$hash}.pdf";

//----------------------------------------------------------------------------------
if($handle = opendir($filedir))
{
   while (false !== ($file = readdir($handle)))
      if ($file != "." && $file != ".." && strpos($file,"{$_REQUEST["id"]}-{$doctype}-") !== false)
         unlink("{$filedir}{$file}");
   closedir($handle);
}

define('FPDF_FONTPATH','../../thirdparty/fpdf17/font/');
require('../../thirdparty/fpdf17/lib/pdftable.inc.php');

$html = file_get_contents("{$_SESSION["_CONF"]["conf_shopadmin_url"]}/pdfgenerate.order.php?id={$_REQUEST["id"]}");
   
//----------------------------------------------------------------------------------
$p = new PDFTable();
$p->setfont('Helvetica','',10);
$p->SetPadding(1);
$p->SetSpacing(0);
$p->SetMargins(10,10,10,10);
$p->AddPage("P", array(216,356));
$p->SetPadding(1);
$p->SetSpacing(0);
$p->SetMargins(0,0,0,0);
$p->htmltable($html);
$buffer     = $p->output('','S');
file_put_contents($filename, $buffer);

header('Content-Type: application/pdf;');
header("Content-Disposition: attachment; filename={$headdata["req_number"]}.pdf");
echo $buffer;
?>