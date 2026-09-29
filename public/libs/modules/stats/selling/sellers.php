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
$_sesmodulename         = "invc_seller_stats";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2,1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número Doc."      => 1,
                                "Fecha"            => 2,
                                "Vencimiento"      => 3,
                                "Canal"            => 14,
                                "Cliente"          => 4,
                                "RUT"              => 5,
                                "Fecha Creacion"   => 15,
                                "Vendedor"         => 6,
                                "Monto total"      => 7);
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_seller"]        = (int)$_REQUEST["sql_seller"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_origtype"]      = (int)$_REQUEST["sql_origtype"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_paystatus"]     = $_REQUEST["sql_paystatus"];
   $_SESSION[$_sesmodulename]["sql_vencstatus"]    = $_REQUEST["sql_vencstatus"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
   $_SESSION[$_sesmodulename]["sql_cust_presu"]  = trim(addslashes($_REQUEST["sql_cust_presu"]));
}

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON);
$sellers    = getSellers($CON);
if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_shop"])
{
   $first = false;
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"] && !$first)
      {
         $_SESSION[$_sesmodulename]["sql_shop"] = $shop["id"];
         $first = true;
      }
}
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
if(!is_array($_SESSION[$_sesmodulename]["sql_paystatus"]))
   $_SESSION[$_sesmodulename]["sql_paystatus"] = Array(0=>0);
