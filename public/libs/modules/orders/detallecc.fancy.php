<?php
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

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

$sql = " select *
         from orders
         where
         id = {$_REQUEST["reqid"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

$_REQUEST["sql_stext"] = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));

//----------------------------------------------------------------------------------

$sql = " select t9.equipo_name                                                    as equipo
               , t4.evt_crtdat                                                    as inicio
               , t4.evt_enddat                                                    as termino
               , t4.evt_amount                                                    as produccion
               , case when t4.evt_enddat = 0 then 'En Curso' else 'Terminado' end as estado
               , t2.ag_amount                                                     as cantidad
               , o1.req_number                                                    as req_number  
               , o1.req_crtdat                                                    as fecha_creacion
               , c1.cust_rut                                                      as rut
               , c1.cust_name                                                     as nombre
            from prod_header t1
               INNER JOIN prod_agenda t2              ON t1.id = t2.ag_prdid
               INNER JOIN prod_worker_ot t3           ON t3.wok_ag_id = t2.id
               INNER JOIN prod_worker_ot_events t4    ON t4.evt_prod_worker_otid = t3.id
               INNER JOIN orders o1                   ON o1.id = t1.prd_reqid           
               LEFT OUTER JOIN equipo t9              ON t2.ag_equipo_id = t9.id
               INNER JOIN customer c1                 ON c1.id = o1.req_cust_id            
            where t1.prd_status  = 2 
               and t2.ag_status   > 0 
               and t1.prd_reqid = {$_REQUEST["reqid"]}
               and evt_type = 'prod'
               order by t2.ag_equipo_id ";
$items = $CON->select($sql);

$sql = " select * from orders o1
            INNER JOIN customer c1 ON c1.id = o1.req_cust_id            
         where o1.id = {$_REQUEST["reqid"]} ";
$cabecera = $CON->select($sql);
//----------------------------------------------------------------------------------
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <script type="text/javascript" src="/libs/jscripts/jquery.table_navigation.js"></script>
   <style type="text/css">tr.selected {background-color: <?=$_SESSION["_PAGE"]->getEffectVal("js_content_hover")?>;}</style>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="<?php if(count($items) == 0 || $items == false) echo "document.xform_itemsearch.sql_stext.focus()"?>">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="searchitem.fancy.php" method="post" name="xform_itemsearch" class="fokusfirst" 
           onsubmit="return checkform(new Array(this.sql_stext))">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="reqid" value="<?=$_REQUEST["reqid"]?>">
      <?=Nifty_printH("box1", "980", 0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="100">
         <col width="385">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Detalle de Confirmacion de Compras</td>
      </tr>
      <tr>
         <td class="content_rowl">Numero CC</td>
         <td class="content_row"><?=$cabecera[0]["req_number"]?></td>
         <td class="content_rowl">Fecha</td>
         <td class="content_row"><?=date('d.m.Y', $items[0]["fecha_creacion"])?></td>
      </tr>
      <tr>
         <td class="content_rowl">Rut</td>
         <td class="content_row"><?=$cabecera[0]["cust_rut"]?></td>
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?=$cabecera[0]["cust_name"]?></td>
      </tr>
      <tr>
         <td class="content_rowl">Cantidad Solicitada</td>
         <td class="content_row"><?=printPrice($items[0]["cantidad"],0)?></td>
         <td class="content_rowl"></td>
         <td class="content_row"></td>
      </tr>
      </table>
      <?=Nifty_printF()?>
      </form>
   </td>
</tr>
</table>
<?=Nifty_printH("box1", "980",0)?>
<table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="80">
      <col width="90">
      <col width="90">
      <col width="85">
   </colgroup>
   <tbody>
   <?php
      $equipo = "";
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         if($equipo != $items[$x]["equipo"])
         {
            $equipo = $items[$x]["equipo"];
            ?>
            <tr>
               <td bgcolor="<?=getRowColor(1)?>" class="content_tbl_header" colspan="4" align=""><?echo $equipo.$sw ?></td>
            </tr>
            <tr>
                  <td class="content_tbl_subheader">Inicio</td>
                  <td class="content_tbl_subheader">Termino</td>
                  <td class="content_tbl_subheader">Estado</td>
                  <td class="content_tbl_subheader">Unidad Ingresada</td>
            </tr>
            <?php
         }   
         ?>
           <tr>
              <td class="content_row"><nobr><?=date('d.m.Y',$items[$x]["inicio"])?></nobr></td>
              <?if((int)$items[$x]["termino"])
              {
              ?>
                 <td class="content_row"><?=date('d.m.Y',$items[$x]["termino"])?></td>
              <?php
              }
              else
              {
              ?>
                 <td class="content_row"> </td>
              <?php
              }
              ?>
              <td class="content_row"><?=$items[$x]["estado"]?></td>
              <td class="content_row"><?=printPrice($items[$x]["produccion"],0)?></td>
            </tr>
         <?php
        }
        if(!$x)
        {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="6" align="center" valign="middle" height="30">
                  <br>
                  <b class="msg_save_err">No hay datos disponibles.</b>
                  <br><br>
               </td>
            </tr>
            <?php
        }
      ?>
   </tbody>
</table>
<?=Nifty_printF()?>
<script type="text/javascript">
jQuery.tableNavigation({
   table_selector: 'table.navigateable',
   row_selector: 'table.navigateable tbody tr.viewable_records',
   selected_class: 'selected',
   activation_selector: 'a.activation',
   bind_key_events_to_links: true,
   focus_links_on_select: true,
   select_event: 'click',
   activate_event: 'dblclick',
   activation_element_activate_event: 'click',
   scroll_overlap: 20,
   cookie_name: null,
   focus_tables: true,
   focused_table_class: 'focused',
   jump_between_tables: false,
   disabled: false,
   on_activate: null,
   on_select: null
});
</script>
</body>
</html>