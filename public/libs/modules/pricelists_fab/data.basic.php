<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $_REQUEST["pl_title"]         = trim(addslashes($_REQUEST["pl_title"]));
   $_REQUEST["pl_desc"]          = trim(addslashes($_REQUEST["pl_desc"]));
   $_REQUEST["pl_isdefault"]     = (int)$_REQUEST["pl_isdefault"];
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $sql = " insert into price_lists_fab
               (pl_title, pl_desc, pl_crtusr, pl_crtdat, pl_isdefault)
               VALUES
               ('{$_REQUEST["pl_title"]}', '{$_REQUEST["pl_desc"]}', {$_SESSION["user_id"]},
                {$currtme}, {$_REQUEST["pl_isdefault"]})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'thisid'
                  from price_lists_fab
                  where
                  pl_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
      
         $_REQUEST["id"] = $thisid[0]["thisid"];
         $redirect = true;
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update price_lists_fab
               set
               pl_title          = '{$_REQUEST["pl_title"]}',
               pl_desc           = '{$_REQUEST["pl_desc"]}',
               pl_isdefault      = {$_REQUEST["pl_isdefault"]},
               pl_updusr         = {$_SESSION["user_id"]},
               pl_upddat         = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

   if((int)$_REQUEST["pl_isdefault"])
   {
      $sql = " update price_lists_fab
               set
               pl_isdefault = 0
               where
               id != {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }

   $sql = " delete from price_lists_fab_shops
            where
            pl_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   foreach($_REQUEST["shops"] AS $shopid)
   {
      $sql = " insert into price_lists_fab_shops
               (pl_id, shop_id)
               VALUES
               ({$_REQUEST["id"]}, {$shopid})";
      $CON->no_result($sql);            
   }

   if($redirect)
   {  ?>
      <script language="JavaScript">
         location.href = '/index.php?mid=1103&exec=edit&id=<?=$_REQUEST["id"]?>';
      </script>
      <?php
      exit;
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $sql = " select t1.*, 
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from price_lists_fab t1
            LEFT OUTER JOIN user t2 ON t1.pl_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.pl_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $plist = $CON->select($sql);
   $plist = $plist[0];

   $sql = " select *
            from price_lists_fab_shops
            where
            pl_id = {$_REQUEST["id"]}";
   $selshops = $CON->select($sql);
   foreach($selshops AS $selshop)
      $_SELSHOPS[$selshop["shop_id"]] = 1;
}
//----------------------------------------------------------------------------------
$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

if($rowcount <= 6)
   $rowcount = 10;
else
   $rowcount += 5;

//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_pl"
onsubmit="return checkform(new Array(this.pl_title))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="del_custid" value="">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de lista</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input name="pl_title" type="text" class="text" style="width:100%" value="<?=$plist["pl_title"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción</td>
   <td class="content_row">
      <textarea name="pl_desc" class="text" style="width:100%;height:50px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$plist["pl_desc"]?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl">Por defecto</td>
   <td class="content_row">
      <input type="checkbox" value="1" class="checkbox" name="pl_isdefault" id="pl_isdefault"
      <?php if((int)$plist["pl_isdefault"]) echo "checked" ?>> Activado
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
   <td class="content_row"><?php if($plist["pl_crtusr"] != "") echo "{$plist["crt_firstname"]} {$plist["crt_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
   <td class="content_row"><?php if($plist["pl_crtusr"] != "") echo displayDate($plist["pl_crtdat"])?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
   <td class="content_row"><?php if($plist["pl_updusr"] != "") echo "{$plist["upd_firstname"]} {$plist["upd_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
   <td class="content_row"><?php if($plist["pl_updusr"] != "") echo displayDate($plist["pl_upddat"])?>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td align="left" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
      ?>
      </td>
      <?php
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   ?>
   <td align="right" width="130" style="padding-right:5px">
      <?php
      printButton("Copiar lista", "postnav", "javascript: deactivateFormChange()", "if(askDel('')) { location.href = 'index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=basic&id={$_REQUEST["id"]}&execcopy=1'; }", "applications");
      ?>
   </td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_pl)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_pl');" ?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php
//----------------------------------------------------------------------------------
if((int)$_REQUEST["execcopy"])
{
   $pl_title = trim(addslashes($plist["pl_title"]))." (COPIA)";
   $currtme  = time();
   
   $sql = " insert into price_lists_fab
            (pl_title, pl_crtusr, pl_crtdat, pl_isdefault)
            VALUES
            ('{$pl_title}', {$_SESSION["user_id"]}, {$currtme}, 0)";
   $res = $CON->no_result($sql);
   if($res)
   {
      $plid = mysql_insert_id();

      //----------------------------------------------------------------------------------
      $sql = " select *
               from price_lists_fab_amounts
               where
               pl_id = {$_REQUEST["id"]} and
               amt_status > 0";
      $amts = $CON->select($sql);
      foreach($amts AS $amt)
      {
         $sql = " insert into price_lists_fab_amounts
                  (pl_id, amt_val)
                  VALUES
                  ({$plid}, {$amt["amt_val"]})";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      $sql = " select *
               from price_lists_fab_increments
               where
               pl_id = {$_REQUEST["id"]} and
               inc_status > 0";
      $incs = $CON->select($sql);
      foreach($incs AS $inc)
      {
         $inc_name = trim(addslashes($inc["inc_name"]));
         $sql = " insert into price_lists_fab_increments
                  (pl_id, inc_name)
                  VALUES
                  ({$plid}, '{$inc_name}')";
         $res = $CON->no_result($sql);

         if($res)
         {
            $newincid = mysql_insert_id();
            $_INCIDS[$inc["id"]] = $newincid;
            $sql = " select *
                     from price_lists_fab_increments_pos
                     where
                     pl_id       = {$_REQUEST["id"]} and
                     pos_incid   = {$inc["id"]}";
            $incpos = $CON->select($sql);
            foreach($incpos AS $incposrow)
            {
               $prc_amount             = (int)$incposrow["pos_amount"];
               $aum_prc_flexo_2sides   = (int)$incposrow["aum_prc_flexo_2sides"];
               $aum_prc_flexo_1sides   = (int)$incposrow["aum_prc_flexo_1sides"];
               $aum_prc_seri_2sides    = (int)$incposrow["aum_prc_seri_2sides"];
               $aum_prc_seri_1sides    = (int)$incposrow["aum_prc_seri_1sides"];
               $sql = " insert into price_lists_fab_increments_pos
                        (pos_amount, pos_incid, pl_id, aum_prc_flexo_2sides, aum_prc_flexo_1sides, aum_prc_seri_2sides, aum_prc_seri_1sides)
                        VALUES
                        ({$prc_amount}, {$newincid}, {$plid}, {$aum_prc_flexo_2sides}, {$aum_prc_flexo_1sides},
                         {$aum_prc_seri_2sides}, {$aum_prc_seri_1sides})";
               $CON->no_result($sql);
            }
         }
      }

      //----------------------------------------------------------------------------------
      $sql = " select *
               from price_lists_fab_items
               where
               pl_id = {$_REQUEST["id"]}";
      $fab_items = $CON->select($sql);
      foreach($fab_items AS $fab_item)
      {
         $fab_item["fab_desc"] = trim(addslashes($fab_item["fab_desc"]));
         $fab_inc_id = (int)$_INCIDS[$fab_item["fab_inc_id"]];
         $sql = " insert into price_lists_fab_items
                  (pl_id, fab_item_id, fab_type, fab_desc, fab_med_width, fab_med_height, fab_med_fuelle,
                   fab_min_amt, fab_corte_machine, fab_roll_width, fab_fabric_gr, fab_manilla_length,
                   fab_print_width, fab_print_height, fab_noprint_discount, fab_active, fab_inc_id)
                  VALUES
                  ({$plid}, {$fab_item["fab_item_id"]}, '{$fab_item["fab_type"]}', '{$fab_item["fab_desc"]}', {$fab_item["fab_med_width"]},
                   {$fab_item["fab_med_height"]}, {$fab_item["fab_med_fuelle"]}, {$fab_item["fab_min_amt"]}, {$fab_item["fab_corte_machine"]},
                   {$fab_item["fab_roll_width"]}, {$fab_item["fab_fabric_gr"]}, {$fab_item["fab_manilla_length"]}, {$fab_item["fab_print_width"]},
                   {$fab_item["fab_print_height"]}, {$fab_item["fab_noprint_discount"]}, {$fab_item["fab_active"]}, {$fab_inc_id})";
         $res = $CON->no_result($sql);
         if($res)
         {
            $prc_headerid = mysql_insert_id();

            $sql = " select *
                     from price_lists_fab_items_prices
                     where
                     pl_id = {$_REQUEST["id"]} and
                     prc_headerid = {$fab_item["id"]}";
            $items_prices = $CON->select($sql);
            foreach($items_prices AS $items_price)
            {
               $sql = " insert into price_lists_fab_items_prices
                        (prc_headerid, pl_id, prc_amount, prc_price)
                        VALUES
                        ({$prc_headerid}, {$plid}, {$items_price["prc_amount"]}, {$items_price["prc_price"]})";
               $CON->no_result($sql);
            }

            $sql = " select *
                     from price_lists_fab_items_predefines
                     where
                     header_id = {$fab_item["id"]}";
            $predefines = $CON->select($sql);
            foreach($predefines AS $predefine)
            {
               $sql = " insert into price_lists_fab_items_predefines
                        (header_id, fab_med_width, fab_med_height, fab_med_fuelle, fab_printtype,
                         fab_print_colors_front, fab_print_colors_back)
                        VALUES
                        ({$prc_headerid}, {$predefine["fab_med_width"]}, {$predefine["fab_med_height"]}, {$predefine["fab_med_fuelle"]},
                         '{$predefine["fab_printtype"]}', {$predefine["fab_print_colors_front"]}, {$predefine["fab_print_colors_back"]})";
               $CON->no_result($sql);
            }
         }
      }
      ?>
      <script language="JavaScript">
         alert("Copia generada, redireccionando...");
         location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=basic&id=<?=$plid?>';
      </script>
      <?php
   }
}