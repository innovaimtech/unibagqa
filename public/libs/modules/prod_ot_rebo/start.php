<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   $_REQUEST["company_id"]    = (int)trim($_REQUEST["company_id"]);
   $_REQUEST["shop_id"]       = (int)trim($_REQUEST["shop_id"]);
   $_REQUEST["prd_plantaid"]  = (int)trim($_REQUEST["prd_plantaid"]);
   $_REQUEST["rebo_name"]     = trim(addslashes($_REQUEST["rebo_name"]));
   $_REQUEST["rebo_desc"]     = trim(addslashes($_REQUEST["rebo_desc"]));

   $_REQUEST["cust_id_0"]              = (int)trim($_REQUEST["cust_id_0"]);
   $_REQUEST["fab_type"]               = trim(addslashes($_REQUEST["fab_type"]));
   $_REQUEST["fab_printtype"]          = trim(addslashes($_REQUEST["fab_printtype"]));
   $_REQUEST["fab_mat_gramms"]         = (int)trim($_REQUEST["fab_mat_gramms"]);
   $_REQUEST["fab_mat_fabric_color"]   = (int)trim($_REQUEST["fab_mat_fabric_color"]);
   $_REQUEST["fab_mat_manilla_color"]  = (int)trim($_REQUEST["fab_mat_manilla_color"]);
   $_REQUEST["fab_item_id"]            = (int)trim($_REQUEST["fab_item_id"]);

   $sql = " insert into prod_ot_rebo_header
            (rebo_crtdat, rebo_crtusr, rebo_status, rebo_desc, rebo_plantaid,
             rebo_company_id, rebo_shop_id, rebo_name,
             cust_id_0, fab_type, fab_printtype, fab_mat_gramms, fab_mat_fabric_color,
             fab_mat_manilla_color, fab_item_id)
            VALUES
            ({$currtme}, {$_SESSION["user_id"]}, 1, '{$_REQUEST["rebo_desc"]}', {$_REQUEST["prd_plantaid"]},
             {$_REQUEST["company_id"]}, {$_REQUEST["shop_id"]}, '{$_REQUEST["rebo_name"]}',
             {$_REQUEST["cust_id_0"]}, '{$_REQUEST["fab_type"]}', '{$_REQUEST["fab_printtype"]}',
             {$_REQUEST["fab_mat_gramms"]}, {$_REQUEST["fab_mat_fabric_color"]},
             {$_REQUEST["fab_mat_manilla_color"]}, {$_REQUEST["fab_item_id"]})";
   $res = $CON->no_result($sql);
   if($res)
   {
      $reboid = mysql_insert_id();

      foreach($_REQUEST["equiposact"] AS $equiposactid)
      {
         $sql = " insert into prod_ot_rebo_compat_equipos
                  (rebo_id, equ_id)
                  VALUES
                  ({$reboid}, {$equiposactid})";
         $CON->no_result($sql);
      }
      ?>
      <script language="Javascript">
         location.href = 'index.php?mid=10056&id=<?=$reboid?>&exec=edit';
      </script>
      <?php
      exit;

   }
}

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON, false, true);

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from plantas t1
         where
         t1.planta_status > 0
         order by t1.planta_name";
$plantas = $CON->select($sql);

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
onsubmit="return checkform(new Array(this.company_id, this.shop_id, this.prd_plantaid, this.rebo_name,
this.cust_id_0, this.fab_type, this.fab_item_id, this.fab_mat_gramms, this.fab_mat_fabric_color))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de la OT</td>
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
               fabt_status > 0 and
               fabt_code IN ('PLA', 'TNT')
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
<tr style="display:none">
   <td class="content_rowl">Cantidad *</td>
   <td class="content_row">
      <input type="text" class="text" style="width:40px" name="item_amount" value="1">
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl" width="1">Medida *</td>
   <td class="content_row">
      <input type="text" class="text" style="width:40px" name="final_fab_med_width" value="0"> x
      <input type="text" class="text" style="width:40px" name="final_fab_med_height" value="0"> x
      <input type="text" class="text" style="width:40px" name="final_fab_med_fuelle" value="0">
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Area impresión *</td>
   <td class="content_row">
      <input type="text" class="text" style="width:40px" name="final_fab_print_width" value="0"> x
      <input type="text" class="text" style="width:40px" name="final_fab_print_height" value="0">
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Manilla *</td>
   <td class="content_row">
      <input type="text" class="text" style="width:40px" name="final_fab_manilla_length" value="0">
   </td>
</tr>
<tr>
   <td class="content_rowl" height="1">Tipo Impresión *</td>
   <td class="content_row">
      <select class="text" style="width:500px;" name="fab_printtype">
         <option value="FLEX" selected>Flexografía</option>
         <option value="SERI">Serigrafía</option>
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
      <select class="text" style="width:500px;" name="fab_mat_fabric_color"
      onchange="$('#fab_mat_manilla_color').val($(this).val());">
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
<tr style="display:none">
   <td class="content_rowl" height="1">Color Manillas *</td>
   <td class="content_row">
      <select class="text" style="width:500px;" name="fab_mat_manilla_color" id="fab_mat_manilla_color"
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
$sql = " select *
         from equipo_type
         where
         type_ant_status > 0 and
         type_ant_prod_dabl = 0 and
         type_ant_title like '%REBOB%'
         order by type_ant_title";
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
            <td class="content_rowl">Asignar máquinas</td>
            <td class="content_row">
               <?php
               foreach($equipos AS $equipo)
               {  ?>
                  <nobr>
                     <input type="checkbox" name="equiposact[]" value="<?=$equipo["id"]?>" checked> <?=$equipo["equipo_name"]?><br>
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
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input class="text" name="rebo_name" id="rebo_name" style="width:500px;"></input>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción</td>
   <td class="content_row">
      <textarea class="text" name="rebo_desc" id="rebo_desc" style="width:500px;height:60px"></textarea>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton("Agregar OT", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.idx_xform)", "plus");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<iframe id="idxifrsrc2" height="0" width="0" frameborder="0"></iframe>
</form>