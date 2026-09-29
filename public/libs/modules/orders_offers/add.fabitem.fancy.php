<?php
//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.company_short, t4.shop_name, t7.pay_title,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t8.country_name, t9.name, t10.nombre, t11.pro_name,
                t12.pl_title
         from offers t1
         LEFT OUTER JOIN company_data t3     ON t1.req_company_id       = t3.id
         LEFT OUTER JOIN company_shops t4    ON t1.req_shop_id          = t4.id
         LEFT OUTER JOIN user t5             ON t1.req_updusr           = t5.id
         LEFT OUTER JOIN user t6             ON t1.req_crtusr           = t6.id
         LEFT OUTER JOIN payments t7         ON t1.req_paymentid        = t7.id
         LEFT OUTER JOIN country t8          ON t1.req_cust_countryid   = t8.id
         LEFT OUTER JOIN regions t9          ON t1.req_cust_regionid    = t9.id
         LEFT OUTER JOIN comunas t10         ON t1.req_cust_comunaid    = t10.id
         LEFT OUTER JOIN provincias t11      ON t1.req_cust_provinciaid = t11.id
         LEFT OUTER JOIN price_lists_fab t12 ON t1.req_plid_fab = t12.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$_FRONT_COLORS    = 0;
$_BACK_COLORS     = 0;
$_COLORS_2SIDES   = 0;
$_COLORS_1SIDES   = 0;
foreach(array_keys($_REQUEST) AS $reqkey)
{
   if(strpos($reqkey, "fab_print_colors_front_") !== false && strpos($reqkey, "fab_print_colors_front_") == 0)
   {
      $idx = substr($reqkey, strrpos($reqkey, "_") +1);
      if((int)$_REQUEST["fab_print_colors_front_{$idx}"] && (int)$_REQUEST["fab_print_colors_back_{$idx}"])
         $_COLORS_2SIDES++;
      else
         $_COLORS_1SIDES++;
         
      $_FRONT_COLORS++;
   }
   if(strpos($reqkey, "fab_print_colors_back_") !== false && strpos($reqkey, "fab_print_colors_back_") == 0)
   {
      $idx = substr($reqkey, strrpos($reqkey, "_") +1);

      if((int)$_REQUEST["fab_print_colors_back_{$idx}"] && !(int)$_REQUEST["fab_print_colors_front_{$idx}"])
         $_COLORS_1SIDES++;
      
      $_BACK_COLORS++;
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "additems")
{
   $_REQUEST["item_compdesc"]             = trim(addslashes($_REQUEST["item_compdesc"]));
   $_REQUEST["fab_type"]                  = trim(addslashes($_REQUEST["fab_type"]));
   $_REQUEST["fab_printtype"]             = trim(addslashes($_REQUEST["fab_printtype"]));
   $_REQUEST["fab_med_width"]             = (int)$_REQUEST["final_fab_med_width"];
   $_REQUEST["fab_med_height"]            = (int)$_REQUEST["final_fab_med_height"];
   $_REQUEST["fab_med_fuelle"]            = (int)$_REQUEST["final_fab_med_fuelle"];
   $_REQUEST["fab_print_width"]           = (int)$_REQUEST["final_fab_print_width"];
   $_REQUEST["fab_print_height"]          = (int)$_REQUEST["final_fab_print_height"];
   $_REQUEST["fab_manilla_length"]        = (int)$_REQUEST["final_fab_manilla_length"];
   $_REQUEST["fab_mat_fabric_color"]      = (int)$_REQUEST["fab_mat_fabric_color"];
   $_REQUEST["fab_mat_manilla_color"]     = (int)$_REQUEST["fab_mat_manilla_color"];
   $_REQUEST["fab_print_colors_front_1"]  = (int)$_REQUEST["fab_print_colors_front_1"];
   $_REQUEST["fab_print_colors_back_1"]   = (int)$_REQUEST["fab_print_colors_back_1"];
   $_REQUEST["fab_print_colors_front_2"]  = (int)$_REQUEST["fab_print_colors_front_2"];
   $_REQUEST["fab_print_colors_back_2"]   = (int)$_REQUEST["fab_print_colors_back_2"];
   $_REQUEST["fab_print_colors_front_3"]  = (int)$_REQUEST["fab_print_colors_front_3"];
   $_REQUEST["fab_print_colors_back_3"]   = (int)$_REQUEST["fab_print_colors_back_3"];
   $_REQUEST["fab_print_colors_front_4"]  = (int)$_REQUEST["fab_print_colors_front_4"];
   $_REQUEST["fab_print_colors_back_4"]   = (int)$_REQUEST["fab_print_colors_back_4"];
   $_REQUEST["fab_print_colors_front_5"]  = (int)$_REQUEST["fab_print_colors_front_5"];
   $_REQUEST["fab_print_colors_back_5"]   = (int)$_REQUEST["fab_print_colors_back_5"];
   
   if((int)$headdata["req_prices_cnt"] == 1)
   {
      foreach($_REQUEST["additems"] AS $additem)
      {
         $sql = " select MAX(item_pos) 'max_item_pos'
                  from offers_items
                  where
                  req_id = {$_REQUEST["id"]}";
         $max_item_pos = $CON->select($sql);
         if($max_item_pos[0]["max_item_pos"] != "")
            $max_item_pos = $max_item_pos[0]["max_item_pos"] + 1;
         else
            $max_item_pos = 0;

         $additemarr    = explode("_", $additem);
         $item_amount   = (int)$additemarr[0];
         $sql_sellnetto = (int)$additemarr[1];
         $sql_taxesperc = $_SESSION["_CONF"]["conf_taxes"];
         $sql_taxes     = round($sql_sellnetto / 100 * $sql_taxesperc); 
         $sql_sellprice = $sql_sellnetto + $sql_taxes;

         $sql = " select item_img
                  from item
                  where
                  id = {$_REQUEST["fab_item_id"]}";
         $item_img = $CON->select($sql);
         $item_img = $item_img[0]["item_img"];

         $new_item_img = "";
         if($item_img != "")
         {
            $new_item_img = $_REQUEST["id"]."_".md5(microtime()).$item_img;
            copy("{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/items/{$item_img}", "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.offer/{$new_item_img}");
         }
         
         $sql = " insert into offers_items
                  (req_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                   item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes, item_desc,
                   item_sellprice_netto_ccval1, item_sellprice_netto_ccval2, item_sellprice_netto_ccval3,
                   item_sellprice_netto_ccval4, item_sellprice_netto_ccval5, item_compdesc,
                   fab_type, fab_printtype, fab_med_width, fab_med_height, fab_med_fuelle, fab_print_width,
                   fab_print_height, fab_manilla_length, fab_mat_fabric_color, fab_mat_manilla_color, fab_print_colors_front_1,
                   fab_print_colors_back_1, fab_print_colors_front_2, fab_print_colors_back_2, fab_print_colors_front_3,
                   fab_print_colors_back_3, fab_print_colors_front_4, fab_print_colors_back_4, fab_print_colors_front_5,
                   fab_print_colors_back_5, item_img_hash)
                  VALUES
                  ({$_REQUEST["id"]}, {$_REQUEST["fab_item_id"]}, {$max_item_pos}, {$item_amount}, 'item',
                   {$sql_sellprice}, {$sql_taxesperc}, {$sql_sellnetto}, {$sql_taxes}, '',
                   0, 0, 0, 0, 0, '{$_REQUEST["item_compdesc"]}',
                   '{$_REQUEST["fab_type"]}', '{$_REQUEST["fab_printtype"]}',
                   {$_REQUEST["fab_med_width"]}, {$_REQUEST["fab_med_height"]}, {$_REQUEST["fab_med_fuelle"]},
                   {$_REQUEST["fab_print_width"]}, {$_REQUEST["fab_print_height"]}, {$_REQUEST["fab_manilla_length"]},
                   {$_REQUEST["fab_mat_fabric_color"]}, {$_REQUEST["fab_mat_manilla_color"]}, 
                   {$_REQUEST["fab_print_colors_front_1"]}, {$_REQUEST["fab_print_colors_back_1"]},
                   {$_REQUEST["fab_print_colors_front_2"]}, {$_REQUEST["fab_print_colors_back_2"]},
                   {$_REQUEST["fab_print_colors_front_3"]}, {$_REQUEST["fab_print_colors_back_3"]},
                   {$_REQUEST["fab_print_colors_front_4"]}, {$_REQUEST["fab_print_colors_back_4"]},
                   {$_REQUEST["fab_print_colors_front_5"]}, {$_REQUEST["fab_print_colors_back_5"]},
                   '{$new_item_img}')";
         $CON->no_result($sql);
         recalcOrderItem($CON, $_REQUEST["id"], $_REQUEST["fab_item_id"], $max_item_pos, true, "OFFER");
      }
      recalcOrder($CON, $_REQUEST["id"], "OFFER");
   }
   elseif((int)$headdata["req_prices_cnt"] > 1)
   {
      $sql = " select MAX(item_pos) 'max_item_pos'
               from offers_items
               where
               req_id = {$_REQUEST["id"]}";
      $max_item_pos = $CON->select($sql);
      if($max_item_pos[0]["max_item_pos"] != "")
         $max_item_pos = $max_item_pos[0]["max_item_pos"] + 1;
      else
         $max_item_pos = 0;


      $_PRICESSQL["item_sellprice_netto_ccval1"] = 0;
      $_PRICESSQL["item_sellprice_netto_ccval2"] = 0;
      $_PRICESSQL["item_sellprice_netto_ccval3"] = 0;
      $_PRICESSQL["item_sellprice_netto_ccval4"] = 0;
      $_PRICESSQL["item_sellprice_netto_ccval5"] = 0;
      for($xx = 1; $xx <= $headdata["req_prices_cnt"]; $xx++)
      {
         if($headdata["req_prices_ccval{$xx}"] > 0)
         {
            $hccval = (int)$headdata["req_prices_ccval{$xx}"];
            foreach($_REQUEST["additems"] AS $additem)
            {
               $additemarr    = explode("_", $additem);
               $item_amount   = (int)$additemarr[0];
               $sql_sellnetto = (int)$additemarr[1];

               if((int)$item_amount == $hccval)
               {
                  $_PRICESSQL["item_sellprice_netto_ccval{$xx}"] = $sql_sellnetto;
               }
            }
         }
      }

      $sql_taxesperc = $_SESSION["_CONF"]["conf_taxes"];
      $sql = " insert into offers_items
               (req_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes, item_desc,
                item_sellprice_netto_ccval1, item_sellprice_netto_ccval2, item_sellprice_netto_ccval3,
                item_sellprice_netto_ccval4, item_sellprice_netto_ccval5, item_compdesc,
                fab_type, fab_printtype, fab_med_width, fab_med_height, fab_med_fuelle, fab_print_width,
                fab_print_height, fab_manilla_length, fab_mat_fabric_color, fab_mat_manilla_color, fab_print_colors_front_1,
                fab_print_colors_back_1, fab_print_colors_front_2, fab_print_colors_back_2, fab_print_colors_front_3,
                fab_print_colors_back_3, fab_print_colors_front_4, fab_print_colors_back_4, fab_print_colors_front_5,
                fab_print_colors_back_5)
               VALUES
               ({$_REQUEST["id"]}, {$_REQUEST["fab_item_id"]}, {$max_item_pos}, 0, 'item',
                0, {$sql_taxesperc}, 0, 0, '',
                {$_PRICESSQL["item_sellprice_netto_ccval1"]}, {$_PRICESSQL["item_sellprice_netto_ccval2"]},
                {$_PRICESSQL["item_sellprice_netto_ccval3"]}, {$_PRICESSQL["item_sellprice_netto_ccval4"]},
                {$_PRICESSQL["item_sellprice_netto_ccval5"]}, '{$_REQUEST["item_compdesc"]}',
                '{$_REQUEST["fab_type"]}', '{$_REQUEST["fab_printtype"]}',
                 {$_REQUEST["fab_med_width"]}, {$_REQUEST["fab_med_height"]}, {$_REQUEST["fab_med_fuelle"]},
                 {$_REQUEST["fab_print_width"]}, {$_REQUEST["fab_print_height"]}, {$_REQUEST["fab_manilla_length"]},
                 {$_REQUEST["fab_mat_fabric_color"]}, {$_REQUEST["fab_mat_manilla_color"]},
                 {$_REQUEST["fab_print_colors_front_1"]}, {$_REQUEST["fab_print_colors_back_1"]},
                 {$_REQUEST["fab_print_colors_front_2"]}, {$_REQUEST["fab_print_colors_back_2"]},
                 {$_REQUEST["fab_print_colors_front_3"]}, {$_REQUEST["fab_print_colors_back_3"]},
                 {$_REQUEST["fab_print_colors_front_4"]}, {$_REQUEST["fab_print_colors_back_4"]},
                 {$_REQUEST["fab_print_colors_front_5"]}, {$_REQUEST["fab_print_colors_back_5"]})";
      $CON->no_result($sql);
   }

   
   ?>
   <script language="JavaScript">
      parent.location.href = '/index.php?mid=770&exec=edit&subcatexec=basic&id=<?=$_REQUEST["id"]?>';
   </script>
   <?php
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "set_fab_type")
{
   $_REQUEST["fab_item_id"]      = "";
   $_REQUEST["fab_item_posid"]   = "";
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "set_fab_item_id")
{
   $_REQUEST["fab_item_posid"]   = "";
}

//----------------------------------------------------------------------------------
if($_REQUEST["fab_type"] != "")
{
   $sql = " select distinct t2.id, t2.item_number_prod, t2.item_title, t2.item_fabricate_prefix,
                   t2.item_fabricate_fabrictext, t2.item_fabricate_fuelletext
            from price_lists_fab_items t1
            INNER JOIN item t2 ON t1.fab_item_id = t2.id
            where
            t1.pl_id       = {$headdata["req_plid_fab"]} and
            t1.fab_type    = '{$_REQUEST["fab_type"]}' and
            t1.fab_active  = 1
            order by t2.item_title, t2.item_number_prod";
   $items = $CON->select($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["fab_item_id"] != "")
{
   $sql = " select distinct t1.id, t1.fab_med_width, t1.fab_med_height, t1.fab_med_fuelle, t1.fab_desc,
                   t1.fab_print_width, t1.fab_print_height, t1.fab_manilla_length, t1.fab_inc_id,
                   t1.fab_noprint_discount
            from price_lists_fab_items t1
            where
            t1.pl_id       = {$headdata["req_plid_fab"]} and
            t1.fab_type    = '{$_REQUEST["fab_type"]}' and
            t1.fab_item_id = {$_REQUEST["fab_item_id"]} and
            t1.fab_active  = 1
            order by t1.fab_med_width, t1.fab_med_height, t1.fab_med_fuelle, t1.fab_desc, t1.fab_print_width, t1.fab_print_height";
   $medidas = $CON->select($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "set_fab_item_posid")
{
   $_REQUEST["final_fab_med_width"]       = "";
   $_REQUEST["final_fab_med_height"]      = "";
   $_REQUEST["final_fab_med_fuelle"]      = "";
   $_REQUEST["final_fab_print_width"]     = "";
   $_REQUEST["final_fab_print_height"]    = "";
   $_REQUEST["final_fab_manilla_length"]  = "";

   foreach($medidas AS $medida)
   {
      if($medida["id"] == $_REQUEST["fab_item_posid"])
      {
         $_REQUEST["final_fab_med_width"]       = (int)$medida["fab_med_width"];
         $_REQUEST["final_fab_med_height"]      = (int)$medida["fab_med_height"];
         $_REQUEST["final_fab_med_fuelle"]      = (int)$medida["fab_med_fuelle"];
         $_REQUEST["final_fab_print_width"]     = (int)$medida["fab_print_width"];
         $_REQUEST["final_fab_print_height"]    = (int)$medida["fab_print_height"];
         $_REQUEST["final_fab_manilla_length"]  = (int)$medida["fab_manilla_length"];
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select t2.id, t2.add_name
         from tran_comments t1
         INNER JOIN tran_comments_vals t2 ON t2.add_com_id = t1.id
         where
         t1.id          = {$_CONFIG["TELA_COLOR_CHARACTID"]} and
         t2.add_status  = 1
         order by t2.add_name";
$colors = $CON->select($sql);

// echo "<pre>";
// print_r($_REQUEST);

//----------------------------------------------------------------------------------
?>
<div style="height:3px"></div>
<form action="iframe.fancy.php" method="post" name="idx_text" class="fokusfirst">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="module" value="<?=$_REQUEST["module"]?>">
<input type="hidden" name="subexec" value="reload">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<?=Nifty_printH("box2", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="60">
   <col>
   <col width="60">
   <col>
   <col width="100">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="6">Datos básicos</td>
</tr>
<tr>
   <td class="content_rowl">Folio</td>
   <td class="content_row"><?=$headdata["req_number"]?></td>
   <td class="content_rowl">Cliente</td>
   <td class="content_row"><?=$headdata["req_cust_company"]?></td>
   <td class="content_rowl">Lista de precios</td>
   <td class="content_row"><?=$headdata["pl_title"]?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box2", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col width="300">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Configuración del producto</td>
</tr>
<tr>
   <td class="content_rowl" height="1">Tipo Material</td>
   <td class="content_row">
      <?php
      $sql = " select *
               from fabric_types
               where
               fabt_status > 0
               order by fabt_code";
      $fabric_types = $CON->select($sql);
      ?>
      <select name="fab_type" class="text" style="width:100%;"
      onchange="document.idx_text.subexec.value = 'set_fab_type';document.idx_text.submit();">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($fabric_types AS $fabric_type)
         {  ?>
            <option value="<?=$fabric_type["fabt_code"]?>" <?if($_REQUEST["fab_type"] == $fabric_type["fabt_code"]) echo "selected"?>><?=$fabric_type["fabt_code"]?> - <?=$fabric_type["fabt_name"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl" valign="top" rowspan="11">
      <?php
      if($_REQUEST["fab_type"] != "" && (int)$_REQUEST["fab_item_id"] && (int)$_REQUEST["fab_item_posid"] &&
         $_REQUEST["fab_printtype"] != "" &&(int)$_REQUEST["fab_mat_fabric_color"] &&
         ((int)$_REQUEST["fab_mat_manilla_color"] || (!(int)$_REQUEST["fab_mat_manilla_color"] && !(int)$_REQUEST["final_fab_manilla_length"])))
      {
         $itemtitle = "";
         $fulle_addtxt = "";

         //----------------------------------------------------------------------------------
         $omitFabDesc = false;
         foreach($items AS $item)
         {
            if($item["id"] == $_REQUEST["fab_item_id"])
            {
               $itemtitle = "<b>{$item["item_fabricate_prefix"]} modelo {$item["item_title"]}</b>";
               if($item["item_fabricate_fuelletext"] != "")
                  $fulle_addtxt = " {$item["item_fabricate_fuelletext"]}";
               
               if($item["item_fabricate_fabrictext"] != "")
               {
                  $itemtitle .= " {$item["item_fabricate_fabrictext"]}, medidas ";
                  $omitFabDesc = true;
               }
            }
         }
         $gentext = $itemtitle;

         if(!$omitFabDesc)
         {
            $sql = " select fabt_desc
                     from fabric_types
                     where
                     fabt_status > 0 and
                     fabt_code   = '{$_REQUEST["fab_type"]}'";
            $fabt_desc = $CON->select($sql);
            $fabt_desc = $fabt_desc[0]["fabt_desc"];

            if($fabt_desc != "")
               $gentext .= " {$fabt_desc}, medidas ";
            else
               $gentext .= " medidas ";
         }

         //----------------------------------------------------------------------------------
         foreach($medidas AS $medida)
         {
            if($medida["id"] == $_REQUEST["fab_item_posid"])
            {
               $gentext .= "<b>".(int)$_REQUEST["final_fab_med_width"]."cm (ancho) x ".(int)$_REQUEST["final_fab_med_height"]."cm (alto) ";
               if((int)$_REQUEST["final_fab_med_fuelle"])
                  $gentext .= "x ".(int)$_REQUEST["final_fab_med_fuelle"]."cm (fuelle{$fulle_addtxt})";
               $gentext .= "</b>, ";

               $pos_incid = $medida["fab_inc_id"];
               $fab_noprint_discount = $medida["fab_noprint_discount"];

               foreach($colors AS $color)
               {
                  if($color["id"] == $_REQUEST["fab_mat_fabric_color"])
                     $gentext .= "color {$color["add_name"]}, ";
               }

               if((int)$_REQUEST["final_fab_manilla_length"])
               {
                  $gentext .= "con manillas de largo ".(int)$_REQUEST["final_fab_manilla_length"]." cm de color ";
                  foreach($colors AS $color)
                  {
                     if($color["id"] == $_REQUEST["fab_mat_manilla_color"])
                        $gentext .= "{$color["add_name"]},";
                  }
               }

               $gentext .= " impresión por ";
               if($_REQUEST["fab_printtype"] == "FLEX")
                  $gentext .= "<b>Flexografía";
               elseif($_REQUEST["fab_printtype"] == "SERI")
                  $gentext .= "<b>Serigrafía";

               $_x_item_compdesc = " a ".(int)$_FRONT_COLORS."/".(int)$_BACK_COLORS." colores.</b>";

               if((int)$_FRONT_COLORS == (int)$_BACK_COLORS && (int)$_FRONT_COLORS > 0)
               {
                  if((int)$_FRONT_COLORS == 1)
                     $_x_item_compdesc = " a ".$_LANG["MODULE"]["OFFERNUMS"][(int)$_FRONT_COLORS]." color, ambos lados.";
                  else
                     $_x_item_compdesc = " a ".$_LANG["MODULE"]["OFFERNUMS"][(int)$_FRONT_COLORS]." colores, ambos lados.";
               }
               elseif((int)$_FRONT_COLORS > (int)$_BACK_COLORS && (int)$_BACK_COLORS == 0)
               {
                  if((int)$_FRONT_COLORS == 1)
                     $_x_item_compdesc = " a ".$_LANG["MODULE"]["OFFERNUMS"][(int)$_FRONT_COLORS]." color, un lado.";
                  else
                     $_x_item_compdesc = " a ".$_LANG["MODULE"]["OFFERNUMS"][(int)$_FRONT_COLORS]." colores, un lado.";
               }

               $gentext .= $_x_item_compdesc;
               
               //$gentext .= " a ".(int)$_FRONT_COLORS."/".(int)$_BACK_COLORS." colores.</b>";
               $gentext .= " Área de impresión: ".(int)$_REQUEST["final_fab_print_width"]."cm (ancho) x ".(int)$_REQUEST["final_fab_print_height"]." cm (alto).";
            }
         }
         ?>
         <textarea name="item_compdesc" class="text" style="width:100%;height:70px;display:none"><?=stripslashes($gentext)?></textarea>
         <div style="padding:6px;border:1px dashed #666666"><?=$gentext?></div>
         <?php
         if((int)$headdata["req_prices_cnt"] == 1)
         {
            $sql = " select *
                     from price_lists_fab_amounts
                     where
                     pl_id = {$headdata["req_plid_fab"]} and
                     amt_status > 0 
                     order by amt_val asc";
            $amounts = $CON->select($sql);
            ?>
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col>
               <col>
               <col>
               <col>
               <col>
               <col width="20">
            </colgroup>
            <tr>
               <td class="content_tbl_header">Cantidad</td>
               <td class="content_tbl_header" align="right">Precio/Base</td>
               <td class="content_tbl_header" align="right">Precio/Incr.</td>
               <td class="content_tbl_header" align="right">Precio/Unit.</td>
               <td class="content_tbl_header" align="right">Precio/Total</td>
               <td class="content_tbl_header" align="center">Act.</td>
            </tr>
            <?php
            $ax = 0;
            foreach($amounts AS $amount)
            {
               $sql = " select prc_price
                        from price_lists_fab_items_prices
                        where
                        pl_id          = {$headdata["req_plid_fab"]} and
                        prc_headerid   = {$_REQUEST["fab_item_posid"]} and
                        prc_amount     = {$amount["amt_val"]} ";
               $prc_price_base = $CON->select($sql);
               $prc_price_base = $prc_price_base[0]["prc_price"];

               if($prc_price_base > 0.00)
               {
                  $_ROW_COLORS_1SIDES = $_COLORS_1SIDES;
                  $_ROW_COLORS_2SIDES = $_COLORS_2SIDES;
                  
                  $sql = " select *
                           from price_lists_fab_increments_pos
                           where
                           pl_id       = {$headdata["req_plid_fab"]} and
                           pos_amount  = {$amount["amt_val"]} and
                           pos_incid   = {$pos_incid}";
                  $increment = $CON->select($sql);
                  $increment = $increment[0];

                  $incrval = 0;

                  if(!(int)$_ROW_COLORS_1SIDES && !(int)$_ROW_COLORS_2SIDES)
                     $incrval = $fab_noprint_discount * -1;
                  else
                  {
                     if($_REQUEST["fab_printtype"] == "FLEX")
                     {
                        if($_ROW_COLORS_2SIDES > 0)
                           $_ROW_COLORS_2SIDES--;
                        elseif($_ROW_COLORS_1SIDES > 0)
                           $_ROW_COLORS_1SIDES--;

                        if($_ROW_COLORS_2SIDES > 0)
                           $incrval += ($_ROW_COLORS_2SIDES * $increment["aum_prc_flexo_2sides"]);
                        if($_ROW_COLORS_1SIDES > 0)
                           $incrval += ($_ROW_COLORS_1SIDES * $increment["aum_prc_flexo_1sides"]);
                     }
                     elseif($_REQUEST["fab_printtype"] == "SERI")
                     {
                        if($_ROW_COLORS_2SIDES > 0)
                        {
                           $_ROW_COLORS_2SIDES--;
                           $_ROW_COLORS_1SIDES++;
                        }
                        elseif($_ROW_COLORS_1SIDES > 0)
                           $_ROW_COLORS_1SIDES--;

                        if($_ROW_COLORS_2SIDES > 0)
                           $incrval += ($_ROW_COLORS_2SIDES * 2 * $increment["aum_prc_seri_1sides"]);
                        if($_ROW_COLORS_1SIDES > 0)
                           $incrval += ($_ROW_COLORS_1SIDES * $increment["aum_prc_seri_1sides"]);
                     }
                  }

                  if($amount["amt_dsc"] > 0.00)
                  {
                     if((int)$_COLORS_1SIDES == 0 && (int)$_COLORS_2SIDES == 0)
                        $incrval -= $amount["amt_dsc"];
                  }

                  $itemunitprice    = $prc_price_base + $incrval;
                  $itemtotalprice   = $amount["amt_val"] * $itemunitprice;
                  ?>
                  <tr  onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                     <td class="content_row_os"><?=printPrice($amount["amt_val"])?></td>
                     <td class="content_row_os" align="right"><?=printPrice($prc_price_base)?></td>
                     <td class="content_row_os" align="right"><?=printPrice($incrval)?></td>
                     <td class="content_row_os" align="right"><?=printPrice($itemunitprice)?></td>
                     <td class="content_row_os" align="right"><?=printPrice($itemtotalprice)?></td>
                     <td class="content_row_os" align="center">
                        <input type="checkbox" style="margin:0px;padding:0px" name="additems[]"
                        value="<?=(int)$amount["amt_val"]?>_<?=(int)$itemunitprice?>">
                     </td>
                  </tr>
                  <?php
                  $canAdd = true;
                  $ax++;
               }
            }
            ?>
            </table>
            <?php
         }
         elseif((int)$headdata["req_prices_cnt"] > 1)
         {
            $sql_amt_str = "";
            for($xx = 1; $xx <= $headdata["req_prices_cnt"]; $xx++)
            {
               if($headdata["req_prices_ccval{$xx}"] > 0)
                  $sql_amt_str .= (int)$headdata["req_prices_ccval{$xx}"].",";
            }
            $sql_amt_str = substr($sql_amt_str, 0, -1);
            
            $sql = " select *
                     from price_lists_fab_amounts
                     where
                     pl_id       = {$headdata["req_plid_fab"]} and
                     amt_status  > 0 and
                     amt_val     IN ({$sql_amt_str})
                     order by amt_val asc";
            $amounts = $CON->select($sql);
            ?>
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col>
               <col>
               <col>
               <col>
               <col>
               <col width="20">
            </colgroup>
            <tr>
               <td class="content_tbl_header">Cantidad</td>
               <td class="content_tbl_header" align="right">Precio/Base</td>
               <td class="content_tbl_header" align="right">Precio/Incr.</td>
               <td class="content_tbl_header" align="right">Precio/Unit.</td>
               <td class="content_tbl_header" align="right">Precio/Total</td>
               <td class="content_tbl_header" align="center">Act.</td>
            </tr>
            <?php
            $ax = 0;
            foreach($amounts AS $amount)
            {
               $sql = " select prc_price
                        from price_lists_fab_items_prices
                        where
                        pl_id          = {$headdata["req_plid_fab"]} and
                        prc_headerid   = {$_REQUEST["fab_item_posid"]} and
                        prc_amount     = {$amount["amt_val"]} ";
               $prc_price_base = $CON->select($sql);
               $prc_price_base = $prc_price_base[0]["prc_price"];

               if($prc_price_base > 0.00)
               {
                  $_ROW_COLORS_1SIDES = $_COLORS_1SIDES;
                  $_ROW_COLORS_2SIDES = $_COLORS_2SIDES;
                  
                  $sql = " select *
                           from price_lists_fab_increments_pos
                           where
                           pl_id       = {$headdata["req_plid_fab"]} and
                           pos_amount  = {$amount["amt_val"]} and
                           pos_incid   = {$pos_incid}";
                  $increment = $CON->select($sql);
                  $increment = $increment[0];

                  $incrval = 0;

                  if(!(int)$_ROW_COLORS_1SIDES && !(int)$_ROW_COLORS_2SIDES)
                     $incrval = $fab_noprint_discount * -1;
                  else
                  {
                     if($_REQUEST["fab_printtype"] == "FLEX")
                     {
                        if($_ROW_COLORS_2SIDES > 0)
                           $_ROW_COLORS_2SIDES--;
                        elseif($_ROW_COLORS_1SIDES > 0)
                           $_ROW_COLORS_1SIDES--;

                        if($_ROW_COLORS_2SIDES > 0)
                           $incrval += ($_ROW_COLORS_2SIDES * $increment["aum_prc_flexo_2sides"]);
                        if($_ROW_COLORS_1SIDES > 0)
                           $incrval += ($_ROW_COLORS_1SIDES * $increment["aum_prc_flexo_1sides"]);
                     }
                     elseif($_REQUEST["fab_printtype"] == "SERI")
                     {
                        if($_ROW_COLORS_2SIDES > 0)
                        {
                           $_ROW_COLORS_2SIDES--;
                           $_ROW_COLORS_1SIDES++;
                        }
                        elseif($_ROW_COLORS_1SIDES > 0)
                           $_ROW_COLORS_1SIDES--;

                        if($_ROW_COLORS_2SIDES > 0)
                           $incrval += ($_ROW_COLORS_2SIDES * 2 * $increment["aum_prc_seri_1sides"]);
                        if($_ROW_COLORS_1SIDES > 0)
                           $incrval += ($_ROW_COLORS_1SIDES * $increment["aum_prc_seri_1sides"]);
                     }
                  }

                  if($amount["amt_dsc"] > 0.00)
                  {
                     if((int)$_COLORS_1SIDES == 0 && (int)$_COLORS_2SIDES == 0)
                        $incrval -= $amount["amt_dsc"];
                  }

                  $itemunitprice    = $prc_price_base + $incrval;
                  $itemtotalprice   = $amount["amt_val"] * $itemunitprice;
                  ?>
                  <tr  onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                     <td class="content_row_os"><?=printPrice($amount["amt_val"])?></td>
                     <td class="content_row_os" align="right"><?=printPrice($prc_price_base)?></td>
                     <td class="content_row_os" align="right"><?=printPrice($incrval)?></td>
                     <td class="content_row_os" align="right"><?=printPrice($itemunitprice)?></td>
                     <td class="content_row_os" align="right"><?=printPrice($itemtotalprice)?></td>
                     <td class="content_row_os" align="center">
                        <input type="checkbox" style="margin:0px;padding:0px" name="additems[]" checked
                        value="<?=(int)$amount["amt_val"]?>_<?=(int)$itemunitprice?>">
                     </td>
                  </tr>
                  <?php
                  $canAdd = true;
                  $ax++;
               }
            }
            ?>
            </table>
            <?php
         }
      }
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl" height="1">Producto</td>
   <td class="content_row">
      <select class="text" style="width:100%;" name="fab_item_id"
      onchange="document.idx_text.subexec.value = 'set_fab_item_id';document.idx_text.submit();">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($items AS $item)
         {  ?>
            <option value="<?=$item["id"]?>" <?if($item["id"] == $_REQUEST["fab_item_id"]) echo "selected"?>>
               <?=$item["item_title"]?> (<?=$item["item_number_prod"]?>)
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl" height="1" valign="top">Tamaño</td>
   <td class="content_row">
      <select class="text" style="width:100%;" name="fab_item_posid"
      onchange="document.idx_text.subexec.value = 'set_fab_item_posid';document.idx_text.submit();">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($medidas AS $medida)
         {  ?>
            <option value="<?=$medida["id"]?>" <?if($medida["id"] == $_REQUEST["fab_item_posid"]) echo "selected"?>>
               <?=(int)$medida["fab_med_width"]?>x<?=(int)$medida["fab_med_height"]?>x<?=(int)$medida["fab_med_fuelle"]?>
               <?php
               if($medida["fab_desc"] != "")
               {  ?>
                  | <?=$medida["fab_desc"]?>
                  <?php
               }
               ?>
               | Area Impr.: <?=(int)$medida["fab_print_width"]?>x<?=(int)$medida["fab_print_height"]?>
               <?php
               if((int)$medida["fab_manilla_length"])
               {  ?>
                  | Manilla: <?=(int)$medida["fab_manilla_length"]?>
                  <?php
               }
               ?>
            </option>
            <?php
         }
         ?>
      </select>
      <?php
      if((int)$_REQUEST["fab_item_posid"])
      {  ?>
         <div style="height:3px"></div>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <tr>
            <td class="content_row_clear" width="1">Medida</td>
            <td class="content_row_clear">
               <input type="text" class="text" style="width:40px" name="final_fab_med_width" value="<?=$_REQUEST["final_fab_med_width"]?>"> x
               <input type="text" class="text" style="width:40px" name="final_fab_med_height" value="<?=$_REQUEST["final_fab_med_height"]?>"> x
               <input type="text" class="text" style="width:40px" name="final_fab_med_fuelle" value="<?=$_REQUEST["final_fab_med_fuelle"]?>">
            </td>
         </tr>
         <tr>
            <td class="content_row_clear">Area</td>
            <td class="content_row_clear">
               <input type="text" class="text" style="width:40px" name="final_fab_print_width" value="<?=$_REQUEST["final_fab_print_width"]?>"> x
               <input type="text" class="text" style="width:40px" name="final_fab_print_height" value="<?=$_REQUEST["final_fab_print_height"]?>">
            </td>
         </tr>
         <tr>
            <td class="content_row_clear">Manilla</td>
            <td class="content_row_clear">
               <input type="text" class="text" style="width:40px" name="final_fab_manilla_length" value="<?=$_REQUEST["final_fab_manilla_length"]?>">
               <input type="button" class="button" value="Actualizar" style="width:95px;margin-left:8px"
               onclick="document.idx_text.subexec.value = 'set_fab_meds';document.idx_text.submit();">
            </td>
         </tr>
         </table>
         <?php
      }
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl" height="1">Tipo Impresión</td>
   <td class="content_row">
      <select class="text" style="width:100%;" name="fab_printtype"
      onchange="document.idx_text.subexec.value = 'set_fab_printtype';document.idx_text.submit();">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <option value="FLEX" <?if($_REQUEST["fab_printtype"] == "FLEX") echo "selected"?>>Flexografía</option>
         <option value="SERI" <?if($_REQUEST["fab_printtype"] == "SERI") echo "selected"?>>Serigrafía</option>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl" height="1">Color Tela</td>
   <td class="content_row">
      <select class="text" style="width:100%;" name="fab_mat_fabric_color"
      onchange="document.idx_text.submit();">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($colors AS $color)
         {  ?>
            <option value="<?=$color["id"]?>" <?if($color["id"] == $_REQUEST["fab_mat_fabric_color"]) echo "selected"?>>
               <?=$color["add_name"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl" height="1">Color Manillas</td>
   <td class="content_row">
      <select class="text" style="width:100%;" name="fab_mat_manilla_color"
      onchange="document.idx_text.submit();">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($colors AS $color)
         {  ?>
            <option value="<?=$color["id"]?>" <?if($color["id"] == $_REQUEST["fab_mat_manilla_color"]) echo "selected"?>>
               <?=$color["add_name"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<?php
for($x = 1; $x <= 5; $x++)
{  ?>
   <tr>
      <td class="content_rowl" height="1">Color #<?=$x?></td>
      <td class="content_row">
         <input type="checkbox" name="fab_print_colors_front_<?=$x?>" value="1" <?if((int)$_REQUEST["fab_print_colors_front_{$x}"]) echo "checked"?>
         onclick="document.idx_text.subexec.value = 'set_fab_print_colors_front';document.idx_text.submit();"> Frente
         <input type="checkbox" name="fab_print_colors_back_<?=$x?>" value="1" <?if((int)$_REQUEST["fab_print_colors_back_{$x}"]) echo "checked"?>
         onclick="document.idx_text.subexec.value = 'set_fab_print_colors_back';document.idx_text.submit();"> Dorso
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?php
if($canAdd)
{  ?>
   <?=Nifty_printH("boxopt_b", "99%")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>&nbsp;</td>
      <td align="right" width="260">
         <?php
         printButton("Agregar seleccionados a la cotización", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.idx_text.subexec.value='additems';submitForm(document.idx_text);}", "tick-circle-frame");
         ?>
      </td>
   </tr>
   </table>
   <?php
}
?>
</form>