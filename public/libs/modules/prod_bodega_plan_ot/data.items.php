<?php
//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cust_name, t2.cust_email, t3.company_short, t4.shop_name, t1.req_cust_id, t2.cust_notes, t7.pay_title,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t8.user_firstname 'seller_firstname', t8.user_lastname 'seller_lastname',
                t9.user_firstname 'cashing_firstname', t9.user_lastname 'cashing_lastname',
                t10.trans_name
         from orders t1
         LEFT OUTER JOIN customer t2         ON t1.req_cust_id          = t2.id
         LEFT OUTER JOIN company_data t3     ON t1.req_company_id       = t3.id
         LEFT OUTER JOIN company_shops t4    ON t1.req_shop_id          = t4.id
         LEFT OUTER JOIN user t5             ON t1.req_updusr           = t5.id
         LEFT OUTER JOIN user t6             ON t1.req_crtusr           = t6.id
         LEFT OUTER JOIN payments t7         ON t1.req_paymentid        = t7.id
         LEFT OUTER JOIN user t8             ON t1.req_userid_seller    = t8.id
         LEFT OUTER JOIN user t9             ON t1.req_userid_cashing   = t9.id
         LEFT OUTER JOIN transports t10      ON t1.req_transportid      = t10.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from storehousechanges t1
         where
         t1.strc_status          > 1 and
         t1.strc_rel_reqid       = {$headdata["id"]} and
         t1.strc_is_frombodega   = 1
         order by t1.id";
$reservas = $CON->select($sql);
$_RESERVAITEMS = Array();
foreach($reservas AS $reserva)
{
   $sql = " select t2.*, t3.item_title, t3.item_number_prod, t4.st_name 'from_sth', t5.st_name 'to_sth'
            from storehousechanges_items t2
            LEFT OUTER JOIN item t3 ON t2.item_id = t3.id
            LEFT OUTER JOIN company_shops_storehouses t4 ON t2.item_st_id = t4.id
            LEFT OUTER JOIN company_shops_storehouses t5 ON t2.item_st_dest_id = t5.id
            where
            t2.strc_id     = {$reserva["id"]} and
            t2.item_type   = 'item'
            order by 3 asc";
   $reservaposdatas = $CON->select($sql);
   foreach($reservaposdatas AS $reservaposdata)
   {
      $_RESERVAITEM              = Array();
      $_RESERVAITEM["TRAN"]      = $reserva["strc_number"];
      $_RESERVAITEM["DATE"]      = date("d.m.Y", $reserva["strc_date"]);
      $_RESERVAITEM["CODE"]      = $reservaposdata["item_number_prod"];
      $_RESERVAITEM["ITEM"]      = $reservaposdata["item_title"];
      $_RESERVAITEM["AMT"]       = $reservaposdata["item_amount"];
      $_RESERVAITEM["FROMSTH"]   = $reservaposdata["from_sth"];
      $_RESERVAITEM["TOSTH"]     = $reservaposdata["to_sth"];

      $_RESERVAITEMS[] = $_RESERVAITEM;   
   }      
}

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from storehousechanges t1
         where
         t1.strc_status             > 1 and
         t1.strc_is_fromplanreqid   = {$headdata["id"]} and
         t1.strc_is_frombodega      = 0
         order by t1.id";
$strhs = $CON->select($sql);
$_TRASPASOITEMS = Array();
foreach($strhs AS $strh)
{
   $sql = " select t2.*, t3.item_title, t3.item_number_prod, t4.st_name 'from_sth', t5.st_name 'to_sth'
            from storehousechanges_items t2
            LEFT OUTER JOIN item t3 ON t2.item_id = t3.id
            LEFT OUTER JOIN company_shops_storehouses t4 ON t2.item_st_id = t4.id
            LEFT OUTER JOIN company_shops_storehouses t5 ON t2.item_st_dest_id = t5.id
            where
            t2.strc_id     = {$strh["id"]} and
            t2.item_type   = 'item'
            order by 3 asc";
   $traspasoposdatas = $CON->select($sql);
   foreach($traspasoposdatas AS $traspasoposdata)
   {
      $_TRASPASOITEM              = Array();
      $_TRASPASOITEM["TRAN"]      = $strh["strc_number"];
      $_TRASPASOITEM["DATE"]      = date("d.m.Y", $strh["strc_date"]);
      $_TRASPASOITEM["CODE"]      = $traspasoposdata["item_number_prod"];
      $_TRASPASOITEM["ITEM"]      = $traspasoposdata["item_title"];
      $_TRASPASOITEM["AMT"]       = $traspasoposdata["item_amount"];
      $_TRASPASOITEM["FROMSTH"]   = $traspasoposdata["from_sth"];
      $_TRASPASOITEM["TOSTH"]     = $traspasoposdata["to_sth"];

      $_TRASPASOITEMS[] = $_TRASPASOITEM;   
   }      
}


