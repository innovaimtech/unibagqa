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
$sql = " select t1.*
         from productcats t1
         where
         t1.cat_status = 1
         order by t1.id asc";
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
      function setStockcountCatid(selcatid)
      {
         var obj     = document.getElementById('stc_selcatid_' +selcatid);
         var trobj   = document.getElementById('stc_trcatid_' +selcatid);

         if(obj.checked)
            trobj.style.backgroundColor = '#E1FFD6';
         else
            trobj.style.backgroundColor = '';
      }

      function updateParentString()
      {
         var probj   = parent.document.getElementById('stc_selfamilies');
         var boxes   = $(":checkbox:checked");
         probj.value = '';
         
         $(":checkbox:checked").each(function ()
         {
            probj.value = probj.value + $(this).val() +'#';
         });
         probj.value = probj.value.substr(0, probj.value.length - 1);
      }

      function detectEvent(event, sval)
      {
         var keyCode = ('which' in event) ? event.which : event.keyCode;
         if(keyCode == 13)
         {
            if($('#catjq_' +sval).length)
            {
               var selid = $('#catjq_' +sval).val();
               document.getElementById('stc_selcatid_' +selid).checked = true;
               setStockcountCatid(selid);
               updateParentString();
               parent.$.fancybox.close();
            }
         }
      }
   </script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="$('#searchcat').focus()">
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="30">
   <col width="70">
   <col>
   <col width="90">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Recuento del inventario: Selección de familias</td>
</tr>
<tr>
   <td class="content_tbl_subheader" colspan="4">
      Busqueda:
      <input type="text" class="text" id="searchcat"
      onkeyup="detectEvent(event, this.value)">
   </td>
</tr>
<tr>
   <td class="content_tbl_subheader">&nbsp;</td>
   <td class="content_tbl_subheader">Número</td>
   <td class="content_tbl_subheader">Familia</td>
   <td class="content_tbl_subheader" align="center">Opciones</td>
</tr>
<?php
$x = 0;
foreach($data AS $row)
{
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" id="stc_trcatid_<?=$row["id"]?>">
      <td class="content_row">
         <input type="hidden" id="catjq_<?=sprintf("%03s", $row["id"])?>" value="<?=$row["id"]?>">
         <input type="checkbox" class="checkbox" id="stc_selcatid_<?=$row["id"]?>"
         value="<?=$row["id"]?>"
         onclick="setStockcountCatid('<?=$row["id"]?>');updateParentString();">
      </td>
      <td class="content_row"><?=sprintf("%03s", $row["id"])?></td>
      <td class="content_row"><?=$row["cat_title"]?></td>
      <td class="content_row" align="center">
         <?php
         printButton("Seleccione", "postnav_save", "javascript: void(0)",
         "document.getElementById('stc_selcatid_{$row["id"]}').checked = !document.getElementById('stc_selcatid_{$row["id"]}').checked;
          setStockcountCatid('{$row["id"]}'); updateParentString();", "plus");
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
   var probjvalue = parent.document.getElementById('stc_selfamilies').value;
   if(probjvalue != '')
   {
      var probjvaluearr = probjvalue.split('#');
      for(var i=0;i<probjvaluearr.length;i++)
      {
         document.getElementById('stc_selcatid_' +probjvaluearr[i]).checked = true;
         setStockcountCatid(probjvaluearr[i]);
      }
      updateParentString();
   }
</script>
</body>
</html>