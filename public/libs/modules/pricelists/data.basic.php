<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $_REQUEST["pl_title"]         = trim(addslashes($_REQUEST["pl_title"]));
   $_REQUEST["pl_desc"]          = trim(addslashes($_REQUEST["pl_desc"]));
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $sql = " insert into price_lists
               (pl_title, pl_desc, pl_crtusr, pl_crtdat)
               VALUES
               ('{$_REQUEST["pl_title"]}', '{$_REQUEST["pl_desc"]}', {$_SESSION["user_id"]}, {$currtme})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'thisid'
                  from price_lists
                  where
                  pl_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
      
         $_REQUEST["id"] = $thisid[0]["thisid"];
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update price_lists
               set
               pl_title          = '{$_REQUEST["pl_title"]}',
               pl_desc           = '{$_REQUEST["pl_desc"]}',
               pl_updusr         = {$_SESSION["user_id"]},
               pl_upddat         = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      foreach(array_keys($_REQUEST) AS $reqkey)
      {
         if(strpos($reqkey, "cust_id_") !== false && strpos($reqkey, "cust_id_") == 0)
         {
            $sql = " update customer
                     set
                     cust_plid = {$_REQUEST["id"]}
                     where
                     id = {$_REQUEST[$reqkey]}";
            $CON->no_result($sql);
         }
      }

      //----------------------------------------------------------------------------------
      if($_REQUEST["del_custid"] != "")
      {
         $sql = " update customer
                  set
                  cust_plid = 0
                  where
                  id = {$_REQUEST["del_custid"]}";
         $CON->no_result($sql);
      }
   }

   $sql = " delete from price_lists_shops
            where
            pl_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   foreach($_REQUEST["shops"] AS $shopid)
   {
      $sql = " insert into price_lists_shops
               (pl_id, shop_id)
               VALUES
               ({$_REQUEST["id"]}, {$shopid})";
      $CON->no_result($sql);            
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $sql = " select t1.*, 
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from price_lists t1
            LEFT OUTER JOIN user t2 ON t1.pl_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.pl_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $plist = $CON->select($sql);
   $plist = $plist[0];

   $sql = " select id 'cust_id', cust_name
            from customer
            where
            cust_status = 1 and
            cust_plid   = {$_REQUEST["id"]}
            order by cust_name";
   $posdata = $CON->select($sql);

   $sql = " select *
            from price_lists_shops
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
<table cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed">
<colgroup>
   <col width="500" valign="top">
   <col width="15">
   <col valign="top">
</colgroup>
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
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
            <input name="pl_title" type="text" class="text" style="width:360px" value="<?=$plist["pl_title"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" valign="top">Descripción</td>
         <td class="content_row">
            <textarea name="pl_desc" class="text" style="width:360px;height:50px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$plist["pl_desc"]?></textarea>
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
   </td>
   <td></td>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
      <colgroup>
         <col width="140">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Asignar Sucursales</td>
      </tr>
      <tr>
         <td class="content_row" colspan="2">
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <?php
            $shops = getShops($CON, false, true);
            foreach($shops AS $shop)
            {  ?>
               <tr>
                  <td class="content_row">
                     <input type="checkbox" value="<?=$shop["id"]?>" name="shops[]"
                     <?php if((int)$_SELSHOPS[$shop["id"]]) echo "checked"?>>
                     <?=$shop["company_short"]?>:&nbsp;<?=$shop["shop_name"]?>
                  </td>
               </tr>
               <?php
            }
            ?>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
   </td>
</tr>
</table>
<br>
<?php
if($_REQUEST["id"] != "")
{  ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="85">
      <col width="28">
      <col width="380">
      <col width="85">
      <col width="28">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="6">Asignación clientes</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < $rowcount; $zz = 1)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <?php
         for($y = 0; $y < 2; $y++)
         {  ?>
            <td class="content_row">
               <table border="0" cellpadding="0" cellspacing="0" width="100%" id="idx_tdcol1_<?=$x?>">
               <tr>
                  <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
                  <td>
                     <input type="text" class="text" style="width:60px" name="xf_search_<?=$x?>"
                     onfocus="markfield(this,0)"
                     <?php
                     if(!(int)$posdata[$x]["cust_id"])
                     {  ?>
                        onblur="markfield(this,1);document.all.idxifrsrc.src='./libs/modules/orders/searchcust.php?rowcount=<?=$x?>&search=' +this.value"
                        <?php
                     }
                     else
                     {  ?>
                        onblur="markfield(this,1)"
                        <?php
                        $hasItems = true;
                     }
                     ?>>
                  </td>
               </tr>
               </table>
            </td>
            <td class="content_row" valign="top">
               <?php
               if((int)$posdata[$x]["cust_id"])
               {  ?>
                  <input type="button" class="buttonred" value="x" style="width:20px"
                  onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
                  onclick="if(askDel('')) { document.xform_pl.del_custid.value='<?=$posdata[$x]["cust_id"]?>'; submitForm(document.xform_pl); }">
                  <?php
               }
               ?>
            </td>
            <td class="content_row" valign="top">
               <select class="text" style="width:360px;" name="cust_id_<?=$x?>" id="cust_id_<?=$x?>"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <?php
                  if((int)$posdata[$x]["cust_id"])
                  {  ?>
                     <option value="<?=$posdata[$x]["cust_id"]?>"><?=$posdata[$x]["cust_name"]?></option>
                     <?php
                  }
                  ?>
               </select>
            </td>
            <?php
            if($x == 0 && !(int)$posdata[$x]["cust_id"])
               $_SESSION["JSEXEC"] .= "document.xform_pl.xf_search_{$x}.focus();";
         
            $x++;
         }
         ?>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}
?>
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
      printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid=762&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
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