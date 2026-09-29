<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_venta_encres";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "";
$_sortlinks             = Array();

unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_xstate"]        = (int)$_REQUEST["sql_xstate"];
   $_SESSION[$_sesmodulename]["sql_custname"]      = trim(addslashes($_REQUEST["sql_custname"]));
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 3;

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y');
}
if($_SESSION[$_sesmodulename]["sql_date"] == "")
   $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');
if($_SESSION[$_sesmodulename]["sql_date_pfrom"] == "")
{
   $_SESSION[$_sesmodulename]["sql_date_pfrom"] = date('d.m.Y', time() - (86400 * 10));
   $_SESSION[$_sesmodulename]["sql_date_pto"]   = date('d.m.Y', time());
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_selmode"] == 1)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 2)
{
   $sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], 1, $_SESSION[$_sesmodulename]["sql_year2"]);
   $datedays      = date('t', $sql_dateto);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], $datedays, $_SESSION[$_sesmodulename]["sql_year2"]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 3)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pfrom"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pto"]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
$sql = " select t0.*, t1.id 'docid', t1.invc_date, t2.cust_company, t2.cust_name, t2.cust_rut, t2.cust_email,
                'Factura' AS 'xtype', t1.invc_total_brutto, 'invoices_sell' AS 'tblname'
         from offers_online_encuestas t0
         INNER JOIN invoices_sell t1   ON t0.enc_invc_id = t1.id
         INNER JOIN customer t2        ON t1.invc_cust_id = t2.id
         where
         t0.enc_status     = 2 and
         t0.enc_upddat     between {$sql_datefrom} and {$sql_dateto} ";
if($_SESSION[$_sesmodulename]["sql_custname"] != "")
   $sql .= " and (
               t2.cust_name      like '%{$_SESSION[$_sesmodulename]["sql_custname"]}%' or
               t2.cust_company   like '%{$_SESSION[$_sesmodulename]["sql_custname"]}%'
             ) ";
$sql .= " UNION ALL
         select t0.*, t1.id 'docid', t1.invc_date, t2.cust_company, t2.cust_name, t2.cust_rut, t2.cust_email,
                'Boleta' AS 'xtype', t1.invc_total_brutto, 'invoices_sell_bol' AS 'tblname'
         from offers_online_encuestas t0
         INNER JOIN invoices_sell_bol t1  ON t0.enc_invcbol_id = t1.id
         INNER JOIN customer t2           ON t1.invc_cust_id = t2.id
         where
         t0.enc_status     = 2 and
         t0.enc_upddat     between {$sql_datefrom} and {$sql_dateto} ";
if($_SESSION[$_sesmodulename]["sql_custname"] != "")
   $sql .= " and (
               t2.cust_name      like '%{$_SESSION[$_sesmodulename]["sql_custname"]}%' or
               t2.cust_company   like '%{$_SESSION[$_sesmodulename]["sql_custname"]}%'
             ) ";
$sql .= " order by 1 desc";
$data = $CON->select($sql);

