<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$sql = " select *
         from user
         where
         id = {$_SESSION["user_id"]}";
$userdata = $CON->select($sql);
$userdata = $userdata[0];

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_fabextern";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2,1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número Doc."      => 1,
                                "Fecha"            => 2,
                                "Vencimiento"      => 3,
                                "Cliente"          => 4,
                                "RUT"              => 5,
                                "Vendedor"         => 6,
                                "Monto total"      => 7);
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_supplier"]      = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_assigned"]      = (int)$_REQUEST["sql_assigned"];
   $_SESSION[$_sesmodulename]["sql_guianumber"]    = trim($_REQUEST["sql_guianumber"]);
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_ccnumber"]      = trim($_REQUEST["sql_ccnumber"]);
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON);
$sellers    = getSellers($CON);
if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y');
}
if($_SESSION[$_sesmodulename]["sql_date"] == "")
   $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');
if($_SESSION[$_sesmodulename]["sql_date_pfrom"] == "")
{
   $_SESSION[$_sesmodulename]["sql_date_pfrom"] = date('d.m.Y', time() - (86400 * 7));
   $_SESSION[$_sesmodulename]["sql_date_pto"]   = date('d.m.Y');
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_selmode"] == 1)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 2)
{
   $sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], 1, $_SESSION[$_sesmodulename]["sql_year2"]);
   $datedays      = date('t', $sql_dateto);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], $datedays, $_SESSION[$_sesmodulename]["sql_year2"]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 3)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pfrom"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pto"]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
if($_REQUEST["discardid"] != "")
{
   $_posidx    = explode("_", $_REQUEST["discardid"]);
   $_DLVID     = (int)$_posidx[0];
   $_ITEMID    = (int)$_posidx[1];
   $_POSID     = (int)$_posidx[2];

   $sql = " update orders_delivery_items
            set
            item_dlv_externprod_discardact = 1
            where
            dlv_id      = {$_DLVID} and
            item_id     = {$_ITEMID} and
            item_pos    = {$_POSID}";
   $CON->no_result($sql);
}

