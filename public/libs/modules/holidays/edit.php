<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       20.01.2011
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["year"] == "")
   $_REQUEST["year"] = date('Y');

$startdate  = mktime(0, 0, 0, 1, 1, $_REQUEST["year"]);
$enddate    = mktime(23, 59, 59, 12, 31, $_REQUEST["year"]);

$startlimit = date('Y-m-d', $startdate);
$endlimit   = date('Y-m-d', $enddate);

$weekdays[1]   = $_LANG["MODULE"]["CAL"][12];
$weekdays[2]   = $_LANG["MODULE"]["CAL"][13];
$weekdays[3]   = $_LANG["MODULE"]["CAL"][14];
$weekdays[4]   = $_LANG["MODULE"]["CAL"][15];
$weekdays[5]   = $_LANG["MODULE"]["CAL"][16];
$weekdays[6]   = $_LANG["MODULE"]["CAL"][17];
$weekdays[7]   = $_LANG["MODULE"]["CAL"][18];

if($_REQUEST["exec"] == "save")
{
   $sql = " delete from holidays
            where
            holiday_date between {$startdate} and {$enddate}";
   $CON->no_result($sql);

   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "holiday_date_") !== false && $_REQUEST[$reqkey] != "")
      {
         $idx  = substr($reqkey, strrpos($reqkey, "_") +1);
         $day  = $_REQUEST[$reqkey];
         $day  = explode(".", $day);
         $day  = mktime(0, 0, 0, $day[1], $day[0], $day[2]);
         $week = date('W / Y', $day);
         $desc = trim(addslashes($_REQUEST["holiday_desc_{$idx}"]));

         if($day > 0)
         {         
            $sql = " insert into holidays
                     (holiday_date, holiday_week, holiday_desc)
                     VALUES
                     ({$day}, '{$week}', '{$desc}') ";
            $CON->no_result($sql);
         }
      }
   }
}

$sql = " select *
         from holidays
         where
         holiday_date between {$startdate} and {$enddate}
         order by holiday_date";
$holidays = $CON->select($sql);
?>
<form action="index.php" method="post" name="idx_holiday">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="year" value="<?=$_REQUEST["year"]?>">
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="650">
<tr>
   <td height="30"><b class="content_header">Dias feriados: <?=$_REQUEST["year"]?></b></td>
   <td align="right">
      <table border="0" cellpadding="0" cellspacing="0">
      <tr>
         <td class="content_row_clear">&nbsp;&nbsp;</td>
         <td class="content_row_clear">
            <b>Jahr:</b>
            <select class="text" name="filter_year"
            onchange="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&year=' +this.value">
               <?php
               $startyear  = date('Y') -6;
               $endyear    = date('Y') +2;
               
               for($x = $startyear; $x <= $endyear; $x++)
               {
                  ?>
                  <option value="<?=$x?>"
                  <?php if($x == $_REQUEST["year"]) echo "selected" ?>><?=$x?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="100">
   <col width="100">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Dias feriados: <?=$_REQUEST["year"]?></td>
</tr>
<tr>
   <td class="content_tbl_subheader">Fecha</td>
   <td class="content_tbl_subheader">Dia</td>
   <td class="content_tbl_subheader">Descripción</td>
</tr>
<?php
for($x = 0; $x < 20; $x++)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row">
         <input type="text" style="width:70px" id="holiday_date_<?=$x?>" name="holiday_date_<?=$x?>"
         class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency range-low-<?=$startlimit?> range-high-<?=$endlimit?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         value="<?php if((int)$holidays[$x]["holiday_date"]) echo date('d.m.Y', $holidays[$x]["holiday_date"])?>">
      </td>
      <td class="content_row">
         <?php
         if((int)$holidays[$x]["holiday_date"])
         {
            $weekday = date('w', $holidays[$x]["holiday_date"]);
            if($weekday == 0)
               $weekday = 7;
            echo $weekdays[$weekday];
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row">
         <input class="text" name="holiday_desc_<?=$x?>" style="width:420px" value="<?=$holidays[$x]["holiday_desc"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <ul class="postnav_save">
         <a href="#"
         onclick="submitForm(document.idx_holiday)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
      </ul>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('idx_holiday');" ?>