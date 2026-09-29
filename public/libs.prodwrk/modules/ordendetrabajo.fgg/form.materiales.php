<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
$_REQUEST["agid"] = (int)$_REQUEST["agid"];
/* ?><script> alert("materiales <?php echo $_REQUEST["agid"]; ?>");</script><?php */

$_REQUEST["sql_catid"] = (int)$_REQUEST["sql_catid"];

foreach(array_keys($_REQUEST) AS $reqkey)
{
   if(strpos($reqkey, "sql_comvals_") !== false && strpos($reqkey, "sql_comvals_") == 0)
   {
      $compid     = substr($reqkey, strrpos($reqkey, "_") +1);
      $compvalid  = (int)$_REQUEST[$reqkey];
      if((int)$compid && (int)$compvalid)
      {
         $_SQL_COMPIDS[$compid] = $compvalid;
      }
   }
}

//----------------------------------------------------------------------------------
$_IS_SELLADORA = false;
if(strpos(strtoupper($hasopeninit["equipo_name"]), "SELLADORA") !== false)
   $_IS_SELLADORA = true;

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cat_id, t3.cat_title
         from item t1
         INNER JOIN item_productcats t2   ON t1.id = t2.item_id
         INNER JOIN productcats t3        ON t2.cat_id = t3.id
         where
         t1.item_status       > 0 and 
         t1.item_released     > 0 and 
         t1.item_prodwrk_act  = 1 and
         (
            t3.cat_itemreg_machine_assign = 0 or
            (
               t3.cat_itemreg_machine_assign = 1 and
               (
                  select count(*)
                  from item_equipos_rel trel
                  where
                  trel.item_id   = t1.id and 
                  trel.equipo_id = {$hasopeninit["win_equipoid"]}
               ) > 0
            )
         ) and
         t3.cat_repuestos_act = 0 and
         t3.id IN ({$_CONFIG["PINTURAS_CATID"]},{$_CONFIG["TELA_CATID"]}) ";
if($_IS_SELLADORA)
   $sql .= " and t3.id NOT IN ({$_CONFIG["PINTURAS_CATID"]}) ";
$sql .= " order by t3.cat_title, t1.item_title";
$allitems = $CON->select($sql);

