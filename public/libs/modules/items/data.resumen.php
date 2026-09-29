<?php
//--------------------------------------------------------------------------------------------------------------------------------------
$datsql      = " select distinct t1.pl_title
                          , t1.pl_crtdat
                          , t1.pl_crtusr
                          , t3.user_firstname 'crt_firstname'
                          , t3.user_lastname 'crt_lastname'
                          , t1.id
                    from price_lists_fab t1
                    LEFT OUTER JOIN user t3 ON t1.pl_crtusr = t3.id
                    where t1.pl_status = 1 
                    order by t1.pl_title";
$pricelists  = $CON->select($datsql);

// -------------------------------------------------------------------------------------------------------------------
$sql = " select t2.id, t2.cat_title
            from item_productcats t1, productcats t2
            where
            t1.cat_id      = t2.id and 
            t1.item_id     = {$_REQUEST["id"]} and
            t2.cat_status  = 1";
    $selitemcatid = $CON->select($sql);
    $selitemcatidstr = $selitemcatid[0]["id"];
    $selitemcatname  = $selitemcatid[0]["cat_title"];
// -------------------------------------------------------------------------------------------------------------------
$sql = " select *
         from productcats
           where id = {$selitemcatid[0]["id"]}";
    $pcatdata = $CON->select($sql);
    $pcatdata = $pcatdata[0];
$sql         = "select * from item_shops where item_id = {$_REQUEST["id"]} and pl_id = {$_REQUEST["sql_precio"]}";
$item_shops  = $CON->select($sql);
$item_shops  = $item_shops[0];
// ---------------------------------------------------------------------------------------------------------------------
$sql         = " select t1.*
                    from price_lists_fab_amounts t1
                    where
                    t1.pl_id      = {$_REQUEST["sql_precio"]} and
                    t1.amt_status  = 1
                    order by t1.amt_val";
$amounts     = $CON->select($sql);    
// ---------------------------------------------------------------------------------------------------------------------
$sql         = "select t1.*
                from price_lists_fab_increments t1
                where
                t1.pl_id      = {$_REQUEST["sql_precio"]} and
                t1.inc_status  = 1
                order by t1.inc_name";
$incrementos = $CON->select($sql);         

$sql      = "select * from price_lists_fab_items where pl_id = {$_REQUEST["sql_precio"]} and fab_item_id = {$_REQUEST["id"]}";
$item_fab = $CON->select($sql);
$item_fab = $item_fab[0];


if($_REQUEST["opcion_2"]=="delete_2")
{
    $sql = "delete from price_lists_fab_items_predefines where id = {$_REQUEST["id_ref"]}";
    $res = $CON->no_result($sql);
    $savemsg = getSaveMessage($res);
}

