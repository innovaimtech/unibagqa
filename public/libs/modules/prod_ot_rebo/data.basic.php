<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   // echo "<pre>";
   // print_r($_REQUEST);

   $currtme = time();

   $_REQUEST["rebo_name"]                 = trim(addslashes($_REQUEST["rebo_name"]));
   $_REQUEST["rebo_desc"]                 = trim(addslashes($_REQUEST["rebo_desc"]));
   $_REQUEST["req_rebo_type"]             = trim(addslashes($_REQUEST["req_rebo_type"]));
   $_REQUEST["req_rebo_state"]            = trim(addslashes($_REQUEST["req_rebo_state"]));
   $_REQUEST["req_rebo_rolloscc_opttype"] = trim(addslashes($_REQUEST["req_rebo_rolloscc_opttype"]));
   $_REQUEST["req_rebo_rolloscc"]         = (float)getPrice(trim($_REQUEST["req_rebo_rolloscc"]));
   $_REQUEST["req_rebo_cortescc"]         = (float)getPrice(trim($_REQUEST["req_rebo_cortescc"]));
   $_REQUEST["req_rebo_rolloscc_opt"]     = trim(addslashes($_REQUEST["req_rebo_rolloscc_opt"]));

   $_REQUEST["rebov2_input_rollos_fabtype"]        = trim(addslashes($_REQUEST["rebov2_input_rollos_fabtype"]));
   $_REQUEST["rebov2_input_rollos_matfabriccolor"] = (int)$_REQUEST["rebov2_input_rollos_matfabriccolor"];
   $_REQUEST["rebov2_input_rollos_matgramms"]      = (int)$_REQUEST["rebov2_input_rollos_matgramms"];
   $_REQUEST["rebov2_input_rollos_telawidth"]      = trim(addslashes($_REQUEST["rebov2_input_rollos_telawidth"]));

   $_REQUEST["rebov2_input_metros_fabtype"]        = trim(addslashes($_REQUEST["rebov2_input_metros_fabtype"]));
   $_REQUEST["rebov2_input_metros_matfabriccolor"] = (int)$_REQUEST["rebov2_input_metros_matfabriccolor"];
   $_REQUEST["rebov2_input_metros_matgramms"]      = (int)$_REQUEST["rebov2_input_metros_matgramms"];
   $_REQUEST["rebov2_input_metros_telawidth"]      = trim(addslashes($_REQUEST["rebov2_input_metros_telawidth"]));


   $sql = " update prod_ot_rebo_header
            set
            rebo_name                           = '{$_REQUEST["rebo_name"]}',
            rebo_desc                           = '{$_REQUEST["rebo_desc"]}',
            req_rebo_type                       = '{$_REQUEST["req_rebo_type"]}',
            req_rebo_state                      = '{$_REQUEST["req_rebo_state"]}',
            req_rebo_rolloscc_opttype           = '{$_REQUEST["req_rebo_rolloscc_opttype"]}',
            req_rebo_rolloscc                   = {$_REQUEST["req_rebo_rolloscc"]},
            req_rebo_cortescc                   = {$_REQUEST["req_rebo_cortescc"]},
            req_rebo_rolloscc_opt               = '{$_REQUEST["req_rebo_rolloscc_opt"]}',
            rebov2_input_rollos_fabtype         = '{$_REQUEST["rebov2_input_rollos_fabtype"]}',
            rebov2_input_rollos_matfabriccolor  = {$_REQUEST["rebov2_input_rollos_matfabriccolor"]},
            rebov2_input_rollos_matgramms       = {$_REQUEST["rebov2_input_rollos_matgramms"]},
            rebov2_input_rollos_telawidth       = '{$_REQUEST["rebov2_input_rollos_telawidth"]}',
            rebov2_input_metros_fabtype         = '{$_REQUEST["rebov2_input_metros_fabtype"]}',
            rebov2_input_metros_matfabriccolor  = {$_REQUEST["rebov2_input_metros_matfabriccolor"]},
            rebov2_input_metros_matgramms       = {$_REQUEST["rebov2_input_metros_matgramms"]},
            rebov2_input_metros_telawidth       = '{$_REQUEST["rebov2_input_metros_telawidth"]}',
            rebo_upddat                         = {$currtme},
            rebo_updusr                         = {$_SESSION["user_id"]}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);

   //----------------------------------------------------------------------------------
   $sql = " delete from prod_ot_rebo_compat_equipos
            where
            rebo_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   foreach($_REQUEST["equiposact"] AS $equiposactid)
   {
      $sql = " insert into prod_ot_rebo_compat_equipos
               (rebo_id, equ_id)
               VALUES
               ({$_REQUEST["id"]}, {$equiposactid})";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from prod_ot_rebo_header_values
            where
            rebo_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "rebov2_input_rolloamt_") !== false && strpos($reqkey, "rebov2_input_rolloamt_") == 0)
      {
         $idx        = substr($reqkey, strrpos($reqkey, "_") +1);
         $rollo_amt  = (float)getPrice($_REQUEST["rebov2_input_rolloamt_{$idx}"]);
         $rollo_dims = (float)getPrice($_REQUEST["rebov2_input_rollodims_{$idx}"],2);
         if($rollo_amt > 0 && $rollo_dims > 0)
         {
            $sql = " insert into prod_ot_rebo_header_values
                     (rebo_id, req_rebo_type, rollo_amt, rollo_dims)
                     VALUES
                     ({$_REQUEST["id"]}, 'rollo', {$rollo_amt}, {$rollo_dims})";
            $CON->no_result($sql);
         }
      }
      if(strpos($reqkey, "rebov2_input_rollos_amt_") !== false && strpos($reqkey, "rebov2_input_rollos_amt_") == 0)
      {
         $idx        = substr($reqkey, strrpos($reqkey, "_") +1);
         $rollo_amt  = (float)getPrice($_REQUEST["rebov2_input_rollos_amt_{$idx}"]);
         if($rollo_amt > 0)
         {
            $sql = " insert into prod_ot_rebo_header_values
                     (rebo_id, req_rebo_type, rollo_amt)
                     VALUES
                     ({$_REQUEST["id"]}, 'rollo_amt', {$rollo_amt})";
            $CON->no_result($sql);
         }
      }
      if(strpos($reqkey, "rebov2_input_metroamt_") !== false && strpos($reqkey, "rebov2_input_metroamt_") == 0)
      {
         $idx        = substr($reqkey, strrpos($reqkey, "_") +1);
         $rollo_amt  = (float)getPrice($_REQUEST["rebov2_input_metroamt_{$idx}"]);
         $rollo_dims = (float)getPrice($_REQUEST["rebov2_input_metrodims_{$idx}"],2);
         if($rollo_amt > 0 && $rollo_dims > 0)
         {
            $sql = " insert into prod_ot_rebo_header_values
                     (rebo_id, req_rebo_type, rollo_amt, rollo_dims)
                     VALUES
                     ({$_REQUEST["id"]}, 'metro', {$rollo_amt}, {$rollo_dims})";
            $CON->no_result($sql);
         }
      }
      if(strpos($reqkey, "rebov2_input_metros_amt_") !== false && strpos($reqkey, "rebov2_input_metros_amt_") == 0)
      {
         $idx        = substr($reqkey, strrpos($reqkey, "_") +1);
         $rollo_amt  = (float)getPrice($_REQUEST["rebov2_input_metros_amt_{$idx}"]);
         if($rollo_amt > 0)
         {
            $sql = " insert into prod_ot_rebo_header_values
                     (rebo_id, req_rebo_type, rollo_amt)
                     VALUES
                     ({$_REQUEST["id"]}, 'metro_amt', {$rollo_amt})";
            $CON->no_result($sql);
         }
      }
   }

   if((int)$_REQUEST["activate"])
   {
      // echo "<pre>";
      // print_r($_REQUEST);

      //----------------------------------------------------------------------------------
      $sql = " select t1.*
               from prod_ot_rebo_header t1
               where
               t1.id = {$_REQUEST["id"]}";
      $headdata = $CON->select($sql);
      $headdata = $headdata[0];

      $req_num = str_replace("NV", "INT", createTransactionNumber($CON, $headdata["rebo_company_id"], "invoice"));
      $currtme = time();

      //DEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLY = 1
      $req_prod_checklist_term = 0;
      //DEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLY = 1


      $sql = " insert into orders
              (req_number, req_company_id, req_shop_id, req_cust_id, req_paymentid,
               req_userid_seller, req_userid_cashing, req_taxes, req_transportid,
               req_crtdat, req_crtusr, req_isinvcbrutto, req_isreserva, req_crtdat_first,
               req_offerid, req_despacho_desc, req_isfabricate, req_production_act,
               req_prod_checklist_term)
              VALUES
              ('{$req_num}', {$headdata["rebo_company_id"]}, {$headdata["rebo_shop_id"]}, {$headdata["cust_id_0"]},
                0, {$_SESSION["user_id"]}, {$_SESSION["user_id"]}, 1,
                0, {$currtme}, {$_SESSION["user_id"]}, 0, 0, {$currtme}, 0, '', 1, 1, {$req_prod_checklist_term})";
      $res = $CON->no_result($sql);
      // $res = 1;
      if($res)
      {
         $req_id = mysql_insert_id();

         $sql = " insert into orders_items
                  (
                     req_id, item_id, item_pos, item_amount, item_amount_shipped, item_type,
                     item_compdesc, fab_type, fab_printtype,
                     fab_med_width, fab_med_height, fab_med_fuelle, fab_print_width, fab_print_height,
                     fab_manilla_length, fab_mat_fabric_color, fab_mat_manilla_color, fab_mat_gramms,
                     fab_design_imagehash
                  )
                  VALUES
                  (
                     {$req_id}, {$headdata["fab_item_id"]}, 0, 1, 0, 'item', 'OT Rebobinadora',
                     '{$headdata["fab_type"]}', '{$headdata["fab_printtype"]}', 0,
                     0, 0, 0,
                     0, 0, {$headdata["fab_mat_fabric_color"]},
                     {$headdata["fab_mat_fabric_color"]},
                     {$headdata["fab_mat_gramms"]}, ''
                  )";
         $CON->no_result($sql);

         $sql = " update orders
                  set
                  req_status = 4,
                  req_rebo_cc_genrefid = {$_REQUEST["id"]},
                  req_order_shipped = 1,
                  req_updusr = {$_SESSION["user_id"]},
                  req_upddat = {$currtme}
                  where
                  id = {$req_id}";
         $CON->no_result($sql);

         $prd_number = createNumberSystem($CON, "PRODOT");

         $sql = " insert into prod_header
                  (prd_crtdat, prd_crtusr, prd_status, prd_desc, prd_plantaid, prd_reqid, prd_number)
                  VALUES
                  ({$currtme}, {$_SESSION["user_id"]}, 2, '', {$headdata["rebo_plantaid"]},
                   {$req_id}, '{$prd_number}')";
         $res = $CON->no_result($sql);

         if($res)
         {
            $prodid = mysql_insert_id();
            $prodplan_date = mktime(15, 0, 0, date("m"), date("d"), date("Y"));
            $sql = " insert into prod_amtplan
                     (prodplan_amt, prodplan_date, prodplan_prdid)
                     VALUES
                     (1, {$prodplan_date}, {$prodid})";
            $CON->no_result($sql);


            foreach($_REQUEST["equiposact"] AS $equiposactid)
            {
               $sql = " insert into prod_compat_equipos
                        (prd_id, equ_id)
                        VALUES
                        ({$prodid}, {$equiposactid})";
               $CON->no_result($sql);
            }


            //----------------------------------------------------------------------------------
            $sql = " select *
                     from prod_ot_rebo_header_values
                     where
                     rebo_id = {$_REQUEST["id"]}
                     order by id";
            $rebovals = $CON->select($sql);
            foreach($rebovals AS $reboval)
            {
               $sql = " insert into orders_classify_rebo_values
                        (req_id, req_rebo_type, rollo_amt, rollo_dims)
                        VALUES
                        ({$req_id}, '{$reboval["req_rebo_type"]}', {$reboval["rollo_amt"]}, {$reboval["rollo_dims"]})";
               $CON->no_result($sql);
            }

            //----------------------------------------------------------------------------------
            $req_operador_tela_width = 0;
            if($headdata["req_rebo_rolloscc_opttype"] == "Por metro")
            {
               $req_operador_tela_width = (int)$headdata["rebov2_input_metros_telawidth"];
            }
            else
            {
               $req_operador_tela_width = (int)$headdata["rebov2_input_rollos_telawidth"];
            }

            $sql = " update orders
                     set
                     req_rebo_type             = '{$headdata["req_rebo_type"]}',
                     req_rebo_state            = '{$headdata["req_rebo_state"]}',
                     req_rebo_rolloscc         = {$headdata["req_rebo_rolloscc"]},
                     req_rebo_cortescc         = {$headdata["req_rebo_cortescc"]},
                     req_rebo_rolloscc_opt     = '{$headdata["req_rebo_rolloscc_opt"]}',
                     req_rebo_rolloscc_opttype = '{$headdata["req_rebo_rolloscc_opttype"]}',
                     req_operador_tela_width   = {$req_operador_tela_width}
                     where
                     id = {$req_id}";
            $CON->no_result($sql);

            $sql = " update prod_ot_rebo_header
                     set
                     rebo_status          = 3,
                     rebo_order_refnum    = '{$req_num}',
                     rebo_order_refid     = {$req_id}
                     where
                     id = {$_REQUEST["id"]}";
            $CON->no_result($sql);
         }

      }
   }

}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.company_short, t4.shop_name,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t7.cust_name, t8.item_title, t9.add_name
         from prod_ot_rebo_header t1
         LEFT OUTER JOIN company_data t3        ON t1.rebo_company_id       = t3.id
         LEFT OUTER JOIN company_shops t4       ON t1.rebo_shop_id          = t4.id
         LEFT OUTER JOIN user t5                ON t1.rebo_updusr           = t5.id
         LEFT OUTER JOIN user t6                ON t1.rebo_crtusr           = t6.id
         LEFT OUTER JOIN customer t7            ON t1.cust_id_0             = t7.id
         LEFT OUTER JOIN item t8                ON t1.fab_item_id           = t8.id
         LEFT OUTER JOIN tran_comments_vals t9  ON t1.fab_mat_fabric_color  = t9.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$sql = " select *
         from prod_ot_rebo_compat_equipos
         where
         rebo_id = {$_REQUEST["id"]}";