//----------------------------------------------------------------------------------
$sql = " select *
         from prod_header
         where
         prd_reqid   = {$_REQUEST["id"]} and
         prd_status  > 0";
$proddata = $CON->select($sql);
$proddata = $proddata[0];

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "generate")
{
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_amount_") !== false && strpos($reqkey, "item_amount_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $fromsthid  = (int)$_REQUEST["fromsthid_{$idx}"];
         $tosthid    = (int)$_REQUEST["tosthid_{$idx}"];
         $itemamt    = (float)getPrice($_REQUEST["item_amount_{$idx}"], 10);

         if($fromsthid && $tosthid && $itemamt)
         {
            unset($newrow);
            $newrow["fromsthid"]    = $fromsthid;
            $newrow["tosthid"]      = $tosthid;
            $newrow["itemamt"]      = $itemamt;
            $newrow["itemid"]       = $idx;
            $_GENDATA[] = $newrow;
         }
      }
   }

   if(count($_GENDATA))
   {
      $strcnumber = createTransactionNumber($CON, $headdata["req_company_id"], "storehouse");
      $sql_date   = mktime(15, 0, 0, date("m"), date("d"), date("Y"));
      $currtme    = time();

      $sql = " insert into storehousechanges
               (strc_company_id, strc_shop_id, strc_company_dest_id, strc_shop_dest_id, strc_date, 
                strc_number, strc_crtusr, strc_crtdat, strc_is_fromplanreqid)
               VALUES
               ({$headdata["req_company_id"]}, {$headdata["req_shop_id"]}, {$headdata["req_company_id"]}, {$headdata["req_shop_id"]},
                {$sql_date}, '{$strcnumber}', {$_SESSION["user_id"]}, {$currtme}, {$headdata["id"]})";
      $res = $CON->no_result($sql);
      if($res)
      {
         $strcid = mysql_insert_id();
         $poscounter = 0;
         foreach($_GENDATA AS $_GENDATAROW)
         {
            $sql = " insert into storehousechanges_items
                     (strc_id, item_id, item_pos, item_type, item_amount, item_st_id, item_st_dest_id, item_charges_act)
                     VALUES
                     ({$strcid}, {$_GENDATAROW["itemid"]}, {$poscounter}, 'item', {$_GENDATAROW["itemamt"]},
                      {$_GENDATAROW["fromsthid"]}, {$_GENDATAROW["tosthid"]}, 0)";
            $CON->no_result($sql);
            $poscounter++;
         }

         bookStorehouseChange($CON, $strcid);
      }         
   }
   ?>
   <script language="Javascript">
      location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=items&id=<?=$_REQUEST["id"]?>';
   </script>
   <?php
   exit;
}

//----------------------------------------------------------------------------------
$sql = " select *
         from prod_amtplan
         where
         prodplan_prdid = {$proddata["id"]}
         order by prodplan_date asc";
$prodplans = $CON->select($sql);

//----------------------------------------------------------------------------------
$posdata    = getOrderPos($CON, $_REQUEST["id"]);
$thispos    = $posdata[0];

//----------------------------------------------------------------------------------
$sql = " select add_name
         from tran_comments_vals
         where
         id = {$thispos["fab_mat_fabric_color"]}";
$fabric_color = $CON->select($sql);
$fabric_color = $fabric_color[0]["add_name"];

//----------------------------------------------------------------------------------
$sql = " select add_name
         from tran_comments_vals
         where
         id = {$thispos["fab_mat_manilla_color"]}";
$manilla_color = $CON->select($sql);
$manilla_color = $manilla_color[0]["add_name"];

