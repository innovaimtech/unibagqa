<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $_REQUEST["company_id"] = (int)trim($_REQUEST["company_id"]);
   $_REQUEST["shop_id"] = (int)trim($_REQUEST["shop_id"]);
   $_REQUEST["cust_id_0"] = (int)trim($_REQUEST["cust_id_0"]);
   $_REQUEST["fab_type"] = trim(addslashes($_REQUEST["fab_type"]));
   $_REQUEST["fab_item_id"] = (int)trim($_REQUEST["fab_item_id"]);
   $_REQUEST["fab_desc"] = trim(addslashes($_REQUEST["fab_desc"]));
   $_REQUEST["item_amount"] = (int)trim($_REQUEST["item_amount"]);
   $_REQUEST["final_fab_med_width"] = (int)trim($_REQUEST["final_fab_med_width"]);
   $_REQUEST["final_fab_med_height"] = (int)trim($_REQUEST["final_fab_med_height"]);
   $_REQUEST["final_fab_med_fuelle"] = (int)trim($_REQUEST["final_fab_med_fuelle"]);
   $_REQUEST["final_fab_print_width"] = (int)trim($_REQUEST["final_fab_print_width"]);
   $_REQUEST["final_fab_print_height"] == (int)trim($_REQUEST["final_fab_print_height"]);
   $_REQUEST["final_fab_manilla_length"] = (int)trim($_REQUEST["final_fab_manilla_length"]);
   $_REQUEST["fab_printtype"] = trim(addslashes($_REQUEST["fab_printtype"]));
   $_REQUEST["fab_mat_fabric_color"] = (int)trim($_REQUEST["fab_mat_fabric_color"]);
   $_REQUEST["fab_mat_manilla_color"] = (int)trim($_REQUEST["fab_mat_manilla_color"]);
   $_REQUEST["fab_mat_gramms"] = (int)trim($_REQUEST["fab_mat_gramms"]);
   $_REQUEST["prd_plantaid"] = (int)trim($_REQUEST["prd_plantaid"]);

   $_REQUEST["fab_print_colors_front_1"] = (int)trim($_REQUEST["fab_print_colors_front_1"]);
   $_REQUEST["fab_print_colors_back_1"] = (int)trim($_REQUEST["fab_print_colors_back_1"]);
   $_REQUEST["fab_print_colors_front_2"] = (int)trim($_REQUEST["fab_print_colors_front_2"]);
   $_REQUEST["fab_print_colors_back_2"] = (int)trim($_REQUEST["fab_print_colors_back_2"]);
   $_REQUEST["fab_print_colors_front_3"] = (int)trim($_REQUEST["fab_print_colors_front_3"]);
   $_REQUEST["fab_print_colors_back_3"] = (int)trim($_REQUEST["fab_print_colors_back_3"]);
   $_REQUEST["fab_print_colors_front_4"] = (int)trim($_REQUEST["fab_print_colors_front_4"]);
   $_REQUEST["fab_print_colors_back_4"] = (int)trim($_REQUEST["fab_print_colors_back_4"]);
   $_REQUEST["fab_print_colors_front_5"] = (int)trim($_REQUEST["fab_print_colors_front_5"]);
   $_REQUEST["fab_print_colors_back_5"] = (int)trim($_REQUEST["fab_print_colors_back_5"]);

   $_REQUEST["fab_print_colordesc_1"] = trim(addslashes($_REQUEST["fab_print_colordesc_1"]));
   $_REQUEST["fab_print_colordesc_2"] = trim(addslashes($_REQUEST["fab_print_colordesc_2"]));
   $_REQUEST["fab_print_colordesc_3"] = trim(addslashes($_REQUEST["fab_print_colordesc_3"]));
   $_REQUEST["fab_print_colordesc_4"] = trim(addslashes($_REQUEST["fab_print_colordesc_4"]));
   $_REQUEST["fab_print_colordesc_5"] = trim(addslashes($_REQUEST["fab_print_colordesc_5"]));

    $req_num = str_replace("NV", "INT", createTransactionNumber($CON, $_REQUEST["company_id"], "invoice"));
    $currtme = time();

    $sql = " insert into orders
            (req_number, req_company_id, req_shop_id, req_cust_id, req_paymentid,
             req_userid_seller, req_userid_cashing, req_taxes, req_transportid,
             req_crtdat, req_crtusr, req_isinvcbrutto, req_isreserva, req_crtdat_first,
             req_offerid, req_despacho_desc, req_isfabricate, req_production_act)
            VALUES
            ('{$req_num}', {$_REQUEST["company_id"]}, {$_REQUEST["shop_id"]}, {$_REQUEST["cust_id_0"]},
              0, {$_SESSION["user_id"]}, {$_SESSION["user_id"]}, 1,
              0, {$currtme}, {$_SESSION["user_id"]}, 0, 0, {$currtme}, 0, '', 1, 1)";
   $res = $CON->no_result($sql);
   if($res)
   {
      $req_id = mysql_insert_id();
      $sql = " insert into orders_items
                  (
                     req_id, item_id, item_pos, item_amount, item_amount_shipped, item_type,
                     item_compdesc, fab_type, fab_printtype,
                     fab_med_width, fab_med_height, fab_med_fuelle, fab_print_width, fab_print_height,
                     fab_manilla_length, fab_mat_fabric_color, fab_mat_manilla_color, fab_print_colors_front_1,
                     fab_print_colors_back_1, fab_print_colors_front_2, fab_print_colors_back_2,
                     fab_print_colors_front_3, fab_print_colors_back_3, fab_print_colors_front_4,
                     fab_print_colors_back_4, fab_print_colors_front_5, fab_print_colors_back_5,
                     fab_print_colordesc_1, fab_print_colordesc_2, fab_print_colordesc_3, fab_print_colordesc_4,
                     fab_print_colordesc_5, fab_mat_gramms, fab_design_imagehash
                  )
                  VALUES
                  (
                     {$req_id}, {$_REQUEST["fab_item_id"]}, 0, {$_REQUEST["item_amount"]}, 0, 'item', '{$_REQUEST["fab_desc"]}',
                     '{$_REQUEST["fab_type"]}', '{$_REQUEST["fab_printtype"]}', {$_REQUEST["final_fab_med_width"]},
                     {$_REQUEST["final_fab_med_height"]}, {$_REQUEST["final_fab_med_fuelle"]}, {$_REQUEST["final_fab_print_width"]},
                     {$_REQUEST["final_fab_print_height"]}, {$_REQUEST["final_fab_manilla_length"]}, {$_REQUEST["fab_mat_fabric_color"]},
                     {$_REQUEST["fab_mat_manilla_color"]},
                     {$_REQUEST["fab_print_colors_front_1"]}, {$_REQUEST["fab_print_colors_back_1"]},
                     {$_REQUEST["fab_print_colors_front_2"]}, {$_REQUEST["fab_print_colors_back_2"]},
                     {$_REQUEST["fab_print_colors_front_3"]}, {$_REQUEST["fab_print_colors_back_3"]},
                     {$_REQUEST["fab_print_colors_front_4"]}, {$_REQUEST["fab_print_colors_back_4"]},
                     {$_REQUEST["fab_print_colors_front_5"]}, {$_REQUEST["fab_print_colors_back_5"]},
                     '{$_REQUEST["fab_print_colordesc_1"]}',
                     '{$_REQUEST["fab_print_colordesc_2"]}',
                     '{$_REQUEST["fab_print_colordesc_3"]}',
                     '{$_REQUEST["fab_print_colordesc_4"]}',
                     '{$_REQUEST["fab_print_colordesc_5"]}',
                     {$_REQUEST["fab_mat_gramms"]}, 'dummy'
                  )";
      $CON->no_result($sql);

      $sql = " update orders
               set
               req_status = 4,
               req_order_shipped = 1,
               req_updusr = {$_SESSION["user_id"]},
               req_upddat = {$currtme}
               where
               id = {$req_id}";
      $CON->no_result($sql);

      $prd_number = createNumberSystem($CON, "PRODOT");

      $sql = " insert into prod_header
               (prd_crtdat, prd_crtusr, prd_status, prd_desc, prd_plantaid, prd_reqid, prd_number)
               VALUES
               ({$currtme}, {$_SESSION["user_id"]}, 2, '', {$_REQUEST["prd_plantaid"]},
                {$req_id}, '{$prd_number}')";
      $res = $CON->no_result($sql);
      if($res)
      {
         $prodid = mysql_insert_id();
         $prodplan_date = mktime(15, 0, 0, date("m"), date("d"), date("Y"));
         $sql = " insert into prod_amtplan
                  (prodplan_amt, prodplan_date, prodplan_prdid)
                  VALUES
                  ({$_REQUEST["item_amount"]}, {$prodplan_date}, {$prodid})";
         $CON->no_result($sql);


         foreach($_REQUEST["equiposact"] AS $equiposactid)
         {
            $sql = " insert into prod_compat_equipos
                     (prd_id, equ_id)
                     VALUES
                     ({$prodid}, {$equiposactid})";
            $CON->no_result($sql);
         }

         ?>
         <table border="0" cellpadding="0" cellspacing="0" width="1180">
         <tr>
            <td height="30"><b class="content_header">Agregar OT</b></td>
            <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
         </tr>
         <tr>
            <td class="content_headerline" colspan="2">&nbsp;</td>
         </tr>
         </table>
         <div style="background-color:#56AF52;color:white;text-shadow:none;padding:10px;width:200px">
            <b>OT <u><?=$prd_number?></u> creada exitosamente</b>
         </div>
         <?php
         exit;
      }
   }
}

