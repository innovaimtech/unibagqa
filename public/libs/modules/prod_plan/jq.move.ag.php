<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

$_REQUEST["agid"] = (int)$_REQUEST["agid"];

$sql = " select *
         from prod_agenda
         where
         id = {$_REQUEST["agid"]}";
$agdata = $CON->select($sql);
$agdata = $agdata[0];

//----------------------------------------------------------------------------------
$sql = " select distinct t0.*
         from prod_agenda t0
         INNER JOIN prod_header t3x       ON t0.ag_prdid = t3x.id and t3x.prd_status >= 2
         INNER JOIN orders t1             ON t0.ag_reqid = t1.id
         LEFT OUTER JOIN company_data t2  ON t1.req_company_id = t2.id
         LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id    = t3.id
         LEFT OUTER JOIN customer t4      ON t1.req_cust_id    = t4.id
         INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
         INNER JOIN item t2x              ON t1x.item_id = t2x.id
         LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
         LEFT OUTER JOIN tran_comments_vals v2 ON t1x.fab_mat_manilla_color = v2.id
         where
         t0.ag_status      > 0 and
         t0.ag_date        = '{$agdata["ag_date"]}' and
         t0.ag_plantaid    = {$agdata["ag_plantaid"]} and
         t0.ag_equipo_id   = {$agdata["ag_equipo_id"]}
         order by t0.ag_order, t0.id asc";
$agendas = $CON->select($sql);
$origags = $agendas;

//----------------------------------------------------------------------------------
$thisidx = 0;
for($x = 0; $x < count($agendas) && $agendas != false; $x++)
{
   if((int)$agendas[$x]["id"] == $agdata["id"])
      $thisidx = $x;
}

//----------------------------------------------------------------------------------
if($_REQUEST["xmode"] == "downlast")
{
   $temp = Array();
   for($x = 0; $x < count($agendas) && $agendas != false; $x++)
   {
      if($x != $thisidx)
         $temp[] = $agendas[$x];
   }
   $temp[] = $agendas[$thisidx];
   $agendas = $temp;
}

//----------------------------------------------------------------------------------
if($_REQUEST["xmode"] == "down")
{
   $temp = Array();
   for($x = 0; $x < count($agendas) && $agendas != false; $x++)
   {
      if($x != $thisidx)
      {
         $temp[] = $agendas[$x];
      }
      if($x == $thisidx +1)
         $temp[] = $agendas[$thisidx];
   }
   $agendas = $temp;
}

//----------------------------------------------------------------------------------
if($_REQUEST["xmode"] == "up" && $thisidx != 0)
{
   $temp = Array();
   for($x = 0; $x < count($agendas) && $agendas != false; $x++)
   {
      if($x == $thisidx-1)
      {
         $temp[] = $agendas[$thisidx];
         $temp[] = $agendas[$x];
      }
      elseif($x != $thisidx)
      {
         $temp[] = $agendas[$x];
      }
   }
   $agendas = $temp;
}

//----------------------------------------------------------------------------------
if($_REQUEST["xmode"] == "upfirst")
{
   $temp = Array();
   $temp[] = $agendas[$thisidx];
   for($x = 0; $x < count($agendas) && $agendas != false; $x++)
   {
      if($x != $thisidx)
         $temp[] = $agendas[$x];
   }
   $agendas = $temp;
}

//----------------------------------------------------------------------------------
for($x = 0; $x < count($agendas) && $agendas != false; $x++)
{
   $sql = " update prod_agenda
            set
            ag_order = {$x}
            where
            id = {$agendas[$x]["id"]}";
   $CON->no_result($sql);
}
?>
<div style="display:none"><?=md5(microtime())?></div>
<script language="JavaScript">
   <?php
   if($_REQUEST["xmode"] == "down")
   {  ?>
      var nextdiv = $('#idx_agitem_<?=$_REQUEST["agid"]?>').next();
      if(nextdiv.length)
      {
         $('#idx_agitem_<?=$_REQUEST["agid"]?>').fadeOut(300, function()
         {
            var temp = $('#idx_agitem_<?=$_REQUEST["agid"]?>').detach();
            temp.insertAfter('#' +nextdiv.attr('id'));
            temp.fadeIn(600);
         });
         
      }
      <?php
   }
   elseif($_REQUEST["xmode"] == "up")
   {  ?>
      var prevdiv = $('#idx_agitem_<?=$_REQUEST["agid"]?>').prev();
      if(prevdiv.length)
      {
         $('#idx_agitem_<?=$_REQUEST["agid"]?>').fadeOut(300, function()
         {
            var temp = $('#idx_agitem_<?=$_REQUEST["agid"]?>').detach();
            temp.insertBefore('#' +prevdiv.attr('id'));
            temp.fadeIn(600);
         });
      }
      <?php
   }
   elseif($_REQUEST["xmode"] == "upfirst")
   {  ?>
      var prevdiv = $('#idx_agitem_<?=$origags[0]["id"]?>');
      if(prevdiv.length)
      {
         $('#idx_agitem_<?=$_REQUEST["agid"]?>').fadeOut(300, function()
         {
            var temp = $('#idx_agitem_<?=$_REQUEST["agid"]?>').detach();
            temp.insertBefore('#' +prevdiv.attr('id'));
            temp.fadeIn(600);
         });
      }
      <?php
   }
   elseif($_REQUEST["xmode"] == "downlast")
   {  ?>
      var nextdiv = $('#idx_agitem_<?=$origags[(count($origags)-1)]["id"]?>');
      if(nextdiv.length)
      {
         $('#idx_agitem_<?=$_REQUEST["agid"]?>').fadeOut(300, function()
         {
            var temp = $('#idx_agitem_<?=$_REQUEST["agid"]?>').detach();
            temp.insertAfter('#' +nextdiv.attr('id'));
            temp.fadeIn(600);
         });
      }
      <?php
   }
   ?>
   
</script>
<?php