$prdequs = $CON->select($sql);
foreach($prdequs AS $prdequ)
   $_EQUIPO_ACT[$prdequ["equ_id"]] = 1;

//----------------------------------------------------------------------------------
$sql = " select *
         from prod_ot_rebo_header_values
         where
         rebo_id = {$_REQUEST["id"]}
         order by id";
$rebovals = $CON->select($sql);
foreach($rebovals AS $reboval)
   $_REBOVALS[$reboval["req_rebo_type"]][$reboval["id"]] = $reboval;

//----------------------------------------------------------------------------------
$sql = " select t2.id, t2.add_name
         from tran_comments t1
         INNER JOIN tran_comments_vals t2 ON t2.add_com_id = t1.id
         where
         t1.id          = {$_CONFIG["TELA_COLOR_CHARACTID"]} and
         t2.add_status  = 1
         order by t2.add_name";
$colors = $CON->select($sql);

// ---------------------------------------------------------------------------------
$sql = " select distinct t1.item_reg_gsm
         from item t1
            INNER JOIN item_productcats t2         ON t1.id = t2.item_id
            INNER JOIN tran_comments_item_vals t3  ON t1.id = t3.item_id
            INNER JOIN tran_comments t4            ON t3.com_id = t4.id
            INNER JOIN tran_comments_vals t5       ON t3.val_id = t5.id
         where
         t1.item_status > 0 and
         t2.cat_id      = {$_CONFIG["TELA_CATID"]}
         order by t1.item_reg_gsm asc";