if($_REQUEST["opcion_2"]=="save")
{
    $fab_inc_id           = (int)$_REQUEST["fab_inc_id"];
    $fab_active           = (int)$_REQUEST['fab_active'];
    $fab_noprint_discount = (int)$_REQUEST['fab_noprint_discount'];

    //----------------------------------------------------------------------------------
    $sql = " delete from tran_comments_item_vals where
                item_id = {$_REQUEST["id"]}";
    $CON->no_result($sql);
    
    foreach(array_keys($_REQUEST) AS $reqkey)
    {
        if(strpos($reqkey, "comvals_") !== false && strpos($reqkey, "comvals_") == 0 && strpos($reqkey, "_eng") !== true)
        {
            $idxarr        = explode("_", $reqkey);
            $com_id        = $idxarr[1];
            $val_id        = $_REQUEST[$reqkey];

            if((int)$val_id && (int)$com_id)
            {
                $sql = " insert into tran_comments_item_vals
                        (item_id, com_id, val_id)
                        VALUES
                        ({$_REQUEST["id"]}, {$com_id}, {$val_id})";
                $CON->no_result($sql);
            }
        }
    }

    $sql = "select t1.com_name as descripcion
                 , t1.id       as id_caracteristica
                 , t3.id       as id_dato
                 , t3.add_name as dato
                from tran_comments t1
                    INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
                    INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
                    inner join tran_comments_item_vals t4 on t4.item_id = {$_REQUEST["id"]} 
                where t1.com_status  > 0 and
                    t2.cat_id      = {$pcatdata["id"]} and
                    t3.add_status  > 0 and t3.id = t4.val_id
                order by t1.com_name, t3.add_name ";
    $datos = $CON->select($sql);
    foreach( $datos as $dat) 
    {
        if($dat["id_caracteristica"]==39)
        {
           $_REQUEST['fab_med_height'] = $dat["dato"];
        }
        if($dat["id_caracteristica"]==31)
        {
           $_REQUEST['fab_med_width'] = $dat["dato"];
        }
        if($dat["id_caracteristica"]==35)
        {
            $_REQUEST['fab_med_fuelle'] = $dat["dato"];
        }
        if($dat["id_caracteristica"]==37)
        {
            $_REQUEST['fab_manilla_length'] = $dat["dato"];
        }
        if($dat["id_caracteristica"]==42)
        {
            $_REQUEST['fab_fabric_gr'] = $dat["dato"];
        }
    }

    //----------------------------------------------------------------------------------
    
    if(!(int)$item_fab["id"])
    {
        $sql = "insert into price_lists_fab_items(pl_id
                                    , fab_item_id
                                    , fab_type
                                    , fab_desc
                                    , fab_med_width
                                    , fab_med_height
                                    , fab_med_fuelle
                                    , fab_min_amt
                                    , fab_corte_machine
                                    , fab_roll_width
                                    , fab_fabric_gr
                                    , fab_manilla_length
                                    , fab_print_width
                                    , fab_print_height
                                    , fab_noprint_discount
                                    , fab_active
                                    , fab_inc_id)
                values({$_REQUEST['sql_precio']}
                      ,{$_REQUEST["id"]}
                     ,'{$_REQUEST['fab_type']}'
                     ,'{$_REQUEST['fab_desc']}'
                      ,{$_REQUEST['fab_med_width']}
                      ,{$_REQUEST['fab_med_height']}
                      ,{$_REQUEST['fab_med_fuelle']}
                      ,{$_REQUEST['fab_min_amt']}
                      ,{$_REQUEST['fab_corte_machine']}
                      ,{$_REQUEST['fab_roll_width']}
                      ,{$_REQUEST['fab_fabric_gr']}
                      ,{$_REQUEST['fab_manilla_length']}
                      ,{$_REQUEST['fab_print_width']}
                      ,{$_REQUEST['fab_print_height']}
                      ,{$fab_noprint_discount}
                      ,{$fab_active}
                      ,{$fab_inc_id}) ";
        $res = $CON->no_result($sql);
        if($res)
        {
            $newid = mysql_insert_id();
            $item_fab["id"] = $newid;
            foreach($amounts as $pl)
            {
                if($_REQUEST["prc_price_".(int)$pl["amt_val"]]  > 0)
                {
                    $valor = (int)$_REQUEST["prc_price_".(int)$pl["amt_val"]];
                    $sql = "insert into price_lists_fab_items_prices(pl_id, prc_headerid, prc_amount, prc_price)
                           values({$_REQUEST["sql_precio"]}
                                  ,{$newid}
                                  ,{$pl["amt_val"]}
                                  ,{$valor}
                                 ) ";
                    $res = $CON->no_result($sql);
            
                }
            }
        }
    }
    else
    {
        $sql = "update price_lists_fab_items set fab_type             = '{$_REQUEST['fab_type']}'
                                               , fab_desc             = '{$_REQUEST['fab_desc']}'
                                               , fab_med_width        = {$_REQUEST['fab_med_width']}
                                               , fab_med_height       = {$_REQUEST['fab_med_height']}
                                               , fab_med_fuelle       = {$_REQUEST['fab_med_fuelle']}
                                               , fab_min_amt          = {$_REQUEST['fab_min_amt']}
                                               , fab_corte_machine    = {$_REQUEST['fab_corte_machine']}
                                               , fab_roll_width       = {$_REQUEST['fab_roll_width']}
                                               , fab_fabric_gr        = {$_REQUEST['fab_fabric_gr']}
                                               , fab_manilla_length    = {$_REQUEST['fab_manilla_length']}
                                               , fab_print_width      = {$_REQUEST['fab_print_width']}
                                               , fab_print_height     = {$_REQUEST['fab_print_height']}
                                               , fab_noprint_discount = {$fab_noprint_discount}
                                               , fab_active           = {$fab_active}
                                               , fab_inc_id           = {$fab_inc_id}
                    where pl_id = {$_REQUEST["sql_precio"]} and fab_item_id = {$_REQUEST["id"]} ";
        $res = $CON->no_result($sql);
        if($res)
        {
            $sql = "delete from price_lists_fab_items_prices where prc_headerid = {$item_fab["id"]}";
            $CON->no_result($sql);
            
            foreach($amounts as $pl)
            {
                $valor = (int)$_REQUEST["prc_price_".(int)$pl["amt_val"]];
                if($_REQUEST["prc_price_".(int)$pl["amt_val"]]  > 0)
                {
                    $valor = $_REQUEST["prc_price_".(int)$pl["amt_val"]];
                    $valor = str_replace('.', '', $valor);
                    $sql = "insert into price_lists_fab_items_prices(pl_id, prc_headerid, prc_amount, prc_price)
                           values({$_REQUEST["sql_precio"]}
                                  ,{$item_fab["id"]}
                                  ,{$pl["amt_val"]}
                                  ,{$valor}
                                 ) ";
                    $res = $CON->no_result($sql);
                }
            }            
        }

    }

    $savemsg = getSaveMessage($res);
    
}