$sql = " select distinct t1.id, t1.item_number_prod, t1.item_title, t7.cat_itemreg_width,
                t7.cat_itemreg_gsm, t7.cat_itemreg_length, t7.cat_itemreg_kg,
                t1.item_reg_width, t1.item_reg_gsm, t1.item_reg_length, t1.item_reg_kg
         from item t1
         INNER JOIN item_productcats t6   ON t1.id = t6.item_id
         INNER JOIN productcats t7        ON t6.cat_id = t7.id
         where
         t1.item_status    = 1 and
         t1.item_released  = 1 and 
         t6.cat_id         = {$_CONFIG["TELA_CATID"]}
         order by t1.item_title";
$alltelas = $CON->select($sql);
$seltelas = Array();
for($x = 0; $x < count($alltelas) && $alltelas != false; $x++)    
{
   $sql = " select *
            from tran_comments_item_vals t1
            INNER JOIN tran_comments t2 ON t1.com_id = t2.id
            INNER JOIN tran_comments_vals t3 ON t1.val_id = t3.id
            where
            t1.item_id = {$alltelas[$x]["id"]} and
            t2.com_status > 0 ";
   $chars = $CON->select($sql);
   foreach($chars AS $char)
   {
      if((int)$char["com_id"] == $_CONFIG["TELA_COLOR_CHARACTID"]) 
      {
         if((int)$char["val_id"] == $thispos["fab_mat_fabric_color"])
         {
            $idx = 1;
            if((int)$alltelas[$x]["item_reg_gsm"] == (int)$thispos["fab_mat_gramms"])
               $idx = 0;

            $hasmat = false;
            foreach($chars AS $xchar)
            {
               if($xchar["add_name"] == $thispos["fab_type"])
                  $hasmat = true;
            }

            if($hasmat)
               $seltelas[$idx][] = $alltelas[$x];   
         }
      }
   }
}

$sql = " select distinct t1.id, t1.item_number_prod, t1.item_title, t7.cat_itemreg_width,
                t7.cat_itemreg_gsm, t7.cat_itemreg_length, t7.cat_itemreg_kg,
                t1.item_reg_width, t1.item_reg_gsm, t1.item_reg_length, t1.item_reg_kg
         from item t1
         INNER JOIN item_productcats t6   ON t1.id = t6.item_id
         INNER JOIN productcats t7        ON t6.cat_id = t7.id
         where
         t1.item_status    = 1 and
         t1.item_released  = 1 and 
         t6.cat_id         IN ({$_CONFIG["PINTURAS_CATID"]})
         order by t1.item_title";
$allpinturas = $CON->select($sql);
$selpinturas = Array();
for($x = 0; $x < count($allpinturas) && $allpinturas != false; $x++)    
{
   $sql = " select *
            from tran_comments_item_vals t1
            INNER JOIN tran_comments t2 ON t1.com_id = t2.id
            INNER JOIN tran_comments_vals t3 ON t1.val_id = t3.id
            where
            t1.item_id = {$allpinturas[$x]["id"]} and
            t2.com_status > 0 ";
   $chars = $CON->select($sql);

   foreach($chars AS $char)
   {
      if($thispos["fab_printtype"] == "FLEX")
      {
         if((int)$char["com_id"] == $_CONFIG["FLEX_TINTA_COLOR_CHARACTID"] ||
            (int)$char["com_id"] == $_CONFIG["FLEX_TINTA_COLOR_CHARACTID_2"]) 
         {
            $selpinturas[] = $allpinturas[$x];   
         }
      }
      elseif($thispos["fab_printtype"] == "SERI")
      {
         if((int)$char["com_id"] == $_CONFIG["SERI_TINTA_COLOR_CHARACTID"] ||
            (int)$char["com_id"] == $_CONFIG["SERI_TINTA_COLOR_CHARACTID_2"]) 
         {
            $selpinturas[] = $allpinturas[$x];   
         }
      }
   }
}


//----------------------------------------------------------------------------------
$sql = " select t2.id, t2.st_name
         from company_shops_storehouses t2 
         where
         t2.st_status               = 1 and
         t2.st_shop_id              = {$headdata["req_shop_id"]} and
         t2.st_repuestos_act        = 0 and
         t2.st_unibagflexo_act      = 0 and
         t2.st_unibagseri_act       = 0 and
         t2.st_unibagsellador_act   = 0
         order by t2.st_name";
