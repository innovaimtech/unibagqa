<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2017 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../classes/page.php");
require_once("../../classes/mysql.php");
require_once("../../config.php");


//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../lang/es.php");
require_once("../../functions.php");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

$_REQUEST["currturnoid"]   = (int)$_REQUEST["currturnoid"];
$_REQUEST["instid"]        = (int)$_REQUEST["instid"];

?>
<option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
<?php

if((int)$_REQUEST["instid"])
{
   $sql = " select t1.*, t2.jorn_name
            from turnos t1
            LEFT OUTER JOIN turnos_jornadas t2              ON t1.turn_jornada_id = t2.id
            LEFT OUTER JOIN customer_instalaciones_hours t3 ON t1.id = t3.turno_id
            where
            t1.id = {$_REQUEST["currturnoid"]} or
            (
               t1.turn_status > 0 and
               t2.jorn_status > 0 and
               t3.inst_id     = {$_REQUEST["instid"]}
            )
            order by t2.jorn_order, t2.jorn_name, t1.turn_order, t1.turn_name";
   $turnos = $CON->select($sql);

   for($x = 0; $x < count($turnos) && $turnos != false; $x++)
   {
      $row = $turnos[$x];
      if(!is_array($_RES[$row["jorn_name"]]))
         $_RES[$row["jorn_name"]] = Array();
      $_RES[$row["jorn_name"]][] = $row;
   }

   foreach(array_keys($_RES) AS $jornname)
   {  ?>
      <option value="">* * * * * * * * * * * * <?=$jornname?> * * * * * * * * * * * *</option>
      <?php
      foreach($_RES[$jornname] AS $row)
      {  ?>
         <option value="<?=$row["id"]?>" <?php if($row["id"] == $_REQUEST["currturnoid"]) echo "selected"?>><?=$row["turn_name"]?></option>
         <?php
      }
   }
}



/*
$sql = " select t1.*
         from turnos_pautas_sections t1
         where
         t1.sect_status > 0 and
         t1.sect_pauta_id = {$_REQUEST["pautaid"]}
         order by t1.sect_order, t1.sect_name";
$sections = $CON->select($sql);
?>
<option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
<?php
foreach($sections AS $section)
{
   $sql = " select *
            from turnos_pautas_sections_groups
            where
            grp_status  > 0 and
            grp_sect_id = {$section["id"]}
            order by grp_order, grp_name";
   $grps = $CON->select($sql);
   foreach($grps AS $grp)
   {
      ?>
      <option value="">* * * * * * * * * * * * <?=$section["sect_name"]?>: <?=$grp["grp_name"]?> * * * * * * * * * * * *</option>
      <?php
      $sql = " select t1.*
               from turnos_pautas_sections_groups_turnos t1
               where
               t1.turn_status > 0 and
               t1.turn_grp_id = {$grp["id"]}
               order by t1.id";
      $posdata = $CON->select($sql);
      foreach($posdata AS $posdatarow)
      {
         ?>
         <option value="<?=$posdatarow["id"]?>"><?=$posdatarow["turn_name"]?></option>
         <?php
      }
   }
}
*/