foreach($allitems AS $allitem)
{
   $_CANADD = false;
   if($allitem["cat_id"] != (int)$_CONFIG["TELA_CATID"])
   {
      $sql = " select *, t3.id 'val_id'
               from tran_comments t1
               INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
               INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
               where
               t1.com_status  > 0 and
               t2.cat_id      = {$allitem["cat_id"]} and
               t3.add_status  > 0
               order by t1.com_name, t3.add_name";
      $chars = $CON->select($sql);

      foreach($chars AS $char)
      {
         if($agenda["fab_printtype"] == "FLEX")
         {
            if((int)$char["com_id"] == $_CONFIG["FLEX_TINTA_COLOR_CHARACTID"] ||
               (int)$char["com_id"] == $_CONFIG["FLEX_TINTA_COLOR_CHARACTID_2"]) 
            {
               $_CANADD = true;
            }
         }
         elseif($agenda["fab_printtype"] == "SERI")
         {
            if((int)$char["com_id"] == $_CONFIG["SERI_TINTA_COLOR_CHARACTID"] ||
               (int)$char["com_id"] == $_CONFIG["SERI_TINTA_COLOR_CHARACTID_2"]) 
            {
               $_CANADD = true;
            }
         }
      }
   }
   else
      $_CANADD = true;

   if($_CANADD)
      $_ALLCATS[$allitem["cat_id"]] = $allitem["cat_title"];
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cat_id, t3.cat_title
         from item t1
         INNER JOIN item_productcats t2   ON t1.id = t2.item_id
         INNER JOIN productcats t3        ON t2.cat_id = t3.id
         where
         t1.item_status       > 0 and 
         t1.item_released     > 0 and 
         t1.item_prodwrk_act  = 1 and
         (
            t3.cat_itemreg_machine_assign = 0 or
            (
               t3.cat_itemreg_machine_assign = 1 and
               (
                  select count(*)
                  from item_equipos_rel trel
                  where
                  trel.item_id   = t1.id and 
                  trel.equipo_id = {$hasopeninit["win_equipoid"]}
               ) > 0
            )
         ) ";
if((int)$_REQUEST["sql_catid"])
   $sql .= " and t2.cat_id = {$_REQUEST["sql_catid"]} ";
if($_IS_SELLADORA)
   $sql .= " and t3.id NOT IN ({$_CONFIG["PINTURAS_CATID"]}) ";
if(count($_SQL_COMPIDS))
{
   foreach(array_keys($_SQL_COMPIDS) AS $sql_compid)
   {
      $sql .= " and 
                (
                   select count(*)
                   from tran_comments_item_vals tvals
                   where
                   tvals.item_id = t1.id and
                   tvals.com_id  = {$sql_compid} and
                   tvals.val_id  = {$_SQL_COMPIDS[$sql_compid]}
                ) > 0 ";
   }
}
$sql .= " order by t3.cat_title, t1.item_title";
$allitems = $CON->select($sql);
foreach($allitems AS $allitem)
{
   $_ITEMS[$allitem["cat_id"]][] = $allitem;
}

//----------------------------------------------------------------------------------
// PREFILTER TELA ITEMS
$telaitems = $_ITEMS[$_CONFIG["TELA_CATID"]];
unset($_ITEMS[$_CONFIG["TELA_CATID"]]);
foreach($telaitems AS $telaitem)
{
   $hastelacolor = false;
   $hastelatype  = false;

   $sql = " select t1.*
            from tran_comments_item_vals t1
            INNER JOIN tran_comments_vals t2 ON t1.val_id = t2.id
            where
            t1.item_id = {$telaitem["id"]} and
            t1.com_id  = {$_CONFIG["TELA_COLOR_CHARACTID"]} and
            t1.val_id  IN ({$agenda["fab_mat_fabric_color"]}, {$agenda["fab_mat_manilla_color"]})";
   $comvals = $CON->select($sql);
   if((int)$comvals[0]["item_id"])
   {
      $hastelacolor = true;
   }

   $sql = " select t1.*
            from tran_comments_item_vals t1
            INNER JOIN tran_comments_vals t2 ON t1.val_id = t2.id
            where
            t1.item_id  = {$telaitem["id"]} and
            t1.com_id   = {$_CONFIG["TELA_MATERIAL_CHARACTID"]} and
            t2.add_name = '{$agenda["fab_type"]}'";
   $comvals = $CON->select($sql);
   if((int)$comvals[0]["item_id"])
   {
      $hastelatype = true;
   }
   if($hastelacolor && $hastelatype)
   {
      $_ITEMS[$_CONFIG["TELA_CATID"]][] = $telaitem;
   }
}

//----------------------------------------------------------------------------------
if((int)$_REQUEST["deletetran"])
{
   $currtme = time();

   delStockChange($CON, $_REQUEST["deletetran"]);

   $sql = " update stockchanges
            set
            stk_status     = 0,
            stk_updusr     = {$_SESSION["user_id"]},
            stk_upddat     = {$currtme}
            where
            id = {$_REQUEST["deletetran"]}";
   ?><script> alert("Borrar <?php echo $sql; ?>");</script><?php 
   $CON->no_result($sql);
}

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
$sql = " select t1.*, t3.item_title, t3.item_number_prod, t2.item_amount
         from stockchanges t1
         INNER JOIN stockchanges_items t2 ON t1.id = t2.stk_id
         INNER JOIN item t3               ON t2.item_id = t3.id
         where
         t1.sth_fromprodotid = {$hasopenot["id"]} and
         t1.stk_status = 2";
$stockchanges = $CON->select($sql);
?>
<form action="prodwrk.php" method="post" name="xform_inp" id="xform_inp">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="mode" value="<?=$_REQUEST["mode"]?>">
<input type="hidden" name="submode" value="">
<input type="hidden" name="refid" value="<?=$_REQUEST["refid"]?>">
<input type="hidden" name="deletetran" value="">
<input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">
<?php
if(count($stockchanges) && $stockchanges != false)
{  ?>
   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <table border="0" width="100%" cellpadding="6" cellspacing="0">
         <colgroup>
            <col width="100">
            <col width="150">
            <col>
            <col width="120">
            <col width="120">
            <col width="120">
         </colgroup>
         <tr>
            <td colspan="6" class="tdheader" style="color: white;background-color: #D23C48;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Egresos contabilizados</td>
         </tr>
         <tr>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD">Transacción</td>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD">Fecha/Hora</td>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD">Material</td>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD">Código</td>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">Cantidad</td>
            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">Opciones</td>
         </tr>
         <?php
         foreach($stockchanges AS $stockchange)
         {  ?>
            <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$stockchange["stk_num"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=date("d.m.Y H:i:s", $stockchange["stk_bookdate"])?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$stockchange["item_title"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$stockchange["item_number_prod"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center"><?=$stockchange["stk_annotation"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                  <div class="btnred" onclick="if(askDel('')) { document.xform_inp.deletetran.value='<?=$stockchange["id"]?>'; document.xform_inp.submit(); }"
                  style="width:120px">
                     <i class="fa fa-fw fa-times-circle" style="color:white;"></i> Eliminar&nbsp;
                  </div>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
      </td>
   </tr>
   </table>
   <div style="height:10px"></div>
   <?php
}
?>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
      <table border="0" width="100%" cellpadding="0" cellspacing="0">
      <tr>
         <td> 
            <select class="inptxt" style="background-color:#FFFFFF;width:400px" name="sql_catid"
            onchange="location.href = '/prodwrk.php?mid=<?=$_REQUEST["mid"]?>&mode=<?=$_REQUEST["mode"]?>&agid=<?=$_REQUEST["agid"]?>&refid=<?=$_REQUEST["refid"]?>&setnew=1&sql_catid=' +this.value">
               <option value="">Seleccione una categoria</option>
               <?php
               foreach(array_keys($_ALLCATS) AS $_ALLCATID)
               {  ?>
                  <option value="<?=$_ALLCATID?>" <?if($_ALLCATID == $_REQUEST["sql_catid"]) echo "selected"?>>
                     <?=$_ALLCATS[$_ALLCATID]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr id="idx_charact_opts" style="<?if(!(int)$_REQUEST["sql_catid"]) echo "display:none"?>">
         <td style="padding-top:10px">
            <div id="idx_charact_jqres">
               <?php
               if((int)$_REQUEST["setnew"])
               {
                  //PRESET DEFAULT FOR PAPERS
                  if((int)$_REQUEST["sql_catid"] == $_CONFIG["TELA_CATID"])
                  {
                     $sql = " select *, t3.id 'val_id'
                              from tran_comments t1
                              INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
                              INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
                              where
                              t1.com_status  > 0 and
                              t2.cat_id      = {$_REQUEST["sql_catid"]} and
                              t3.add_status  > 0
                              order by t1.com_name, t3.add_name";
                     $chars = $CON->select($sql);

                     foreach($chars AS $char)
                     {
                        if((int)$char["com_id"] == $_CONFIG["TELA_COLOR_CHARACTID"]) 
                        {
                           if((int)$char["val_id"] == $agenda["fab_mat_fabric_color"])
                           {
                              $_REQUEST["sql_comvals_{$char["com_id"]}"] = $char["val_id"];

                              $hasmat = false;
                              foreach($chars AS $xchar)
                              {
                                 if($xchar["add_name"] == $agenda["fab_type"])
                                 {
                                    $_REQUEST["sql_comvals_{$xchar["com_id"]}"] = $xchar["val_id"];
                                    $hasmat = true;
                                 }
                              }
                           }
                        }
                     }
                  }
               }

               printPcatFiltersWrk($CON, $_REQUEST["sql_catid"], "prodwrk")
               ?>
            </div>
         </td>
      </tr>
      </table>
   </td>
</tr>
</table>
<?php
if((int)$_REQUEST["sql_catid"])
{  
   $sql = " select *
            from productcats
            where
            id = {$_REQUEST["sql_catid"]}";
   $pcatdata = $CON->select($sql);
   $pcatdata = $pcatdata[0];

   $sql = " select t1.com_name, t3.*
            from tran_comments t1
            INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
            INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
            where
            t1.com_status  > 0 and
            t2.cat_id      = {$_REQUEST["sql_catid"]} and
            t3.add_status  > 0
            order by t1.com_name, t3.add_name";
   $trancoms = $CON->select($sql);
   foreach($trancoms AS $trancom)
   {
      $_TRANSCOM[$trancom["add_com_id"]]["NAME"] = $trancom["com_name"];
      $_TRANSCOM[$trancom["add_com_id"]]["OPTS"][$trancom["id"]] = $trancom["add_name"];
   }
   ?>
   <div style="height:10px"></div>
   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <table border="0" width="100%" cellpadding="6" cellspacing="0">
         <colgroup>
         </colgroup>
         <tr>
            <td class="tdheader" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Descripción material</td>
            <td class="tdheader" width="100" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Código</td>
            <?php
            if((int)$pcatdata["cat_itemreg_width"])
            {  ?>
               <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Ancho(cm)</td>
               <?php
            }
            if((int)$pcatdata["cat_itemreg_length"])
            {  ?>
               <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Longitud(m)</td>
               <?php
            }
            if((int)$pcatdata["cat_itemreg_gsm"])
            {  ?>
               <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">GSM(gr)</td>
               <?php
            }
            if((int)$pcatdata["cat_itemreg_kg"])
            {  ?>
               <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kilogramos</td>
               <?php
            }
            foreach(array_keys($_TRANSCOM) AS $comid)
            {  
               $commname = ucwords(strtolower($_TRANSCOM[$comid]["NAME"]));
               ?>
               <td align="center" class="tdheader" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD"><?=$commname?></td>
               <?php
            }
            ?>
            <td width="100" class="tdheader" align="center" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD;">Stock</td>
            <td width="20" class="tdheader" align="center" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD;border-right:1px solid #DDDDDD"><i class="fa fa-fw fa-download"></i></td>
         </tr>
         <?php
         foreach($_ITEMS[$_REQUEST["sql_catid"]] AS $item)
         {  
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

            if($stock > 0.00)
            {  ?>
               <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$item["item_title"]?></td>
                  <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$item["item_number_prod"]?></td>
                  <?php
                  if((int)$pcatdata["cat_itemreg_width"])
                  {  ?>
                     <td align="center" class="tdnrm" style="border-left:1px solid #DDDDDD"><?=printPrice($item["item_reg_width"])?></td>
                     <?php
                  }
                  if((int)$pcatdata["cat_itemreg_length"])
                  {  ?>
                     <td align="center" class="tdnrm" style="border-left:1px solid #DDDDDD"><?=printPrice($item["item_reg_length"])?></td>
                     <?php
                  }
                  if((int)$pcatdata["cat_itemreg_gsm"])
                  {  ?>
                     <td align="center" class="tdnrm" style="border-left:1px solid #DDDDDD"><?=printPrice($item["item_reg_gsm"])?></td>
                     <?php
                  }
                  if((int)$pcatdata["cat_itemreg_kg"])
                  {  ?>
                     <td align="center" class="tdnrm" style="border-left:1px solid #DDDDDD"><?=printPrice($item["item_reg_kg"],2)?></td>
                     <?php
                  }
                  foreach(array_keys($_TRANSCOM) AS $comid)
                  {  ?>
                     <td align="center" class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$_COMVALS[$comid]?>&nbsp;</td>
                     <?php
                  }
                  ?>
                  <td class="tdnrm" align="center" style="border-left:1px solid #DDDDDD;"><?=printPrice($stock,10)?></td>
                  <td class="tdnrm" align="center" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD;cursor:pointer"
                  onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/fancy.materiales.php?itemid=<?=$item["id"]?>&refid=<?=$_REQUEST["refid"]?>&agid=<?=$_REQUEST["agid"]?>&otid=<?=$hasopenot["id"]?>', 'iframe', '800', '600', 'auto')"
                  title="Retiro material"><i class="fa fa-fw fa-download"></i></td>
               </tr>
               <?php
               $hasstock = true;
            }
         }
         if(!$hasstock)
         {  ?>
            <tr>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD;" colspan="20" align="center">
                  <br>
                  <b class="msg_save_err">No hay existencias en la categoria seleccionada.</b>
                  <br><br>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
      </td>
   </tr>
   </table>
   <?php
}
?>
</form>