<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_SESSION["_SUBMITBTN"] = 1;

if($_REQUEST["exec"] == "save")
{
   $currtme = time();
   
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "usd_val_") !== false)
      {
         $tstamp = substr($reqkey, strrpos($reqkey, "_") +1);

         $uf_val  = $_REQUEST["uf_val_{$tstamp}"];
         $usd_val = $_REQUEST["usd_val_{$tstamp}"];
         $eur_val = $_REQUEST["eur_val_{$tstamp}"];
         $yen_val = $_REQUEST["yen_val_{$tstamp}"];

         $uf_val  = (float)sprintf("%.2f", (float)str_replace(",", ".", str_replace(".", "", $uf_val)));
         $usd_val = (float)sprintf("%.8f", (float)str_replace(",", ".", str_replace(".", "", $usd_val)));
         $eur_val = (float)sprintf("%.8f", (float)str_replace(",", ".", str_replace(".", "", $eur_val)));
         $yen_val = (float)sprintf("%.8f", (float)str_replace(",", ".", str_replace(".", "", $yen_val)));

         if($_REQUEST["uf_val_{$tstamp}"] != "" || $_REQUEST["usd_val_{$tstamp}"] != "" || $_REQUEST["eur_val_{$tstamp}"] != ""  || $_REQUEST["yen_val_{$tstamp}"] != "")
         {
            $sql = " delete from money_exchange
                     where
                     exc_tstamp = {$tstamp}";
            $CON->no_result($sql);

            $sql = " insert into money_exchange
                     (exc_tstamp, exc_ufval, exc_usdval, exc_eurval, exc_yenval, exc_updusr, exc_upddat)
                     VALUES
                     ({$tstamp}, {$uf_val}, {$usd_val}, {$eur_val}, {$yen_val}, {$_SESSION["user_id"]}, {$currtme})";
            $CON->no_result($sql);
         }
      }
   }

   $savemsg = getSaveMessage(true);
}

//----------------------------------------------------------------------------------
$weekdays[1]   = $_LANG["MODULE"]["CAL"][12];
$weekdays[2]   = $_LANG["MODULE"]["CAL"][13];
$weekdays[3]   = $_LANG["MODULE"]["CAL"][14];
$weekdays[4]   = $_LANG["MODULE"]["CAL"][15];
$weekdays[5]   = $_LANG["MODULE"]["CAL"][16];
$weekdays[6]   = $_LANG["MODULE"]["CAL"][17];
$weekdays[7]   = $_LANG["MODULE"]["CAL"][18];

//----------------------------------------------------------------------------------
if($_REQUEST["setstartdate"] != "")
{
   $_REQUEST["startdate"] = explode(".", $_REQUEST["setstartdate"]);
   $_REQUEST["startdate"] = mktime(0, 0, 0, (int)$_REQUEST["startdate"][1], (int)$_REQUEST["startdate"][0], (int)$_REQUEST["startdate"][2]);
}
if($_REQUEST["setenddate"] != "")
{
   $_REQUEST["enddate"] = explode(".", $_REQUEST["setenddate"]);
   $_REQUEST["enddate"] = mktime(0, 0, 0, (int)$_REQUEST["enddate"][1], (int)$_REQUEST["enddate"][0], (int)$_REQUEST["enddate"][2]);
}

//----------------------------------------------------------------------------------
if($_REQUEST["startdate"] == "")
   $_REQUEST["startdate"] = mktime(0, 0, 0, (int)date('m'), (int)date('d'), (int)date('Y'));
if($_REQUEST["enddate"] == "")
   $_REQUEST["enddate"] = $_REQUEST["startdate"] + (30 * 86400);

$today = mktime(0, 0, 0, (int)date('m'), (int)date('d'), (int)date('Y'));

//----------------------------------------------------------------------------------
$sql = " select t1.*,
                t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname'  
         from money_exchange t1
         LEFT OUTER JOIN user t2 ON t1.exc_updusr = t2.id
         where
         t1.exc_tstamp between {$_REQUEST["startdate"]} and {$_REQUEST["enddate"]}";
$moneydata = $CON->select($sql);


foreach($moneydata AS $moneyrow)
   $moneyday[$moneyrow["exc_tstamp"]] = $moneyrow;
