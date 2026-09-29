<?php
//----------------------------------------------------------------------------------

if($_REQUEST["showfullcust"] == "1")
   $_SESSION["xsuppliercont"]["fullcust"] = "1";
if($_REQUEST["showfullcust"] == "0")
   $_SESSION["xsuppliercont"]["fullcust"] = "";
   
//----------------------------------------------------------------------------------
if((int)$_REQUEST["deleteAmts"])
{

   $sql = "select count(*) as contador
   from pro_dis 
      LEFT OUTER join pro_dis_items on id = pro_dis_items_pro_id 
      INNER JOIN pro_dis_detalle on pro_dis_detalle_items_id = pro_dis_items_id
   where id = {$_REQUEST["id"]}  and pro_dis_items_id = {$_REQUEST["deleteAmts"]}";
   $detalle = $CON->select($sql);
   $detalle = $detalle[0];

   if( $detalle["contador"] > 0)
   {
      echo '<script language="javascript">alert("No puede eliminar, tienes Versiones Ingresadas/Cargadas");</script>';
   }
   else
   {
      $sql = "delete from pro_dis_items 
              where pro_dis_items_pro_id = {$_REQUEST["id"]}
                and pro_dis_items_id = {$_REQUEST["deleteAmts"]}
              ";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }
}
else
if($_REQUEST["subexec"] == "create"){
  $currtme = time();
  $_REQUEST["cust_id_0"]     = (int)$_REQUEST["cust_id_0"];
  $_REQUEST["company_id"]    = (int)$_REQUEST["company_id"];
  $_REQUEST["shop_id"]       = (int)$_REQUEST["shop_id"];

  $sql = "insert into pro_dis(pro_dis_fecha_creacion
                             ,pro_dis_user_cr
                             ,pro_dis_fecha
                             ,pro_dis_company_id
                             ,pro_dis_shop_id
                             ,pro_dis_custid
                             ,pro_dis_user_md
                             ,pro_dis_fecha_actualizacion
                             ,pro_dis_status
                             ,pro_dis_descripcion)
             select {$currtme}
                   ,{$_SESSION["user_id"]}
                   ,now()
                   ,{$_REQUEST["company_id"]}
                   ,{$_REQUEST["shop_id"]}
                   ,{$_REQUEST["cust_id_0"]}
                   ,{$_SESSION["user_id"]}
                   ,{$currtme} 
                   ,1 
                   ,'{$_REQUEST["pro_dis_descripcion"]}'";

  $res = $CON->no_result($sql);

  if($res)
  {
     $sql = " select MAX(id) as 'thisid' from pro_dis";
     $sorder = $CON->select($sql);
     $_REQUEST["id"]       = $sorder[0]["thisid"];
     
     $sql = " select COUNT(*) as 'thiscount' from pro_dis where year(pro_dis_fecha) = year(now()) ";
     $sorder = $CON->select($sql);
     $_REQUEST["contador"] = $sorder[0]["thiscount"];
  }

  $sql = "update pro_dis set pro_dis_codigo = concat('PD-', right(cast(YEAR(now()) as char),2) , '-' , right(concat('0000',cast({$_REQUEST["contador"]} as char)),4))
          where id = {$_REQUEST["id"]}";

  $res = $CON->no_result($sql);
  $savemsg = getSaveMessage($res);
}
else
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   $_REQUEST["pro_dis_status"]  = (int)$_REQUEST["pro_dis_status"];
   $_REQUEST["pro_dis_asignado"] = (int)$_REQUEST["pro_dis_asignado"];
   //----------------------------------------------------------------------------------
   
   $sql = " update pro_dis
            set
               pro_dis_observa             = '{$_REQUEST["sord_desc"]}',
               pro_dis_fecha_actualizacion = {$currtme},
               pro_dis_user_md             = {$_SESSION["user_id"]},
               pro_dis_status              = {$_REQUEST["pro_dis_status"]},
               pro_dis_descripcion         = '{$_REQUEST["pro_dis_descripcion"]}',
               pro_dis_asignado            = {$_REQUEST["pro_dis_asignado"]}
            where id = {$_REQUEST["id"]}" ;

   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);
}
//----------------------------------------------------------------------------------
// busca datos de propuesta cabecera
$sql = " select t1.*
                , t3.company_short
                , t4.shop_name
                , t5.user_firstname 'upd_firstname'
                , t5.user_lastname  'upd_lastname'
                , t6.user_firstname 'crt_firstname'
                , t6.user_lastname  'crt_lastname'
         from pro_dis t1
         LEFT OUTER JOIN company_data t3 ON t1.pro_dis_company_id = t3.id 
         LEFT OUTER JOIN company_shops t4 ON t1.pro_dis_shop_id = t4.id 
         LEFT OUTER JOIN user t5          ON t1.pro_dis_user_md  = t5.id
         LEFT OUTER JOIN user t6          ON t1.pro_dis_user_cr  = t6.id
         where
         t1.id = {$_REQUEST["id"]}";

$headdata = $CON->select($sql);
$headdata = $headdata[0];
//----------------------------------------------------------------------------------
// Busca datos del Cliente
$sql = " select t1.*
         from customer t1
         where t1.id = {$headdata["pro_dis_custid"]}";

$cliente = $CON->select($sql);
$cliente = $cliente[0];
//----------------------------------------------------------------------------------
// busca items_A
$sql = "select * from pro_dis_items
          where pro_dis_items_pro_id = {$_REQUEST["id"]}
          order by pro_dis_items_id";
