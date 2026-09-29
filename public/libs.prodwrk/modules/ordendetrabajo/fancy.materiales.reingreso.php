<?php
//----------------------------------------------------------------------------------
require_once("../../../libs/classes/menu.php");
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");
//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

$_REQUEST["agid"] = (int)$_REQUEST["agid"];

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

$hasopenot   = getProdOpenWorkerOTId($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"],$_REQUEST["agid"]);

$sql = " select distinct t0.*,
                t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
                t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
                t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id 'prdid',
                t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
                t1x.fab_print_height, v1.add_name 'fabric_color', v2.add_name 'manilla_color'
                ,fab_print_colors_front_1
                , fab_print_colors_front_2
                , fab_print_colors_front_3
                , fab_print_colors_front_4
                , fab_print_colors_front_5
                , fab_print_colors_front_6
                , fab_print_colors_front_7
                , fab_print_colors_front_8
                , fab_print_colors_front_9
                , fab_print_colors_front_10
                , fab_print_colors_back_1
                , fab_print_colors_back_2
                , fab_print_colors_back_3
                , fab_print_colors_back_4
                , fab_print_colors_back_5
                , fab_print_colors_back_6
                , fab_print_colors_back_7
                , fab_print_colors_back_8
                , fab_print_colors_back_9
                , fab_print_colors_back_10
                , fab_print_colordesc_1
                , fab_print_colordesc_2
                , fab_print_colordesc_3
                , fab_print_colordesc_4
                , fab_print_colordesc_5
                , fab_print_colordesc_6
                , fab_print_colordesc_7
                , fab_print_colordesc_8
                , fab_print_colordesc_9
                , fab_print_colordesc_10
                , t3x.id 'prdid', t0.ag_amount, t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
                t1.req_prod_adjfile_0, t1.req_prod_adjfile_1, t1.req_prod_adjfile_2, t1.req_prod_adjfile_3, t1.req_prod_adjfile_4,
                t1.req_prod_adjcomments_0, t1.req_prod_adjcomments_1, t1.req_prod_adjcomments_2, t1.req_prod_adjcomments_3, t1.req_prod_adjcomments_4,
                t1x.fab_design_imagehash, t1.req_company_id, t1.req_shop_id, t1x.fab_mat_fabric_color, t1x.fab_mat_manilla_color,t2x.item_reg_gsm
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
// $sql = "select * from equipo where id = {$agenda["ag_equipo_id"]}";
// $equipo = $CON->select($sql);

if($agenda["fab_printtype"] == "FLEX")
   $equipo = "FLEXOGRAFIA";
else
   $equipo = "SERIGRAFIA";

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

//---------------------------------------------------------------------------------
$cantidad_front = 0;
for ($i = 1; $i <= 10; $i++) {
     $campo = $agenda["fab_print_colors_front_".$i];
     if (!empty($campo) && (int)$campo == 1) {
        $cantidad_front++;
     }
}

$cantidad_back = 0;
for ($i = 1; $i <= 10; $i++) {
    $campo = $agenda["fab_print_colors_back_".$i];
    if (!empty($campo) && (int)$campo == 1) {
       $cantidad_back++;
    }
}

//----------------------------------------------------------------------------------
$_ITEM_MATERIAL_STR        = strtoupper($_COMVALS[$_CONFIG["TELA_MATERIAL_CHARACTID"]]);
$_ITEM_TELA_COLOR_STR      = strtoupper($_COMVALS[$_CONFIG["TELA_COLOR_CHARACTID"]]);
$_ITEM_ROLLO_ANCHO_STR     = strtoupper($_COMVALS[32]);
$_ITEM_ROLLO_GRAMAJE_STR   = strtoupper($_COMVALS[42])."GR";
$_ITEM_IMPRESO_EN_STR      = $equipo;

$_NEW_ITEM_NAME  = "{$_ITEM_MATERIAL_STR}/{$_ITEM_TELA_COLOR_STR}/{$_ITEM_ROLLO_ANCHO_STR}/";
$_NEW_ITEM_NAME .= "{$_ITEM_ROLLO_GRAMAJE_STR}/{$_ITEM_IMPRESO_EN_STR}/{$cantidad_front}/{$cantidad_back}";
$_NEW_ITEM_NAME  = trim(addslashes($_NEW_ITEM_NAME));

//----------------------------------------------------------------------------------
$_COMID_COLOR_DORSO  = 50;
$_COMID_COLOR_FRENTE = 51;
$_COMID_TIPO_IMPR    = 52;

//----------------------------------------------------------------------------------
$sql = " select *
         from tran_comments_vals
         where
         add_com_id = {$_COMID_COLOR_DORSO} and
         add_status > 0 and
         add_name = '{$cantidad_back}'";
$_VALID_COLOR_DORSO = $CON->select($sql);
$_VALID_COLOR_DORSO = (int)$_VALID_COLOR_DORSO[0]["id"];

//----------------------------------------------------------------------------------
$sql = " select *
         from tran_comments_vals
         where
         add_com_id = {$_COMID_COLOR_FRENTE} and
         add_status > 0 and
         add_name = '{$cantidad_front}'";
$_VALID_COLOR_FRENTE = $CON->select($sql);
$_VALID_COLOR_FRENTE = (int)$_VALID_COLOR_FRENTE[0]["id"];

//----------------------------------------------------------------------------------
$sql = " select *
         from tran_comments_vals
         where
         add_com_id = {$_COMID_TIPO_IMPR} and
         add_status > 0 and
         add_name = '{$equipo}'";
$_VALID_TIPO_IMPR = $CON->select($sql);
$_VALID_TIPO_IMPR = (int)$_VALID_TIPO_IMPR[0]["id"];

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
      $sql = " select *
               from item
               where
               id = {$_REQUEST["itemid"]}";
      $baseitem = $CON->select($sql);
      $baseitem = $baseitem[0];

      $sql = " select *
               from item
               where
               item_used_parent_id  = {$baseitem["id"]} and
               item_status          > 0 and
               item_title           = '{$_NEW_ITEM_NAME}'";
      $childitem = $CON->select($sql);
      $childitem = $childitem[0];

      //----------------------------------------------------------------------------------
      // CREATE ITEM IF NOT EXISTS
      //----------------------------------------------------------------------------------
      if(!(int)$childitem["id"])
      {
         $colnames = array_keys($baseitem);
         unset($colnames[0]);
         $collist = implode(", ", $colnames);
         $sql = " insert into item
                  ({$collist})
                  select {$collist}
                  from item
                  where
                  id = {$baseitem["id"]}";
         $res = $CON->no_result($sql);
         if($res)
         {
            $newitemid        = mysql_insert_id();
            $childitem["id"]  = $newitemid;

            //----------------------------------------------------------------------------------
            $item_crtdat = time();
            $sql = " update item
                     set
                     item_title           = '{$_NEW_ITEM_NAME}',
                     item_used_parent_id  = {$baseitem["id"]},
                     item_crtusr          = 38,
                     item_updusr          = 38,
                     item_crtdat          = {$item_crtdat},
                     item_upddat          = {$item_crtdat}
                     where
                     id = {$newitemid}";
            $CON->no_result($sql);

            //----------------------------------------------------------------------------------
            $item_number_prod = createNumberSystem($CON, "USEDITEM");
            $sql = " update item
                     set
                     item_purchasable = 0,
                     item_number_prod = '{$item_number_prod}'
                     where
                     id = {$newitemid}";
            $CON->no_result($sql);

            //----------------------------------------------------------------------------------
            $sql = " insert into item_productcats
                     (item_id, cat_id)
                     VALUES
                     ({$newitemid}, 27)";
            $CON->no_result($sql);

            // $sql = " insert into item_productcats
            //          (item_id, cat_id)
            //          select {$newitemid} AS 'item_id', cat_id
            //          from item_productcats
            //          where
            //          item_id = {$baseitem["id"]}";
            // $CON->no_result($sql);

            //----------------------------------------------------------------------------------
            $sql = " select *
                     from tran_comments_item_vals
                     where
                     item_id = {$baseitem["id"]}";
            $cvals = $CON->select($sql);
            foreach($cvals AS $cval)
            {
               $val_desc      = trim(addslashes($cval["val_desc"]));
               $val_desc_eng  = trim(addslashes($cval["val_desc_eng"]));

               $sql = " insert into tran_comments_item_vals
                        (item_id, com_id, val_id, val_desc, val_desc_eng)
                        VALUES
                        ({$newitemid}, {$cval["com_id"]}, {$cval["val_id"]},
                         '{$cval["val_desc"]}', '{$cval["val_desc_eng"]}')";
               $CON->no_result($sql);
            }

            //----------------------------------------------------------------------------------
            if((int)$_COMID_TIPO_IMPR && (int)$_VALID_TIPO_IMPR)
            {
               $sql = " insert into tran_comments_item_vals
                        (item_id, com_id, val_id)
                        VALUES
                        ({$newitemid}, {$_COMID_TIPO_IMPR}, {$_VALID_TIPO_IMPR})";
               $CON->no_result($sql);
            }

            //----------------------------------------------------------------------------------
            if((int)$_COMID_COLOR_FRENTE && (int)$_VALID_COLOR_FRENTE)
            {
               $sql = " insert into tran_comments_item_vals
                        (item_id, com_id, val_id)
                        VALUES
                        ({$newitemid}, {$_COMID_COLOR_FRENTE}, {$_VALID_COLOR_FRENTE})";
               $CON->no_result($sql);
            }

            //----------------------------------------------------------------------------------
            if((int)$_COMID_COLOR_DORSO && (int)$_VALID_COLOR_DORSO)
            {
               $sql = " insert into tran_comments_item_vals
                        (item_id, com_id, val_id)
                        VALUES
                        ({$newitemid}, {$_COMID_COLOR_DORSO}, {$_VALID_COLOR_DORSO})";
               $CON->no_result($sql);
            }

            //----------------------------------------------------------------------------------
            $sql = " insert into item_equipos_rel
                     (item_id, equipo_id)
                     select {$newitemid} AS 'item_id', equipo_id
                     from item_equipos_rel
                     where
                     item_id = {$baseitem["id"]}";
            $CON->no_result($sql);

            //----------------------------------------------------------------------------------
            $sql = " insert into item_shops
                     (item_id, shop_id)
                     select {$newitemid} AS 'item_id', shop_id
                     from item_shops
                     where
                     item_id = {$baseitem["id"]}";
            $CON->no_result($sql);
         }
      }


      if($childitem["id"])
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
                    2, {$_SESSION["user_company_id"]}, {$shopid}, {$stk_bookdate},
                    0, {$currtme}, {$_SESSION["user_id"]}, {$stk_fixedsthid}, {$_REQUEST["otid"]})";
         $res = $CON->no_result($sql);
         if($res)
         {
            $stk_id = mysql_insert_id();
            $sql = " insert into stockchanges_items
                     (stk_id, item_id, item_pos, item_amount, item_type, item_st_id, item_costprice_brutto,
                      item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes, item_charges_act,
                      item_sellprice_brutto)
                     VALUES
                     ({$stk_id}, {$childitem["id"]}, 0, {$_REQUEST["item_amount"]}, 'item',
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

}

$sql = "select i.id,
                i.item_number_prod,
                i.item_title,
                t1.com_id,
                t2.com_name,
                t1.val_id,
                t4.add_name
            from tran_comments_item_vals t1
                inner join tran_comments t2      on t1.com_id = t2.id
                inner join item i                on i.id = t1.item_id
                inner join tran_comments_vals t4 on val_id = t4.id
            where t1.item_id = {$_REQUEST["itemid"]} ";
$datos_item = $CON->select($sql);

$item_reg_gsm = "";
$rollo_ancho  = "";

foreach($datos_item as $datos)
{
    if($datos["com_id"]==32)
    {
       $rollo_ancho = $datos["add_name"];
    }

    if($datos["com_id"]==42)
    {
       $item_reg_gsm = $datos["add_name"];
    }
}





$stock = 0.00;
foreach($destsths AS $deststh)
   $stock += getItemShopStorehouseCurrentStock($CON, $agenda["req_shop_id"], $deststh["id"], $item["id"], "item");

$sql = " select *
         from item
         where
         item_used_parent_id  = {$item["id"]} and
         item_status          > 0 and
         item_title           = '{$_NEW_ITEM_NAME}'";
$childrefitem = $CON->select($sql);
$childrefitem = $childrefitem[0];

$stock_used = 0.00;
foreach($destsths AS $deststh)
   $stock_used += getItemShopStorehouseCurrentStock($CON, $agenda["req_shop_id"], $deststh["id"], $childrefitem["id"], "item");
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
<form action="fancy.materiales.reingreso.php" method="post" name="xform_inp" id="xform_inp" onsubmit="return checkSthReg(this)">
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
        <col width="140">
        <col>
    </colgroup>
    <tr>
        <td class="tdheader" colspan="4" style="color: white;background-color: #0EA9A4;">Información del producto : <?=$item["cat_title"]?></td>
    </tr>
    <tr>
        <td class="tdleft">Codigo</td>
        <td class="tdnrm"><?=$item["item_number_prod"]?></td>
        <td class="tdleft"></td>
        <td class="tdnrm"></td>
    </tr>
    <tr>
        <td class="tdleft">Descripción</td>
        <td class="tdnrm" colspan="3"><?=$item["item_title"]?></td>
    </tr>
    <tr>
        <td class="tdleft">Gramaje</td>
        <td class="tdnrm"><?=$item_reg_gsm?></td>
        <td class="tdleft">Ancho Rollo</td>
        <td class="tdnrm"><?=$rollo_ancho?></td>
    </tr>
    <tr>
        <td class="tdleft">Color Tela</td>
        <td class="tdnrm"><?=$agenda["fabric_color"]?></td>
        <td class="tdleft">Materialidad</td>
        <td class="tdnrm"><?=$agenda["fab_type"]?></td>
    </tr>
</table>

<div style="height:10px"></div>
<table border="0" width="100%" cellpadding="6" cellspacing="0" style="border:1px solid #CCCCCC;">
    <colgroup>
    <col width="140">
    <col>
    <col width="140">
    <col>
    </colgroup>
    <tr>
        <td class="tdheader" colspan="4" style="color: white;background-color: #0EA9A4;">Información de Confirmacion de Compras</td>
    </tr>
    <tr>
        <td class="tdleft">Numero</td>
        <td class="tdnrm"><?=$agenda["req_number"]?></td>
        <td class="tdleft">Cliente</td>
        <td class="tdnrm"><?=$agenda["cust_name"]?></td>
    </tr>
    <tr>
        <td class="tdleft">Diseño</td>
        <td class="tdnrm"><?=$agenda["item_title"]?></td>
        <td class="tdleft">Impreso en</td>
        <td class="tdnrm"><?=$equipo?></td>
    </tr>
    <tr>

        <td class="tdleft">Color Frente</td>
        <td class="tdnrm"><?=$cantidad_front?></td>
        <td class="tdleft">Color Dorso</td>
        <td class="tdnrm"><?=$cantidad_back?></td>
    </tr>
</table>
<div style="height:10px"></div>

<table border="0" width="100%" cellpadding="6" cellspacing="0" style="border:1px solid #CCCCCC;">
    <colgroup>
        <col width="140">
        <col>
    </colgroup>
    <tr>
        <td class="tdheader" colspan="2" style="color: white;background-color: #0EA9A4;">Opciones de Ingreso de stock</td>
    </tr>
    <tr>
        <tr>
            <td class="tdleft">Stock</td>
            <td class="tdnrm"><?=printPrice($stock,10)?></td>
        </tr>
        <tr>
            <td class="tdleft">Stock usado</td>
            <td class="tdnrm"><?=printPrice($stock_used,10)?></td>
        </tr>
        <td class="tdleft">Opción de Ingreso</td>
        <td class="tdnrm">
            <input type="radio" name="stock_opt" id="stock_opt0" value="0"
            onclick="$('.trstockopt').hide(0);$('#idx_stock_unit').show(0);">Por unidad

            <span style="<?if(!(int)$pcatdata["cat_itemreg_length"])?>">
                <input type="radio" name="stock_opt" id="stock_opt1" value="1"
                onclick="$('.trstockopt').hide(0);$('#idx_stock_length').show(0);">Por metros
            </span>
            <span style="<?if(!(int)$pcatdata["cat_itemreg_gsm"] && !(int)$pcatdata["cat_itemreg_kg"])?>">
                <input type="radio" name="stock_opt"id="stock_opt2" value="2" checked
                onclick="$('.trstockopt').hide(0);$('#idx_stock_kilos').show(0);">Por peso
            </span>
        </td>
    </tr>
    <tr id="idx_stock_unit" class="trstockopt" style="display:none">
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
    <tr id="idx_stock_kilos" class="trstockopt" >
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

<?php
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
</form>
</body>
</html>