$optsgrams = $CON->select($sql);

$sql = " select distinct add_name
         from tran_comments_vals
         where
         add_com_id = 32 and
         add_status > 0
         order by add_name";
$dbopts = $CON->select($sql);
foreach($dbopts AS $dbopt)
{
   $optval = (int)$dbopt["add_name"];
   if($optval > 0)
      $_OPTS[$optval] = 1;
}
ksort($_OPTS);
$optswidth = array_keys($_OPTS);

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>

<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="idx_xform"
onsubmit="<?if($headdata["rebo_status"] > 1) echo "return false;";?>return checkform(new Array(this.rebo_name))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="activate" value="">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">

<table border="0" cellpadding="0" cellspacing="0" width="1080">
<tr>
   <td class="content_row_clear" valign="top">
      <?=Nifty_printH("box1", "1020",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
      <colgroup>
         <col width="130">
         <col width="360">
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Datos básicos</td>
      </tr>
      <tr>
         <td class="content_rowl">Folio</td>
         <td class="content_row">
            <span style="float:left;padding:3px;padding-left:0px;padding-right:0px;"><?=sprintf("%05s", $headdata["id"])?></span>
            <?php
            if($headdata["rebo_order_refnum"] != "")
            {  ?>
               <span style="font-weight:bold;float:right;background-color:#1AA9A5;color:#FFFFFF;text-shadow:none;padding:3px;padding-left:6px;padding-right:6px;"><?=$headdata["rebo_order_refnum"]?></span>
               <?php
            }
            ?>
         </td>
         <td class="content_rowl">Estado</td>
         <td class="content_row">
            <?php
            if((int)$headdata["rebo_status"] == 1) echo "<b class=msg_save_err>Pendiente</b>";
            if((int)$headdata["rebo_status"] == 2) echo "<b style='color:darkorange'>En curso</b>";
            if((int)$headdata["rebo_status"] == 3) echo "<b class=msg_save_ok>Terminado</b>";
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?=$headdata["company_short"]?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?=$headdata["shop_name"]?></td>
      </tr>
      <tr>
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?=$headdata["cust_name"]?></td>
         <td class="content_rowl">Material</td>
         <td class="content_row"><?=$headdata["fab_type"]?></td>
      </tr>
      <tr>
         <td class="content_rowl">Producto</td>
         <td class="content_row"><?=$headdata["item_title"]?></td>
         <td class="content_rowl">Tipo Impresión</td>
         <td class="content_row">
            <?php
            if($headdata["fab_printtype"] == "FLEX")
               echo "Flexografía";
            elseif($headdata["fab_printtype"] == "SERI")
               echo "Serigrafía";
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Gramaje tela</td>
         <td class="content_row"><?=(int)$headdata["fab_mat_gramms"]?> gr</td>
         <td class="content_rowl">Color Tela</td>
         <td class="content_row"><?=$headdata["add_name"]?></td>
      </tr>
      <tr>
         <td class="content_rowl">Nombre *</td>
         <td class="content_row" colspan="3">
            <input type="text" class="text" style="width:100%" name="rebo_name"
            value="<?=stripslashes($headdata["rebo_name"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" valign="top">Descripción</td>
         <td class="content_row" colspan="3">
            <textarea class="text" style="width:100%;height:60px;" name="rebo_desc"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["rebo_desc"])?></textarea>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Creado por</td>
         <td class="content_row"><?=$headdata["crt_firstname"]?> <?=$headdata["crt_lastname"]?>&nbsp;</td>
         <td class="content_rowl">Cambiado por</td>
         <td class="content_row"><?=$headdata["upd_firstname"]?> <?=$headdata["upd_lastname"]?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl">Creado</td>
         <td class="content_row"><?=displayDate($headdata["rebo_crtdat"])?></td>
         <td class="content_rowl">Cambiado</td>
         <td class="content_row"><?=displayDate($headdata["rebo_upddat"])?></td>
      </tr>
      </table>
      <?=Nifty_printF()?>
   </td>