foreach($pricelists as $pl)
{
    $_REQUEST["prc_price_".(int)$pl["amt_val"]] = 0;
}

$sql         = "select * from fabric_types where fabt_status > 0";
$familia     = $CON->select($sql);

$sql         = " select t1.*
                    from price_lists_fab_amounts t1
                    where
                    t1.pl_id      = {$_REQUEST["sql_precio"]} and
                    t1.amt_status  = 1
                    order by t1.amt_val";
$amounts     = $CON->select($sql);    

foreach($amounts AS $amount)
{  
    $_REQUEST["prc_price_".(int)$amount["amt_val"]] = 0;
}

$sql      = "select * from price_lists_fab_items where pl_id = {$_REQUEST["sql_precio"]} and fab_item_id = {$_REQUEST["id"]}";
$item_fab = $CON->select($sql);
$item_fab = $item_fab[0];

// configuracion  de precios referidos 
$sql = "select id
            , header_id
            , fab_med_width
            , fab_med_height
            , fab_med_fuelle
            , fab_printtype
            , fab_print_colors_front
            , fab_print_colors_back 
        from price_lists_fab_items_predefines 
            where header_id = {$item_fab["id"]} ";
$referidos = $CON->select($sql);
$_REQUEST["tope"] = count($referidos);

if($_REQUEST["opcion_2"]=='save_2')
{
    $sql = "delete from price_lists_fab_items_predefines 
            where header_id = {$item_fab["id"]} ";
    $CON->no_result($sql);

    for($row = 0; $row < count($referidos)+5; $row++)
    {
        if($_REQUEST["fab_printtype_".$row] != "") // && ( (int)$_REQUEST["fab_print_colors_front_".$row] > 0 || (int)$_REQUEST["fab_print_colors_back_".$row] > 0) )
        {
            $sql = "insert into price_lists_fab_items_predefines(header_id
                                                              , fab_med_width
                                                              , fab_med_height
                                                              , fab_med_fuelle
                                                              , fab_printtype
                                                              , fab_print_colors_front
                                                              , fab_print_colors_back) 
                    values({$item_fab["id"]}
                          ,{$item_fab["fab_med_width"]}
                          ,{$item_fab["fab_med_height"]}
                          ,{$item_fab["fab_med_fuelle"]} 
                          ,'{$_REQUEST["fab_printtype_".$row]}'
                          ,{$_REQUEST["fab_print_colors_front_".$row]} 
                          ,{$_REQUEST["fab_print_colors_back_".$row]})                      
                    ";
            $CON->no_result($sql);
        }

    }
}

