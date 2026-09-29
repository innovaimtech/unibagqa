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

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

$sql = " select *
         from supplier
         where
         id = {$_REQUEST["suppid"]}";
$consulta  = $CON->select($sql);
$delivdays  = (int)$consulta[0]["supp_delivery_days"];
$sord_date  = time() + ($delivdays * 86400);
$supptax    = getSupplierTaxes($CON, $_REQUEST["suppid"]);
$supp_cod_moneda = $consulta[0]["supp_cod_moneda"];
?>
<script language="JavaScript">
   /* document.getElementById('idx_start_moneda').style.display = ''; */
   document.getElementById('idx_start_import').style.display = '';
   document.getElementById('idx_start_delivery').style.display = '';
   document.getElementById('idx_start_delivery_date').style.display = '';
   document.getElementById('idx_start_delivery_days').innerHTML = '<?=$delivdays?> Dias';
   document.getElementById('delivery_date').value = '<?=date('d.m.Y', $sord_date)?>';
   document.getElementById('sord_moneda_0').value = '<?=$supp_cod_moneda?>';
   <?php
   if($supptax)
   {  ?>
      document.getElementById('idx_start_import_text').innerHTML = 'No';
      document.getElementById('sord_taxes').value = '1';
      <?php
   }
   else
   {  ?>
      document.getElementById('idx_start_import_text').innerHTML = 'Si';
      document.getElementById('sord_taxes').value = '0';
      <?php
   }
   ?>
</script>