if(!is_array($_SESSION[$_sesmodulename]["sql_vencstatus"]))
   $_SESSION[$_sesmodulename]["sql_vencstatus"] = Array(0=>0,1=>1);
   
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
if((int)$_SESSION[$_sesmodulename]["sql_origtype"] == 0)
{
   $datsql = " select t1.invc_docnumber
                    , t1.invc_date
                    , t1.invc_estpay_date
                    , t6.cust_company
                    , t6.cust_rut
                    , CONCAT(t7.user_firstname, ' ', t7.user_lastname) 'seller_name'
                    , t1.invc_total_netto 'invc_total_brutto'
                    , t1.id
                    , t1.invc_payed
                    , 'type' 'invoice'
                    , 'note_type' '0'
                    , t7.user_comission_perc
                    , t1.invc_userid_seller 'seller_id'
                    , descripcion as canal
                    , t6.cust_crtdat 
                    , t6.cust_presupuesto
                    , t6.cust_permanente
                    , f.total_factura_cliente   as 'cantidaddefacturasemitidas'
               from invoices_sell t1
               INNER JOIN company_data t4                ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
               LEFT OUTER JOIN customer t6               ON ( t1.invc_cust_id = t6.id )
               INNER JOIN user t7                   ON ( t1.invc_userid_seller = t7.id )
               left outer join parametros p_canal   on p_canal.tabla = 'CANAL' and t6.cust_canal = p_canal.codigo
               LEFT JOIN (SELECT invc_cust_id, COUNT(*) AS total_factura_cliente FROM invoices_sell where invc_status > 1 and invc_status  < 4 
                                 GROUP BY invc_cust_id) f ON f.invc_cust_id = t6.id
               where
               t1.invc_status       > 1 and
               t1.invc_status       < 4 and
               t1.invc_date between {$sql_datefrom} and {$sql_dateto}";

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_seller"])
      $seasql .= " and t1.invc_userid_seller   = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $seasql .= " and t1.invc_cust_id   = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if($_SESSION[$_sesmodulename]["sql_cust_presu"] != "")
      $seasql .= " and t6.cust_presupuesto = '{$_SESSION[$_sesmodulename]["sql_cust_presu"]}' ";
   
   if((int)$userdata["user_invclimit_perm"])
      $seasql .= " and
                     ( 
                        (
                           select count(txx2.id) 'cc'
                           from invoices_sell_parts txx
                           INNER JOIN orders txx2 ON txx.part_req_id = txx2.id
                           where
                           txx.part_invc_id  = t1.id and
                           txx.part_req_id   > 0 and
                           txx2.req_crtusr   = {$_SESSION["user_id"]}
                        ) > 0
                        or
                        (
                           select count(txx3.id) 'cc'
                           from invoices_sell_parts txx
                           INNER JOIN orders txx2 ON txx.part_req_id = txx2.id
                           INNER JOIN offers txx3 ON txx2.req_offerid = txx3.id
                           where
                           txx.part_invc_id  = t1.id and
                           txx.part_req_id   > 0 and
                           txx3.req_crtusr   = {$_SESSION["user_id"]}
                        )
                     ) ";

   $datsql .= $seasql;
   $datsql .= " UNION ALL
                select t1.invc_docnumber, t1.invc_date, t1.invc_estpay_date, t6.cust_company, t6.cust_rut,
                      CONCAT(t7.user_firstname, ' ', t7.user_lastname) 'seller_name',
                      t1.invc_total_netto 'invc_total_brutto', t1.id, t1.invc_payed, 'type' 'invoice', 'note_type' '0',
                      t7.user_comission_perc, t1.invc_userid_seller 'seller_id', descripcion as canal, t6.cust_crtdat , cust_presupuesto , t6.cust_permanente
                       ,f.total_factura_cliente   as 'cantidaddefacturasemitidas'
               from invoices_sell_bol t1
               INNER JOIN company_data t4                ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
               LEFT OUTER JOIN customer t6               ON ( t1.invc_cust_id = t6.id )
               INNER JOIN user t7                   ON ( t1.invc_userid_seller = t7.id )
               left outer join parametros p_canal   on p_canal.tabla = 'CANAL' and t6.cust_canal = p_canal.codigo
               LEFT JOIN (SELECT invc_cust_id, COUNT(*) AS total_factura_cliente FROM invoices_sell_bol where invc_status > 1 and invc_status  < 4 
                                 GROUP BY invc_cust_id) f ON f.invc_cust_id = t6.id
         
               where
               t1.invc_status       > 1 and
               t1.invc_status       < 4 and
               t1.invc_date between {$sql_datefrom} and {$sql_dateto}";
   $datsql .= $seasql;

   //----------------------------------------------------------------------------------
   $currtme = time();

   //----------------------------------------------------------------------------------
   $seastatstr = "";
   foreach($_SESSION[$_sesmodulename]["sql_paystatus"] AS $seastat)
      $seastatstr .= $seastat.",";
   $seastatstr = substr($seastatstr, 0, -1);
   $datsql .= " and t1.invc_payed IN ({$seastatstr}) ";

   if($_SESSION[$_sesmodulename]["sql_cust_presu"] != "")
      $seasql .= " and t6.cust_presupuesto = '{$_SESSION[$_sesmodulename]["sql_cust_presu"]}' ";

   if(array_search(0, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false &&
      array_search(1, $_SESSION[$_sesmodulename]["sql_vencstatus"]) === false)
      $datsql .= " and !(t1.invc_payed = 0
                   and t1.invc_estpay_date > 0
                   and t1.invc_estpay_date <= {$currtme}) ";
   if(array_search(1, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false &&
      array_search(0, $_SESSION[$_sesmodulename]["sql_vencstatus"]) === false)
      $datsql .= " and t1.invc_payed = 0
                   and t1.invc_estpay_date > 0
                   and t1.invc_estpay_date <= {$currtme} ";
   if($_SESSION[$_sesmodulename]["sql_cust_presu"] != "")
      $seasql .= " and t6.cust_presupuesto = '{$_SESSION[$_sesmodulename]["sql_cust_presu"]}' ";                   
                   
   $datsql .= " UNION ALL
                select t1.note_docnumber 'invc_docnumber', t1.note_date 'invc_date',
                       t1.note_estpay_date 'invc_estpay_date', t6.cust_company, t6.cust_rut,
                      CONCAT(t7.user_firstname, ' ', t7.user_lastname) 'seller_name',
                      t1.note_total_netto 'invc_total_brutto', t1.id, t1.note_payed 'invc_payed',
                      'type' 'note', t1.note_type, t7.user_comission_perc, t1.note_userid_seller 'seller_id', descripcion as canal, t6.cust_crtdat
                      ,t6.cust_presupuesto , t6.cust_permanente ,f.total_factura_cliente   as 'cantidaddefacturasemitidas'
               from invoices_notes_sell t1
               INNER JOIN company_data t4                ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
               LEFT OUTER JOIN customer t6               ON ( t1.note_cust_id = t6.id )
               INNER JOIN user t7                   ON ( t1.note_userid_seller = t7.id )
               left outer join parametros p_canal   on p_canal.tabla = 'CANAL' and t6.cust_canal = p_canal.codigo
               LEFT JOIN (SELECT note_cust_id, COUNT(*) AS total_factura_cliente FROM invoices_notes_sell where note_status > 1 and note_status  < 4 
                                 GROUP BY note_cust_id) f ON f.note_cust_id = t6.id
         
               where
               t1.note_status       > 1 and
               t1.note_status       < 4 and
               t1.note_date between {$sql_datefrom} and {$sql_dateto} ";
   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $datsql .= " and t1.note_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $datsql .= " and t1.note_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_seller"])
      $datsql .= " and t1.note_userid_seller = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $datsql .= " and t1.note_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if($_SESSION[$_sesmodulename]["sql_cust_presu"] != "")
      $datsql .= " and t6.cust_presupuesto = '{$_SESSION[$_sesmodulename]["sql_cust_presu"]}' ";   
   if((int)$userdata["user_invclimit_perm"])
      $datsql .= " and 1 = 2 ";

   //----------------------------------------------------------------------------------
   $seastatstr = "";
   foreach($_SESSION[$_sesmodulename]["sql_paystatus"] AS $seastat)
      $seastatstr .= $seastat.",";
   $seastatstr = substr($seastatstr, 0, -1);
   $datsql .= " and t1.note_payed IN ({$seastatstr}) ";

   if(array_search(0, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false &&
      array_search(1, $_SESSION[$_sesmodulename]["sql_vencstatus"]) === false)
      $datsql .= " and !(t1.note_payed = 0
                   and t1.note_estpay_date > 0
                   and t1.note_estpay_date <= {$currtme}) ";
   if(array_search(1, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false &&
      array_search(0, $_SESSION[$_sesmodulename]["sql_vencstatus"]) === false)
      $datsql .= " and t1.note_payed = 0
                   and t1.note_estpay_date > 0
                   and t1.note_estpay_date <= {$currtme} ";
   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}";
   $invoices = $CON->select($datsql);
}
else
{
   $datsql = " select t1.req_crtdat 'invc_date', t1.req_number 'invc_docnumber', '0' AS 'invc_estpay_date',
                      t6.cust_company, t6.cust_rut,
                      CONCAT(t7.user_firstname, ' ', t7.user_lastname) 'seller_name',
                      t1.req_total_netto 'invc_total_brutto', t1.id, '1' AS 'invc_payed', 'type' 'order', 'note_type' '0',
                      t7.user_comission_perc, t1.req_userid_seller 'seller_id', descripcion as canal, t6.cust_crtdat , t6.cust_presupuesto , t6.cust_permanente
                      ,f.total_factura_cliente   as 'cantidaddefacturasemitidas'
               from orders t1
               INNER JOIN company_data t4                ON ( t1.req_company_id = t4.id and t4.company_status = 1 )
               LEFT OUTER JOIN customer t6               ON ( t1.req_cust_id = t6.id )
               INNER JOIN user t7                        ON ( t1.req_userid_seller = t7.id )
               left outer join parametros p_canal   on p_canal.tabla = 'CANAL' and t6.cust_canal = p_canal.codigo
               LEFT JOIN (SELECT req_cust_id, COUNT(*) AS total_factura_cliente FROM orders where req_status > 1 and req_status  < 4 
                                 GROUP BY req_cust_id) f ON f.req_cust_id = t6.id
               where
               t1.req_status > 1 and
               t1.req_crtdat between {$sql_datefrom} and {$sql_dateto} ";
               
    //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $datsql .= " and t1.req_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $datsql .= " and t1.req_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_seller"])
      $datsql .= " and t1.req_userid_seller = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $datsql .= " and t1.req_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if((int)$userdata["user_orderlimit_perm"])
      $datsql .= " and t1.req_crtusr = {$_SESSION["user_id"]} ";
   if($_SESSION[$_sesmodulename]["sql_cust_presu"] != "")
      $datsql .= " and t6.cust_presupuesto = '{$_SESSION[$_sesmodulename]["sql_cust_presu"]}' ";
      
   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}";
   $invoices = $CON->select($datsql);
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
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
{
   $sql = " select cust_name
            from customer
            where
            id = {$_SESSION[$_sesmodulename]["sql_customer"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = $custdata[0]["cust_name"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = "TODO";
   
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
{
   $sql = " select user_firstname, user_lastname
            from user
            where
            id = {$_SESSION[$_sesmodulename]["sql_seller"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["SELLER"] = $custdata[0]["user_firstname"]." ".$custdata[0]["user_lastname"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["SELLER"] = "TODO";

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

printJSsetCompanyShop($shops);

//----------------------------------------------------------------------------------
function orderSellerByNameCallback($a, $b)
{
   return (strcmp($a["seller_name"], $b["seller_name"]));
}

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
   <td height="30"><b class="content_header">Ventas por vendedor</b></td>
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
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Vendedor</td>
         <td class="content_row">
            <select name="sql_seller" id="sql_seller" class="text" style="width:375px">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($sellers AS $seller)
               {  ?>
                  <option value="<?=$seller["id"]?>" <?php if($seller["id"] == $_SESSION[$_sesmodulename]["sql_seller"]) echo "selected"; ?>>
                  <?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Pagado</td>
         <td class="content_row">
            <input type="checkbox" name="sql_paystatus[]" value="0" <?php if(array_search(0, $_SESSION[$_sesmodulename]["sql_paystatus"]) !== false) echo "checked"?>>No pagado
            <input type="checkbox" name="sql_paystatus[]" value="1" <?php if(array_search(1, $_SESSION[$_sesmodulename]["sql_paystatus"]) !== false) echo "checked"?>>Pagado
            (solo aplica a facturas)
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Fuente</td>
         <td class="content_row">
            <input type="radio" name="sql_origtype" value="0" <?php if((int)$_SESSION[$_sesmodulename]["sql_origtype"] == 0) echo "checked"?>>Facturas/NC
            <input type="radio" name="sql_origtype" value="1" <?php if((int)$_SESSION[$_sesmodulename]["sql_origtype"] == 1) echo "checked"?>>Confirmación de compra
         </td>
         <td class="content_rowl">Vencido</td>
         <td class="content_row">
            <input type="checkbox" name="sql_vencstatus[]" value="0" <?php if(array_search(0, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false) echo "checked"?>>No vencido
            <input type="checkbox" name="sql_vencstatus[]" value="1" <?php if(array_search(1, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false) echo "checked"?>>Vencido
            (solo aplica a facturas)
         </td>
      </tr>
         <td class="content_rowl" height="32">Cliente en presupuesto</td>
         <td class="content_row">
            <nobr><input type="radio" name="sql_cust_presu" value="" <?php if ($_SESSION[$_sesmodulename]["sql_cust_presu"] == "") echo "checked"; ?>>Todos</nobr>
            <nobr><input type="radio" name="sql_cust_presu" value="1" <?php if ($_SESSION[$_sesmodulename]["sql_cust_presu"] == "1") echo "checked"; ?>>SI</nobr>
            <nobr><input type="radio" name="sql_cust_presu" value="0" <?php if ($_SESSION[$_sesmodulename]["sql_cust_presu"] == "0") echo "checked"; ?>>NO</nobr>
         </td>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="132">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($invoices) > 0 && $invoices != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($invoices) > 0 && $invoices != false)
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
      <?=Nifty_printH("box1", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
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
         <col>
         <col>         
         <col>         
         <col>         
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os" valign="top">Tipo</td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 8)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><nobr>% Com.</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><nobr>$ Com.</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><nobr>Presupuestado</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><nobr>Permanente</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><nobr>Cantidad de Generadas</nobr></td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($invoices) && $invoices != false; $x++)
      {
      
         $estpay = "";
         if((int)$_SESSION[$_sesmodulename]["sql_origtype"] == 0)
         {
            if(!(int)$invoices[$x]["invc_estpay_date"])
               $invoices[$x]["invc_estpay_date"] = $invoices[$x]["invc_date"];

            $estpay = date('d/m/Y', $invoices[$x]["invc_estpay_date"]);
         }
         else
         {
            if((int)$invoices[$x]["invc_estpay_date"])
               $estpay = date('d/m/Y', $invoices[$x]["invc_estpay_date"]);
         }

         if($invoices[$x]["type"] == "typenote" && (int)$invoices[$x]["note_type"] == 1)
         {
            $invoices[$x]["invc_total_brutto"] = $invoices[$x]["invc_total_brutto"] * -1;
            $doctype = "NOTA/C";
         }
         elseif($invoices[$x]["type"] == "typenote" && (int)$invoices[$x]["note_type"] != 1)
            $doctype = "NOTA/D";
         else
            $doctype = "FACTURA/BOL";

         if($invoices[$x]["type"] == "typeorder")
            $doctype = "CONFIRM.COMPRA";

         $comamt = round($invoices[$x]["invc_total_brutto"] / 100 * $invoices[$x]["user_comission_perc"]);

         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$doctype?></td>
            <td class="content_row_os"><?=$invoices[$x]["invc_docnumber"]?></td>
            <td class="content_row_os"><?=date('d/m/Y', $invoices[$x]["invc_date"])?></td>
            <td class="content_row_os"><?=$estpay?>&nbsp;</td>
            <td class="content_row_os"><nobr><?=$invoices[$x]["canal"]?></nobr>&nbsp;</td>
            <td class="content_row_os"><nobr><?=$invoices[$x]["cust_company"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$invoices[$x]["cust_rut"]?></nobr>&nbsp;</td>
            <td class="content_row_os"><nobr><?=date('d/m/Y',$invoices[$x]["cust_crtdat"])?></nobr>&nbsp;</td>
            <td class="content_row_os"><nobr><?=$invoices[$x]["seller_name"]?></nobr>&nbsp;</td>
            <td class="content_row_os" align="right"><?=printPrice($invoices[$x]["invc_total_brutto"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($invoices[$x]["user_comission_perc"],2)?>&nbsp;</td>
            <td class="content_row_os" align="right"><?=printPrice($comamt,0)?>&nbsp;</td>
            <td class="content_row_os"><nobr><?=($invoices[$x]["cust_presupuesto"] == 1) ? 'SI' : 'NO'?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=($invoices[$x]["cust_permanente"] == 1) ? 'SI' : 'NO'?>&nbsp;</nobr></td>
            <td class="content_row_os"><?=$invoices[$x]["cantidaddefacturasemitidas"]?></td>
         </tr>
         <?php
         $_TOTAL["brutto"]    += $invoices[$x]["invc_total_brutto"];
         $_TOTAL["comamt"]    += $comamt;
         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["doctype"]           = $doctype;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_docnumber"]    = $invoices[$x]["invc_docnumber"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_date"]         = date('d/m/Y', $invoices[$x]["invc_date"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_estpay_date"]  = $estpay;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_company"]      = $invoices[$x]["cust_company"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_rut"]          = $invoices[$x]["cust_rut"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["seller_name"]       = $invoices[$x]["seller_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["user_comission_perc"] = printPrice($invoices[$x]["user_comission_perc"],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_brutto"] = printPrice($invoices[$x]["invc_total_brutto"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["comamt"] = printPrice($comamt);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["canal"] = $invoices[$x]["canal"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["creacion"] = $invoices[$x]["cust_crtdat"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["presupuesto"] = ($invoices[$x]["cust_presupuesto"] == 1) ? 'SI' : 'NO';
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_permanente"] = ($invoices[$x]["cust_permanente"] == 1) ? 'SI' : 'NO';
        
         
         $_SESSION["STATS"][$_sesmodulename]["OVERW"][$invoices[$x]["seller_id"]]["NAME"]    = $invoices[$x]["seller_name"];
         $_SESSION["STATS"][$_sesmodulename]["OVERW"][$invoices[$x]["seller_id"]]["VALUE"]  += $invoices[$x]["invc_total_brutto"];
         $_SESSION["STATS"][$_sesmodulename]["OVERW"][$invoices[$x]["seller_id"]]["COMM"]   += $comamt;
         $_SESSION["STATS"][$_sesmodulename]["OVERW"][$invoices[$x]["seller_id"]]["RETEN"]   = round($_SESSION["STATS"][$_sesmodulename]["OVERW"][$invoices[$x]["seller_id"]]["COMM"] / 100 * 10,0);
         $_SESSION["STATS"][$_sesmodulename]["OVERW"][$invoices[$x]["seller_id"]]["PAYMENT"] = $_SESSION["STATS"][$_sesmodulename]["OVERW"][$invoices[$x]["seller_id"]]["COMM"] - $_SESSION["STATS"][$_sesmodulename]["OVERW"][$invoices[$x]["seller_id"]]["RETEN"];


      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="10" align="center">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      else
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_os content_row_totals" colspan="7">TOTAL</td>
            <td class="content_row_os content_row_totals" align="right"><nobr><?=printPrice($_TOTAL["brutto"])?></nobr></td>
            <td class="content_row_os content_row_totals">&nbsp;</td>
            <td class="content_row_os content_row_totals" align="right"><nobr><?=printPrice($_TOTAL["comamt"])?></nobr></td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_docnumber"]    = "<b>TOTAL</b>";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_date"]         = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_estpay_date"]  = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_company"]      = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_rut"]          = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["seller_name"]       = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_brutto"] = printPrice($_TOTAL["brutto"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["comamt"] = printPrice($_TOTAL["comamt"]);
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?php
      orderSellerByNameCallback($_SESSION["STATS"][$_sesmodulename]["OVERW"]);
      ?>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col width="120">
         <col width="120">
         <col width="120">
         <col width="120">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="5">Resumen por vendedor</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os" align="left">Nombre Vendedor</td>
         <td class="content_tbl_subheader content_row_os" align="right">Venta Total</td>
         <td class="content_tbl_subheader content_row_os" align="right">Comisión</td>
         <td class="content_tbl_subheader content_row_os" align="right">Retención</td>
         <td class="content_tbl_subheader content_row_os" align="right">Monto Pago</td>
      </tr>
      <?php
      foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["OVERW"]) AS $sellerid)
      {  ?>
         <tr>
            <td class="content_row_os"><?=$_SESSION["STATS"][$_sesmodulename]["OVERW"][$sellerid]["NAME"]?></td>
            <td class="content_row_os" align="right"><?=printPrice($_SESSION["STATS"][$_sesmodulename]["OVERW"][$sellerid]["VALUE"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($_SESSION["STATS"][$_sesmodulename]["OVERW"][$sellerid]["COMM"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($_SESSION["STATS"][$_sesmodulename]["OVERW"][$sellerid]["RETEN"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($_SESSION["STATS"][$_sesmodulename]["OVERW"][$sellerid]["PAYMENT"])?></td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsSellingSellers($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsSellingSellers($CON);
  
if($pdffile != "")
{
   $doctitle = "Ventas-por-vendedor-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Ventas-por-vendedor-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>