$sql = "select id
            , header_id
            , fab_med_width
            , fab_med_height
            , fab_med_fuelle
            , fab_printtype
            , fab_print_colors_front
            , fab_print_colors_back 
        from price_lists_fab_items_predefines 
            where header_id = {$item_fab["id"]} 
        order by id";
$referidos = $CON->select($sql);
$_REQUEST["tope"] = count($referidos);

$sql = "select * from price_lists_fab_amounts t1
                left join price_lists_fab_items_prices t2 on t2.pl_id = t1.pl_id and prc_headerid = {$item_fab["id"]} and prc_amount = amt_val
            where t1.pl_id = {$_REQUEST["sql_precio"]} 
                and t1.amt_status > 0 
            order by t1.amt_val";
$price_lista = $CON->select($sql); 

foreach($price_lista as $pl)
{
    if((int)$pl["prc_price"] > 0)
    {
        $_REQUEST["prc_price_".(int)$pl["prc_amount"]] = $pl["prc_price"];
    }
}

// -------------------------------------------------------------------------------------------------------------------
$col = 0;
$row = 0;
foreach($referidos as $referido)
{
    if((int)$referido["fab_print_colors_front"])
    {
      $_REQUEST["fab_print_colors_front_".(int)$referido["fab_print_colors_front"]] = (int)$referido["fab_print_colors_front"];
    }

    if((int)$referido["fab_print_colors_back"])
    {
      $_REQUEST["fab_print_colors_back_".(int)$referido["fab_print_colors_back"]] = (int)$referido["fab_print_colors_back"];
    }    
}
// -------------------------------------------------------------------------------------------------------------------
$sql = " select t1.com_name, t3.*
            from tran_comments t1
            INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
            INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
         where t1.com_status  > 0 and
               t2.cat_id      = {$pcatdata["id"]} and
               t3.add_status  > 0
         order by t1.com_name, t3.add_name ";

$trancoms = $CON->select($sql);
foreach($trancoms AS $trancom)
{
    $_TRANSCOM[$trancom["add_com_id"]]["NAME"] = $trancom["com_name"];
    $_TRANSCOM[$trancom["add_com_id"]]["OPTS"][$trancom["id"]] = $trancom["add_name"];
}
// -------------------------------------------------------------------------------------------------------------------
$sql = " select *
    from tran_comments_item_vals
    where item_id = {$_REQUEST["id"]}";
$comvals = $CON->select($sql);
foreach($comvals AS $comval)
{
    $_COMVALS[$comval["com_id"]] = $comval["val_id"];
}

//--------------------------------------------------------------------------------------------------------------------------------------
?>
<script>
    function toggleTable() 
    {
        let select  = document.getElementById("sql_precio");
        let table = document.getElementById("miTabla");

        if (select.value != "0")
        {
            table.style.display = "table"; // Mostrar tabla
        } 
        else
        {
            table.style.display = "none"; // Ocultar tabla
        }

        let tb1 = document.getElementById("tab_1");
        let tb2 = document.getElementById("tab_2");
        let tb3 = document.getElementById("tab_3");
        let tb4 = document.getElementById("tab_4");
        let tb5 = document.getElementById("tab_5");

        tb1.style.display = "none";
        tb2.style.display = "none";
        tb3.style.display = "none";
        tb4.style.display = "none";
        tb5.style.display = "none";

    }
</script>
<style>
    .disabled { 
         pointer-events: none; 
         opacity: 0.5; 
    }
</style>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>

<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
printJSsetCompanyShop($shops);
?>
<form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="search">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="itemtype" value="<?=$_REQUEST["itemtype"]?>">
<input type="hidden" name="opcion" value="0">
<input type="hidden" name="opcion_2" value="">
<input type="hidden" name="generar" value="">
<input type="hidden" name="id_ref" value="">
<input type="hidden" name="tope" value="<?=$_REQUEST["max_referidos"]?>">