//----------------------------------------------------------------------------------
$companies = getCompanies($CON);

//----------------------------------------------------------------------------------
$shops = getShops($CON, false, true);

//----------------------------------------------------------------------------------
$sql = " select distinct t1.item_reg_gsm
         from item t1
         INNER JOIN item_productcats t2         ON t1.id = t2.item_id
         INNER JOIN tran_comments_item_vals t3  ON t1.id = t3.item_id
         INNER JOIN tran_comments t4            ON t3.com_id = t4.id
         INNER JOIN tran_comments_vals t5       ON t3.val_id = t5.id
         where
         t1.item_status > 0 and
         t2.cat_id      = {$_CONFIG["TELA_CATID"]}
         order by t1.item_reg_gsm asc";
$optsgrams = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from plantas t1
         where
         t1.planta_status > 0
         order by t1.planta_name";
$plantas = $CON->select($sql);
?>
<script language="JavaScript">
   function setCompanyShop(companyidx)
   {
      var obj = document.all.shop_id;
      obj.options.length = 0;
      <?php
      foreach($shops AS $shop)
      {  ?>
         if(companyidx == '<?=$shop["shop_company_id"]?>')
         {
            var newIndex   = obj.options.length;
            var newOpt     = new Option('<?=addslashes($shop["shop_name"])?>');
            newOpt.value   = '<?=$shop["id"]?>';
            obj.options[newIndex] = newOpt;
         }
         <?php
      }
      ?>
   }
   function detectEvent (event)
   {
   }
   function loadRelOrders(custid)
   {
   }
