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

$currtme       = $_REQUEST["currtme"];
$start_month   = $_REQUEST["start_month"];
$start_year    = $_REQUEST["start_year"];

$sql = " select t1.*, t2.cust_name, t3.data_puesto_index, t4.cust_name 'cliente', t4.id 'clienteid'
         from turnos_config_balance t1
         INNER JOIN customer_instalaciones t2         ON t1.bal_inst_id = t2.id
         INNER JOIN customer_instalaciones_hours t3   ON t1.bal_inst_hour_id = t3.id
         INNER JOIN customer t4                       ON t2.cust_custid = t4.id
         where
         t2.cust_status    = 1 and
         t3.data_status    = 1 and
         t3.data_dateinit  <= {$currtme} and
         t3.data_dateend   >  {$currtme} and
         (
            (
               t1.bal_month  >= {$start_month} and
               t1.bal_year    = {$start_year}
            ) or
            (
               t1.bal_year   > {$start_year}
            )
         ) ";
if((int)$_REQUEST["custid"])
   $sql .= " and t2.cust_custid = {$_REQUEST["custid"]} ";
if((int)$_REQUEST["instid"])
   $sql .= " and t1.bal_inst_id = {$_REQUEST["instid"]} ";
$sql .= " order by t1.bal_year, t1.bal_month, t2.cust_name, t3.data_puesto_index";
$bals = $CON->select($sql);

$pfijos = Array();
for($x = 0; $x < count($bals) && $bals != false; $x++)
{
   $sql = " select count(*) 'cc'
            from turnos_config_assign
            where
            assign_cust_id             = {$bals[$x]["bal_cust_id"]} and
            assign_inst_id             = {$bals[$x]["bal_inst_id"]} and
            assign_inst_contr_pautaid  = {$bals[$x]["bal_inst_contr_pautaid"]} and
            assign_inst_hour_id        = {$bals[$x]["bal_inst_hour_id"]} and
            assign_isspecial           = {$bals[$x]["bal_isspecial"]} and
            assign_month               = {$bals[$x]["bal_month"]} and
            assign_year                = {$bals[$x]["bal_year"]}";
   $hasAssigns = $CON->select($sql);
   $hasAssigns = (int)$hasAssigns[0]["cc"];

   if(!$hasAssigns)
   {
      $pfijos[] = $bals[$x];
      $_INSTALLS[$bals[$x]["bal_inst_id"]] = $bals[$x]["cust_name"];
   }
}

if((int)$_REQUEST["custid"] && !(int)$_REQUEST["instid"])
{  ?>
   <script language="JavaScript">
      var obj = document.getElementById('sql_filterinst');
      obj.options.length = 1;
      <?php
      foreach(array_keys($_INSTALLS) AS $instid)
      {  ?>
         var newIndex   = obj.options.length;
         var newOpt     = new Option('<?=str_replace("'", "", addslashes($_INSTALLS[$instid]))?>');
         newOpt.value   = '<?=$instid?>';
         obj.options[newIndex] = newOpt;
         <?php
      }
      ?>
   </script>
   <?php
}
?>
<script language="JavaScript">
var obj2 = document.getElementById('sql_puestofijo_new');
obj2.options.length = 1;
<?php
for($x = 0; $x < count($pfijos); $x++)
{
   $xstr = sprintf("%02s", $pfijos[$x]["bal_month"])."-".$pfijos[$x]["bal_year"].":".$pfijos[$x]["cust_name"]." - P_".$pfijos[$x]["data_puesto_index"];
   ?>
   var newIndex   = obj2.options.length;
   var newOpt     = new Option('<?=str_replace("'", "", addslashes($xstr))?>');
   newOpt.value   = '<?=$pfijos[$x]["id"]?>';
   obj2.options[newIndex] = newOpt;
   <?php
}
?>
</script>