<?=Nifty_printH("box1", "980", 0)?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="100">
   <col>
   <col width="100">
   <col width="400">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Opciones de búsqueda de Lista de Precio</td>
</tr>
<tr>
    <td class="content_rowl">Lista de Precio</td>
    <td class="content_row">
        <select class="text" id="sql_precio" name="sql_precio" style="width:500px"
           onmousedown="markfield(this,0)" onblur="markfield(this,1)"
           onchange="toggleTable()">
           <option value="0">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
           <?php
           foreach($pricelists AS $lista)
           {  ?>
              <option value="<?=$lista["id"]?>" <?if($_REQUEST["sql_precio"] == $lista["id"]) echo "selected"?>><?=$lista["pl_title"]?></option><?php
           }
          ?>
        </select>
    </td>
</tr>
<tr>
</table>
<?=Nifty_printF(false)?>
&nbsp;

<?=Nifty_printH("boxopt_b", "980", 0);
if($_REQUEST["sql_precio"]>0)
   $ll = "display:table;";
else
   $ll = "display:none;"; 
?>
<table id="miTabla" name="miTabla" align="center"  border="0" cellpadding="0" cellspacing="0" width="50%" style=<?echo $ll?>>
    <td>
    <?php
        printButton("Configurar Precio", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.opcion.value='1';submitForm(document.xform_itemsearch)", "calculator", 130);
    ?>
    </td>
    <td>
    <?php
        printButton("Productos Definidos", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.opcion.value='2';submitForm(document.xform_itemsearch)", "clipboard-list", 130);
    ?>
    </td>
</table>

<?php
if($_REQUEST["opcion"]=='1')
{
?>
    &nbsp;
    <td>
    <style>
        #tab_1 {
            border: 1px solid #ccc;
        }
        #tab_1 th, #tab_1 td {
            border: none;
        }

        #tab_2 {
            border: 1px solid #ccc;
        }
        #tab_2 th, #tab_2 td {
            border: none;
        }

        #tab_3 {
            border: 1px solid #ccc;
        }
        #tab_3 th, #tab_3 td {
            border: none;
        }

        #tab_4 {
            border: 1px solid #ccc;
        }
        #tab_4 th, #tab_4 td {
            border: none;
            padding: 1px;
        }


    </style>
   
    </td>
    <?=Nifty_printH("boxopt_b", "980", 0);?>
    <table name="tab_1" id="tab_1" cellpadding="0" cellspacing="0" width="980">
        <colgroup>
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
            <td class="content_tbl_header" colspan="8">Caracteristicas</td>
        </tr>
        <tr>
            <tr>
                <td class="content_rowl">Tipo</td>
                <td class="content_row">
                   <select class="text" name="fab_type" id="fab_type" style="width:100px"
                        onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                        <?php
                        foreach($familia AS $fam)
                        {?>
                            <option value="<?=$fam["fabt_code"]?>" <?php if($item_fab["fab_type"] == $fam["fabt_code"]) echo "selected"?>><?=$fam["fabt_code"]." - ".$fam["fabt_name"]?></option>
                        <?php
                        }
                        ?>
                    </select>
                </td>
                <td class="content_rowl">Incremento</td>
                <td class="content_row">
                    <select class="text" name="fab_inc_id" id="fab_inc_id" style="width:100px"
                    onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                    <?php
                    foreach($incrementos AS $incremt)
                    {  ?>
                        <option value="<?=$incremt["id"]?>" <?php if(($incremt["id"] == $item_fab["fab_inc_id"])) echo "selected"?>><?=$incremt["inc_name"]?></option><?php
                    }
                    ?>
                    </select>
                </td>
                <td class="content_rowl">Descripción</td>
                <td class="content_row" colspan="4">
                    <input name="fab_desc" type="text" class="text" style="width:345px" value="<?=$item_fab["fab_desc"]?>"
                        onfocus="markfield(this,0)" onblur="markfield(this,1)">
                </td>
            </tr>

            <tr>
                <td class="content_rowl">Ancho</td>
                <td class="content_row">
                    <input name="fab_med_width" id="fab_med_width" type="number" class="text" 
                       style="width:100px;" value="<?=(int)$item_fab["fab_med_width"]?>" readonly
                    >
                </td>
                <td class="content_rowl">Alto</td>
                <td class="content_row">
                    <input name="fab_med_height" type="number" class="text" style="width:100px;" value="<?=(int)$item_fab["fab_med_height"]?>" readonly>
                </td>

                <td class="content_rowl">Fuelle</td>
                <td class="content_row">
                    <input name="fab_med_fuelle" type="number" class="text" style="width:100px;" value="<?=(int)$item_fab["fab_med_fuelle"]?>" readonly
                    >
                </td>
                <td class="content_rowl">Largo Manilla</td>
                <td class="content_row">
                    <input name="fab_manilla_length" type="number" class="text" style="width:100px;" value="<?=(int)$item_fab["fab_manilla_length"]?>" readonly
                    >
                </td>
            </tr>
            <tr>
                <td class="content_rowl">Corte Maquina</td>
                <td class="content_row">
                    <input name="fab_corte_machine" type="number" class="text" style="width:100px" value="<?=(int)$item_fab["fab_corte_machine"]?>"
                    onfocus="markfield(this,0)" onblur="markfield(this,1)">
                </td>
                <td class="content_rowl">Ancho Rollo</td>
                <td class="content_row">
                    <input name="fab_roll_width" type="number" class="text" style="width:100px" value="<?=(int)$item_fab["fab_roll_width"]?>"
                    onfocus="markfield(this,0)" onblur="markfield(this,1)">
                </td>
                <td class="content_rowl">Gramaje Tela</td>
                <td class="content_row">
                    <input name="fab_fabric_gr" type="number" class="text" style="width:100px" value="<?=(int)$item_fab["fab_fabric_gr"]?>"
                    onfocus="markfield(this,0)" onblur="markfield(this,1)">
                </td>
                <td class="content_row"></td>
                <td class="content_row"></td>
            </tr>

            <tr>
                <td class="content_rowl">Area de Impresión</td>
                <td class="content_row">
                    <input name="fab_print_width" type="number" class="text" style="width:50px" value="<?=(int)$item_fab["fab_print_width"]?>"
                    onfocus="markfield(this,0)" onblur="markfield(this,1)">
                    <input name="fab_print_height" type="text" class="text" style="width:50px" value="<?=(int)$item_fab["fab_print_height"]?>"
                    onfocus="markfield(this,0)" onblur="markfield(this,1)">
                </td>
                <td class="content_rowl">Dsc Sin Impresión</td>
                <td class="content_row">
                    <input name="fab_noprint_discount" type="number" class="text" style="width:100px" value="<?=$item_fab["fab_noprint_discount"]?>"
                    onfocus="markfield(this,0)" onblur="markfield(this,1)">
                </td>
                <td class="content_rowl">Pedido Minimo</td>
                <td class="content_row">
                    <input name="fab_min_amt" type="number" class="text" style="width:100px" value="<?=(int)$item_fab["fab_min_amt"]?>"
                    onfocus="markfield(this,0)" onblur="markfield(this,1)">
                </td>
                <td class="content_row" align="center">
                    <input type="checkbox" name="fab_active" id="fab_active" value="1"
                    <?if((int)$item_fab["fab_active"]) echo "checked"?>> Activo
                </td>
            </tr>
            <td></td>
        </tr>
    </table>
    <?=Nifty_printF(false)?>
    <br>

    <?=Nifty_printH("boxopt_b", "980", 0)?>
      <table name="tab_2" id="tab_2" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="200">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2" style="background-color:#9CC5FF;text-shadow:none">Registro de caracteristicas</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader">Caracterá­stica</td>
         <td class="content_tbl_subheader">Valor</td>
      </tr>
      <?php
      foreach(array_keys($_TRANSCOM) AS $trancomid)
      {  ?>
         <tr>
            <td class="content_rowl"><?=$_TRANSCOM[$trancomid]["NAME"]?>
            </td>
            <td class="content_row">
               <select class="text" style="width:400px;" name="comvals_<?=$trancomid?>" id="comvals_<?=$trancomid?>">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach(array_keys($_TRANSCOM[$trancomid]["OPTS"]) AS $trancomvalid)
                  {  ?>
                     <option value="<?=$trancomvalid?>" <?php if((int)$_COMVALS[$trancomid] == $trancomvalid) echo "selected"?>>
                        <?=$_TRANSCOM[$trancomid]["OPTS"][$trancomvalid]?>
                     </option>
                     <?php
                  }
                  ?>
               </select>
            </td>
         </tr>
         <?php
      }  
      ?>
      </table>
    <?=Nifty_printF(false)?>
    <br>

    <?=Nifty_printH("boxopt_b", "980", 0)?>
    <table name="tab_3" id="tab_3" cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed">
        <tr>
            <td class="content_tbl_header" width="100%" align="center">Rango por Cantidad</td>
        </tr>
    </table>
    <?=Nifty_printF(false)?>

    <?=Nifty_printH("boxopt_b", "980", 0)?>            
    <table name="tab_4" id="tab_4" cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed">
        <tr>
        <?php
        foreach($amounts AS $amount)
        {  
            ?>
            <td class="content_tbl_subheader content_row_os" align="center"><?=printPrice($amount["amt_val"])?></td>
            <?php
        }?>
        </tr>
        <tr>
        <?php
        $x = 0;
        foreach($amounts AS $amount)
        {  
            ?>
            <td class="content_row_os" align="center">
               <input type="text" class="text" style="width:45px;text-align:center" placeholder=""
               name="prc_price_<?=(int)$amount["amt_val"]?>"
               value="<?=PrintPrice($_REQUEST["prc_price_".(int)$amount["amt_val"]],0)?>">
            </td>
            <?php
        }
        ?>        
        </tr>
    </table>
    <?=Nifty_printF(false)?>
    <br>
    <?php
    if($_REQUEST["id"] != "")
    {  ?>
        <?=Nifty_printH("boxopt_b", "980", 0)?>
        <table name="tab_5" id="tab_5" border="0" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td align="left" width="130">
                <?php
                if($_REQUEST["frommid"] != "")
                    $mid = $_REQUEST["frommid"];
                else
                    $mid = $_REQUEST["mid"];
                printButton($_LANG["FORM"]["BUTTON"][1], "postnav"     ,"index.php?mid={$mid}{$extlink}", "", "arrow-180");
                ?>
            </td> 
            <td>&nbsp;</td>
            <td align="right" width="130" style="padding-right:5px">
                <?php
                /*
                if($_SESSION["user_type"] == 1)
                    printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
                else
                    printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/items/auth.fancybox.php?mid={$_REQUEST["mid"]}&id={$_REQUEST["id"]}','iframe', 450, 160, 'no')", "cross-circle-frame");
                */
                ?>
            </td>
            <td align="right" width="130">
                <?php
                printButton("Graba Precio" , "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.opcion_2.value='save';document.xform_itemsearch.opcion.value=1;submitForm(document.xform_itemsearch)", "plus", 130);
                ?>
            </td>
        </tr>
        </table>
        <?=Nifty_printF(false)?>
        <br>
        <?php  
    }
}
?>