if((int)$_REQUEST["delstkid"])
{
   $sql = " delete from stockchanges
            where
            id = {$_REQUEST["delstkid"]}";
   $CON->no_result($sql);

   $sql = " delete from stockchanges_items
            where
            stk_id = {$_REQUEST["delstkid"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
$datsql = " select t1.*, t2.item_amount_shipped, t3.item_title, t3.item_number_prod, t6.supp_rut, t6.supp_company,
                   CONCAT(t2.dlv_id, '_', t2.item_id, '_', t2.item_pos) 'posidx', t2.item_dlv_externprod_adjrefid,
                   t2.item_dlv_externprod_invcnum, t1.dlv_annotation, t7.req_crtdat 'ccdate',
                   t8.cust_name, t9.user_lastname
            from orders_delivery t1
            INNER JOIN orders_delivery_items t2       ON t1.id = t2.dlv_id
            INNER JOIN item t3                        ON t2.item_id = t3.id
            INNER JOIN company_data t4                ON ( t1.dlv_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN supplier t6               ON ( t1.dlv_supplier_id = t6.id )
            LEFT OUTER JOIN orders t7                 ON t1.dlv_externprod_ccid = t7.id
            LEFT OUTER JOIN customer t8               ON t7.req_cust_id = t8.id
            LEFT OUTER JOIN user t9                   ON t7.req_userid_seller = t9.id
            where
            t1.dlv_status                 > 1 and
            t1.dlv_status                 < 5 and
            t1.dlv_supplier_id            > 0 and
            t1.dlv_externprod_act         = 1 and
            t1.dlv_mode                   = 4 and
            t2.item_type                  = 'item' and
            t2.item_dlv_externprod_discardact = 0 and
            t1.dlv_delivery_date between  {$sql_datefrom} and {$sql_dateto} ";

 //----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.dlv_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.dlv_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   $datsql .= " and t1.dlv_supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
if($_SESSION[$_sesmodulename]["sql_ccnumber"] != "")
   $datsql .= " and t1.dlv_externprod_ccnum = '{$_SESSION[$_sesmodulename]["sql_ccnumber"]}' ";
if($_SESSION[$_sesmodulename]["sql_guianumber"] != "")
   $datsql .= " and t1.dlv_docnum = '{$_SESSION[$_sesmodulename]["sql_guianumber"]}' ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t8.id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";

$datsql .= " order by t1.dlv_docnum, t1.dlv_delivery_date, t2.item_pos ";
$dlvs = $CON->select($datsql);

for($x = 0; $x < count($dlvs) && $dlvs != false; $x++)
{
   $idx = $dlvs[$x]["id"];

   $posarr = explode("_", $dlvs[$x]["posidx"]);
   $sql = " select t1.*, t2.stk_bookdate, t2.stk_num, t3.item_amount, t4.st_name, t2.stk_status, t2.id 'stkid'
            from orders_delivery_items_fabext t1
            INNER JOIN stockchanges t2                   ON t1.adjrefid = t2.id
            INNER JOIN stockchanges_items t3             ON t2.id = t3.stk_id
            LEFT OUTER JOIN company_shops_storehouses t4 ON t3.item_st_id = t4.id
            where
            t1.dlv_id         = {$posarr[0]} and
            t1.dlv_item_id    = {$posarr[1]} and
            t1.dlv_item_pos   = {$posarr[2]}
            order by t1.id";
   $recvs = $CON->select($sql);
   $_TOTAL_RCV = 0;
   foreach($recvs AS $recv)
      $_TOTAL_RCV += $dlvs[$x]["item_amount_shipped"];

   $_ADDROW = true;
   if((int)$_SESSION[$_sesmodulename]["sql_assigned"] == 0)
   {
      if($_TOTAL_RCV >= $dlvs[$x]["item_amount_shipped"])
         $_ADDROW = false;
   }
   if((int)$_SESSION[$_sesmodulename]["sql_assigned"] == 1)
   {
      if($_TOTAL_RCV < $dlvs[$x]["item_amount_shipped"])
         $_ADDROW = false;
   }

   if($_ADDROW)
   {
      $_DATA[$idx]["id"]                     =  $dlvs[$x]["id"];
      $_DATA[$idx]["dlv_docnum"]             =  $dlvs[$x]["dlv_docnum"];
      $_DATA[$idx]["dlv_externprod_ccnum"]   =  $dlvs[$x]["dlv_externprod_ccnum"];

      $_DATA[$idx]["ccdate"] = "";
      if((int)$dlvs[$x]["ccdate"])
         $_DATA[$idx]["ccdate"] =  date("d.m.Y", $dlvs[$x]["ccdate"]);


      $_DATA[$idx]["cust_name"]              =  $dlvs[$x]["cust_name"];
      $_DATA[$idx]["user_lastname"]          =  $dlvs[$x]["user_lastname"];
      $_DATA[$idx]["supp_rut"]               =  $dlvs[$x]["supp_rut"];
      $_DATA[$idx]["supp_company"]           =  $dlvs[$x]["supp_company"];
      $_DATA[$idx]["dlv_delivery_date"]      =  $dlvs[$x]["dlv_delivery_date"];
      $_DATA[$idx]["dlv_annotation"]         =  $dlvs[$x]["dlv_annotation"];

      unset($temp);
      $temp["item_number_prod"]              = $dlvs[$x]["item_number_prod"];
      $temp["item_title"]                    = $dlvs[$x]["item_title"];
      $temp["item_amount_shipped"]           = $dlvs[$x]["item_amount_shipped"];
      $temp["posidx"]                        = $dlvs[$x]["posidx"];
      $temp["_RECEIVES"]                     = $recvs;
      $_DATA[$idx]["_ITEMS"][]               = $temp;
   }
}

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
         array_push($selshops, $shop);
}

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

printJSsetCompanyShop($shops);
?>
<script language="JavaScript">
function setInvcUser(uid, invcid, mode)
{
   $.get('/libs/modules/stats/selling/doc.assignment.setuser.php?uid=' +uid +'&invcid=' +invcid +'&mode=' +mode,
   function(data) {
      $('#invc_userid_' +mode +'_' +invcid).css('background-color','#C6FFCC');
   });
}
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Guias de fabricación externa</b></td>
   <td align="right" class="content_row_clear"></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst"
      onsubmit="return checkform(new Array(this.sql_company))">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <input type="hidden" name="restablecerid" value="">
      <input type="hidden" name="discardid" value="">
      <input type="hidden" name="delstkid" value="">
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="90">
         <col>
         <col width="100">
         <col width="300">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Periodo</td>
         <td class="content_row">
            <table border="0" class="content_table" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="70">
                  <input type="radio" name="sql_selmode" value="2"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 2) echo "checked"?>> Meses
               </td>
               <td class="content_row_clear" width="195" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
                  <nobr>
                  <select class="text" name="sql_month1" id="sql_month1"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     for($x = 1; $x <= 12; $x++)
                     {
                        $dsp_month = $x;
                        if($dsp_month < 10)
                           $dsp_month = "0{$dsp_month}";
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_month1"]) echo "selected" ?>><?=$dsp_month?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <select class="text" name="sql_year1" id="sql_year1"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     $startyear  = date('Y') -10;
                     $endyear    = date('Y');

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year1"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  -
                  <select class="text" name="sql_month2" id="sql_month2"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     for($x = 1; $x <= 12; $x++)
                     {
                        $dsp_month = $x;
                        if($dsp_month < 10)
                           $dsp_month = "0{$dsp_month}";
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_month2"]) echo "selected" ?>><?=$dsp_month?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <select class="text" name="sql_year2" id="sql_year2"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     $startyear  = date('Y') -3;
                     $endyear    = date('Y');

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year2"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  </nobr>
               </td>
               <td class="content_row_clear" width="50">
                  <input type="radio" name="sql_selmode" value="1"
                  onclick="document.getElementById('idx_selmode1').style.display='';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 1) echo "checked"?>> Dia
               </td>
               <td class="content_row_clear" width="110" id="idx_selmode1" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 1) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:80px" id="sql_date" name="sql_date"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date"]?>">
                  </nobr>
               </td>
               <td class="content_row_clear" width="75">
                  <input type="radio" name="sql_selmode" value="3"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 3) echo "checked"?>> Periodo
               </td>
               <td class="content_row_clear" width="180" id="idx_selmode3" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 3) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:65px" id="sql_date_pfrom" name="sql_date_pfrom"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
                  -
                  <input type="text" style="width:65px" id="sql_date_pto" name="sql_date_pto"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pto"]?>">
                  </nobr>
               </td>
            </tr>
            </table>
         </td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?php printOverviewSupplierSelect(Array(), $_sesmodulename) ?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Estado</td>
         <td class="content_row">
            <input type="radio" name="sql_assigned" value="0" <?if($_SESSION[$_sesmodulename]["sql_assigned"] == 0) echo "checked"?>> Solo pendientes
            <input type="radio" name="sql_assigned" value="1" <?if($_SESSION[$_sesmodulename]["sql_assigned"] == 1) echo "checked"?>> Solo terminados
            <input type="radio" name="sql_assigned" value="2" <?if($_SESSION[$_sesmodulename]["sql_assigned"] == 2) echo "checked"?>> Todo
         </td>
         <td class="content_rowl">Número guia</td>
         <td class="content_row">
            <input type="text" class="text" style="width:100%;" name="sql_guianumber"
            value="<?=$_SESSION[$_sesmodulename]["sql_guianumber"]?>">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Número C.C.</td>
         <td class="content_row">
            <input type="text" class="text" style="width:100%;" name="sql_ccnumber"
            value="<?=$_SESSION[$_sesmodulename]["sql_ccnumber"]?>">
         </td>
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($_DATA) > 0 && $_DATA != false)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="right">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"])
                     printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                  ?>
               </td>
               <td align="right">
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
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
<tr>
   <td>
      <?php
      foreach(array_keys($_DATA) AS $dlvid)
      {  ?>
         <?=Nifty_printH("box1", "99%")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="90">
            <col width="80">
            <col width="80">
            <col>
            <col width="180">
            <col width="120">
            <col width="30">
            <col width="100">
            <col width="85">
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="10"><?=$_DATA[$dlvid]["supp_company"]?> | <?=$_DATA[$dlvid]["supp_rut"]?></td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os">Fecha C.C.</td>
            <td class="content_tbl_subheader content_row_os">Nº C.C.</td>
            <td class="content_tbl_subheader content_row_os">Código</td>
            <td class="content_tbl_subheader content_row_os">Producto</td>
            <td class="content_tbl_subheader content_row_os">Cliente</td>
            <td class="content_tbl_subheader content_row_os">Vendedor</td>
            <td class="content_tbl_subheader content_row_os" align="center">Cantidad</td>
            <td class="content_tbl_subheader content_row_os">Nº Guia</td>
            <td class="content_tbl_subheader content_row_os">Fecha Guia</td>
            <td class="content_tbl_subheader content_row_os" align="center">Opciones</td>
         </tr>
         <?php
         $data = $_DATA[$dlvid]["_ITEMS"];
         $px = 0;

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($data) && $data != false; $x++)
         {  ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$_DATA[$dlvid]["ccdate"]?></td>
               <td class="content_row_os"><?=$_DATA[$dlvid]["dlv_externprod_ccnum"]?></td>
               <td class="content_row_os"><?=$data[$x]["item_number_prod"]?>&nbsp;</td>
               <td class="content_row_os"><?=$data[$x]["item_title"]?>&nbsp;</td>
               <td class="content_row_os"><?=$_DATA[$dlvid]["cust_name"]?>&nbsp;</td>
               <td class="content_row_os"><?=$_DATA[$dlvid]["user_lastname"]?>&nbsp;</td>
               <td class="content_row_os" align="center"><?=printPrice($data[$x]["item_amount_shipped"])?></td>
               <td class="content_row_os"><?=$_DATA[$dlvid]["dlv_docnum"]?></td>
               <td class="content_row_os"><?=date('d.m.Y', $_DATA[$dlvid]["dlv_delivery_date"])?></td>
               <td class="content_row_os" align="center">
                  <?php
                  if(!count($data[$x]["_RECEIVES"]) || $data[$x]["_RECEIVES"] === false)
                  {  ?>
                     <input type="button" class="buttonred" value="Descartar" style="width:50%;float:left;height:22px"
                     onclick="if(askDel('')) { document.xform_itemsearch.discardid.value = '<?=$data[$x]["posidx"]?>'; document.xform_itemsearch.submit(); }">
                     <input type="button" class="button" value="Ingreso" style="width:50%;float:right"
                     onclick="showFancybox('/libs/modules/stats/other/fabricacion.externa.fancy.php?posidx=<?=$data[$x]["posidx"]?>', 'iframe', 600, 400, 'auto')">
                     <?php
                  }
                  else
                  {  ?>
                     <input type="button" class="button" value="Ingreso" style="width:100%"
                     onclick="showFancybox('/libs/modules/stats/other/fabricacion.externa.fancy.php?posidx=<?=$data[$x]["posidx"]?>', 'iframe', 600, 400, 'auto')">
                     <?php
                  }
                  ?>
               </td>
            </tr>
            <?php
            //----------------------------------------------------------------------------------
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$dlvid][$px]["_DATA"]                   = $_DATA[$dlvid];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$dlvid][$px]["ccdate"]                  = $_DATA[$dlvid]["ccdate"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$dlvid][$px]["dlv_externprod_ccnum"]    = $_DATA[$dlvid]["dlv_externprod_ccnum"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$dlvid][$px]["item_number_prod"]        = $data[$x]["item_number_prod"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$dlvid][$px]["item_title"]              = $data[$x]["item_title"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$dlvid][$px]["cust_name"]               = $_DATA[$dlvid]["cust_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$dlvid][$px]["user_lastname"]           = $_DATA[$dlvid]["user_lastname"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$dlvid][$px]["item_amount_shipped"]     = printPrice($data[$x]["item_amount_shipped"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$dlvid][$px]["dlv_docnum"]              = $_DATA[$dlvid]["dlv_docnum"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$dlvid][$px]["dlv_delivery_date"]       = $_DATA[$dlvid]["dlv_delivery_date"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$dlvid][$px]["_RECEIVES"]               = $data[$x]["_RECEIVES"];
            $px++;

            //----------------------------------------------------------------------------------
            foreach($data[$x]["_RECEIVES"] AS $sthchgdatarow)
            {  ?>
               <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os"><b class="msg_save_ok"><nobr><img src="/images/menu/icons/plus.png" style="vertical-align:bottom"> Recepción</b></nobr></td>
                  <td class="content_row_os"><b class="msg_save_ok"><?=$sthchgdatarow["stk_num"]?></b></td>
                  <td class="content_row_os" colspan="2"><b class="msg_save_ok">Fecha: <?=date('d.m.Y', $sthchgdatarow["stk_bookdate"])?></b></td>
                  <td class="content_row_os" colspan="2"><b class="msg_save_ok"><?=$sthchgdatarow["st_name"]?></b></td>
                  <td class="content_row_os" align="center"><b class="msg_save_ok"><?=printPrice($sthchgdatarow["item_amount"],2)?></b></td>
                  <td class="content_row_os"><b class="msg_save_ok"><?=$sthchgdatarow["invcnum"]?></b></td>
                  <td class="content_row_os"><b class="msg_save_ok"><?=date('d.m.Y', $sthchgdatarow["stk_bookdate"])?></b></td>
                  <td class="content_row_os">
                     <?php
                     if((int)$sthchgdatarow["stk_status"] == 0)
                     {  ?>
                        <input type="button" class="buttonred" value="Eliminar" style="width:100%;height:22px"
                        onclick="if(askDel('')) { document.xform_itemsearch.delstkid.value = '<?=$sthchgdatarow["stkid"]?>'; document.xform_itemsearch.submit(); }">
                        <?php
                     }
                     else
                        echo "&nbsp;";
                     ?>
                  </td>
               </tr>
               <?php
            }
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
      }
      ?>
   </td>
</tr>
</table>
<?php
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';

//----------------------------------------------------------------------------------
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsFabExt($CON);
if($xlsfile != "")
{
   $doctitle = "Guia-fabricacion-externa-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>