</tr>
<tr>
   <td>
      <br>
      <?=Nifty_printH("box2", "1020",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="260">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Asignar máquinas de fabricacción</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os">Máquinas</td>
      </tr>
      <?php
      $sql = " select *
               from equipo_type
               where
               type_ant_status > 0 and
               type_ant_prod_dabl = 0 and
               type_ant_title like '%REBOB%'
               order by type_ant_title";
      $equipotypes = $CON->select($sql);

      $px = 0;
      foreach($equipotypes AS $equipotype)
      {
         $sql = " select *
                  from equipo
                  where
                  equipo_status     > 0 and
                  equipo_type_id    = {$equipotype["id"]} and
                  equipo_planta_id  = {$headdata["rebo_plantaid"]} and
                  equipo_prod_dabl  = 0
                  order by equipo_name";
         $equipos = $CON->select($sql);
         if(count($equipos) && $equipos != false)
         {  ?>
            <tr bgcolor="<?=getRowColor($px)?>">
               <td class="content_row_os">
                  <?php
                  foreach($equipos AS $equipo)
                  {
                     $eventstr = "";
                     $eventcls = "";
                     if(strpos(strtoupper($equipotype["type_ant_title"]), "REBOBIN") !== false)
                     {
                        $eventcls = "clsrebob";
                        $eventstr = "showhideReboOpts()";
                        if((int)$_EQUIPO_ACT[$equipo["id"]])
                           $rebobinadorahide = "";
                     }
                     ?>
                     <nobr>
                        <input type="checkbox" name="equiposact[]" value="<?=$equipo["id"]?>" class="<?=$eventcls?>"
                        onclick="$('#idx_finalbtnx').hide(0);<?=$eventstr?>"
                        <?if((int)$_EQUIPO_ACT[$equipo["id"]]) echo "checked"?>> <?=$equipo["equipo_name"]?>
                     </nobr>
                     <?php
                  }
                  ?>
               </td>
            </tr>
            <?php
            $px++;
         }
      }
      ?>
      </table>
      <?=Nifty_printF(false)?>
   </td>
</tr>
<tr>
   <td>
      <br>
      <?=Nifty_printH("box2", "1020",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="190">
         <col width="350">
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Configuración rebobinadora</td>
      </tr>
      <tr>
         <td class="content_rowl">Tarea</td>
         <td class="content_row">
            <input type="radio" name="req_rebo_type" value="Corte"
            onchange="showreq_rebo_rolloscc_opt(this.value)"
            <?if($headdata["req_rebo_type"] == "Corte") echo "checked"?>> Corte
            <input type="radio" name="req_rebo_type" value="Rebobinado"
            onchange="showreq_rebo_rolloscc_opt(this.value)"
            <?if($headdata["req_rebo_type"] == "Rebobinado") echo "checked"?>> Rebobinado
            <input type="radio" name="req_rebo_type" value="Empalme"
            onchange="showreq_rebo_rolloscc_opt(this.value)"
            <?if($headdata["req_rebo_type"] == "Empalme") echo "checked"?>> Empalme
         </td>
         <td class="content_rowl">Estado</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="req_rebo_state">
               <option value="">Seleccione</option>
               <option value="Nuevo" <?if($headdata["req_rebo_state"] == "Nuevo") echo "selected"?>>Nuevo</option>
               <option value="Usado" <?if($headdata["req_rebo_state"] == "Usado") echo "selected"?>>Usado</option>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo</td>
         <td class="content_row">
            <span style="display:none" id="req_rebo_rolloscc_opttype_rollo">
               <input type="radio" name="req_rebo_rolloscc_opttype" id="id_req_rebo_rolloscc_opttype_rollo" value="Por rollo"
               onchange="set_req_rebo_rolloscc_opttype(this.value)"
               <?if($headdata["req_rebo_rolloscc_opttype"] == "Por rollo") echo "checked"?>> Por rollo
            </span>
            <span style="display:none" id="req_rebo_rolloscc_opttype_metro">
               <input type="radio" name="req_rebo_rolloscc_opttype" id="id_req_rebo_rolloscc_opttype_metro" value="Por metro"
               onchange="set_req_rebo_rolloscc_opttype(this.value)"
               <?if($headdata["req_rebo_rolloscc_opttype"] == "Por metro") echo "checked"?>> Por metro
            </span>
         </td>
         <td class="content_rowl">Cantidad de cortes</td>
         <td class="content_row">
            <input type="text" class="text" name="req_rebo_cortescc" id="req_rebo_cortescc" style="width:100%;"
            value="<?if((float)$headdata["req_rebo_cortescc"]) echo printPrice($headdata["req_rebo_cortescc"])?>">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Rollos</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="req_rebo_rolloscc_opt">
               <option value="">Seleccione</option>
               <option value="100cm_manillas" <?if($headdata["req_rebo_rolloscc_opt"] == "100cm_manillas") echo "selected"?>>Rollos de 100cm para manillas</option>
               <option value="100cm_algunos_metros" <?if($headdata["req_rebo_rolloscc_opt"] == "100cm_algunos_metros") echo "selected"?>>Rollos de 100cm (algunos metros) a rollo de 76cm más manillas y restante debe mantener código</option>
               <option value="solo_rebobinar" <?if($headdata["req_rebo_rolloscc_opt"] == "solo_rebobinar") echo "selected"?>>Rollos solo rebobinar</option>
            </select>
            <input type="text" class="text" name="req_rebo_rolloscc" id="req_rebo_rolloscc" style="width:100%;display:none"
            value="<?if((float)$headdata["req_rebo_rolloscc"]) echo printPrice($headdata["req_rebo_rolloscc"])?>">
         </td>
         <td class="content_rowl"></td>
         <td class="content_row"></td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      <br>
      <div id="iddiv_req_rebo_rolloscc_opttype_rollo"
      style="<?if($headdata["req_rebo_type"] == "" || $headdata["req_rebo_rolloscc_opttype"] != "Por rollo") echo "display:none"?>">
      <?=Nifty_printH("box2", "1020",0)?>
      <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td width="400" valign="top">
            <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="170">
               <col>
               <col width="60">
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="4">Distribución solicitado</td>
            </tr>
            <tr>
               <td class="content_row_os content_tbl_subheader content_rowl">Cantidad</td>
               <td class="content_row_os content_tbl_subheader content_rowl">Medida</td>
               <td class="content_row_os content_tbl_subheader content_rowl">Subtotal</td>
            </tr>
            <?php
            $rebovals = array_values($_REBOVALS["rollo"]);
            $xtotal   = 0;
            for($x = 0; $x <= count($rebovals)+1; $x++)
            {
               $subtotal = $rebovals[$x]["rollo_amt"] * $rebovals[$x]["rollo_dims"];
               ?>
               <tr>
                  <td class="content_row_os">
                     <input type="text" class="text" name="rebov2_input_rolloamt_<?=$x?>" style="width:100%"
                     value="<?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_amt"])?>">
                  </td>
                  <td class="content_row_os">
                     <input type="text" class="text" name="rebov2_input_rollodims_<?=$x?>" style="width:100%"
                     value="<?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_dims"],2)?>">
                  </td>
                  <td class="content_row_os">
                     <?if((int)$rebovals[$x]["id"]) echo printPrice($subtotal,2)?>
                  </td>
               </tr>
               <?php
               $xtotal += $subtotal;
            }
            ?>

            </table>
         </td>
         <td valign="top">
            <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="170">
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="5">Rollos</td>
            </tr>
            <tr>
               <td class="content_row_os content_tbl_subheader content_rowl">Cantidad de rollos</td>
               <td class="content_row_os content_tbl_subheader content_rowl">Materialidad</td>
               <td class="content_row_os content_tbl_subheader content_rowl">Color</td>
               <td class="content_row_os content_tbl_subheader content_rowl">Gramaje</td>
               <td class="content_row_os content_tbl_subheader content_rowl">Ancho</td>
            </tr>
            <?php
            $rebovals = array_values($_REBOVALS["rollo_amt"]);
            $xtotal2   = 0;
            for($x = 0; $x <= count($rebovals)+1; $x++)
            {  ?>
               <tr>
                  <td class="content_row_os">
                     <input type="text" class="text" name="rebov2_input_rollos_amt_<?=$x?>" style="width:100%"
                     value="<?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_amt"])?>">
                  </td>
                  <td class="content_row_os">
                     <?php
                     if($x == 0)
                     {  ?>
                        <select name="rebov2_input_rollos_fabtype" class="text" style="width:100%;">
                           <?php
                           if($headdata["fab_type"] == "PLA")
                           {  ?>
                              <option value="PLA" selected>PLA</option>
                              <?php
                           }
                           if($headdata["fab_type"] == "TNT")
                           {  ?>
                              <option value="TNT" selected>TNT</option>
                              <?php
                           }
                           ?>
                        </select>
                        <?php
                     }
                     ?>
                  </td>
                  <td class="content_row_os">
                     <?php
                     if($x == 0)
                     {  ?>
                        <select class="text" style="width:100%;" name="rebov2_input_rollos_matfabriccolor">
                           <?php
                           foreach($colors AS $color)
                           {
                              if($color["id"] == $headdata["fab_mat_fabric_color"])
                              {  ?>
                                 <option value="<?=$color["id"]?>" selected>
                                    <?=$color["add_name"]?>
                                 </option>
                                 <?php
                              }
                           }
                           ?>
                        </select>
                        <?php
                     }
                     ?>
                  </td>
                  <td class="content_row_os">
                     <?php
                     if($x == 0)
                     {  ?>
                        <select class="text" style="width:100%;" name="rebov2_input_rollos_matgramms">
                           <?php
                           foreach($optsgrams AS $optsgram)
                           {
                              if((int)$optsgram["item_reg_gsm"] == $headdata["fab_mat_gramms"])
                              {  ?>
                                 <option value="<?=(int)$optsgram["item_reg_gsm"]?>" selected>
                                    <?=(int)$optsgram["item_reg_gsm"]?>
                                 </option>
                                 <?php
                              }
                           }
                           ?>
                        </select>
                        <?php
                     }
                     ?>
                  </td>
                  <td class="content_row_os">
                     <?php
                     if($x == 0)
                     {  ?>
                        <select class="text" style="width:100%;" name="rebov2_input_rollos_telawidth">
                           <option value="">Sel.</option>
                           <?php
                           foreach($optswidth AS $opt)
                           {  ?>
                              <option value="<?=$opt?>" <?if($headdata["rebov2_input_rollos_telawidth"] == $opt) echo "selected"?>><?=$opt?></option>
                              <?php
                           }
                           ?>
                        </select>
                        <?php
                     }
                     ?>
                  </td>
               </tr>
               <?php
               $xtotal2 += $rebovals[$x]["rollo_amt"];
            }
            ?>

            </table>
         </td>
      </tr>
      <tr>
         <td class="content_row_clear" colspan="2">
            <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="340">
               <col width="60">
               <col>
            </colgroup>
            <tr>
               <td class="content_row_os content_row_totals">Total</td>
               <td class="content_row_os content_row_totals"><?=printPrice($xtotal, 2, true)?></td>
               <td class="content_row_os content_row_totals"><?=printPrice($xtotal2, 0, true)?></td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      <br>
      </div>
      <div id="iddiv_req_rebo_rolloscc_opttype_metro"
      style="<?if($headdata["req_rebo_type"] == "" || $headdata["req_rebo_rolloscc_opttype"] != "Por metro") echo "display:none"?>">
      <?=Nifty_printH("box2", "1020",0)?>
      <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td width="400" valign="top">
            <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="170">
               <col>
               <col width="60">
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="4">Distribución solicitado</td>
            </tr>
            <tr>
               <td class="content_row_os content_tbl_subheader content_rowl">Cantidad</td>
               <td class="content_row_os content_tbl_subheader content_rowl">Medida</td>
               <td class="content_row_os content_tbl_subheader content_rowl">Subtotal</td>
            </tr>
            <?php
            $rebovals = array_values($_REBOVALS["metro"]);
            $xtotal   = 0;
            for($x = 0; $x <= count($rebovals)+1; $x++)
            {
               $subtotal = $rebovals[$x]["rollo_amt"] * $rebovals[$x]["rollo_dims"];
               ?>
               <tr>
                  <td class="content_row_os">
                     <input type="text" class="text" name="rebov2_input_metroamt_<?=$x?>" style="width:100%"
                     value="<?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_amt"])?>">
                  </td>
                  <td class="content_row_os">
                     <input type="text" class="text" name="rebov2_input_metrodims_<?=$x?>" style="width:100%"
                     value="<?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_dims"],2)?>">
                  </td>
                  <td class="content_row_os">
                     <?if((int)$rebovals[$x]["id"]) echo printPrice($subtotal,2)?>
                  </td>
               </tr>
               <?php
               $xtotal += $subtotal;
            }
            ?>

            </table>
         </td>
         <td valign="top">
            <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col>
               <col>
               <col>
               <col>
               <col width="170">
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="5">Largos</td>
            </tr>
            <tr>
               <td class="content_row_os content_tbl_subheader content_rowl">Materialidad</td>
               <td class="content_row_os content_tbl_subheader content_rowl">Color</td>
               <td class="content_row_os content_tbl_subheader content_rowl">Gramaje</td>
               <td class="content_row_os content_tbl_subheader content_rowl">Ancho</td>
               <td class="content_row_os content_tbl_subheader content_rowl">Largo</td>
            </tr>
            <?php
            $rebovals = array_values($_REBOVALS["metro_amt"]);
            $xtotal2   = 0;
            for($x = 0; $x <= count($rebovals)+1; $x++)
            {  ?>
               <tr>
                  <td class="content_row_os">
                     <?php
                     if($x == 0)
                     {  ?>
                        <select name="rebov2_input_metros_fabtype" class="text" style="width:100%;">
                           <?php
                           if($headdata["fab_type"] == "PLA")
                           {  ?>
                              <option value="PLA" selected>PLA</option>
                              <?php
                           }
                           if($headdata["fab_type"] == "TNT")
                           {  ?>
                              <option value="TNT" selected>TNT</option>
                              <?php
                           }
                           ?>
                        </select>
                        <?php
                     }
                     ?>
                  </td>
                  <td class="content_row_os">
                     <?php
                     if($x == 0)
                     {  ?>
                        <select class="text" style="width:100%;" name="rebov2_input_metros_matfabriccolor">
                           <?php
                           foreach($colors AS $color)
                           {
                              if($color["id"] == $headdata["fab_mat_fabric_color"])
                              {  ?>
                                 <option value="<?=$color["id"]?>" selected>
                                    <?=$color["add_name"]?>
                                 </option>
                                 <?php
                              }
                           }
                           ?>
                        </select>
                        <?php
                     }
                     ?>
                  </td>
                  <td class="content_row_os">
                     <?php
                     if($x == 0)
                     {  ?>
                        <select class="text" style="width:100%;" name="rebov2_input_metros_matgramms">
                           <?php
                           foreach($optsgrams AS $optsgram)
                           {
                              if((int)$optsgram["item_reg_gsm"] == $headdata["fab_mat_gramms"])
                              {  ?>
                                 <option value="<?=(int)$optsgram["item_reg_gsm"]?>" selected>
                                    <?=(int)$optsgram["item_reg_gsm"]?>
                                 </option>
                                 <?php
                              }
                           }
                           ?>
                        </select>
                        <?php
                     }
                     ?>
                  </td>
                  <td class="content_row_os">
                     <?php
                     if($x == 0)
                     {  ?>
                        <select class="text" style="width:100%;" name="rebov2_input_metros_telawidth">
                           <option value="">Sel.</option>
                           <?php
                           foreach($optswidth AS $opt)
                           {  ?>
                              <option value="<?=$opt?>" <?if($headdata["rebov2_input_metros_telawidth"] == $opt) echo "selected"?>><?=$opt?></option>
                              <?php
                           }
                           ?>
                        </select>
                        <?php
                     }
                     ?>
                  </td>
                  <td class="content_row_os">
                     <input type="text" class="text" name="rebov2_input_metros_amt_<?=$x?>" style="width:100%"
                     value="<?if((int)$rebovals[$x]["id"]) echo printPrice($rebovals[$x]["rollo_amt"])?>">
                  </td>
               </tr>
               <?php
               $xtotal2 += $rebovals[$x]["rollo_amt"];
            }
            ?>

            </table>
         </td>
      </tr>
      <tr>
         <td class="content_row_clear" colspan="2">
            <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="340">
               <col>
               <col width="170">
            </colgroup>
            <tr>
               <td class="content_row_os content_row_totals">Total</td>
               <td class="content_row_os content_row_totals"><?=printPrice($xtotal, 2, true)?></td>
               <td class="content_row_os content_row_totals"><?=printPrice($xtotal2, 0, true)?></td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      <br>
      </div>
   </td>