<?php
if($_REQUEST["opcion"]=='2')
{
?>
    &nbsp;
    <?=Nifty_printH("box1", "980", 0)?>
    <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
    <colgroup>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
        </colgroup>
        <tr>
            <td class="content_tbl_header" colspan="7">Opciones de Referidos</td>
        </tr>
        <tr>
            <tr>
                <td class="content_rowl">Tipo de Impresiï¿½n</td>
                <td class="content_rowl">Ancho</td>
                <td class="content_rowl">Alto</td>
                <td class="content_rowl">Fuelle</td>
                <td class="content_rowl" colspan="2" align="center">Colores</td>
                <td class="content_rowl">Opciï¿½n</td>
            </tr>
        </tr>
        <?php
            for($row = 0; $row < count($referidos)+5;$row++)
            {
                ?>
                <tr>
                    <input type="hidden" name="id_ref_<?=$row?>" value="<?=(int)$referidos[$row]["id"]?>">
                    <td class="content_row">
                        <select class="text" style="width:100%" name="fab_printtype_<?=$row?>" id="fab_printtype_<?=$row?>">
                            <option value="">Seleccione</option>
                            <option value="FLEX" <?if($referidos[$row]["fab_printtype"] == "FLEX") echo "selected"?>>Flexografï¿½a</option>
                            <option value="SERI" <?if($referidos[$row]["fab_printtype"] == "SERI") echo "selected"?>>Serigrafï¿½a</option>
                        </select>
                    </td>
                    <td class="content_row_os">
                        <input name="fab_med_fuelle" type="number" class="text" style="width:50px;" value="<?=(int)$referidos[0]["fab_med_width"]?>" readonly>
                    </td>
                    <td class="content_row_os">
                        <input name="fab_med_fuelle" type="number" class="text" style="width:50px;" value="<?=(int)$referidos[0]["fab_med_height"]?>" readonly>
                    </td>
                    <td class="content_row_os">
                        <input name="fab_med_fuelle" type="number" class="text" style="width:50px;" value="<?=(int)$referidos[0]["fab_med_fuelle"]?>" readonly>
                    </td>
                    <td class="content_row_os" align="center" colspan="2">
                        <input type="text" class="text" style="width:45px;text-align:center" placeholder=""
                            name="fab_print_colors_front_<?=$row?>" id="fab_print_colors_front_<?=$row?>"
                            value="<?=(int)$referidos[$row]["fab_print_colors_front"]?>">                        
                        /
                        <input type="text" class="text" style="width:45px;text-align:center" placeholder=""
                            name="fab_print_colors_back_<?=$row?>" id="fab_print_colors_back_<?=$row?>"
                            value="<?=(int)$referidos[$row]["fab_print_colors_back"]?>">                        
                   </td>
                   <td class="content_row_os">
                      <?php
                      if((int)$referidos[$row]["fab_print_colors_back"] || (int)$referidos[$row]["fab_print_colors_front"] || $referidos[$row]["fab_printtype"] != "")
                      {
                         printButton("", "postnav_del", "javascript: deactivateFormChange()","document.xform_itemsearch.opcion_2.value='delete_2';document.xform_itemsearch.opcion.value=2;document.xform_itemsearch.id_ref.value='{$referidos[$row]['id']}';submitForm(document.xform_itemsearch)", "cross-circle-frame");
                      }
                      ?>
                   </td>
                </tr>
                <?php
            }
        ?>
    </table>
    <?=Nifty_printF(false)?>
    &nbsp;
    <?php
    if($_REQUEST["id"] != "")
    {?>
            <?=Nifty_printH("boxopt_b", "980", 0)?>
            <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td align="left" width="130">
                    <?php
                    if($_REQUEST["frommid"] != "")
                        $mid = $_REQUEST["frommid"];
                    else
                        $mid = $_REQUEST["mid"];
                    printButton($_LANG["FORM"]["BUTTON"][1], "postnav"     ,"index.php?mid={$mid}{$extlink}", "", "arrow-180");
                    ?>
                </td> 
                <td>&nbsp;</td>
                <td align="right" width="130" style="padding-right:5px">
                    <?php
                    /*  printButton("Borrar Definidos", "postnav_del", "javascript: deactivateFormChange()","document.xform_itemsearch.opcion_2.value='save_2';document.xform_itemsearch.opcion.value=2;submitForm(document.xform_itemsearch)", "cross-circle-frame"); */
                    ?>
                </td>
                <td align="right" width="130">
                    <?php
                    printButton("Graba Definidos", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.opcion_2.value='save_2';document.xform_itemsearch.opcion.value=2;submitForm(document.xform_itemsearch)", "plus", 130);
                    ?>
                </td>
            </tr>
            </table>
            <?=Nifty_printF(false)?>
            <br>
            <?php  
   }?>
    
<?php  
}
?>
<??>
</form>