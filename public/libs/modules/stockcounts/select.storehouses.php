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

//----------------------------------------------------------------------------------
$sql = " select *
         from company_shops_storehouses
         where
         st_shop_id = {$_REQUEST["shopid"]} and
         st_status  = 1
         order by st_name";
$data = $CON->select($sql);
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script type="text/javascript" src="../../../libs/jscripts/jquery-1.4.2.js"></script>
   <script language="JavaScript">
      <?php
      require_once("../../../libs/jscripts/sourcen.php");
      ?>
      function setStockcountSthid(selsthid)
      {
         var obj     = document.getElementById('stc_selsthid_' +selsthid);
         var trobj   = document.getElementById('stc_trsthid_' +selsthid);

         if(obj.checked)
            trobj.style.backgroundColor = '#E1FFD6';
         else
            trobj.style.backgroundColor = '';
      }

      function updateParentString()
      {
         var probj   = parent.document.getElementById('stc_selstorehouses');
         var boxes   = $(":checkbox:checked");
         probj.value = '';
         
         $(":checkbox:checked").each(function ()
         {
            probj.value = probj.value + $(this).val() +'#';
         });
         probj.value = probj.value.substr(0, probj.value.length - 1);
      }
   </script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content">
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="30">
   <col>
   <col width="90">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Recuento del inventario: Selección de bodegas</td>
</tr>
<tr>
   <td class="content_tbl_subheader">&nbsp;</td>
   <td class="content_tbl_subheader">Nombre</td>
   <td class="content_tbl_subheader" align="center">Opciones</td>
</tr>
<?php
$x = 0;
foreach($data AS $row)
{
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" id="stc_trsthid_<?=$row["id"]?>">
      <td class="content_row">
         <input type="checkbox" class="checkbox" id="stc_selsthid_<?=$row["id"]?>"
         value="<?=$row["id"]?>"
         onclick="setStockcountSthid('<?=$row["id"]?>');updateParentString();">
      </td>
      <td class="content_row"><?=$row["st_name"]?></td>
      <td class="content_row" align="center">
         <?php
         printButton("Seleccione", "postnav_save", "javascript: void(0)",
         "document.getElementById('stc_selsthid_{$row["id"]}').checked = !document.getElementById('stc_selsthid_{$row["id"]}').checked;
          setStockcountSthid('{$row["id"]}'); updateParentString();", "plus");
         ?>
      </td>
   </tr>
   <?php
   $x++;
}
?>
</table>
<?=Nifty_printF()?>
<script language="JavaScript">
   var probjvalue = parent.document.getElementById('stc_selstorehouses').value;
   probjvalue = probjvalue.split('#');
   for(var i=0;i<probjvalue.length;i++)
   {
      document.getElementById('stc_selsthid_' +probjvalue[i]).checked = true;
      setStockcountSthid(probjvalue[i]);
   }
   updateParentString();
</script>
</body>
</html>