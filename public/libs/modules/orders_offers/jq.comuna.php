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

$comuna = getAllComunas($CON, (int)$_REQUEST["comunaid"]);
$comuna = $comuna[0];

if((int)$comuna["id"] && $_REQUEST["xmode"] == "offer")
{  ?>
   <script language="JavaScript">
      var xform = document.form_reqpos;
      var $options = $("#comunas > option").clone();
      xform.country.value='<?=$comuna["id_pais"]?>';
      xform.regions.value='<?=$comuna["id_region"]?>';
      setProvincias('<?=$comuna["id_region"]?>');
      xform.provincias.value='<?=$comuna["prov_id"]?>';
      $('#comunas').html('');
      $('#comunas').append($options);
      $('#comunas').val('<?=$comuna["id"]?>');
   </script>
   <?php
}
elseif((int)$comuna["id"] && ( $_REQUEST["xmode"] == "customer" || $_REQUEST["xmode"] == "supplier" ))
{  ?>
   <script language="JavaScript">
      var xform = document.xform_cust;
      var $options = $("#comunas > option").clone();
      xform.country.value='<?=$comuna["id_pais"]?>';
      xform.regions.value='<?=$comuna["id_region"]?>';
      setProvincias('<?=$comuna["id_region"]?>');
      xform.provincias.value='<?=$comuna["prov_id"]?>';
      $('#comunas').html('');
      $('#comunas').append($options);
      $('#comunas').val('<?=$comuna["id"]?>');
   </script>
   <?php
}