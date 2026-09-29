<?php
//----------------------------------------------------------------------------------
require_once("../../../libs/classes/menu.php");
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

$_REQUEST["agid"] = (int)$_REQUEST["agid"];
/* ?><script> alert("fancy.materales <?php echo $_REQUEST["agid"]; ?>");</script><?php  */

//----------------------------------------------------------------------------------
session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
unset($_SESSION["_CONF"]);

$sql = " select *
         from config_system";
$conf = $CON->select($sql);
$conf = $conf[0];
foreach(array_keys($conf) AS $ckey)
   $_SESSION["_CONF"][$ckey] = trim($conf[$ckey]);

require_once("../../../libs/lang/es.php");
require_once("../../../libs/functions.php");
require_once("../../../libs/functions.erp.php");

//----------------------------------------------------------------------------------

// $hasopenot   = getProdOpenWorkerOT($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"]);
$hasopenot   = getProdOpenWorkerOTId($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"],$_REQUEST["agid"]);
$sql = " select distinct t0.*,
                t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
                t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
                t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id 'prdid',
                t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
                t1x.fab_print_height, v1.add_name 'fabric_color', v2.add_name 'manilla_color',
                fab_print_colors_front_1, fab_print_colors_front_2, fab_print_colors_front_3, fab_print_colors_front_4,fab_print_colors_front_5,
                fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5,
                fab_print_colordesc_1, fab_print_colordesc_2, fab_print_colordesc_3, fab_print_colordesc_4, fab_print_colordesc_5,
                t3x.id 'prdid', t0.ag_amount, t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
                t1.req_prod_adjfile_0, t1.req_prod_adjfile_1, t1.req_prod_adjfile_2, t1.req_prod_adjfile_3, t1.req_prod_adjfile_4, 
                t1.req_prod_adjcomments_0, t1.req_prod_adjcomments_1, t1.req_prod_adjcomments_2, t1.req_prod_adjcomments_3, t1.req_prod_adjcomments_4,
                t1x.fab_design_imagehash, t1.req_company_id, t1.req_shop_id, t1x.fab_mat_fabric_color, t1x.fab_mat_manilla_color
         from prod_agenda t0
         INNER JOIN prod_header t3x       ON t0.ag_prdid = t3x.id and t3x.prd_status >= 2
         INNER JOIN orders t1             ON t0.ag_reqid = t1.id
         LEFT OUTER JOIN company_data t2  ON t1.req_company_id = t2.id
         LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id    = t3.id
         LEFT OUTER JOIN customer t4      ON t1.req_cust_id    = t4.id
         INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
         INNER JOIN item t2x              ON t1x.item_id = t2x.id
         LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
         LEFT OUTER JOIN tran_comments_vals v2 ON t1x.fab_mat_manilla_color = v2.id
         where
         t0.id = {$hasopenot["wok_ag_id"]}";
$agenda = $CON->select($sql);
$agenda = $agenda[0];

//----------------------------------------------------------------------------------
if($agenda["fab_printtype"] == "FLEX")
{
   $sql = " select t2.id, t2.st_name
            from company_shops_storehouses t2 
            where
            t2.st_status               = 1 and
            t2.st_shop_id              = {$agenda["req_shop_id"]} and
            t2.st_unibagflexo_act      = 1
            order by t2.st_name";
   $destsths = $CON->select($sql);
}
elseif($agenda["fab_printtype"] == "SERI")
{
   $sql = " select t2.id, t2.st_name
            from company_shops_storehouses t2 
            where
            t2.st_status               = 1 and
            t2.st_shop_id              = {$agenda["req_shop_id"]} and
            t2.st_unibagseri_act       = 1
            order by t2.st_name";
   $destsths = $CON->select($sql);
}

//----------------------------------------------------------------------------------
$_REQUEST["itemid"] = (int)$_REQUEST["itemid"];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cat_id, t3.cat_title
         from item t1
         INNER JOIN item_productcats t2   ON t1.id = t2.item_id
         INNER JOIN productcats t3        ON t2.cat_id = t3.id
         where
         t1.id = {$_REQUEST["itemid"]}";
$item = $CON->select($sql);
$item = $item[0];

//----------------------------------------------------------------------------------
$sql = " select *
         from productcats
         where
         id = {$item["cat_id"]}";
$pcatdata = $CON->select($sql);
$pcatdata = $pcatdata[0];

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "save")
{
   $_REQUEST["item_amount"] = getPrice(trim($_REQUEST["item_amount"]), 10);

   $annottext = printPrice($_REQUEST["item_amount"], 10)." Unidades";
   
   //METER
   if((int)$_REQUEST["stock_opt"] == 1)
   {
      $unit_full_length          = (int)$item["item_reg_length"];
      $inp_length                = (int)$_REQUEST["item_amount_meter"];
      $_REQUEST["item_amount"]   = round($inp_length / $unit_full_length,10);

      $annottext = printPrice($inp_length)." Metros";
   }
   //KILOS
   elseif((int)$_REQUEST["stock_opt"] == 2)
   {
      if((int)$item["item_reg_kg"])
      {
         $inp_kilos                 = (int)$_REQUEST["item_amount_kg"];
         $_REQUEST["item_amount"]   = round($inp_kilos / $item["item_reg_kg"],10);
      }
      else
      {
         $unit_full_kilos           = ($item["item_reg_length"] * $item["item_reg_width"] * $item["item_reg_gsm"]) / 1000;
         $inp_kilos                 = (int)$_REQUEST["item_amount_kg"];
         $_REQUEST["item_amount"]   = round($inp_kilos / $unit_full_kilos,10);
      }

      $annottext = printPrice($inp_kilos)." Kilogramos";
   }

   if($_REQUEST["item_amount"] > 0.00)
   {
      $shopid           = (int)$agenda["req_shop_id"];
      $stk_fixedsthid   = (int)$destsths[0]["id"];
      $stk_bookdate     = time();
      $currtme          = time();
      $stk_num          = createTransactionNumber($CON, $_SESSION["user_company_id"], "stockchange");

      $sql = " insert into stockchanges
               (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate,
                stk_negative, stk_crtdat, stk_crtusr, stk_fixedsthid, sth_fromprodotid)
               VALUES
               ('{$stk_num}', '{$annottext}',
                 {$_CONFIG["_PRODSTHS"]["_ISSUEID"]}, {$_SESSION["user_company_id"]}, {$shopid}, {$stk_bookdate},
                 1, {$currtme}, {$_SESSION["user_id"]}, {$stk_fixedsthid}, {$_REQUEST["otid"]})";
      $res = $CON->no_result($sql);
      if($res)
      {
         $stk_id = mysql_insert_id();
         $sql = " insert into stockchanges_items
                  (stk_id, item_id, item_pos, item_amount, item_type, item_st_id, item_costprice_brutto,
                   item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes, item_charges_act,
                   item_sellprice_brutto)
                  VALUES
                  ({$stk_id}, {$_REQUEST["itemid"]}, 0, {$_REQUEST["item_amount"]}, 'item',
                   {$stk_fixedsthid}, 0.00, 0.00, 0.00, 0.00, 0, 0)";
         $CON->no_result($sql);
         bookStockChange($CON, $stk_id);

         ?>
         <script language="Javascript">
             parent.document.xform_inp.submit();
         </script>
         <?php
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.com_name, t3.*
         from tran_comments t1
         INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
         INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
         where
         t1.com_status  > 0 and
         t2.cat_id      = {$item["cat_id"]} and
         t3.add_status  > 0
         order by t1.com_name, t3.add_name";
$trancoms = $CON->select($sql);
foreach($trancoms AS $trancom)
{
   $_TRANSCOM[$trancom["add_com_id"]]["NAME"] = $trancom["com_name"];
   $_TRANSCOM[$trancom["add_com_id"]]["OPTS"][$trancom["id"]] = $trancom["add_name"];
}

//----------------------------------------------------------------------------------
unset($_COMVALS);
$sql = " select t1.*, t2.add_name
         from tran_comments_item_vals t1
         INNER JOIN tran_comments_vals t2 ON t1.val_id = t2.id
         where
         t1.item_id = {$item["id"]}";
$comvals = $CON->select($sql);
foreach($comvals AS $comval)
   $_COMVALS[$comval["com_id"]] = $comval["add_name"];

$stock = 0.00;
foreach($destsths AS $deststh)
   $stock += getItemShopStorehouseCurrentStock($CON, $agenda["req_shop_id"], $deststh["id"], $item["id"], "item");
?>
<html style="padding:0px;margin:0px;width:100%;height:100%">
<head>
   <title>Producción - Operador</title>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <link href="/css/fontawesome/css/all.min.css" rel="stylesheet" type="text/css">
   <link href="/css/style.css?ruid=<?=md5(microtime())?>" rel="stylesheet" type="text/css">
   <script language="Javascript"><?php require_once("../../../libs/jscripts/sourcen.php") ?></script>
   <link rel="stylesheet" href="/libs/jscripts/colorbox/colorbox.css">
   <script src="/libs/jscripts/colorbox/jquery.colorbox-min.js"></script>
</head>
<script language="Javascript">
   function checkSthReg(xform)
   {
      if(document.getElementById('stock_opt0').checked)
      {
         return checkform(new Array(xform.item_amount));
      }
      if(document.getElementById('stock_opt1').checked)
      {
         return checkform(new Array(xform.item_amount_meter));
      }
      if(document.getElementById('stock_opt2').checked)
      {
         return checkform(new Array(xform.item_amount_kg));
      }
      return false;
   }
</script>
<body style="background-color:#FFFFFF;padding:0px;margin:0px;width:100%;height:100%">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<input type="hidden" name="jschk_obitpanel" id="jschk_obitpanel" value="<?=(int)$_SESSION["jschk_obitpanel"]?>">
<input type="hidden" name="jschk_currenturl" id="jschk_currenturl" value="<?=$_SERVER["REQUEST_URI"]?>">
<form action="fancy.materiales.php" method="post" name="xform_inp" id="xform_inp" onsubmit="return checkSthReg(this)">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="mode" value="save">
<input type="hidden" name="refid" value="<?=$_REQUEST["refid"]?>">
<input type="hidden" name="itemid" value="<?=$_REQUEST["itemid"]?>">
<input type="hidden" name="otid" value="<?=$_REQUEST["otid"]?>">
<input type="hidden" name="deletemode" value="">
<input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">
<table border="0" width="100%" cellpadding="6" cellspacing="0" style="border:1px solid #CCCCCC;">
<colgroup>
   <col width="140">
   <col>
</colgroup>
<tr>
   <td class="tdheader" colspan="2" style="color: white;background-color: #0EA9A4;">Información del producto</td>
</tr>
<tr>
   <td class="tdleft">Categoria</td>
   <td class="tdnrm"><?=$item["cat_title"]?></td>
</tr>
<tr>
   <td class="tdleft">Material</td>
   <td class="tdnrm"><?=$item["item_title"]?></td>
</tr>
<tr>
   <td class="tdleft">Código</td>
   <td class="tdnrm"><?=$item["item_number_prod"]?></td>
</tr>
<?php
if((int)$pcatdata["cat_itemreg_width"])
{  ?>
   <tr>
      <td class="tdleft">Ancho</td>
      <td class="tdnrm"><?=printPrice($item["item_reg_width"])?> cm</td>
   </tr>
   <?php
}
if((int)$pcatdata["cat_itemreg_length"])
{  ?>
   <tr>
      <td class="tdleft">Longitud</td>
      <td class="tdnrm"><?=printPrice($item["item_reg_length"])?> m</td>
   </tr>
   <?php
}
if((int)$pcatdata["cat_itemreg_gsm"])
{  ?>
   <tr>
      <td class="tdleft">GSM</td>
      <td class="tdnrm"><?=printPrice($item["item_reg_gsm"])?> gr</td>
   </tr>
   <?php
}
if((int)$pcatdata["cat_itemreg_kg"])
{  ?>
   <tr>
      <td class="tdleft">Kilogramos</td>
      <td class="tdnrm"><?=printPrice($item["item_reg_kg"],2)?> kg</td>
   </tr>
   <?php
}
foreach(array_keys($_TRANSCOM) AS $comid)
{  
   $commname = ucwords(strtolower($_TRANSCOM[$comid]["NAME"]));
   ?>
   <tr>
      <td class="tdleft"><?=$commname?></td>
      <td class="tdnrm"><?=$_COMVALS[$comid]?>&nbsp;</td>
   </tr>
   <?php
}

function getProdOpenWorkerOTId($CON, $wrkid, $plantaid, $idagid)
{
   $hasopeninit = getProdOpenWorkerInit($CON, $wrkid, $plantaid);
   if((int)$hasopeninit["id"])
   {
      $sql = " select t1.*, t4.prd_number, t4.prd_reqid
                 from prod_worker_ot t1
                 INNER JOIN prod_worker_init t2   ON t1.wok_init_id = t2.id
                 INNER JOIN prod_agenda t3        ON t1.wok_ag_id = t3.id
                 INNER JOIN prod_header t4        ON t3.ag_prdid = t4.id
                 where
                 t1.wok_init_id = {$hasopeninit["id"]} and
                 t1.wok_ag_id   = {$idagid} and
                 t1.wok_status  = 1";
      $hasopenot = $CON->select($sql);
      $hasopenot = $hasopenot[0];
  
      return $hasopenot;
   }
   return false;
}
?>
<tr>
   <td class="tdleft">Stock</td>
   <td class="tdnrm"><?=printPrice($stock,10)?></td>
</tr>
</table>
<div style="height:10px"></div>
<table border="0" width="100%" cellpadding="6" cellspacing="0" style="border:1px solid #CCCCCC;">
<colgroup>
   <col width="140">
   <col>
</colgroup>
<tr>
   <td class="tdheader" colspan="2" style="color: white;background-color: #0EA9A4;">Opciones de retiro de stock</td>
</tr>
<tr>
   <td class="tdleft">Opción de retiro</td>
   <td class="tdnrm">
      <input type="radio" name="stock_opt" id="stock_opt0" value="0" checked
      onclick="$('.trstockopt').hide(0);$('#idx_stock_unit').show(0);">Por unidad

      <span style="<?if(!(int)$pcatdata["cat_itemreg_length"]) echo "display: none"?>">
         <input type="radio" name="stock_opt" id="stock_opt1" value="1"
         onclick="$('.trstockopt').hide(0);$('#idx_stock_length').show(0);">Por metros
      </span>
      <span style="<?if(!(int)$pcatdata["cat_itemreg_gsm"] && !(int)$pcatdata["cat_itemreg_kg"]) echo "display: none"?>">
         <input type="radio" name="stock_opt"id="stock_opt2" value="2"
         onclick="$('.trstockopt').hide(0);$('#idx_stock_kilos').show(0);">Por peso
      </span>
   </td>
</tr>
<tr id="idx_stock_unit" class="trstockopt">
   <td class="tdleft">Cantidad</td>
   <td class="tdnrm">
      <input type="text" class="inptxt" name="item_amount" style="width:100px;text-align:center"> c/u
   </td>
</tr>
<tr id="idx_stock_length" class="trstockopt" style="display:none">
   <td class="tdleft">Metros</td>
   <td class="tdnrm">
      <input type="text" class="inptxt" name="item_amount_meter" style="width:100px;text-align:center"> m
   </td>
</tr>
<tr id="idx_stock_kilos" class="trstockopt" style="display:none">
   <td class="tdleft">Kilogramos</td>
   <td class="tdnrm">
      <input type="text" class="inptxt" name="item_amount_kg" style="width:100px;text-align:center"> kg
   </td>
</tr>
</table> 
<div style="height:10px"></div>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td width="120" style="padding-right:5px">
      <div class="btngrey" onclick="parent.$.colorbox.close();"
      style="float:left;width:120px">
         <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
      </div>
   </td>
   <td></td>
   <td width="120" style="padding-right:5px">
      <div class="btngreen" onclick="submitForm(document.xform_inp)">
         <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar&nbsp;
      </div>
   </td>
</tr>
</table>

</body>
</html>