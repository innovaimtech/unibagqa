<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
$fecha_hoy = $_REQUEST['fecha_hoy'];

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

//----------------------------------------------------------------------------------
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script language="Javascript">
      <?php
      require_once("../../../libs/jscripts/sourcen.php");
      ?>
      function loginExec()
      {
         if(checkform(new Array(document.all.xuser_login, document.all.xuser_pass)))
         {
            document.all.user_login.value    = document.all.xuser_login.value;
            document.all.user_pass.value     = document.all.xuser_pass.value;
            document.all.xuser_login.value   = '';
            document.all.xuser_pass.value    = '';
            document.all.xuser_pass.type     ='text';
            return true;
         }
         return false;
      }
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="document.all.xuser_login.focus()">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<?php

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "login")
{
   $key = $CON->select("select * from parametros where tabla = 'API_BANCOCEN' and codigo = 'KEY'");
   $key = $key[0]["descripcion"];

   $hoy = date("Y-m-d",$_REQUEST["fecha_hoy"]); 
   $dia = date("d",$_REQUEST["fecha_hoy"]); 
   $mes = date("m",$_REQUEST["fecha_hoy"]); 
   $ano = date("Y",$_REQUEST["fecha_hoy"]); 

   $CON->no_result("delete from money_exchange where DATE(FROM_UNIXTIME(exc_upddat)) = '{$hoy}' ");
   $url = "https://api.cmfchile.cl/api-sbifv3/recursos_api/dolar/{$ano}/{$mes}/dias/{$dia}?apikey={$key}&formato=json";

   $ch = curl_init($url);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   $response = curl_exec($ch);
   curl_close($ch);
   $data = json_decode($response, true);
   
   $valor_dolar = 0;
   $valor_euro  = 0;

   if (isset($data['CodigoError'])) 
   {
      echo "Error: " . $data['Mensaje'];
   }
   elseif (isset($data['Dolares'][0]['Valor']) && isset($data['Dolares'][0]['Fecha']))  
   {
      $valor_dolar = str_replace(',', '.', $data['Dolares'][0]['Valor']); 
      $valor_dolar = str_replace(',', '.', $data['Dolares'][0]['Valor']); 
   }
   else
   {
      $valor_dolar = 0;
   }
      
   $url = "https://api.cmfchile.cl/api-sbifv3/recursos_api/euro/{$ano}/{$mes}/dias/{$dia}?apikey={$key}&formato=json";

   $ch = curl_init($url);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   $response = curl_exec($ch);
   curl_close($ch);
   $data = json_decode($response, true);
   if (isset($data['Euros'][0]['Valor']) && isset($data['Euros'][0]['Fecha']))
   {
       $valor_euro = str_replace(',', '.', $data['Euros'][0]['Valor']); 
       $valor_euro = str_replace(',', '.', $data['Euros'][0]['Valor']); 
   }
   else
   {
      $valor_euro = 0;
   }

   if($valor_dolar > 0 || $valor_euro > 0)
   {
      $fecha = date("d.m.Y",$_REQUEST['fecha_hoy']);
      $timestamp = explode(".", $fecha);
      $timestamp= (int)mktime(0, 0, 0, $timestamp[1], $timestamp[0], $timestamp[2]);

      $sql = "insert into money_exchange(exc_upddat, exc_usdval, exc_tstamp, exc_ufval, exc_eurval, exc_yenval, exc_updusr )
              values({$timestamp}, {$valor_dolar} , {$timestamp} , 0, {$valor_euro}, 0 , {$_SESSION["user_id"]})";
      $CON->no_result($sql);
   }
}

$hoy = date("Y-m-d",$_REQUEST["fecha_hoy"]); // Ejemplo: "2025-07-29"

$hoy_buscar = date("d.m.Y",$_REQUEST['fecha_hoy']);
$hoy_buscar = explode(".", $hoy_buscar);
$hoy_buscar = (int)mktime(0, 0, 0, $hoy_buscar[1], $hoy_buscar[0], $hoy_buscar[2]);

$sql = "SELECT * FROM money_exchange WHERE exc_tstamp = '{$hoy_buscar}'";
$indicadores = $CON->select($sql);
$indicadores = $indicadores[0];

?>
<table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
<tr>
   <td align="center" valign="top" height="100%">
      <form action="carga_moneda.fancy.php" method="post" class="fokusfirst" name="xform_log" autocomplete="off">
      <input type="hidden" name="exec" value="login">
      <input type="hidden" name="frmname" value="<?=$_REQUEST["frmname"]?>">
      <input name="user_login" type="text" style="display:none" value="">
      <input name="user_pass" type="text" style="display:none" value="">
      <input type="hidden" name="fecha_hoy" value="<?=$fecha_hoy?>">
      <br>
      <?=Nifty_printH("box1", "350", 0)?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_header" colspan="2">Indicadores día : <?=date("d/m/Y",$_REQUEST["fecha_hoy"])?></td>
      </tr>
      <tr>
         <td class="content_rowl">DOLAR</td>
         <td class="content_row">
            <input name="exc_usdval" type="text" class="text" style="width:180px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=printPrice($indicadores["exc_usdval"],4)?>">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">EURO</td>
         <td class="content_row">
            <input name="exc_eurval" type="text" class="text" style="width:180px"
            onfocus="markfield(this,0);" onblur="markfield(this,1)" value="<?=printPrice($indicadores["exc_eurval"],4)?>">
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?=Nifty_printH("boxopt_b", "350", 0)?>
      <tr>
         <td class="content_row_clear" align="right">
            <?php
            printButton("Cargar Indicadores", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_log)", "money--plus", 200);
            ?>
         </td>
      </tr>
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
</table>
</body>
</html>