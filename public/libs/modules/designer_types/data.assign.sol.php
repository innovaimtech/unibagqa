<?php
// Desarrollador: Fernando Garrido
// Fecha: 15/02/2024
// Descriupcion: Ingreso de Itemas de Propuestas de DiseÃ±os
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "add")
{
   require_once("data.assign.edit.sol.php");
}
else
{
   if((int)$_REQUEST["saveAmts2"] > 0)
   {
      $sql = "select count(*) as contador
                           from pro_dis 
                              LEFT OUTER join pro_dis_items on id = pro_dis_items_pro_id 
                              INNER JOIN pro_dis_detalle on pro_dis_detalle_items_id = pro_dis_items_id
                           where id = {$_REQUEST["id"]} and pro_dis_items.pro_dis_items_status = 2";
      $detalle = $CON->select($sql);
      $detalle = $detalle[0]["contador"];
      if($detalle["contador"]>0)
      {
         echo "<script>alert('Tiene Solicitudes con diseños cargados sin Finalizar');</script>";
      }
      else
      {
         $sql = "update pro_dis_items SET pro_dis_items_status = 0
                                      WHERE pro_dis_items_pro_id = {$_REQUEST["id"]}
                                         AND pro_dis_items_status in(1,2)";
         $CON->no_result($sql);



         $fecha = time();
         $sql = "update pro_dis set pro_dis_status   = 3 
                                   ,pro_dis_fecha_actualizacion = {$fecha}
                                   ,pro_dis_asignado = {$_SESSION["user_id"]}
                     where id = {$_REQUEST["id"]}";
         $CON->no_result($sql);

         require_once("overview.solicitud.php");
      } 
   }

   if((int)$_REQUEST["saveAmts"] > 0)
   {
      $sql = "update pro_dis_items 
                  set pro_dis_items_status = 0, 
                        pro_dis_asignado = 0 
                  where pro_dis_items_id = {$_REQUEST['saveAmts']}";
      $CON->no_result($sql);
   }
   $sql = "select t1.* from pro_dis_detalle t1
            where pro_dis_detalle_items_id = {$_REQUEST["id_item"]}";
   $detalle = $CON->select($sql);

   // busca items_A
   $sql = "select * 
                ,concat(user.user_firstname,' ',user.user_lastname) as responsable
                ,pro_dis_status
              from pro_dis_items
               left join user on user.id = pro_dis_items.pro_dis_asignado
               inner join pro_dis on pro_dis.id = pro_dis_items_pro_id
            where pro_dis_items_pro_id = {$_REQUEST["id"]} and pro_dis_items_status in(1,2,3)
            order by pro_dis_items_id";
   $posdata = $CON->select($sql);

   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <form action="index.php" method="post" name="form_shppos" id="form_shppos" class="fokusfirst" onsubmit="return proformcheck(this)">
   <input type="hidden" name="subexec" value="search">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="id_item" value="<?=$_REQUEST["id_item"]?>">
   <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <input type="hidden" name="deldesignimg" value="">
   <input type="hidden" name="senddesignmail" value="">
   <input type="hidden" name="autoopensendmail" value="">
   <input type="hidden" name="autoopenpdf" value="">
   <input type="hidden" name="saveAmts" value="">
   <input type="hidden" name="saveAmts2" value="">

   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <tr>
      <td>
         <?=Nifty_printH("box2", "980", 0)?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="50">
               <col width="70">
               <col width="70">
               <col width="250">
               <col width="100">
               <col width="50">
               <col width="50">
            </colgroup>
            <tr>
               <?php
               /*
                  if($_REQUEST["mid"]!=10025)
                  {
                  ?>               
                  <td align="right" width="130">
                     <?printButton("Agregar Detalle de Propuesta", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&id_item={$posdata[$x]["pro_dis_items_id"]}&subexec=add", "", "plus");?>
                  </td>
                  <?php
                  }
               */
               ?>               
            </tr>
         </table>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="50">
               <col width="120">
               <col width="70">
               <col width="250">
               <col width="250">
               <col width="50">
               <col>
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="10">Solicitudes Ingresadas</td>
            </tr>
            <tr>
               <td class="content_tbl_subheader content_row_os">Id</td>
               <td class="content_tbl_subheader content_row_os">Codigo</td>
               <td class="content_tbl_subheader content_row_os">Fecha</td>
               <td class="content_tbl_subheader content_row_os">Descripcion</td>
               <td class="content_tbl_subheader content_row_os">Responsable</td>
               <td class="content_tbl_subheader content_row_os"># Versiones</td>
               <td class="content_tbl_subheader content_row_os" align="center" colspan="2">Opciones</td>
            </tr>
            <?php
               for($x = 0; $x < count($posdata) && $posdata != false; $x++)
               {
                  $sql = "select count(*) as contador
                           from pro_dis 
                              LEFT OUTER join pro_dis_items on id = pro_dis_items_pro_id 
                              INNER JOIN pro_dis_detalle on pro_dis_detalle_items_id = pro_dis_items_id
                           where id = {$_REQUEST["id"]}  and pro_dis_items_id = {$posdata[$x]["pro_dis_items_id"]}";
                  $detalle = $CON->select($sql);
                  $detalle = $detalle[0]["contador"];
                  ?>
                  <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                     <td class="content_row_os"><?=$posdata[$x]["pro_dis_items_id"]?></td>
                     <td class="content_row_os"><?=$posdata[$x]["pro_dis_items_codigo"]?></td>
                     <td class="content_row_os"><?=date('d.m.Y', $posdata[$x]["pro_dis_items_fecha"])?></td>
                     <td class="content_row_os"><?=$posdata[$x]["pro_dis_items_descripcion"]?></td>
                     <td class="content_row_os"><?=$posdata[$x]["responsable"]?></td>
                     <td class="content_row_os" align="center"><?=$detalle["contador"]?></td>
                     <td class="content_row_os">
                        <?php
                           printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=assign&id={$_REQUEST["id"]}&subexec=add&id_item={$posdata[$x]['pro_dis_items_id']}","", "pencil");
                        ?>
                     </td>
                     <td class="content_row_os">
                     <?php
                        if($detalle["contador"]==0)
                           printButton("", "postnav", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.saveAmts.value={$posdata[$x]["pro_dis_items_id"]};document.form_shppos.submit();}", "cross");            
                        else
                           printButton("", "postnav", "javascript: deactivateFormChange()", "alert('No puede eliminar propuesta, tiene diseños cargados');", "cross");            
                     ?>
                     </td>
                  </tr>
                  <?php
                  $hasdata = true;
               }
               if(!$x)
               {  ?>
                  <tr bgcolor="<?=getRowColor(0)?>">
                     <td class="content_row" colspan="10" align="center">
                        <br>
                        <br class="msg_save_err">No hay datos disponibles.</b>
                        <br><br>
                     </td>
                  </tr>
                  <?php
               }
               else
               {
                  ?>
                     <br>
                     <table style="width:100%;">
                     <tr>
                        <td align="right">
                           <?php
                           if($posdata[0]["pro_dis_status"] != 3)
                              printButton("Cerrar Propuesta","postnav","javascript: deactivateFormChange()","if(askDel('')){document.form_shppos.saveAmts2.value={$_REQUEST["id"]};document.form_shppos.submit();}","");
                           ?>
                        </td>
                     </tr>
                     </table>
                  <?php
               }
            ?>
         </table>
         <?=Nifty_printF(false)?>
         <br>
      </td>
   </tr>
   </table>
   </form>
   <br>
   <iframe height="0" width="0" frameborder="0" src="" id="xframedoc" name="xframedoc"></iframe>
   <?php
}