<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2018 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["exec"] == "details")
{
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Cierre de caja</b></td>
      <td align="right" class="content_row_clear">&nbsp;</td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?php
   require_once("cash.close.details.php");
}
elseif($_REQUEST["exec"] == "access")
{
   $sql = " select t1.*, t2.user_firstname, t2.user_lastname, t3.ca_name
            from cash_close_comments t1
            LEFT OUTER JOIN user t2                   ON t1.cash_user_id = t2.id
            LEFT OUTER JOIN company_shops_cashings t3 ON t1.cash_caid = t3.id
            where
            t1.id = {$_REQUEST["id"]}";
   $cashclose = $CON->select($sql);
   $cashclose = $cashclose[0];

   unset($_SESSION);
   session_destroy();
   session_start();
   session_regenerate_id(true);
   $_SESSION = Array();

   ?>
   <form action="index.php" method="post" name="xform_login" target="_parent">
   <input type="hidden" name="exec" value="login">
   <input type="hidden" name="xmode" id="xmode" value="CAJA">
   <input type="hidden" name="user_login" id="user_login" value="<?=$cashclose["cash_user_id"]?>">
   <input type="hidden" name="directmode" id="directmode" value="<?=md5($cashclose["cash_user_id"])?>">
   </form>
   <script language="JavaScript">
      document.xform_login.submit();
   </script>
   <?php
   exit;
}
else
{
   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON);

   $_sesmodulename         = "cash_close_history";
   $_sesbasefilterstatus   = "0";
   $_sesbaseorderby        = "1";
   $_sesbaseordersort      = "asc";

   unset($_SESSION["STATS"][$_sesmodulename]);

   //----------------------------------------------------------------------------------
   resetOverviewSession($_sesmodulename);

   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_month"]     = trim($_REQUEST["sql_month"]);
      $_SESSION[$_sesmodulename]["sql_year"]      = trim($_REQUEST["sql_year"]);
      $_SESSION[$_sesmodulename]["sql_user_id"]   = (int)$_REQUEST["sql_user_id"];

      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
      $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];

   foreach($companies AS $company)
      if($company["id"] == $_SESSION[$_sesmodulename]["sql_company"])
      {
         $_SESSION[$_sesmodulename]["DATA"]["_COMPANY_NAME"] = $company["company_name"];
         $_SESSION[$_sesmodulename]["DATA"]["_COMPANY_RUT"]  = $company["company_rut"];
      }

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_month"] == "")
   {
      $_SESSION[$_sesmodulename]["sql_month"]  = (int)date('m');
      $_SESSION[$_sesmodulename]["sql_year"]   = (int)date('Y');

      if($_SESSION[$_sesmodulename]["sql_month"] == 0)
      {
         $_SESSION[$_sesmodulename]["sql_month"] = 12;
         $_SESSION[$_sesmodulename]["sql_year"]--;
      }
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 100);

   //----------------------------------------------------------------------------------
   $closeday  = 1;
   $lastmonth = $_SESSION[$_sesmodulename]["sql_month"];
   $lastyear  = $_SESSION[$_sesmodulename]["sql_year"];
   $sql_startdate = mktime(0, 0, 0, $lastmonth, $closeday, $lastyear);
   $sql_endate    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month"], date('t', $sql_startdate), $_SESSION[$_sesmodulename]["sql_year"]);

   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from user t1
            where
            t1.user_status = 1
            order by t1.user_firstname, t1.user_lastname";
   $allusers = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.user_firstname, t2.user_lastname, t3.ca_name, t4.shop_name
            from cash_close_comments t1
            LEFT OUTER JOIN user                   t2 ON t1.cash_user_id = t2.id
            LEFT OUTER JOIN company_shops_cashings t3 ON t1.cash_caid = t3.id
            LEFT OUTER JOIN company_shops          t4 ON t1.cash_shop_id = t4.id
            where
            t1.cash_date  between {$sql_startdate} and {$sql_endate}";
   if((int)$_SESSION[$_sesmodulename]["sql_user_id"])
      $sql .= " and t1.cash_user_id = {$_SESSION[$_sesmodulename]["sql_user_id"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $sql .= " and t1.cash_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
      $sql .= " and t1.cash_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   $sql .= " order by t1.id desc";
   $data = $CON->select($sql);

   ?>
   <script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
   <div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <tr>
      <td class="aula_content_module">
         <table border="0" cellpadding="0" cellspacing="0" width="980">
         <tr>
            <td height="30"><b class="content_header">Cierres de caja: <?=date('d.m.Y', $sql_startdate)?> hasta <?=date('d.m.Y', $sql_endate)?></b></td>
            <td align="right" class="content_row_clear">&nbsp;</td>
         </tr>
         <tr>
            <td class="content_headerline" colspan="2">&nbsp;</td>
         </tr>
         </table>
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td>
               <?=Nifty_printH("box2", "980")?>
               <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst" style="margin:0px;padding:0px">
               <input type="hidden" name="subexec" value="search">
               <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
               <input type="hidden" name="submid" value="<?=$_REQUEST["submid"]?>">
               <input type="hidden" name="printpdf" value="0">
               <input type="hidden" name="printxls" value="0">
               
               <table cellpadding="3" cellspacing="0" width="100%" border="0" class="aula_formtbl">
               <colgroup>
                  <col width="100">
                  <col>
                  <col width="100">
                  <col width="300">
               </colgroup>
               <tr>
                  <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
               </tr>
               <tr>
                  <td class="content_rowl">Empresa</td>
                  <td class="content_row">
                     <select class="text" name="sql_company" style="width:375px"
                     onmousedown="markfield(this,0)" onblur="markfield(this,1)"
                     onchange="setCompanyShop(this.value)">
                        <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                        <?php
                        foreach($companies AS $company)
                        {  ?>
                           <option value="<?=$company["id"]?>"
                           <?php if($company["id"] == $_SESSION[$_sesmodulename]["sql_company"]) echo "selected"?>><?=$company["company_short"]?></option><?php
                        }
                        ?>
                     </select>
                  </td>
                  <td class="content_rowl">Sucursal</td>
                  <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
               </tr>
               <tr>
                  <td class="content_rowl">Mes</td>
                  <td class="content_row">
                     <select class="text" name="sql_month" id="sql_month"
                     onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                        <?php
                        for($x = 1; $x <= 12; $x++)
                        {
                           $dsp_month = $x;
                           if($dsp_month < 10)
                              $dsp_month = "0{$dsp_month}";
                           ?>
                           <option value="<?=$x?>"
                           <?php if($x == $_SESSION[$_sesmodulename]["sql_month"]) echo "selected" ?>><?=$dsp_month?></option>
                           <?php
                        }
                        ?>
                     </select>
                     <select class="text" name="sql_year" id="sql_year"
                     onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                        <?php
                        $startyear  = date('Y') -3;
                        $endyear    = date('Y') +1;

                        for($x = $startyear; $x <= $endyear; $x++)
                        {
                           ?>
                           <option value="<?=$x?>"
                           <?php if($x == $_SESSION[$_sesmodulename]["sql_year"]) echo "selected" ?>><?=$x?></option>
                           <?php
                        }
                        ?>
                     </select>
                  </td>
                  <td class="content_rowl">Cajera</td>
                  <td class="content_row">
                     <select class="text" name="sql_user_id" id="sql_user_id" style="width:375px"
                     onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                        <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                        <?php
                        foreach($allusers AS $seluser)
                        {  ?>
                           <option value="<?=$seluser["id"]?>"
                           <?php if($seluser["id"] == $_SESSION[$_sesmodulename]["sql_user_id"]) { echo "selected"; $cajeraname = "{$seluser["user_firstname"]} {$seluser["user_lastname"]}"; } ?>>
                              <?=$seluser["user_firstname"]?> <?=$seluser["user_lastname"]?>
                           </option><?php
                        }
                        ?>
                     </select>
                  </td>
               </tr>
               <tr>
                  <td class="content_row" colspan="4">
                     <table border="0" cellpadding="0" cellspacing="0" width="100%">
                     <colgroup>
                        <col width="130">
                        <col width="130">
                        <col>
                     </colgroup>
                     <tr>
                        <td align="left">&nbsp;</td>
                        <!--
                        <td align="left">
                           <?php
                           printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value=1;submitForm(document.xform_itemsearch)", "document-excel", 130);
                           ?>
                        </td>
                        -->
                        <td align="right" width="1" style="padding-right:5px">
                           <?php
                           if((int)$_SESSION[$_sesmodulename]["search_active"])
                              printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                           ?>
                        </td>
                        <td align="right" width="1">
                           <?php
                           printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
                           $_SESSION["_SUBMITBTN"] = 1;
                           ?>
                        </td>
                     </tr>
                     </table>
                  </td>
               </tr>
               </table>
               </form>
               <?=Nifty_printF(false)?>
            </td>
         </tr>
         <tr>
            <td class="content_row_clear">
               <br>
               <?=Nifty_printH("box1", "980")?>
               <table border="0" cellpadding="3" cellspacing="0" width="100%" class="aula_listtbl" style="margin-top:0px">
               <colgroup>
                  <col>
                  <col>
                  <col>
                  <col>
                  <col>
                  <col>
                  <col>
                  <col>
                  <col>
                  <col width="80">
               </colgroup>
               <tr>
                  <td class="content_tbl_header" colspan="10">Detalle de cajas</td>
               </tr>
               <tr>
                  <td class="content_row_os content_tbl_subheader">Folio</td>
                  <td class="content_row_os content_tbl_subheader">Apertura</td>
                  <td class="content_row_os content_tbl_subheader">Sucursal</td>
                  <td class="content_row_os content_tbl_subheader">Cajera</td>
                  <td class="content_row_os content_tbl_subheader">Estado</td>
                  <td class="content_row_os content_tbl_subheader">Cierre</td>
                  <td class="content_row_os content_tbl_subheader" align="right">Total/Sistema</td>
                  <td class="content_row_os content_tbl_subheader" align="right">Total/Real</td>
                  <td class="content_row_os content_tbl_subheader" align="right">Diferencia</td>
                  <td class="content_row_os content_tbl_subheader" align="center">Opción</td>
               </tr>
               <?php
               $x             = 0;
               $realcounter   = 0;
               $rowcc         = 0;
               for($x = 0; $x < count($data) && $data != false; $x++)
               {
                  $row = $data[$x];

                  $sql = " select t1.cash_payment_id, t2.pay_title, SUM(t1.cash_close_calcvalue) 'cash_close_calcvalue',
                                  SUM(t1.cash_close_real_value) 'cash_close_real_value', SUM(t1.cash_close_diff_value) 'cash_close_diff_value'
                           from cash_close_differences t1
                           INNER JOIN payments t2 ON t1.cash_payment_id = t2.id
                           where
                           t1.close_id IN ({$row["id"]}) and t1.cash_payment_id = 9
                           group by 1,2
                           order by t2.pay_title";
                  $abonopayments = $CON->select($sql);
                  foreach($abonopayments AS $abonopayment)
                     $row["cash_close_calcvalue"] -= $abonopayment["cash_close_calcvalue"];

                  $state = "Finalizado";
                  $scss  = "#47AE47";
                  if(!(int)$row["cash_date_close"])
                  {
                     $state = "Abierto";
                     $scss  = "#E33939";
                  }
                  ?>
                  <tr>
                     <td class="content_row_os"><?=sprintf("%05s", $row["id"])?></td>
                     <td class="content_row_os"><?=date('d.m.Y H:i', $row["cash_date"])?></td>
                     <td class="content_row_os"><?=$row["shop_name"]?>&nbsp;</td>
                     <td class="content_row_os"><?=$row["user_firstname"]?>&nbsp;<?=$row["user_lastname"]?></td>
                     <td class="content_row_os" style="color:#FFFFFF;background-color:<?=$scss?>"><?=$state?></td>
                     <td class="content_row_os"><?php if((int)$row["cash_date_close"]) echo date('d.m.Y H:i', $row["cash_date_close"])?>&nbsp;</td>
                     <td class="content_row_os" align="right"><nobr>$ <?=printPrice($row["cash_close_calcvalue"])?></nobr></td>
                     <td class="content_row_os" align="right"><nobr>$ <?=printPrice($row["cash_dep_real_value"])?></nobr></td>
                     <td class="content_row_os" align="right"
                     style="color:<?php if($row["cash_dep_diff_value"] < 0.00) echo "red"; else echo "green"?>"><nobr>$ <?=printPrice($row["cash_dep_diff_value"])?></nobr></td>
                     <td class="content_row_os" align="center" valign="top">
                        <?php
                        printButton("MOSTRAR", "postnav", "index.php?mid={$_REQUEST["mid"]}&submid={$_REQUEST["submid"]}&id={$row["id"]}&exec=details", "", "pencil", 80, "", "edit");
                        ?>
                     </td>
                  </tr>
                  <?php
                  $_GES_CALC += $row["cash_close_calcvalue"];
                  $_GES_REAL += $row["cash_dep_real_value"];
                  $_GES_DIFF += $row["cash_dep_diff_value"];
               }
               ?>
               <tr>
                  <td class="content_row_os content_tbl_subheader" colspan="5" style="font-weight:bold">TOTAL</td>
                  <td class="content_row_os content_tbl_subheader" align="right" style="font-weight:bold">$ <?=printPrice($_GES_CALC)?>
                  <td class="content_row_os content_tbl_subheader" align="right" style="font-weight:bold">$ <?=printPrice($_GES_REAL)?>
                  <td class="content_row_os content_tbl_subheader" align="right" style="font-weight:bold">$ <?=printPrice($_GES_DIFF)?>
                  <td class="content_row_os content_tbl_subheader">&nbsp;</td>
               </tr>
               </table>
               <?=Nifty_printF()?>
            </td>
         </tr>
         </table>
      </td>
   </tr>
   </table>
   <?php
}