</script>

<table border="0" cellpadding="0" cellspacing="0" width="1180">
<tr>
   <td height="30"><b class="content_header">Agregar OT</b></td>
   <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="idx_xform"
onsubmit="return checkform(new Array(this.company_id, this.shop_id, this.cust_id_0, this.fab_type,
this.fab_item_id, this.fab_desc, this.final_fab_med_width, this.item_amount, this.prd_plantaid,
this.final_fab_med_height, this.final_fab_med_fuelle, this.final_fab_print_width,
this.final_fab_print_height, this.final_fab_manilla_length, this.fab_printtype,
this.fab_mat_fabric_color, this.fab_mat_manilla_color, this.fab_mat_gramms))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="activate" value="">
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de confirmación de compra</td>
</tr>
<tr>
   <td class="content_rowl">Empresa *</td>
   <td class="content_row">
      <select class="text" name="company_id" id="company_id" style="width:500px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)"
      onchange="setCompanyShop(this.value);">
         <?php
         foreach($companies AS $company)
         {  ?>
            <option value="<?=$company["id"]?>">
               <?=$company["company_short"]?>
            </option><?php
         }
         ?>
      </select>
      <?php
      $_SESSION["JSEXEC"] .= "; setCompanyShop({$companies[0]["id"]}); ";
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Sucursal *</td>
   <td class="content_row">
      <select class="text" name="shop_id" id="shop_id" style="width:500px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Planta *</td>
   <td class="content_row">
      <select class="text" style="width:500px" name="prd_plantaid">
         <?php
         foreach($plantas AS $planta)
         {  ?>
            <option value="<?=$planta["id"]?>" <?if($planta["id"] == $proddata["prd_plantaid"]) echo "selected"?>><?=$planta["planta_name"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Cliente *</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td width="160">
            <input type="text" class="text" style="width:150px" onfocus="markfield(this,0)" name="cust_search" id="cust_search" value="<?=$_REQUEST["cust_search"]?>"
            onblur="markfield(this,1); if(this.value != '') document.getElementById('idxifrsrc').src='./libs/modules/orders/searchcust.php?setOrders=1&rowcount=0' +'&search=' +this.value;"
            onkeyup="detectEvent(event)">
         </td>
         <td>
            <select class="text" name="cust_id_0" style="width:340px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="loadRelOrders(this.value)">
            </select>
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_rowl">Material *</td>
   <td class="content_row">
      <?php
      $sql = " select *
               from fabric_types
               where
               fabt_status > 0
               order by fabt_code";
      $fabric_types = $CON->select($sql);
      ?>
      <select name="fab_type" class="text" style="width:500px;">
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
</tr>
<tr>
   <td class="content_rowl">Producto *</td>
   <td class="content_row">
      <select class="text" style="width:500px;" name="fab_item_id">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         $sql = " select distinct t2.id, t2.item_number_prod, t2.item_title, t2.item_fabricate_prefix,
                         t2.item_fabricate_fabrictext, t2.item_fabricate_fuelletext
                  from price_lists_fab_items t1
                  INNER JOIN item t2 ON t1.fab_item_id = t2.id
                  where
                  t1.fab_active  = 1 and
                  t2.item_status > 0
                  order by t2.item_title, t2.item_number_prod";
         $items = $CON->select($sql);
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
   <td class="content_rowl" valign="top">Descripción *</td>
   <td class="content_row">
      <textarea class="text" name="fab_desc" id="fab_desc" style="width:500px;height:60px"></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl">Cantidad *</td>
   <td class="content_row">
      <input type="text" class="text" style="width:40px" name="item_amount" value="">
   </td>
</tr>
<tr>
   <td class="content_rowl" width="1">Medida *</td>
   <td class="content_row">
      <input type="text" class="text" style="width:40px" name="final_fab_med_width" value="<?=$_REQUEST["final_fab_med_width"]?>"> x
      <input type="text" class="text" style="width:40px" name="final_fab_med_height" value="<?=$_REQUEST["final_fab_med_height"]?>"> x
      <input type="text" class="text" style="width:40px" name="final_fab_med_fuelle" value="<?=$_REQUEST["final_fab_med_fuelle"]?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Area impresión *</td>
   <td class="content_row">
      <input type="text" class="text" style="width:40px" name="final_fab_print_width" value="<?=$_REQUEST["final_fab_print_width"]?>"> x
      <input type="text" class="text" style="width:40px" name="final_fab_print_height" value="<?=$_REQUEST["final_fab_print_height"]?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Manilla *</td>
   <td class="content_row">
      <input type="text" class="text" style="width:40px" name="final_fab_manilla_length" value="<?=$_REQUEST["final_fab_manilla_length"]?>">
   </td>
</tr>
<tr>
   <td class="content_rowl" height="1">Tipo Impresión *</td>
   <td class="content_row">
      <select class="text" style="width:500px;" name="fab_printtype">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <option value="FLEX" <?if($_REQUEST["fab_printtype"] == "FLEX") echo "selected"?>>Flexografía</option>
         <option value="SERI" <?if($_REQUEST["fab_printtype"] == "SERI") echo "selected"?>>Serigrafía</option>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Gramaje tela *</td>
   <td class="content_row">
      <select class="text" style="width:500px;" name="fab_mat_gramms">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($optsgrams AS $optsgram)
         {  ?>
            <option value="<?=(int)$optsgram["item_reg_gsm"]?>" <?if((int)$optsgram["item_reg_gsm"] == $thispos["fab_mat_gramms"]) echo "selected"?>>
               <?=(int)$optsgram["item_reg_gsm"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl" height="1">Color Tela *</td>
   <td class="content_row">
      <select class="text" style="width:500px;" name="fab_mat_fabric_color">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         //----------------------------------------------------------------------------------
         $sql = " select t2.id, t2.add_name
                  from tran_comments t1
                  INNER JOIN tran_comments_vals t2 ON t2.add_com_id = t1.id
                  where
                  t1.id          = {$_CONFIG["TELA_COLOR_CHARACTID"]} and
                  t2.add_status  = 1
                  order by t2.add_name";
         $colors = $CON->select($sql);
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
   <td class="content_rowl" height="1">Color Manillas *</td>
   <td class="content_row">
      <select class="text" style="width:500px;" name="fab_mat_manilla_color"
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
         <input type="checkbox" name="fab_print_colors_front_<?=$x?>" value="1"> Frente
         <input type="checkbox" name="fab_print_colors_back_<?=$x?>" value="1"> Dorso
         <input type="text" class="text" style="width:380px" name="fab_print_colordesc_<?=$x?>" placeholder="Descripción color" value="">
      </td>
   </tr>
   <?php
}
?>
<?php
$sql = " select *
         from equipo_type
         where
         type_ant_status > 0 and
         type_ant_prod_dabl = 0 ";
$sql .= " order by type_ant_title";
$equipotypes = $CON->select($sql);

$px = 0;
foreach($equipotypes AS $equipotype)
{
   $sql = " select *
            from equipo
            where
            equipo_status     > 0 and
            equipo_type_id    = {$equipotype["id"]} and
            equipo_prod_dabl  = 0
            order by equipo_name";
   $equipos = $CON->select($sql);
   if(count($equipos) && $equipos != false)
   {
      $showline = true;

      if($showline)
      {  ?>
         <tr>
            <td class="content_rowl" valign="top">Asignar máquinas</td>
            <td class="content_row">
               <b><?=$equipotype["type_ant_title"]?></b><br>
               <?php
               foreach($equipos AS $equipo)
               {  ?>
                  <nobr>
                     <input type="checkbox" name="equiposact[]" value="<?=$equipo["id"]?>"> <?=$equipo["equipo_name"]?><br>
                  </nobr>
                  <?php
               }
               ?>
            </td>
         </tr>
         <?php
         $px++;
      }
   }
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton("Generar OT", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.idx_xform)", "plus");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<iframe id="idxifrsrc2" height="0" width="0" frameborder="0"></iframe>
</form>