$origsths = $CON->select($sql);

//----------------------------------------------------------------------------------
if($thispos["fab_printtype"] == "FLEX")
{
   $sql = " select t2.id, t2.st_name
            from company_shops_storehouses t2 
            where
            t2.st_status               = 1 and
            t2.st_shop_id              = {$headdata["req_shop_id"]} and
            t2.st_unibagflexo_act      = 1
            order by t2.st_name";
   $destsths = $CON->select($sql);
}
elseif($thispos["fab_printtype"] == "SERI")
{
   $sql = " select t2.id, t2.st_name
            from company_shops_storehouses t2 
            where
            t2.st_status               = 1 and
            t2.st_shop_id              = {$headdata["req_shop_id"]} and
            t2.st_unibagseri_act       = 1
            order by t2.st_name";
   $destsths = $CON->select($sql);
}
?>
<form action="index.php" method="post" name="form_strc">
<input type="hidden" name="subexec" value="generate">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="exec" value="edit">
<?=Nifty_printH("box1", "1020")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Entregas al cliente</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Cantidad</td>
   <td class="content_tbl_subheader">Fecha Entrega</td>
</tr>
<?php
for($x = 0; $x < count($prodplans) && $prodplans != false; $x++)
{  ?>
   <tr>
      <td class="content_row"><?=printPrice($prodplans[$x]["prodplan_amt"])?></td>
      <td class="content_row"><?=date("d.m.Y", $prodplans[$x]["prodplan_date"])?></td>
   </tr>
   <?php
}
?> 
</table>
<?=Nifty_printF()?>
<br>
<?php
if(count($_RESERVAITEMS))
{  ?>
   <?=Nifty_printH("box1", "1020")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
   <colgroup>
      <col width="80">
      <col width="80">
      <col width="100">
      <col>
      <col width="100">
      <col width="120">
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="7" style="background-color:#BF3540;color:white;text-shadow:none">Reservas realizadas anteriormente</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Transacción</td>
      <td class="content_tbl_subheader">Fecha</td>
      <td class="content_tbl_subheader">Código</td>
      <td class="content_tbl_subheader">Descripción</td>
      <td class="content_tbl_subheader">Cantidad</td>
      <td class="content_tbl_subheader">Origen</td>
      <td class="content_tbl_subheader">Destino</td>
   </tr>
   <?php
   $x = 0;
   foreach($_RESERVAITEMS AS $_RESERVAITEM)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$_RESERVAITEM["TRAN"]?>&nbsp;</td>
         <td class="content_row"><?=$_RESERVAITEM["DATE"]?>&nbsp;</td>
         <td class="content_row"><?=$_RESERVAITEM["CODE"]?>&nbsp;</td>
         <td class="content_row"><?=$_RESERVAITEM["ITEM"]?>&nbsp;</td>
         <td class="content_row"><?=printPrice($_RESERVAITEM["AMT"],10)?>&nbsp;</td>
         <td class="content_row"><?=$_RESERVAITEM["FROMSTH"]?>&nbsp;</td>
         <td class="content_row"><?=$_RESERVAITEM["TOSTH"]?>&nbsp;</td>
      </tr>
      <?php
      $x++;
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}
if(count($_TRASPASOITEMS))
{  ?>
   <?=Nifty_printH("box1", "1020")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
   <colgroup>
      <col width="80">
      <col width="80">
      <col width="100">
      <col>
      <col width="100">
      <col width="120">
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="7" style="background-color:#0EA9A6;color:white;text-shadow:none">Entregas de materiales realizadas</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Transacción</td>
      <td class="content_tbl_subheader">Fecha</td>
      <td class="content_tbl_subheader">Código</td>
      <td class="content_tbl_subheader">Descripción</td>
      <td class="content_tbl_subheader">Cantidad</td>
      <td class="content_tbl_subheader">Origen</td>
      <td class="content_tbl_subheader">Destino</td>
   </tr>
   <?php
   $x = 0;
   foreach($_TRASPASOITEMS AS $_TRASPASOITEM)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$_TRASPASOITEM["TRAN"]?>&nbsp;</td>
         <td class="content_row"><?=$_TRASPASOITEM["DATE"]?>&nbsp;</td>
         <td class="content_row"><?=$_TRASPASOITEM["CODE"]?>&nbsp;</td>
         <td class="content_row"><?=$_TRASPASOITEM["ITEM"]?>&nbsp;</td>
         <td class="content_row"><?=printPrice($_TRASPASOITEM["AMT"],10)?>&nbsp;</td>
         <td class="content_row"><?=$_TRASPASOITEM["FROMSTH"]?>&nbsp;</td>
         <td class="content_row"><?=$_TRASPASOITEM["TOSTH"]?>&nbsp;</td>
      </tr>
      <?php
      $x++;
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}
?>
<b style="font-size:20px;font-family:Arial">Traspaso de telas</b>
<div style="height:12px"></div>
<?=Nifty_printH("box1", "1020")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Requerimiento</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Tipo</td>
   <td class="content_tbl_subheader">Material</td>
   <td class="content_tbl_subheader">Color</td>
   <td class="content_tbl_subheader">Medidas</td>