//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Resultados de encuestas</b></td>
   <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults(count($data)); else echo $savemsg;?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="80">
         <col>
         <col width="80">
         <col width="380">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Periodo</td>
         <td class="content_row">
            <table border="0" class="content_table" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="70">
                  <input type="radio" name="sql_selmode" value="2"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 2) echo "checked"?>> Meses
               </td>
               <td class="content_row_clear" width="205" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
                  <nobr>
                  <select class="text" name="sql_month1" id="sql_month1"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     for($x = 1; $x <= 12; $x++)
                     {
                        $dsp_month = $x;
                        if($dsp_month < 10)
                           $dsp_month = "0{$dsp_month}";
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_month1"]) echo "selected" ?>><?=$dsp_month?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <select class="text" name="sql_year1" id="sql_year1"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     $startyear  = date('Y') -20;
                     $endyear    = date('Y');

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year1"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  &nbsp;-&nbsp;
                  <select class="text" name="sql_month2" id="sql_month2"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     for($x = 1; $x <= 12; $x++)
                     {
                        $dsp_month = $x;
                        if($dsp_month < 10)
                           $dsp_month = "0{$dsp_month}";
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_month2"]) echo "selected" ?>><?=$dsp_month?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <select class="text" name="sql_year2" id="sql_year2"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     $startyear  = date('Y') -20;
                     $endyear    = date('Y');

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year2"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  </nobr>
               </td>
               <td class="content_row_clear" width="50">
                  <input type="radio" name="sql_selmode" value="1"
                  onclick="document.getElementById('idx_selmode1').style.display='';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 1) echo "checked"?>> Dia
               </td>
               <td class="content_row_clear" width="110" id="idx_selmode1" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 1) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:80px" id="sql_date" name="sql_date" 
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date"]?>">
                  </nobr>
               </td>
               <td class="content_row_clear" width="75">
                  <input type="radio" name="sql_selmode" value="3"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 3) echo "checked"?>> Periodo
               </td>
               <td class="content_row_clear" width="180" id="idx_selmode3" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 3) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:75px" id="sql_date_pfrom" name="sql_date_pfrom"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
                  -
                  <input type="text" style="width:75px" id="sql_date_pto" name="sql_date_pto"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pto"]?>">
                  </nobr>
               </td>
            </tr>
            </table>
         </td>
         <td class="content_rowl">Nombre</td>
         <td class="content_row">
            <input type="text" style="width:100%" id="sql_custname" name="sql_custname" class="text"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?=$_SESSION[$_sesmodulename]["sql_custname"]?>">
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td align="left" width="1" style="padding-right:5px">
                  <?php
                  if(count($data) > 0 && $data != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($data) > 0 && $data != false)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <?php
               if((int)$_SESSION[$_sesmodulename]["search_active"])
               {  ?>
                  <td align="right" style="padding-right:5px" width="1">
                  <?php
                  printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                  ?>
                  </td>
                  <?php
               }
               ?>
               <td align="right" width="1">
                  <?php
                  printButton("Actualizar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
                  $_SESSION["_SUBMITBTN"] = 1;
                  ?>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box1", "99%")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header content_row_os">Venta</td>
         <td class="content_tbl_header content_row_os">RUT</td>
         <td class="content_tbl_header content_row_os">Nombre</td>
         <td class="content_tbl_header content_row_os">Email</td>
         <td class="content_tbl_header content_row_os">Monto</td>
         <td class="content_tbl_header content_row_os" style="border-left:3px double #333333">Fecha<br>Encuesta</td>
         <td class="content_tbl_header content_row_os" align="center">Organización</td>
         <td class="content_tbl_header content_row_os" align="center">Calidad<br>producto</td>
         <td class="content_tbl_header content_row_os" align="center">Rapidez<br>envío</td>
         <td class="content_tbl_header content_row_os" align="center">Cumpl.<br>diseño</td>
         <td class="content_tbl_header content_row_os" align="center">Experiencia<br>compra</td>
         <td class="content_tbl_header content_row_os" align="center">Atención</td>
         <td class="content_tbl_header content_row_os" align="center">Precio/<br>Calidad</td>
         <td class="content_tbl_header content_row_os" align="center">Recomen<br>dación</td>
         <td class="content_tbl_header content_row_os" align="center">Como se<br>enteró</td>
         <td class="content_tbl_header content_row_os" align="center">Comentarios</td>
         <td class="content_tbl_header content_row_os" align="center">Productos<br>comprados</td>
         <td class="content_tbl_header content_row_os" align="center">Gustaria<br>comprar</td>
         <td class="content_tbl_header content_row_os" align="center">Otros<br>productos</td>
      </tr>
      <?php
      for($x = 0; $x < count($data) && $data != false; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=date("d.m.Y", $data[$x]["invc_date"])?></td>
            <td class="content_row_os"><nobr><?=$data[$x]["cust_rut"]?>&nbsp;</nobr></td>
            <td class="content_row_os"><?=$data[$x]["cust_name"]?></td>
            <td class="content_row_os"><?=$data[$x]["cust_email"]?>&nbsp;</td>
            <td class="content_row_os"><?=printPrice($data[$x]["invc_total_brutto"])?></td>
            <td class="content_row_os" style="border-left:3px double #333333"><?=date("d.m.Y", $data[$x]["enc_upddat"])?></td>
            <td class="content_row_os" align="center"><?=$data[$x]["enc_resp_1"]?>&nbsp;</td>
            <td class="content_row_os" align="center"><span style="color:white;text-shadow:none;background-color:#259492;padding:2px;padding-left:6px;padding-right:6px;border-radius:3px"><?=$data[$x]["enc_resp_2"]?></span></td>
            <td class="content_row_os" align="center"><span style="color:white;text-shadow:none;background-color:#259492;padding:2px;padding-left:6px;padding-right:6px;border-radius:3px"><?=$data[$x]["enc_resp_3"]?></span></td>
            <td class="content_row_os" align="center"><?=$data[$x]["enc_resp_4"]?>&nbsp;</td>
            <td class="content_row_os" align="center"><span style="color:white;text-shadow:none;background-color:#259492;padding:2px;padding-left:6px;padding-right:6px;border-radius:3px"><?=$data[$x]["enc_resp_5"]?></span></td>
            <td class="content_row_os" align="center"><span style="color:white;text-shadow:none;background-color:#259492;padding:2px;padding-left:6px;padding-right:6px;border-radius:3px"><?=$data[$x]["enc_resp_6"]?></span></td>
            <td class="content_row_os" align="center"><span style="color:white;text-shadow:none;background-color:#259492;padding:2px;padding-left:6px;padding-right:6px;border-radius:3px"><?=$data[$x]["enc_resp_7"]?></span></td>
            <td class="content_row_os" align="center"><?=$data[$x]["enc_resp_8"]?>&nbsp;</td>
            <td class="content_row_os" align="center"><?=$data[$x]["enc_resp_9"]?>&nbsp;</td>
            <td class="content_row_os" align="center"><?=$data[$x]["enc_resp_10"]?>&nbsp;</td>
            <td class="content_row_os" align="center"><?=$data[$x]["enc_resp_11"]?>&nbsp;</td>
            <td class="content_row_os" align="center"><?=$data[$x]["enc_resp_12"]?>&nbsp;</td>
            <td class="content_row_os" align="center"><?=$data[$x]["enc_resp_13"]?>&nbsp;</td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_date"]         = date("d.m.Y", $data[$x]["invc_date"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_rut"]          = $data[$x]["cust_rut"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_name"]         = $data[$x]["cust_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_email"]        = $data[$x]["cust_email"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_brutto"] = printPrice($data[$x]["invc_total_brutto"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_upddat"]        = date("d.m.Y", $data[$x]["enc_upddat"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_1"]        = $data[$x]["enc_resp_1"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_2"]        = $data[$x]["enc_resp_2"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_3"]        = $data[$x]["enc_resp_3"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_4"]        = $data[$x]["enc_resp_4"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_5"]        = $data[$x]["enc_resp_5"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_6"]        = $data[$x]["enc_resp_6"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_7"]        = $data[$x]["enc_resp_7"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_8"]        = $data[$x]["enc_resp_8"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_9"]        = $data[$x]["enc_resp_9"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_10"]       = $data[$x]["enc_resp_10"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_11"]       = $data[$x]["enc_resp_11"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_12"]       = $data[$x]["enc_resp_12"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["enc_resp_13"]       = $data[$x]["enc_resp_13"];
      }
      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="19" align="center" valign="middle" height="30">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      </form>
      <br>
   </td>
</tr>
</table>
</form>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsEncuestas($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsEncuestas($CON);
  
if($pdffile != "")
{
   $doctitle = "Encuestas-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Encuestas-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>