</tr>
</table>
<br>
<script language="JavaScript">
   function set_req_rebo_rolloscc_opttype(xval)
   {
      $('#iddiv_req_rebo_rolloscc_opttype_rollo').hide(0);
      $('#iddiv_req_rebo_rolloscc_opttype_metro').hide(0);
      if(xval == 'Por rollo')
      {
         $('#iddiv_req_rebo_rolloscc_opttype_rollo').show(0);
      }
      else if(xval == 'Por metro')
      {
         $('#iddiv_req_rebo_rolloscc_opttype_metro').show(0);
      }
   }
   function showreq_rebo_rolloscc_opt(xval)
   {
      $('#iddiv_req_rebo_rolloscc_opttype_rollo').hide(0);
      $('#iddiv_req_rebo_rolloscc_opttype_metro').hide(0);
      $('#id_req_rebo_rolloscc_opttype_rollo').attr('checked', false);
      $('#id_req_rebo_rolloscc_opttype_metro').attr('checked', false);

      $('#req_rebo_rolloscc_opttype_rollo').hide(0);
      $('#req_rebo_rolloscc_opttype_metro').hide(0);

      if(xval != '')
         $('#req_rebo_rolloscc_opttype_rollo').show(0);

      if(xval == "Corte")
         $('#req_rebo_rolloscc_opttype_metro').show(0);
      if(xval == "Rebobinado")
         $('#req_rebo_rolloscc_opttype_metro').show(0);

      if(xval == "Empalme")
      {
         $('#id_req_rebo_rolloscc_opttype_rollo').attr('checked', true);
         set_req_rebo_rolloscc_opttype('Por rollo');
      }
   };
</script>
<?php
if($headdata["req_rebo_type"] != "")
{  ?>
   <script language="JavaScript">
      $(document).ready(function()
      {
         $('#req_rebo_rolloscc_opttype_rollo').show(0);
         <?php
         if($headdata["req_rebo_type"] == "Corte")
         {  ?>
            $('#req_rebo_rolloscc_opttype_metro').show(0);
            <?php
         }
         if($headdata["req_rebo_type"] == "Rebobinado")
         {  ?>
            $('#req_rebo_rolloscc_opttype_metro').show(0);
            <?php

         }
         ?>
      });
   </script>
   <?php
}
?>
<?=Nifty_printH("boxopt_b", "1020",0)?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130" style="padding-right:5px">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($headdata["rebo_status"] == 1)
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.idx_xform)", "disk-black");
         ?>
      </td>
      <td align="right" width="130">
         <?php
         printButton("Generar C.C.", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.idx_xform.activate.value='1';submitForm(document.idx_xform); }", "tick-circle-frame");
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>