</tr>
<tr>
   <td class="content_row">Bolsa</td>
   <td class="content_row"><?=$thispos["fab_type"]?>, <?=$thispos["fab_mat_gramms"]?>gr</td>
   <td class="content_row"><?=$fabric_color?></td>
   <td class="content_row">
      <?=(int)$thispos["fab_med_width"]?>x<?=(int)$thispos["fab_med_height"]?> cm
      <?php
      if((int)$thispos["fab_med_fuelle"])
         echo ", Fuelle ".(int)$thispos["fab_med_fuelle"]." cm";
      ?>
   </td>
</tr>
<tr>
   <td class="content_row">Manillas</td>
   <td class="content_row"><?=$thispos["fab_type"]?>, <?=$thispos["fab_mat_gramms"]?>gr</td>
   <td class="content_row"><?=$manilla_color?></td>
   <td class="content_row"><?=(int)$thispos["fab_manilla_length"]?> cm</td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "1020")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col>
   <col>
   <col>
   <col>
   <col>
   <col width="120">
   <col width="165">
   <col width="165">
</colgroup>
<tr>
   <td class="content_tbl_header">Código</td>
   <td class="content_tbl_header">Material</td>
   <td class="content_tbl_header">Ancho</td>
   <td class="content_tbl_header">Longitud</td>
   <td class="content_tbl_header">GSM</td>
   <td class="content_tbl_header">Cantidad</td>
   <td class="content_tbl_header">Origen</td>
   <td class="content_tbl_header">Destino</td>
</tr>
<?php
$x = 0;
foreach(array_keys($seltelas) AS $idx)
{  
   $trcss = "";
   if($idx == 1)
      $trcss = "color:red";
   foreach($seltelas[$idx] AS $row)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" style="<?=$trcss?>"><?=$row["item_number_prod"]?>&nbsp;</td>
         <td class="content_row"><?=$row["item_title"]?>&nbsp;</td>
         <td class="content_row"><?=(int)$row["item_reg_width"]?>cm</td>
         <td class="content_row"><?=(int)$row["item_reg_length"]?>m</td>
         <td class="content_row" style="<?=$trcss?>"><?=(int)$row["item_reg_gsm"]?>gr</td>
         <td class="content_row">
            <nobr>
            <input type="text" class="text" style="width:70px;text-align:center" 
            name="item_amount_<?=$row["id"]?>" id="item_amount_<?=$row["id"]?>">
            <input type="button" class="button" value="Calc."
            onclick="showFancybox('./libs/modules/prod_storehousechanges/fancy.materiales.calc.php?idx=<?=$row["id"]?>&itemid=<?=$row["id"]?>', 'iframe', 800, 600, 'auto');">
            </nobr>
         </td>
         <td class="content_row">
            <select class="text" style="width:100%" name="fromsthid_<?=$row["id"]?>">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($origsths AS $origsth)
               {  
                  $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["req_shop_id"], $origsth["id"], (int)$row["id"], "item");
                  ?>
                  <option value="<?=$origsth["id"]?>"><?=$origsth["st_name"]?> (<?=printPrice($currstock,10)?>)</option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_row">
            <select class="text" style="width:100%" name="tosthid_<?=$row["id"]?>">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($destsths AS $deststh)
               {  
                  $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["req_shop_id"], $deststh["id"], (int)$row["id"], "item");
                  ?>
                  <option value="<?=$deststh["id"]?>"><?=$deststh["st_name"]?> (<?=printPrice($currstock,10)?>)</option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <?php
      $x++;
   }
}
?>
</table>
<?=Nifty_printF()?>
<br>
<b style="font-size:20px;font-family:Arial">Traspaso de pinturas</b>
<div style="height:12px"></div>
<?=Nifty_printH("box1", "1020")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Requerimiento</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Nº</td>
   <td class="content_tbl_subheader">Frente/Dorso</td>
   <td class="content_tbl_subheader">Pantone</td>
   <td class="content_tbl_subheader">Area de impresión</td>
