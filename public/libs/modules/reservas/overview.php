<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2019 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "orders_reservas";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Pedido" => "2", "Cliente" => "7", "Fecha Pedido" => "5,6", "Monto" => "6", "Creado" => "3", "Estado" => "4");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if($_REQUEST["exec"] == "edit")
   require_once("overview.edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $sql_item = explode("#", $_REQUEST["item_id"]);
      
      $_SESSION[$_sesmodulename]["sql_scust"]     = trim(addslashes($_REQUEST["sql_scust"]));
      $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes($_REQUEST["sql_stext"]));
      $_SESSION[$_sesmodulename]["sql_sitem"]     = trim(addslashes($_REQUEST["sql_sitem"]));
      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
      $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
      $_SESSION[$_sesmodulename]["sql_mode"]      = (int)$_REQUEST["sql_mode"];
      $_SESSION[$_sesmodulename]["sql_xstate_pay"] = (int)$_REQUEST["sql_xstate_pay"];
      $_SESSION[$_sesmodulename]["sql_xstate_dlv"] = (int)$_REQUEST["sql_xstate_dlv"];
      $_SESSION[$_sesmodulename]["sql_xstate_resv"] = (int)$_REQUEST["sql_xstate_resv"];
      $_SESSION[$_sesmodulename]["sql_xstate_trans"] = (int)$_REQUEST["sql_xstate_trans"];
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   if($_SESSION[$_sesmodulename]["filter_status"] != 4)
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]))
         $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3);

   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "del")
   {
      $currtme = time();
      $sql = " update reservas_header
               set
               res_status = 0,
               res_updusr  = {$_SESSION["user_id"]},
               res_upddat  = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = "";

   $cntsql = " select count(distinct t1.id) 'cc'
               from reservas_header t1
               {$joisql}
               where
               t1.res_status IN ({$_SESSION[$_sesmodulename]["filter_status"]})";

   $datsql = " select distinct t1.id, t1.res_type, t1.res_id, t1.res_ref, t1.res_status, t1.res_crtdat, t1.res_custname,
                      t1.res_date, t1.res_amount, t1.res_street, t1.res_city, t1.res_paystate, t1.res_dlvstate,
                      t1.res_retiroshopid, t1.res_comments, t1.res_delivprice, t1.res_paydesc
               from reservas_header t1
               {$joisql}
               where
               t1.res_status IN ({$_SESSION[$_sesmodulename]["filter_status"]}) ";

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_xstate_pay"] == 1)
      $seasql .= " and t1.res_paystate = 1 ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_xstate_pay"] == 2)
      $seasql .= " and t1.res_paystate = 0 ";
   if((int)$_SESSION[$_sesmodulename]["sql_xstate_dlv"] == 1)
      $seasql .= " and t1.res_dlvstate = 1 ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_xstate_dlv"] == 2)
      $seasql .= " and t1.res_dlvstate = 0 ";

   if($_SESSION[$_sesmodulename]["sql_xstate_resv"] == 1)
      $seasql .= " and t1.res_paydesc like '%reservado%' ";
   elseif($_SESSION[$_sesmodulename]["sql_xstate_resv"] == 2)
      $seasql .= " and t1.res_paydesc NOT like '%reservado%' ";

   if($_SESSION[$_sesmodulename]["sql_xstate_trans"] == 1)
      $seasql .= " and t1.res_paydesc like '%transferencia%' and t1.res_paydesc like '%verificad%' ";
   elseif($_SESSION[$_sesmodulename]["sql_xstate_trans"] == 2)
      $seasql .= " and t1.res_paydesc NOT like '%transferencia%' ";
      
   if($_SESSION[$_sesmodulename]["sql_scust"] != "")
      $seasql .= " and t1.res_custname like '%{$_SESSION[$_sesmodulename]["sql_scust"]}%' ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and t1.res_id = '{$_SESSION[$_sesmodulename]["sql_stext"]}' ";
   if($_SESSION[$_sesmodulename]["sql_sitem"] != "")
      $seasql .= " and (
                     select count(*)
                     from reservas_pos t2
                     where
                     t2.pos_header_id = t1.id and
                     t2.pos_itemdesc  like '%{$_SESSION[$_sesmodulename]["sql_sitem"]}%'
                   ) > 0 ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
   {
      if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
         $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
      if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
         $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

      $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
      $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);

      $seasql .= " and t1.res_crtdat between {$sqldate_from} and {$sqldate_to} ";
   }

   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;
   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " order by t1.id desc ";
   $datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

   //----------------------------------------------------------------------------------
   $orders = $CON->select($datsql);
   $shops  = getShops($CON);

   //----------------------------------------------------------------------------------
   ?>
   <script language="JavaScript">
   function setNewState(xtype, xid, xstate)
   {
      if(xtype == 'pay')
      {
         $('#inppayload_' +xid).hide(0);
         $('#payload_' +xid).show(0);
      }
      else if(xtype == 'dlv')
      {
         $('#inpdlvload_' +xid).hide(0);
         $('#dlvload_' +xid).show(0);
      }
      else if(xtype == 'retiro')
      {
         $('#inpretiroload_' +xid).hide(0);
         $('#retiroload_' +xid).show(0);
      }
      
      var dataString = 'xtype=' +xtype +'&xid=' +xid +'&xstate=' +xstate;
      $.ajax({
         type:       "POST",
         cache:      false,
         url:        "/libs/modules/reservas/jq.setstate.php",
         data:       dataString,
         dataType:   "html",
         success: function(res)
         {
            $('#inppayload_' +xid).show(0);
            $('#inpdlvload_' +xid).show(0);
            $('#inpretiroload_' +xid).show(0);
            $('#payload_' +xid).hide(0);
            $('#dlvload_' +xid).hide(0);
            $('#retiroload_' +xid).hide(0);
         }
      });
   }
   </script>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <table border="0" cellpadding="0" cellspacing="0" width="99%">
   <tr>
      <td height="30"><b class="content_header">Resumen de despachos</b></td>
      <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($itemcount); else echo $savemsg;?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <table border="0" cellpadding="0" cellspacing="0" width="99%">
   <tr>
      <td>
         <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
         <input type="hidden" name="subexec" value="search">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
         <?=Nifty_printH("box2", "980")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="100">
            <col>
            <col width="100">
            <col width="420">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Número</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:100%"
               value="<?=$_SESSION[$_sesmodulename]["sql_stext"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Artículo</td>
            <td class="content_row">
               <input name="sql_sitem" type="text" class="text" style="width:100%"
               value="<?=$_SESSION[$_sesmodulename]["sql_sitem"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr>
            <td class="content_rowl">Cliente</td>
            <td class="content_row">
               <input name="sql_scust" type="text" class="text" style="width:100%"
               value="<?=$_SESSION[$_sesmodulename]["sql_scust"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Pago</td>
            <td class="content_row">
               <input type="radio" value="0" name="sql_xstate_pay" <?php if($_SESSION[$_sesmodulename]["sql_xstate_pay"] == 0) echo "checked"?>> Todos
               <input type="radio" value="1" name="sql_xstate_pay" <?php if($_SESSION[$_sesmodulename]["sql_xstate_pay"] == 1) echo "checked"?>> Con pago
               <input type="radio" value="2" name="sql_xstate_pay" <?php if($_SESSION[$_sesmodulename]["sql_xstate_pay"] == 2) echo "checked"?>> Sin pago
            </td>
            <td class="content_rowl">Despacho</td>
            <td class="content_row">
               <input type="radio" value="0" name="sql_xstate_dlv" <?php if($_SESSION[$_sesmodulename]["sql_xstate_dlv"] == 0) echo "checked"?>> Todos
               <input type="radio" value="1" name="sql_xstate_dlv" <?php if($_SESSION[$_sesmodulename]["sql_xstate_dlv"] == 1) echo "checked"?>> Con despacho
               <input type="radio" value="2" name="sql_xstate_dlv" <?php if($_SESSION[$_sesmodulename]["sql_xstate_dlv"] == 2) echo "checked"?>> Sin despacho
            </td>
         </tr>
         <tr>
            <td class="content_rowl">Reservado</td>
            <td class="content_row" colspan="3">
               <input type="radio" value="0" name="sql_xstate_resv" <?php if($_SESSION[$_sesmodulename]["sql_xstate_resv"] == 0) echo "checked"?>> Todos
               <input type="radio" value="1" name="sql_xstate_resv" <?php if($_SESSION[$_sesmodulename]["sql_xstate_resv"] == 1) echo "checked"?>> Pendiente por despachar
               <input type="radio" value="2" name="sql_xstate_resv" <?php if($_SESSION[$_sesmodulename]["sql_xstate_resv"] == 2) echo "checked"?>> No Pendiente por despachar 
            </td>
         </tr>
         <tr>
            <td class="content_rowl">Transferencia</td>
            <td class="content_row" colspan="2">
               <input type="radio" value="0" name="sql_xstate_trans" <?php if($_SESSION[$_sesmodulename]["sql_xstate_trans"] == 0) echo "checked"?>> Todos
               <input type="radio" value="1" name="sql_xstate_trans" <?php if($_SESSION[$_sesmodulename]["sql_xstate_trans"] == 1) echo "checked"?>> Transferencia verificada
               <input type="radio" value="2" name="sql_xstate_trans" <?php if($_SESSION[$_sesmodulename]["sql_xstate_trans"] == 2) echo "checked"?>> Sin Transferencia verificada
            </td>
            <td class="content_row" align="right" colspan="1">
               <table border="0" cellpadding="0" cellspacing="0" width="270">
               <tr>
                  <td align="right">
                     <?php
                     if((int)$_SESSION[$_sesmodulename]["search_active"])
                        printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                     ?>
                  </td>
                  <td align="right">
                     <?php
                     printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                     ?>
                  </td>
               </tr>
               </table>
            </td>
         </tr>
         </table>
         <?=Nifty_printF(false)?>
         </form>
      </td>
   </tr>
   <tr>
      <td>
         <?=Nifty_printH("box1", "100%")?>
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
         <div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="100">
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="80">
            <col width="80">
            <col width="120">
            <col width="25">
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_header content_row_os">Formato</td>
            <td class="content_tbl_header content_row_os">Pedido</td>
            <td class="content_tbl_header content_row_os">Cliente</td>
            <td class="content_tbl_header content_row_os">Dirección</td>
            <td class="content_tbl_header content_row_os">Ciudad</td>
            <td class="content_tbl_header content_row_os">Fecha Pedido</td>
            <td class="content_tbl_header content_row_os">Monto</td>
            <td class="content_tbl_header content_row_os">Creado</td>
            <td class="content_tbl_header content_row_os" align="center">Pago</td>
            <td class="content_tbl_header content_row_os" align="center">Despachado</td>
            <td class="content_tbl_header content_row_os" align="center">Destino/Retiro</td>
            <td class="content_tbl_header content_row_os" align="center">Com.</td>
            <td class="content_tbl_header content_row_os" align="center">Opciones</td>
         </tr>
         <?php

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($orders) && $orders != false; $x++)
         {
            $statimg = "";
            switch((int)$orders[$x]["res_status"])
            {
               case 1: $statimg = "red_active.gif"; break;
               case 2: $statimg = "green_active.gif"; break;
            }

            $sql = " select id
                     from invoices_sell_bol
                     where
                     invc_resv_id = {$orders[$x]["id"]} and
                     invc_status > 1";
            $hasBol = $CON->select($sql);
            $hasBol = (int)$hasBol[0]["id"];

            $bgcolor = getRowColor($x);
            if(!$hasBol)
            {
               $bgcolor = "#FFBFBF";
               if(strpos(strtoupper($orders[$x]["res_paydesc"]), "RESERVADO") !== false)
                  $bgcolor = "#FDFF79";
            }


            //----------------------------------------------------------------------------------
            $sql = " select *
                     from reservas_pos
                     where
                     pos_header_id = {$orders[$x]["id"]}
                     order by id asc";
            $itemposdata = $CON->select($sql);

            $xdesc  = "<table border=0 cellpadding=3 cellspacing=0 width=650 style=\\'border:3px solid #CCCCCC\\'>";
            $xdesc .= "<colgroup><col width=60><col><col width=120></colgroup>";
            $xdesc .= "<tr><td class=content_tbl_header align=center>Cantidad</td><td class=content_tbl_header>Producto</td><td class=content_tbl_header>Precio</td></tr>";
            foreach($itemposdata AS $itemposdatarow)
            {
               $xdesc .= "<tr>";
               $xdesc .= "<td class=content_row align=center>".printPrice($itemposdatarow["pos_itemamt"])."</td>";
               $xdesc .= "<td class=content_row>".str_replace("'", "", str_replace('"', "", $itemposdatarow["pos_itemdesc"]))."</td>";
               $xdesc .= "<td class=content_row>".printPrice($itemposdatarow["pos_itemprice"])."</td>";
               $xdesc .= "</tr>";
            }
            if($orders[$x]["res_delivprice"] > 0.00)
            {
               $xdesc .= "<tr>";
               $xdesc .= "<td class=content_row align=center>1</td>";
               $xdesc .= "<td class=content_row>Despacho</td>";
               $xdesc .= "<td class=content_row>".printPrice($orders[$x]["res_delivprice"])."</td>";
               $xdesc .= "</tr>";
            }
            $xdesc .= "</table>";

            $overlibout    = "return nd()";
            $overlibover   = $xdesc;
            $overlibover   = "return overlib('{$overlibover}', WIDTH, 450, LEFT, FGCOLOR, '#FFFFFF', BGCOLOR, '#333333', ABOVE)";
            $overlibscript = "onmouseover=\"{$overlibover}\" onmouseout=\"{$overlibout}\"";
            ?>
            <tr bgcolor="<?=$bgcolor?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td style="<?=$bgcolortxt?>" class="content_row_os"><?=$orders[$x]["res_type"]?></td>
               <td style="<?=$bgcolortxt?>" class="content_row_os"><?=$orders[$x]["res_id"]?></td>
               <td <?=$overlibscript?> style="<?=$bgcolortxt?>" class="content_row_os"><?=$orders[$x]["res_custname"]?>&nbsp;</td>
               <td style="<?=$bgcolortxt?>" class="content_row_os"><?=$orders[$x]["res_street"]?>&nbsp;
               <?php
               if($orders[$x]["res_comments"] != "")
                  echo "<br><b style='color:navy'>{$orders[$x]["res_comments"]}</b>";
               ?></td>
               <td style="<?=$bgcolortxt?>" class="content_row_os"><?=$orders[$x]["res_city"]?>&nbsp;</td>
               <td style="<?=$bgcolortxt?>" class="content_row_os"><?=$orders[$x]["res_date"]?></td>
               <td style="<?=$bgcolortxt?>" class="content_row_os"><?=printPrice($orders[$x]["res_amount"])?></td>
               <td style="<?=$bgcolortxt?>" class="content_row_os"><?=date('d.m.Y',$orders[$x]["res_crtdat"])?></td>
               <td class="content_row_os" align="center">
                  <select id="inppayload_<?=$orders[$x]["id"]?>" class="text" style="width:100%" onchange="setNewState('pay', '<?=$orders[$x]["id"]?>', this.value)">
                     <option value="0" <?if((int)$orders[$x]["res_paystate"] == 0) echo "selected"?>>No</option>
                     <option value="1" <?if((int)$orders[$x]["res_paystate"] == 1) echo "selected"?>>Si</option>
                  </select>
                  <img src="/images/content/loading.gif" width="16" id="payload_<?=$orders[$x]["id"]?>" style="display:none">
               </td>
               <td class="content_row_os" align="center">
                  <select id="inpdlvload_<?=$orders[$x]["id"]?>" class="text" style="width:100%" onchange="setNewState('dlv', '<?=$orders[$x]["id"]?>', this.value)">
                     <option value="0" <?if((int)$orders[$x]["res_dlvstate"] == 0) echo "selected"?>>No</option>
                     <option value="1" <?if((int)$orders[$x]["res_dlvstate"] == 1) echo "selected"?>>Si</option>
                  </select>
                  <img src="/images/content/loading.gif" width="16" id="dlvload_<?=$orders[$x]["id"]?>" style="display:none">
               </td>
               <td class="content_row_os" align="center">
                  <select id="inpretiroload_<?=$orders[$x]["id"]?>" class="text" style="width:100%" onchange="setNewState('retiro', '<?=$orders[$x]["id"]?>', this.value)">
                     <option value="-1">Seleccione</option>
                     <option value="0" <?if((int)$orders[$x]["res_retiroshopid"] == 0) echo "selected"?>>DESPACHO</option>
                     <?php
                     foreach($shops AS $shop)
                     {  ?>
                        <option value="<?=$shop["id"]?>"  <?if((int)$orders[$x]["res_retiroshopid"] == $shop["id"]) echo "selected"?>>
                           <?=$shop["shop_name"]?>
                        </option>
                        <?php
                     }
                     ?>
                  </select>
                  <img src="/images/content/loading.gif" width="16" id="retiroload_<?=$orders[$x]["id"]?>" style="display:none">
               </td>
               <td class="content_row_os" align="center">
                  <img src="/images/menu/icons/balloon-ellipsis.png" style="cursor:pointer"
                  onclick="showFancybox('/libs/modules/reservas/fancycomments.php?id=<?=$orders[$x]["id"]?>', 'iframe', 650, 300, 'auto')">
               </td>
               <td class="content_row_os" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$orders[$x]["id"]}", "", "pencil");
                  ?>
               </td>
            </tr>
            <?php
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row_os" colspan="12" align="center">
                  <br>
                  <b class="msg_save_err">No hay datos disponibles.</b>
                  <br><br>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <?=Nifty_printF()?>
         <br>
      </td>
   </tr>
   </table>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
   $_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
}