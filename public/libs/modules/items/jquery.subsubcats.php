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

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

$catids      = explode("#", $_REQUEST["catid"]);
$catid       = $catids[0];
$subcatid    = $catids[1];

$sql = " select *
         from website_subsubcats
         where
         cat_id    = {$catid} and
         subcat_id = {$subcatid} and
         website_subsubcat_status > 0
         order by website_subsubcat_title";
$subsubcats = $CON->select($sql);

if($subsubcats !== false)
{
   ?>
   <option value="">
      - - -
   </option>
   <?php
   foreach($subsubcats AS $subsubcat)
   {  ?>
      <option value="<?=$subsubcat["id"]?>">
         <?=$subsubcat["website_subsubcat_title"]?>
      </option>
      <?php
   }
}
else
{  ?>
   <option value="">
      - - -
   </option>
   <?php
}