</tr>
<?php
for($x = 1; $x <= 5; $x++)
{
   if((int)$thispos["fab_print_colors_front_{$x}"] || (int)$thispos["fab_print_colors_back_{$x}"])
   {  ?>      
      <tr>
         <td class="content_row">Color #<?=$x?></td>
         <td class="content_row">
            <?php
            if((int)$thispos["fab_print_colors_front_{$x}"] && !(int)$thispos["fab_print_colors_back_{$x}"])
               echo "Frente";
            elseif(!(int)$thispos["fab_print_colors_front_{$x}"] && (int)$thispos["fab_print_colors_back_{$x}"])
               echo "Dorso";
            elseif((int)$thispos["fab_print_colors_front_{$x}"] && (int)$thispos["fab_print_colors_back_{$x}"])
               echo "Frente/Dorso";
            ?>
         </td>
         <td class="content_row"><?=$thispos["fab_print_colordesc_{$x}"]?>&nbsp;</td>
         <td class="content_row"><?=(int)$thispos["fab_print_width"]?> x <?=(int)$thispos["fab_print_height"]?> cm</td>
      </tr>
      <?php
   }
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "1020")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col>
   <col>
   <col>
   <col width="120">
   <col width="165">
   <col width="165">
</colgroup>
<tr>
   <td class="content_tbl_header">Código</td>
   <td class="content_tbl_header">Material</td>
   <td class="content_tbl_header">Peso</td>
   <td class="content_tbl_header">Cantidad</td>
   <td class="content_tbl_header">Origen</td>
   <td class="content_tbl_header">Destino</td>
</tr>
<?php
$x = 0;
foreach($selpinturas AS $row)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row" style="<?=$trcss?>"><?=$row["item_number_prod"]?>&nbsp;</td>
      <td class="content_row"><?=$row["item_title"]?>&nbsp;</td>
      <td class="content_row"><?=(int)$row["item_reg_kg"]?>kg</td>
      <td class="content_row">
         <nobr>
         <input type="text" class="text" style="width:70px;text-align:center" 
         name="item_amount_<?=$row["id"]?>" id="item_amount_<?=$row["id"]?>">
         <input type="button" class="button" value="Calc."
         onclick="showFancybox('./libs/modules/prod_storehousechanges/fancy.materiales.calc.php?idx=<?=$row["id"]?>&itemid=<?=$row["id"]?>', 'iframe', 800, 600, 'auto');">
         </nobr>
      </td>
      <td class="content_row">
         <select class="text" style="width:100%" name="fromsthid_<?=$row["id"]?>">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($origsths AS $origsth)
            {  
               $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["req_shop_id"], $origsth["id"], (int)$row["id"], "item");
               ?>
               <option value="<?=$origsth["id"]?>"><?=$origsth["st_name"]?> (<?=printPrice($currstock,10)?>)</option>
               <?php
            }
            ?>
         </select>
      </td>
      <td class="content_row">
         <select class="text" style="width:100%" name="tosthid_<?=$row["id"]?>">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($destsths AS $deststh)
            {  
               $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["req_shop_id"], $deststh["id"], (int)$row["id"], "item");
               ?>
               <option value="<?=$deststh["id"]?>"><?=$deststh["st_name"]?> (<?=printPrice($currstock,10)?>)</option>
               <?php
            }
            ?>
         </select>
      </td>
   </tr>
   <?php
   $x++;
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "1020")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="200" style="padding-right:0px" id="idx_fin_button">
      <?php
      printButton("Generar traspasos", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_strc.submit();}", "tick-circle-frame");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>

