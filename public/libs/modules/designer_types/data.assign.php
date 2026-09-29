<?php
// Desarrollador: Fernando Garrido
// Fecha: 15/02/2024
// Descriupcion: Ingreso de Itemas de Propuestas de Diseños
//----------------------------------------------------------------------------------

$valor = $_POST['deleteAmts'];
if((int)$_REQUEST["clonexec"])
{  
   /*
   ?><script>alert("paso por aca ( <?php echo $_REQUEST["id"]." - ".$_REQUEST["idclonar"] ?> )");</script><?php
   vamor a empezar a clonar
   */
   /* Formando codigo */
   $sql = "select t1.*
              ,count(*) as contador
          from pro_dis_items t1
          where t1.pro_dis_items_pro_id = {$_REQUEST["id"]}
          order by t1.pro_dis_items_id desc ";
   $contador = $CON->select($sql);
   $sql = "select * from pro_dis where  id = {$_REQUEST["id"]} ";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];
   $pro_dis_items_codigo = $headdata["pro_dis_codigo"]."-".chr($contador[0]["contador"]+65);

   /* creando clon */ 
   $fecha = time();

   $sql = "insert into pro_dis_items(pro_dis_items_pro_id
            , pro_dis_items_codigo
            , pro_dis_items_fecha
            , pro_dis_items_descripcion
            , pro_dis_items_user_cr
            , pro_dis_items_user_md
            , pro_dis_items_fecha_cr
            , pro_dis_items_fecha_md
            , pro_dis_items_modelo_bolsa
            , pro_dis_items_materialidad
            , pro_dis_items_color_tela
            , pro_dis_items_tipo_impresion
            , pro_dis_items_color1
            , pro_dis_items_color2
            , pro_dis_items_color3
            , pro_dis_items_color4
            , pro_dis_items_color5
            , pro_dis_items_color6
            , pro_dis_items_color7
            , pro_dis_items_color8
            , pro_dis_items_color9
            , pro_dis_items_color10
            , pro_dis_items_frente1
            , pro_dis_items_frente2
            , pro_dis_items_frente3
            , pro_dis_items_frente4
            , pro_dis_items_frente5
            , pro_dis_items_frente6
            , pro_dis_items_frente7
            , pro_dis_items_frente8
            , pro_dis_items_frente9
            , pro_dis_items_frente10
            , pro_dis_items_dorso1
            , pro_dis_items_dorso2
            , pro_dis_items_dorso3
            , pro_dis_items_dorso4
            , pro_dis_items_dorso5
            , pro_dis_items_dorso6
            , pro_dis_items_dorso7
            , pro_dis_items_dorso8
            , pro_dis_items_dorso9
            , pro_dis_items_dorso10
            , pro_dis_items_status
            , pro_dis_items_color_manilla
            , pro_dis_items_observacion
            , pro_dis_asignado
            , pro_dis_items_codigo_barra
            , pro_dis_items_print_ancho
            , pro_dis_items_print_alto
            , pro_dis_items_pie_imprenta
            , pro_dis_items_medida_bolsa
            , pro_dis_items_cantidad_color)
         select pro_dis_items_pro_id
            , '{$pro_dis_items_codigo}'
            , {$fecha}
            , pro_dis_items_descripcion
            , {$_SESSION["user_id"]}
            , {$_SESSION["user_id"]}
            , {$fecha}
            , {$fecha}
            , pro_dis_items_modelo_bolsa
            , pro_dis_items_materialidad
            , pro_dis_items_color_tela
            , pro_dis_items_tipo_impresion
            , pro_dis_items_color1
            , pro_dis_items_color2
            , pro_dis_items_color3
            , pro_dis_items_color4
            , pro_dis_items_color5
            , pro_dis_items_color6
            , pro_dis_items_color7
            , pro_dis_items_color8
            , pro_dis_items_color9
            , pro_dis_items_color10
            , pro_dis_items_frente1
            , pro_dis_items_frente2
            , pro_dis_items_frente3
            , pro_dis_items_frente4
            , pro_dis_items_frente5
            , pro_dis_items_frente6
            , pro_dis_items_frente7
            , pro_dis_items_frente8
            , pro_dis_items_frente9
            , pro_dis_items_frente10
            , pro_dis_items_dorso1
            , pro_dis_items_dorso2
            , pro_dis_items_dorso3
            , pro_dis_items_dorso4
            , pro_dis_items_dorso5
            , pro_dis_items_dorso6
            , pro_dis_items_dorso7
            , pro_dis_items_dorso8
            , pro_dis_items_dorso9
            , pro_dis_items_dorso10
            , 0
            , pro_dis_items_color_manilla
            , pro_dis_items_observacion
            , 0
            , pro_dis_items_codigo_barra
            , pro_dis_items_print_ancho
            , pro_dis_items_print_alto
            , pro_dis_items_pie_imprenta
            , pro_dis_items_medida_bolsa
            , pro_dis_items_cantidad_color
         from pro_dis_items where pro_dis_items_id = {$_REQUEST["idclonar"]} ";
   $res = $CON->no_result($sql);
   if( $res)
      ?><script>alert("Se ha creado Propuesta <?php echo $pro_dis_items_codigo ?>");</script><?php
}

