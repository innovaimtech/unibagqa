<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
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
         from offers t1
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

// print_r($headdata);

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
      function setSelData()
      {
         if($('#sql_customer').val() != '')
         {
            parent.location.href = '/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=basic&id=<?=$_REQUEST["id"]?>&clonegen=1&clontocustid=' +$('#sql_customer').val();
         }
      }
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="<?php if(count($items) == 0 || $items == false) echo "document.xform_itemsearch.sql_stext.focus()"?>">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="fancy.clone.php" method="post" name="xform_itemsearch" class="fokusfirst" onsubmit="return false">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="rowcount" value="<?=$_REQUEST["rowcount"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <?=Nifty_printH("box1", "99%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Seleccionar cliente</td>
      </tr>
      <tr>
         <td class="content_rowl">Cliente</td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="105">
               <col>
            </colgroup>
            <tr>
               <td>
                  <input type="text" class="text" style="width:100px" onfocus="markfield(this,0)" name="xf_custsearch"
                  onblur="markfield(this,1);if(this.value != '') document.all.idxifrsrc.src='/libs/modules/orders/searchcust.php?rowcount=0&destobj=sql_customer&search=' +this.value"
                  onkeyup="detectCustEvent(event, 'orders')">
               </td>
               <td>
                  <select class="text" style="width:270px" name="sql_customer" id="sql_customer"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     if($headdata["req_cust_rut"] != "")
                     {
                        $sql_rut = trim(addslashes(str_replace(".","",$headdata["req_cust_rut"])));
                        $sql = " select id, cust_name
                                 from customer
                                 where
                                 cust_status = 1 and
                                 REPLACE(cust_rut,'.','') = '{$sql_rut}'";
                        $selcustomer = $CON->select($sql);
                        ?>
                        <option value="<?=$selcustomer[0]["id"]?>">
                           <?=$selcustomer[0]["cust_name"]?>
                        </option>
                        <?php
                     }
                     ?>
                  </select>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="270">
            <tr>
               <td align="right">&nbsp;</td>
               <td align="right">
                  <?php
                  printButton("Clonear", "postnav_save", "javascript: deactivateFormChange()", "setSelData()", "gear", 130);
                  $_SESSION["_SUBMITBTN"] = 1;
                  ?>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
      </form>
   </td>
</tr>
</table>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
</body>
</html>