$posdata = $CON->select($sql);

//-------------------------------------------------------------
$sql = "select u.id
            , concat(user_firstname,' ',user_lastname) as usuario_asignado
         from user u
            inner join  user_group ug on ug.user_id = u.id and ug.group_id = 20
            where u.user_status > 0";
$asignados = $CON->select($sql);            
//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
   function proformcheck(obj)
   {
      var frmchk = checkform(new Array(obj.pro_dis_descripcion));
      if(!frmchk)
      {
         return false;
      }
      else
      {
         return true;
   }
      return true;
   }
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="form_shppos" id="xform_itemprices" onsubmit="return proformcheck(this)" enctype="multipart/form-data">
   <input type="hidden" name="exec" value="edit">
   <input type="hidden" name="subexec" value="save">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <input type="hidden" name="sord_status" value="">
   <input type="hidden" name="printpdf" id="printpdf" value="">
   <input type="hidden" name="deleteAmts" value="">

   <?=Nifty_printH("box1", "980", 0)?>
   <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%" id="ifx_tblheader">
      <colgroup>
         <col width="130">
         <col width="350">
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Datos basicos</td>
      </tr>
      <tr>
         <td class="content_rowl">Numero</td>
         <td class="content_row"><?=$headdata["pro_dis_codigo"]?></td>
         <td class="content_rowl">Referencia de Pedido *</td>
         <td class="content_row">
            <input name="pro_dis_descripcion" id="pro_dis_descripcion" type="text" class="text" style="width:100%" value="<?=$headdata["pro_dis_descripcion"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?=$headdata["company_short"]?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?=$headdata["shop_name"]?></td>
      </tr>
      <tr>
         <td class="content_rowl">Rut Cliente</td>
         <td class="content_row"><?=$cliente["cust_rut"]?></td>
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?=$cliente["cust_name"]?></td>
      </tr>
      <tr>
         <!--
         <td class="content_rowl">Estado</td>
         <td class="content_row">
            <select disabled class="text" style="width:350px;" name="pro_dis_status" id="pro_dis_status" onfocus="markfield(this,0)" onblur="markfield(this,1)" >
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <option value="1" <?if($headdata["pro_dis_status"] == "0") echo "selected"?>>Ingresada</option>
               <option value="1" <?if($headdata["pro_dis_status"] == "1") echo "selected"?>>Solicitada</option>
               <option value="1" <?if($headdata["pro_dis_status"] == "2") echo "selected"?>>En Proceso</option>
               <option value="1" <?if($headdata["pro_dis_status"] == "3") echo "selected"?>>Terminada</option>
               <option value="4" <?if($headdata["pro_dis_status"] == "4") echo "selected"?>>Anulada</option>
            </select>
         </td>
         <td class="content_rowl">Asignado</td>
         <td class="content_row">
            <select class="text" style="width:330px" name="sql_asignado" id="sql_asignado" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
                     foreach($asignados as $asignado)
                     {
                     ?>
                        <option value="<?=$asignado["id"]?>"
                           <?php if($asignado["id"] == $_SESSION[$_sesmodulename]["sql_asignado"]) echo "selected"?>><?=$asignado["usuario_asignado"]?>
                        </option> 
                     <?php 
                     }
               ?>
            </select>
         </td>
         -->
      </tr>
      <!--
      <tr>
         <td class="content_tbl_header" colspan=4>Detalles<td>
      </tr>
      <tr>
         <td class="content_row" colspan="4">
            <textarea class="text" style="width:100%; height:90px" name="sord_desc" <?=$rdlo?>
            onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["pro_dis_observa"])?></textarea>
         </td>
      </tr>
      -->
      <tr>
         <td class="content_rowl">Creado por</td>
         <td class="content_row"><?=$headdata["crt_firstname"]?> <?=$headdata["crt_lastname"]?>&nbsp;</td>
         <td class="content_rowl">Cambiado por</td>
         <td class="content_row"><?=$headdata["upd_firstname"]?> <?=$headdata["upd_lastname"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Creado</td>
         <td class="content_row"><?=date('d.m.Y H:i:s', $headdata["pro_dis_fecha_creacion"])?></td>
         <td class="content_rowl">Cambiado</td>
         <td class="content_row"><?=date('d.m.Y H:i:s',$headdata["pro_dis_fecha_actualizacion"])?></td>
      </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("boxopt_b", "980", 0)?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
      <tr>
         <td width="130">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&subcatexec=basic", "", "arrow-180");
            ?>
         </td>
         <td>&nbsp;</td>
         <td align="right" width="130" style="padding-right:5px">
         <?php
            $sql = "select count(*) as encontro from pro_dis pd
               inner join pro_dis_items on pro_dis_items_pro_id = pd.id
               inner join pro_dis_detalle on pro_dis_detalle_items_id = pro_dis_items_id
            where id = {$_REQUEST["id"]}";

            $tiene_items = $CON->select($sql);
            $tiene_items = $tiene_items[0];
            if((int)$tiene_items["encontro"]==0)
               printButton("Anular Propuesta", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "");
         ?>
         </td>
         <td align="right" width="130" style="padding-right:5px">
         <?php
            printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "disk-black");
         ?>
         </td>
      </tr>
   </table>
   <?=Nifty_printF(false)?>
</form>
<iframe height="0" width="0" frameborder="0" src="" id="xframedoc" name="xframedoc"></iframe>


