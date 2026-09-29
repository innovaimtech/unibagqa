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

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

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

$sql = " select t1.*, t2.docto_title, t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
         from tran_docs t1
         LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
         LEFT OUTER JOIN user t3 ON t1.doc_crtusr = t3.id
         where
         t1.doc_tran_id    = {$_REQUEST["id"]} and
         t1.doc_tran_type  = 'orders_offers'  and
         t1.doc_name      != ''
         order by t1.id";
$anexos = $CON->select($sql);
foreach($anexos AS $anexo)
{  ?>
   <span style="float:left;background-color:#00A9A6;color:white;text-shadow:none;padding:3px;padding-left:6px;padding-right:6px;margin-right:3px;border-radius:3px"><?=$anexo["doc_name"]?></span>
   <?php
}