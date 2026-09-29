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
error_reporting(E_ALL);
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

//----------------------------------------------------------------------------------
$currtme       = time();
$doc_name      = "scan-".date('y-m-d-H').".jpg";
$doc_dir       = "../../../docs.ftpupload/";
$doc_source    = "{$_REQUEST["id"]}.{$_REQUEST["img"]}.jpg";

$doc_dest      = "{$_REQUEST["id"]}_".md5(microtime()).".jpg";
$doc_destdir   = "../../../docs.tran/{$_REQUEST["mode"]}/";

//----------------------------------------------------------------------------------
resizeImage("{$doc_dir}{$doc_source}", 1200, "", "{$doc_destdir}{$doc_dest}");
unlink("{$doc_dir}{$doc_source}");

//----------------------------------------------------------------------------------
$sql = " insert into docs_versiones
         (doc_tran_id, doc_tran_type, doc_name, doc_file, doc_desc, doc_typeid, doc_crtdat, doc_crtusr)
         VALUES
         ({$_REQUEST["id"]}, '{$_REQUEST["mode"]}', '{$doc_name}', '{$doc_dest}', '{$_REQUEST["doc_desc"]}', 0,
          {$currtme}, {$_SESSION["user_id"]})";
$res = $CON->no_result($sql);

//----------------------------------------------------------------------------------
if($res)
{
   $sql = " select MAX(id) 'thisid'
            from docs_versiones
            where
            doc_tran_id    = {$_REQUEST["id"]} and
            doc_tran_type  = '{$_REQUEST["mode"]}' and
            doc_crtusr     = {$_SESSION["user_id"]}";
   $docdata = $CON->select($sql);
   $doc_id  = $docdata[0]["thisid"];
   ?>
   <script language="Javascript">
      location.href='overview.php?subcatexec=file&subexec=edit&id=<?=$_REQUEST["id"]?>&mode=<?=$_REQUEST["mode"]?>&doc_id=<?=$doc_id?>';
   </script>
   <?php
}