<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();

   $_REQUEST["user_id"]    = (int)$_REQUEST["user_id"];
   $_REQUEST["company_id"] = (int)$_REQUEST["company_id"];
   $_REQUEST["shop_id"]    = (int)$_REQUEST["shop_id"];

   $sql_date = mktime(0, 0, 0, date("m"), date("d"), date("Y"));

   $sql = " insert into bonus
            (bon_user_id, bon_company_id, bon_shop_id, bon_date, bon_crtusr, bon_crtdat)
            VALUES
            ({$_REQUEST["user_id"]}, {$_REQUEST["company_id"]}, {$_REQUEST["shop_id"]}, {$sql_date},
            {$_SESSION["user_id"]}, {$currtme})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from bonus
               where
               bon_crtusr = {$_SESSION["user_id"]}";
      $thisid = $CON->select($sql);
      $_REQUEST["id"] = $thisid[0]["thisid"];

      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=822&exec=edit&id=<?=$_REQUEST["id"]?>'
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $_REQUEST["bon_desc"]    = trim(addslashes($_REQUEST["bon_desc"]));

   $sql_date = explode(".", $_REQUEST["bon_date"]);
   $sql_date = mktime(0, 0, 0, $sql_date[1], $sql_date[0], $sql_date[2]);

   $sql = " update bonus
            set
            bon_desc    = '{$_REQUEST["bon_desc"]}',
            bon_date    = {$sql_date},
            bon_status  = {$_REQUEST["setStatus"]},
            bon_updusr  = {$_SESSION["user_id"]},
            bon_upddat  = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " delete from bonus_pos
            where
            bon_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      //----------------------------------------------------------------------------------
      if(strpos($reqkey, "pos_desc_") !== false && strpos($reqkey, "pos_desc_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $desc  = trim(addslashes($_REQUEST["pos_desc_{$idx}"]));
         $price = getPrice($_REQUEST["pos_price_{$idx}"]);

         if($_REQUEST["pos_desc_{$idx}"] != "")
         {
            $sql = " insert into bonus_pos
                     (bon_id, pos_desc, pos_price)
                     VALUES
                     ({$_REQUEST["id"]}, '{$desc}', {$price})";
            $CON->no_result($sql);
         }
      }
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t6.company_short, t7.shop_name,
         t5.user_firstname 'bon_firstname', t5.user_lastname 'bon_lastname',
         t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname',
         t4.user_firstname 'upd_firstname', t4.user_lastname 'upd_lastname'
         from bonus t1
         LEFT OUTER JOIN user t3 ON t1.bon_crtusr  = t3.id
         LEFT OUTER JOIN user t4 ON t1.bon_updusr  = t4.id
         LEFT OUTER JOIN user t5 ON t1.bon_user_id = t5.id
         LEFT OUTER JOIN company_data  t6 ON t1.bon_company_id = t6.id
         LEFT OUTER JOIN company_shops t7 ON t1.bon_shop_id    = t7.id
         where
         t1.id = {$_REQUEST["id"]}";
$bonus = $CON->select($sql);
$bonus = $bonus[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from bonus_pos t1
         where
         t1.bon_id = {$_REQUEST["id"]}
         order by t1.id";
$posdata = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from user
         where
         user_status = 1
         order by user_firstname, user_lastname";
$users = $CON->select($sql);

//----------------------------------------------------------------------------------
$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

if($rowcount <= 6)
   $rowcount = 10;
else
   $rowcount += 5;

//----------------------------------------------------------------------------------
$rdnl = "";
$dabl = "";
$statstr2 = "<b class='msg_save_err'>Pendiente</b>";
if($bonus["bon_status"] == 2)
{
   //----------------------------------------------------------------------------------
   $rowcount = count($posdata);

   //----------------------------------------------------------------------------------
   $rdnl = "readonly";
   $dabl = "disabled";

   //----------------------------------------------------------------------------------
   $statstr2 = "<b class='msg_save_ok'>Finalizado</b>";
}
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function setStatusx(val)
   {
      document.getElementById('setStatus').value = val;
   }
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_bonus"
onsubmit="return checkform(new Array(this.bon_date))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="setStatus" id="setStatus" value="<?=$bonus["bon_status"]?>">
<?=Nifty_printH("box1", "980")?>
<table cellpadding="3" cellspacing="0" width="100%" border="0">
<colgroup>
   <col width="130">
   <col width="400">
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos básicos</td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$bonus["company_short"]?></td>
   <td class="content_rowl">Surcusal</td>
   <td class="content_row"><?=$bonus["shop_name"]?></td>
</tr>
<tr>
   <td class="content_rowl">Persona</td>
   <td class="content_row"><?=$bonus["bon_firstname"]?> <?=$bonus["bon_lastname"]?></td>
   <td class="content_rowl">Fecha *</td>
   <td class="content_row">
      <input name="bon_date" id="bon_date" style="width:70px" readonly
      value="<?php if((int)$bonus["bon_date"]) echo date('d.m.Y', $bonus["bon_date"]); else echo date("d.m.Y");?>"
      class="text <?if($rdnl == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency ".getDateSelectLimits();?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdnl?>>
   </td>
</tr>
<tr>
   <td class="content_rowl">Estado</td>
   <td class="content_row" colspan="3">
      <table border="0" cellpadding="1" cellspacing="0">
      <tr>
         <td width="25">
            <img class="select" src="./images/content/red<?php
            $statstr = "Pendiente";
            if($bonus["bon_status"] == 1 || $_REQUEST["id"] == "")
            {
               echo "_active";
            }
            ?>.gif" title="Status: <?=$statstr?>">
         </td>
         <td width="25">
            <img class="select" src="./images/content/green<?php
            $statstr = "Finalizado";
            if($bonus["bon_status"] == 2)
            {
               echo "_active";
            }
            ?>.gif" title="Status: <?=$statstr?>">
         </td>
         <td width="125" class="content_row_clear">&nbsp;<?=$statstr2?></td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripcíon</td>
   <td class="content_row" colspan="3">
      <textarea type="text" class="text" name="bon_desc" style="width:850px; height:40px" <?=$rdnl?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$bonus["bon_desc"]?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
   <td class="content_row"><?php if($bonus["bon_crtusr"] != "") echo "{$bonus["crt_firstname"]} {$bonus["crt_lastname"]}"?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
   <td class="content_row"><?php if($bonus["bon_crtusr"] != "") echo displayDate($bonus["bon_crtdat"])?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
   <td class="content_row"><?php if($bonus["bon_updusr"] != "") echo "{$bonus["upd_firstname"]} {$bonus["upd_lastname"]}"?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
   <td class="content_row"><?php if($bonus["bon_updusr"] != "") echo displayDate($bonus["bon_upddat"])?>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("box1", "980")?>
<table cellpadding="3" cellspacing="0" width="100%" border="0">
<colgroup>
   <col width="40">
   <col>
   <col width="100">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="6">Artículos</td>
</tr>
<tr>
   <td class="content_tbl_subheader" align="center">Act.</td>
   <td class="content_tbl_subheader">Descripción</td>
   <td class="content_tbl_subheader" align="right">Monto</td>
</tr>
<?php
for($x = 0; $x < $rowcount; $x++)
{  ?>
   <tr>
      <td class="content_row" align="center">
         <?php
         if((int)$posdata[$x]["id"] && $bonus["bon_status"] != 2)
         {  ?>
            <input type="button" class="buttonred" value="x" style="width:20px"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
            onclick="if(askDel('')) { document.xform_bonus.pos_desc_<?=$x?>.value=''; submitForm(document.xform_bonus); }">
            <?php
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row">
         <input type="text" class="text" style="width:820px" name="pos_desc_<?=$x?>" <?=$rdnl?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$posdata[$x]["pos_desc"]?>">
      </td>
      <td class="content_row" align="right">
         <input type="text" class="text" style="width:90px; text-align:right" name="pos_price_<?=$x?>" <?=$rdnl?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?if($posdata[$x]["pos_price"] > 0) echo printPrice($posdata[$x]["pos_price"])?>">
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
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
      <?php
   }
   ?>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["id"] != "" && $bonus["bon_status"] == 2)
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton("Anular", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) { setStatusx(1); submitForm(document.xform_bonus); }", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   if($_REQUEST["id"] != "" && $bonus["bon_status"] != 2)
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   if((int)$bonus["bon_status"] != 2)
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.xform_bonus)", "disk-black");
         ?>
      </td>
      <?php
   }
   if($_REQUEST["id"] != "" && (int)$bonus["bon_status"] == 1)
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton("Finalizar", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { setStatusx(2); submitForm(document.xform_bonus); }", "tick-circle-frame");
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
</form>
<?=Nifty_printF(false)?>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_bonus');" ?>