//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="xform_moneysel">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<table border="0" cellpadding="0" cellspacing="0" width="820">
<tr>
   <td height="30"><b class="content_header">Cambio de moneda</b></td>
   <td align="center"><?=$savemsg?></td>
   <td align="right" width="390">
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear" width="15">De</td>
         <td class="content_row_clear" width="90">
            <input type="text" style="width:70px" name="setstartdate" value="<?=date('d.m.Y', $_REQUEST["startdate"])?>"
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency" id="setstartdate"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row_clear" width="15">Hasta</td>
         <td class="content_row_clear" width="90">
            <input type="text" style="width:70px" name="setenddate" value="<?=date('d.m.Y', $_REQUEST["enddate"])?>"
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency" id="setenddate"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row_clear" width="120">
            <ul class="postnav">
               <a href="javascript: document.xform_moneysel.submit()">Mostrar</a>
            </ul>
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_headerline" colspan="3">&nbsp;</td>
</tr>
</form>
</table>
<form action="index.php" method="post" class="fokusfirst" name="xform_money">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="setstartdate" value="<?=date('d.m.Y', $_REQUEST["startdate"])?>">
<input type="hidden" name="setenddate" value="<?=date('d.m.Y', $_REQUEST["enddate"])?>">
<?=Nifty_printH("box1", "822")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="75">
   <col width="75">
   <col width="155">
   <col width="155">
   <col width="155">
   <col>
   <col width="95">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="7">Valores diarios</td>
</tr>
<tr>
   <td class="content_tbl_subheader content_row_os">Dia</td>
   <td class="content_tbl_subheader content_row_os">Fecha</td>
   <td class="content_tbl_subheader content_row_os" align="center">Valor: USD</td>
   <td class="content_tbl_subheader content_row_os" align="center">Valor: EUR</td>
   <td class="content_tbl_subheader content_row_os" align="center">Valor: CAD</td>
   <td class="content_tbl_subheader content_row_os">Cambiado por</td>
   <td class="content_tbl_subheader content_row_os">Cambiado</td>
</tr>
<?php
//----------------------------------------------------------------------------------

$counter    = 0;
$btnshowed  = false;
for($x = $_REQUEST["startdate"]; $x <= $_REQUEST["enddate"]; $x += 86400)
{

   //----------------------------------------------------------------------------------
   $weekday       = date('w', $x);
   if($weekday == 0)
      $weekday = 7;
      
   $weekdayname   = $weekdays[$weekday];

   //----------------------------------------------------------------------------------
   $hasvalues = false;
   if((int)$moneyday[$x]["id"])
   {
      $hasvalues = true;
   }

   if($x == $today)
      $rowcolor = "color:green";
   elseif($x < $today)
      $rowcolor = "color:red";
   else
      $rowcolor = "";
   ?>
   <tr bgcolor="<?=getRowColor($counter)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os" style="<?=$rowcolor?>"><b><?=$weekdayname?></b></td>
      <td class="content_row_os" style="<?=$rowcolor?>"><?=date('d.m.Y', $x)?></td>
      <td class="content_row_os" style="<?=$rowcolor?>">
         <nobr>
         1 USD=
         <input type="text" class="text" style="width:70px;text-align:right" name="usd_val_<?=$x?>"
         value="<?php if($hasvalues) echo printPrice($moneyday[$x]["exc_usdval"], 8)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         CLP
         </nobr>
      </td>
      <td class="content_row_os" style="<?=$rowcolor?>">
         <nobr>
         1 EUR=
         <input type="text" class="text" style="width:70px;text-align:right" name="eur_val_<?=$x?>"
         value="<?php if($hasvalues) echo printPrice($moneyday[$x]["exc_eurval"], 8)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         CLP
         </nobr>
      </td>
      <td class="content_row_os" style="<?=$rowcolor?>">
         <nobr>
         1 CAD=
         <input type="text" class="text" style="width:70px;text-align:right" name="yen_val_<?=$x?>"
         value="<?php if($hasvalues) echo printPrice($moneyday[$x]["exc_yenval"], 8)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         CLP
         </nobr>
      </td>
      <td class="content_row_os"><?php if($hasvalues) echo $moneyday[$x]["upd_lastname"]?>&nbsp;</td>
      <td class="content_row_os"><?php if($hasvalues) echo displayDate($moneyday[$x]["exc_upddat"])?>&nbsp;</td>
   </tr>
   <?php
   if($weekday == 7)
   {  ?>
      <tr>
         <td class="content_row_os" colspan="7">
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <tr>
               <td class="content_row_clear">&nbsp;</td>
               <td class="content_row_clear" width="120">
                  <ul class="postnav_save">
                     <a href="javascript: submitForm(document.xform_money)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
                  </ul>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <?php
      $btnshowed = true;
   }
   $counter++;
}
//----------------------------------------------------------------------------------
if(!$btnshowed)
{  ?>
   <tr>
      <td class="content_row" colspan="5">
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <tr>
            <td class="content_row_clear">&nbsp;</td>
            <td class="content_row_clear" width="120">
               <ul class="postnav_save">
                  <a href="javascript: submitForm(document.xform_money)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
               </ul>
            </td>
         </tr>
         </table>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
</form>
<br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_money');" ?>