if((int)$valor)
{
   $sql = "delete from pro_dis_items where pro_dis_items_id = {$valor}";
   $CON->no_result($sql);
}

if($_REQUEST["subexec"] == "add")
{
   require_once("data.assign.edit.php");
}
else
{
   // Buscador de Detalles
   $sql = "select t1.* from pro_dis_detalle t1
            where pro_dis_detalle_items_id = {$_REQUEST["id_item"]}";
   $detalle = $CON->select($sql);

   // busca items_A
   $sql = "select * 
                ,concat(user.user_firstname,' ',user.user_lastname) as responsable
              from pro_dis_items
               left join user on user.id = pro_dis_items.pro_dis_asignado
            where pro_dis_items_pro_id = {$_REQUEST["id"]} and pro_dis_items_status < 99
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
   <input type="hidden" name="deleteAmts" value="">
   <input type="hidden" name="clonexec" value="">
   <input type="hidden" name="idclonar" value="">
      
   
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
               <td align="right" width="130">
                  <?php 
                      printButton("Agregar Detalle de Propuesta", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&id_item=0&subexec=add", "", "plus");
                  ?>
               </td>
            </tr>
         </table>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="120">
               <col width="70">
               <col width="250">
               <col width="250">
               <col width="100">
               <col width="50">
               <col>
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="10">Solicitudes Ingresadas</td>
            </tr>
            <tr>
               <td class="content_tbl_subheader content_row_os">Codigo</td>
               <td class="content_tbl_subheader content_row_os">Fecha</td>
               <td class="content_tbl_subheader content_row_os">Referencia Pedido</td>
               <td class="content_tbl_subheader content_row_os">Responsable</td>
               <td class="content_tbl_subheader content_row_os"># Versiones</td>
               <td class="content_tbl_subheader content_row_os">Estado</td>
               <td class="content_tbl_subheader content_row_os" align="center" colspan="3">Opciones</td>
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
                  $detalle = $detalle[0];

                  $statimg = "";
                  switch((int)$posdata[$x]["pro_dis_items_status"])
                  {
                     case 0: $statimg = "<b style='color:blue'>Ingresada</b>"; break;         
                     case 1: $statimg = "<b class='color:green'>Solicitadas</b>"; break;
                     case 2: $statimg = "<b style='color:orange'>En proceso</b>"; break;
                     case 3: $statimg = "<b class='msg_save_err'>Terminadas</b>"; break;
                     case 4: $statimg = "<b style='color:purple'>Anuladas</b>"; break;
                  }

                  ?>
                  <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                     <td class="content_row_os"><?=$posdata[$x]["pro_dis_items_codigo"]?></td>
                     <td class="content_row_os"><?=date('d.m.Y', $posdata[$x]["pro_dis_items_fecha"])?></td>
                     <td class="content_row_os"><?=$posdata[$x]["pro_dis_items_descripcion"]?></td>
                     <td class="content_row_os"><?=$posdata[$x]["responsable"]?></td>
                     <td class="content_row_os" align="center"><?=$detalle["contador"]?></td>
                     <td class="content_row_os" align="center"><nobr><?=$statimg?></nobr></td>
                     <td class="content_row_os">
                        <?php
                           printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=assign&id={$_REQUEST["id"]}&subexec=add&id_item={$posdata[$x]['pro_dis_items_id']}","", "pencil");
                        ?>
                     </td>
                     <?php
                     if($detalle["contador"] == 0)
                     {
                        ?>
                        <td class="content_row_os">
                        <?php
                           printButton("", "postnav", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.deleteAmts.value={$posdata[$x]["pro_dis_items_id"]};document.form_shppos.submit();}", "cross");            
                        ?>
                        </td>
                     <?php
                     }
                     else
                     {
                     ?>
                        <td class="content_row_os">
                           <?php
                              printButton("", "postnav", "javascript: deactivateFormChange()", "alert('No puede eliminar, tiene Versiones ingresadas');", "cross");            
                           ?>
                        </td>
                     <?php
                     }
                     ?>
                     <td class="content_row_os">
                        <?php
                           /* printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=assign&id={$_REQUEST["id"]}&subexec=add&id_item={$posdata[$x]['pro_dis_items_id']}","", "magnifier-left"); */
                           printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=assign2&id={$_REQUEST["id"]}&id_item={$posdata[$x]['pro_dis_items_id']}", "", "magnifier-left");
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