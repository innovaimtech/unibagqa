<?php
//----------------------------------------------------------------------------------
$_sesmodulename         = "presupuesto";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2,1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_item_id"]       = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"]     = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_seller"]        = (int)$_REQUEST["sql_seller"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_origtype"]      = (int)$_REQUEST["sql_origtype"];
   $_SESSION[$_sesmodulename]["sql_xitemtype"]     = (int)$_REQUEST["sql_xitemtype"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_stext2"]        = trim(addslashes($_REQUEST["sql_stext2"]));
   $_SESSION[$_sesmodulename]["sql_folio"]         = trim(addslashes($_REQUEST["sql_folio"]));
   $_SESSION[$_sesmodulename]["sql_paystatus"]     = $_REQUEST["sql_paystatus"];
   $_SESSION[$_sesmodulename]["sql_vencstatus"]    = $_REQUEST["sql_vencstatus"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_cctype"]        = (int)$_REQUEST["sql_cctype"];
   $_SESSION[$_sesmodulename]["sql_pcat"]          = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
   $_SESSION[$_sesmodulename]["sql_status"]       = $_REQUEST["sql_status"];
}


//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON);
$sellers    = getSellers($CON);
//----------------------------------------------------------------------------------

if($_SESSION[$_sesmodulename]["filter_status"] != 5)
   if(!is_array($_SESSION[$_sesmodulename]["sql_status"]))
      $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3,3=>4);

//----------------------------------------------------------------------------------

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_shop"])
{
   $first = false;
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"] && !$first)
      {
         $_SESSION[$_sesmodulename]["sql_shop"] = $shop["id"];
         $first = true;
      }
}
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;

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
   $_SESSION[$_sesmodulename]["sql_date_pfrom"] = date('d.m.Y', time() - (86400 * 7));
   $_SESSION[$_sesmodulename]["sql_date_pto"]   = date('d.m.Y');
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

$datsql = "select t2.cust_rut               as 'rutcliente'
                 ,t10.descripcion           as 'canal'
                 ,t2.cust_company           as 'razonsocial'
                 ,t2.cust_name              as 'nombre'
                 ,t11.cat_name              as 'categoria'
                 ,t1.invc_docnumber         as 'Facturas'
                 ,invc_date                 as 'fechadefactura'
                 ,t7.req_number             as 'Confirmacion'
                 ,t5.item_number_prod       as 'SKU'
                 ,t5.item_title             as 'Producto'
                 ,t5.id                     as  id_item
                 ,t8.fab_type               as 'material'
                 ,t8.fab_printtype          as 'Impresion'
                 ,t14.add_name              as 'colordetela'
                 ,t8.fab_mat_gramms         as 'gramaje'
                 ,t4.item_sellprice_netto   as 'precioneto'
                 ,t4.item_amount            as 'cantidad'
                 ,invc_total_netto          as 'totalneto'
                 ,fmax.fecha_ultima_factura as 'fechaultimafactura'
                 ,o.fecha_ultima_cotizacion as 'fechaultimacotizacion'
                 ,f.total_factura_cliente   as 'cantidaddefacturasemitidas'
                 ,concat(t3.user_firstname,' ',t3.user_lastname) as 'vendedor'
                 ,t13.add_name              as 'tipodebolsa'
                 ,concat(t18.user_firstname,' ',t18.user_lastname) as 'cartera'
                 ,t2.cust_presupuesto
                 ,m2.add_name               as mat_prod
                 ,g2.add_name               as gra_prod
                 ,'Facturas'                as documento
                 ,csc1.sub_cat_name                 
            from invoices_sell t1
               inner join customer t2 on t1.invc_cust_id = t2.id
               inner join user t3 on t1.invc_userid_seller = t3.id
               inner join invoices_sell_parts t6 on t6.part_invc_id = t1.id               
               inner join invoices_sell_parts_items t4 on t1.id = t4.invc_id  and t4.part_id = t6.id   
               LEFT OUTER JOIN item t5 on t5.id = t4.item_id  and t4.item_type = 'item'
               LEFT OUTER JOIN item_productcats t15      ON t5.id = t15.item_id
               LEFT OUTER JOIN productcats t16           ON t15.cat_id = t16.id
               left outer join parametros t10 on t10.tabla = 'CANAL' and t10.codigo = t2.cust_canal
               left join (
                  select replace(req_cust_rut, '.', '') as rut_limpio,
                        MAX(req_crtdat) AS fecha_ultima_cotizacion
                  from offers
                  group by replace(req_cust_rut, '.', '')
               ) o on replace(t2.cust_rut, '.', '') = o.rut_limpio
               left outer join orders t7 on t7.id = t6.part_req_id
               left outer join orders_items t8 on t8.req_id = t7.id and t8.item_id = t4.item_id
               left outer join customer_cats t11 on t11.id = t2.cust_catid
               left outer join customer_sub_cats csc1 on t2.cust_subrubro = csc1.id
               left outer join tran_comments_item_vals t12 on t12.item_id = t5.id and t12.com_id = 20
               left outer join tran_comments_vals t13 on t12.val_id = t13.id
               left outer join tran_comments_vals t14 on t14.id = t8.fab_mat_fabric_color
               left outer join user t18 on t2.cust_sellerid = t18.id
               LEFT JOIN (SELECT invc_cust_id, MAX(invc_date) AS fecha_ultima_factura
                           FROM invoices_sell
                           WHERE invc_status = 2
                           GROUP BY invc_cust_id
                     ) fmax ON fmax.invc_cust_id = t2.id
               LEFT JOIN (SELECT invc_cust_id, COUNT(*) AS total_factura_cliente FROM invoices_sell where invc_status > 1 and invc_status  < 4 
                                 GROUP BY invc_cust_id) f ON f.invc_cust_id = t2.id
               left outer join tran_comments_item_vals m1 on m1.item_id = t5.id and m1.com_id = 18
                  left outer join tran_comments_vals m2 on m1.val_id = m2.id 

               left outer join tran_comments_item_vals g1 on g1.item_id = t5.id and g1.com_id = 42
                  left outer join tran_comments_vals g2 on g1.val_id = g2.id 

            where invc_date between {$sql_datefrom} and {$sql_dateto}
            and invc_status in(2,3) ";      
 //----------------------------------------------------------------------------------

 // echo($datsql);

if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id  = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $datsql .= " and t1.invc_userid_seller = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
if($_SESSION[$_sesmodulename]["sql_stext2"] != "")
   $datsql .= " and t7.req_number = '{$_SESSION[$_sesmodulename]["sql_stext2"]}' ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t1.invc_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_cctype"] == 1)
   $datsql .= " and t7.req_isfabricate = 1 ";
elseif((int)$_SESSION[$_sesmodulename]["sql_cctype"] == 2)
   $datsql .= " and t7.req_isfabricate = 0 ";
if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   $datsql .= " and t15.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
   $datsql .= " and t5.item_ventaonline_act = 1 ";
elseif((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
   $datsql .= " and t5.item_ventaonline_act = 0 ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $datsql .= " and t8.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t8.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";


$datsql .=" union all

           select t2.cust_rut               as 'rutcliente'
                 ,t10.descripcion           as 'canal'
                 ,t2.cust_company           as 'razonsocial'
                 ,t2.cust_name              as 'nombre'
                 ,t11.cat_name              as 'categoria'
                 ,t1.invc_docnumber         as 'Facturas'
                 ,invc_date                 as 'fechadefactura'
                 ,t7.req_number             as 'Confirmacion'
                 ,t5.item_number_prod       as 'SKU'
                 ,t5.item_title             as 'Producto'
                 ,t5.id                     as  id_item
                 ,t8.fab_type               as 'material'
                 ,t8.fab_printtype          as 'Impresion'
                 ,t14.add_name              as 'colordetela'
                 ,t8.fab_mat_gramms         as 'gramaje'
                 ,t4.item_sellprice_netto   as 'precioneto'
                 ,t4.item_amount            as 'cantidad'
                 ,invc_total_netto          as 'totalneto'
                 ,fmax.fecha_ultima_factura as 'fechaultimafactura'
                 ,o.fecha_ultima_cotizacion as 'fechaultimacotizacion'
                 ,f.total_factura_cliente   as 'cantidaddefacturasemitidas'
                 ,concat(t3.user_firstname,' ',t3.user_lastname) as 'vendedor'
                 ,t13.add_name              as 'tipodebolsa'
                 ,concat(t18.user_firstname,' ',t18.user_lastname) as 'cartera'
                 ,t2.cust_presupuesto
                 ,m2.add_name               as mat_prod
                 ,g2.add_name               as gra_prod
                 ,'Boleta'                as documento
                 ,csc1.sub_cat_name
            from invoices_sell_bol t1 
               inner join customer t2 on t1.invc_cust_id = t2.id 
               inner join user t3 on t1.invc_userid_seller = t3.id 
               inner join invoices_sell_bol_parts_items t4 on t1.id = t4.invc_id 
               inner join invoices_sell_bol_parts t6 on t6.part_invc_id = t1.id 
               inner join item t5 on t5.id = t4.item_id 
               LEFT OUTER JOIN item_productcats t15      ON t5.id = t15.item_id
               LEFT OUTER JOIN productcats t16           ON t15.cat_id = t16.id
               left outer join parametros t10 on t10.tabla = 'CANAL' and t10.codigo = t2.cust_canal
               left join (
                  select replace(req_cust_rut, '.', '') as rut_limpio,
                        MAX(req_crtdat) AS fecha_ultima_cotizacion
                  from offers
                  group by replace(req_cust_rut, '.', '')
               ) o on replace(t2.cust_rut, '.', '') = o.rut_limpio
               left outer join orders t7 on t7.id = t6.part_req_id
               left outer join orders_items t8 on t8.req_id = t7.id and t8.item_id = t4.item_id
               left outer join customer_cats t11 on t11.id = t2.cust_catid
               left outer join customer_sub_cats csc1 on t2.cust_subrubro = csc1.id
               left outer join tran_comments_item_vals t12 on t12.item_id = t5.id and t12.com_id = 20
               left outer join tran_comments_vals t13 on t12.val_id = t13.id
               left outer join tran_comments_vals t14 on t14.id = t8.fab_mat_fabric_color
               left outer join user t18 on t2.cust_sellerid = t18.id
               LEFT JOIN (SELECT invc_cust_id, MAX(invc_date) AS fecha_ultima_factura
                           FROM invoices_sell
                           WHERE invc_status = 2
                           GROUP BY invc_cust_id
                     ) fmax ON fmax.invc_cust_id = t2.id
               LEFT JOIN (SELECT invc_cust_id, COUNT(*) AS total_factura_cliente FROM invoices_sell where invc_status > 1 and invc_status  < 4 
                                 GROUP BY invc_cust_id) f ON f.invc_cust_id = t2.id
               left outer join tran_comments_item_vals m1 on m1.item_id = t5.id and m1.com_id = 18
                  left outer join tran_comments_vals m2 on m1.val_id = m2.id 

               left outer join tran_comments_item_vals g1 on g1.item_id = t5.id and g1.com_id = 42
                  left outer join tran_comments_vals g2 on g1.val_id = g2.id 

            where invc_date between {$sql_datefrom} and {$sql_dateto}
            and invc_status in(2,3) ";      
 //----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id  = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $datsql .= " and t7.req_userid_seller = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
if($_SESSION[$_sesmodulename]["sql_stext2"] != "")
   $datsql .= " and t7.req_number = '{$_SESSION[$_sesmodulename]["sql_stext2"]}' ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t7.req_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_cctype"] == 1)
   $datsql .= " and t7.req_isfabricate = 1 ";
elseif((int)$_SESSION[$_sesmodulename]["sql_cctype"] == 2)
   $datsql .= " and t7.req_isfabricate = 0 ";
if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   $datsql .= " and t15.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
   $datsql .= " and t5.item_ventaonline_act = 1 ";
elseif((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
   $datsql .= " and t5.item_ventaonline_act = 0 ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $datsql .= " and t8.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t8.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";


$datsql .= "union all
      
            select t2.cust_rut                                      as 'rutcliente' 
                  ,t10.descripcion                                  as 'canal' 
                  ,t2.cust_company                                  as 'razonsocial' 
                  ,t2.cust_name                                     as 'nombre' 
                  ,t11.cat_name                                     as 'categoria' 
                  ,t1.note_docnumber                                as 'Facturas' 
                  ,t1.note_date                                     as 'fechadefactura' 
                  ,''                                               as 'Confirmacion' 
                  ,t5.item_number_prod                              as 'SKU' 
                  ,t5.item_title                                    as 'Producto' 
                  ,t5.id                                            as id_item 
                  ,''                                               as 'material' 
                  ,''                                               as 'Impresion' 
                  ,''                                               as 'colordetela' 
                  ,''                                               as 'gramaje' 
                  ,t4.item_sellprice_netto precioneto
                  ,t4.item_amount * -1                              as 'cantidad' 
                  ,note_total_netto                                 as 'totalneto' 
                  ,fmax.fecha_ultima_factura                        as 'fechaultimafactura' 
                  ,0 as 'fechaultimacotizacion' 
                  ,f.total_factura_cliente                          as 'cantidaddefacturasemitidas' 
                  ,concat(t3.user_firstname,' ',t3.user_lastname)   as 'vendedor' 
                  ,t13.add_name                                     as 'tipodebolsa' 
                  ,concat(t18.user_firstname,' ',t18.user_lastname) as 'cartera' 
                  ,t2.cust_presupuesto 
                  ,m2.add_name                                      as mat_prod
                  ,g2.add_name                                      as gra_prod                  
                  ,'Nota de Credito'                                as documento
                  ,csc1.sub_cat_name
            from invoices_notes_sell t1  
               inner join customer t2 on t1.note_cust_id = t2.id 
               left outer join user     t3 on t1.note_userid_seller = t3.id 
               inner join invoices_notes_sell_items t4 on t1.id = t4.note_id 
               inner join item     t5 on t5.id = t4.item_id 
               LEFT OUTER JOIN item_productcats t15 ON t5.id = t15.item_id 
               LEFT OUTER JOIN productcats t16 ON t15.cat_id = t16.id 
               left outer join parametros t10 on t10.tabla = 'CANAL' and t10.codigo = t2.cust_canal 
               left outer join customer_cats t11 on t11.id = t2.cust_catid 
               left outer join customer_sub_cats csc1 on t2.cust_subrubro = csc1.id
               left outer join tran_comments_item_vals t12 on t12.item_id = t5.id and t12.com_id = 20 
               left outer join tran_comments_vals t13 on t12.val_id = t13.id 
               left outer join user t18 on t2.cust_sellerid = t18.id 
               LEFT JOIN (SELECT note_cust_id, MAX(note_date) AS fecha_ultima_factura FROM invoices_notes_sell WHERE note_status = 2 GROUP BY note_cust_id ) fmax ON fmax.note_cust_id = t2.id 
               LEFT JOIN (SELECT note_cust_id, COUNT(*) AS total_factura_cliente FROM invoices_notes_sell where note_status > 1 and note_status < 4 GROUP BY note_cust_id) f ON f.note_cust_id = t2.id 
               left outer join tran_comments_item_vals m1 on m1.item_id = t5.id and m1.com_id = 18      
                     left outer join tran_comments_vals m2 on m1.val_id = m2.id 
               left outer join tran_comments_item_vals g1 on g1.item_id = t5.id and g1.com_id = 42
                  left outer join tran_comments_vals g2 on g1.val_id = g2.id 
            where t1.note_date between {$sql_datefrom} and {$sql_dateto}
               and note_status = 2 and note_type = 1 ";

if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.note_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.note_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $datsql .= " and t1.note_userid_seller = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t1.note_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $datsql .= " and t5.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t5.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
               
$datsql .= " order by 7, 6";
$data = $CON->select($datsql);
// echo($datsql);
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
         array_push($selshops, $shop);
}

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
{
   $sql = " select cust_name
            from customer
            where
            id = {$_SESSION[$_sesmodulename]["sql_customer"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = $custdata[0]["cust_name"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = "TODO";

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
{
   $sql = " select user_firstname, user_lastname
            from user
            where
            id = {$_SESSION[$_sesmodulename]["sql_seller"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["SELLER"] = $custdata[0]["user_firstname"]." ".$custdata[0]["user_lastname"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["SELLER"] = "TODO";


//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

printJSsetCompanyShop($shops);

//----------------------------------------------------------------------------------
function orderSellerByNameCallback($a, $b)
{
   return (strcmp($a["seller_name"], $b["seller_name"]));
}

?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Presupuesto</b></td>
   <td align="right" class="content_row_clear"></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst"
      onsubmit="return checkform(new Array(this.sql_company))">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <?=Nifty_printH("box2", "980",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="110">
         <col>
         <col width="100">
         <col width="300">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de Búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">N° CC</td>
         <td class="content_row">
            <input name="sql_stext2" type="text" class="text" style="width:100%"
            value="<?=$_SESSION[$_sesmodulename]["sql_stext2"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Período</td>
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
               <td class="content_row_clear" width="195" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
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
                     $startyear  = date('Y') -10;
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
                  -
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
                     $startyear  = date('Y') -3;
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
                  <input type="text" style="width:65px" id="sql_date_pfrom" name="sql_date_pfrom"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
                  -
                  <input type="text" style="width:65px" id="sql_date_pto" name="sql_date_pto"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pto"]?>">
                  </nobr>
               </td>
            </tr>
            </table>
         </td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Vendedor</td>
         <td class="content_row">
            <select name="sql_seller" id="sql_seller" class="text" style="width:375px">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($sellers AS $seller)
               {  ?>
                  <option value="<?=$seller["id"]?>" <?php if($seller["id"] == $_SESSION[$_sesmodulename]["sql_seller"]) echo "selected"; ?>>
                  <?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Producto</td>
         <td class="content_row"><?php printOverviewItemSelect($_sesmodulename) ?></td>
         <td class="content_rowl">Tipo CC</td>
         <td class="content_row">
            <input type="radio" name="sql_cctype" value="0" <?php if($_SESSION[$_sesmodulename]["sql_cctype"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_cctype" value="1" <?php if($_SESSION[$_sesmodulename]["sql_cctype"] == 1) echo "checked"?>> Solo fabricación
            <input type="radio" name="sql_cctype" value="2" <?php if($_SESSION[$_sesmodulename]["sql_cctype"] == 2) echo "checked"?>> Solo manual
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Familia</td>
         <td class="content_row">
            <select class="text" name="sql_pcat" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($pcats AS $pcat)
               {  ?>
                  <option value="<?=$pcat["id"]?>"
                  <?php if($pcat["id"] == $_SESSION[$_sesmodulename]["sql_pcat"]) echo "selected"?>><?=sprintf("%03s", $pcat["id"])?> - <?=$pcat["cat_title"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Tipo producto</td>
         <td class="content_row">
            <input type="radio" name="sql_xitemtype" value="0" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_xitemtype" value="1" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 1) echo "checked"?>> Solo venta online
            <input type="radio" name="sql_xitemtype" value="2" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 2) echo "checked"?>> Solo otros
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="132">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($data) > 0 && $data != false)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
               </td>
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
      <style>
         .content_row_os { font-size:11px !important; }
      </style>
      <?=Nifty_printH("box1", "99%", 0)?>
      <table border="0" cellpadding="2" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_header content_row_os">Rut</td>
         <td class="content_tbl_header content_row_os">Razon Social</td>
         <td class="content_tbl_header content_row_os">Nombre</td>
         <td class="content_tbl_header content_row_os">Rubro</td>
         <td class="content_tbl_header content_row_os">Subrubro</td>
         <td class="content_tbl_header content_row_os">Canal</td>
         <td class="content_tbl_header content_row_os">Cartera de</td>
         <td class="content_tbl_header content_row_os"><nobr>En Presupuesto</nobr></td>
         <td class="content_tbl_header content_row_os"><nobr>Documento</nobr></td>
         <td class="content_tbl_header content_row_os">Numero</td>
         <td class="content_tbl_header content_row_os">Fecha Documento</td>
         <td class="content_tbl_header content_row_os">Atendido por</td>
         <td class="content_tbl_header content_row_os">C.C.</td>
         <td class="content_tbl_header content_row_os">SKU</td>
         <td class="content_tbl_header content_row_os">Producto</td>
         <td class="content_tbl_header content_row_os"><nobr>Tipo Bolsa<nobr></td>
         <td class="content_tbl_header content_row_os">Material</td>
         <td class="content_tbl_header content_row_os">Impresión</td>
         <td class="content_tbl_header content_row_os">Color de Tela</td>
         <td class="content_tbl_header content_row_os">Gramaje</td>
         <td class="content_tbl_header content_row_os">Cantidad</td>
         <td class="content_tbl_header content_row_os">Precio</td>
         <td class="content_tbl_header content_row_os">Total Neto</td>
         <td class="content_tbl_header content_row_os"><nobr>Fecha Ultima Documento</nobr></td>
         <td class="content_tbl_header content_row_os"><nobr>Fecha Ultima Cotización</nobr></td>
         <td class="content_tbl_header content_row_os"><nobr>Cantidad de Documentos Emitidas</nobr></td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($data) && $data != false; $x++)
      {
         if($data[$x]["material"] == null )
            $material = $data[$x]["mat_prod"];
         else
            $material = $data[$x]["material"];

         if($data[$x]["gramaje"] == null || empty($data[$x]["gramaje"]) )
            $gramaje = $data[$x]["gra_prod"];
         else
            $gramaje = $data[$x]["gramaje"];

         // echo($data[$x]["SKU"]." gramaje ".$data[$x]["gramaje"].' - '.$data[$x]["gra_prod"]." material ".$data[$x]["material"].' - '.$data[$x]["mat_prod"].".");
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><nobr><?=$data[$x]["rutcliente"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$data[$x]["razonsocial"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$data[$x]["nombre"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$data[$x]["categoria"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$data[$x]["sub_cat_name"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$data[$x]["canal"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$data[$x]["cartera"]?></nobr></td>                           
            <td class="content_row_os"><nobr><?=($data[$x]["cust_presupuesto"] == 1) ? 'SI' : 'NO'?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=$data[$x]["documento"]?></nobr></td>                           
            <td class="content_row_os"><?=$data[$x]["Facturas"]?></td>
            <td class="content_row_os"><?=date('d/m/Y', $data[$x]["fechadefactura"])?></td>
            <td class="content_row_os"><nobr><?=$data[$x]["vendedor"]?></nobr></td>            
            <td class="content_row_os"><nobr><?=$data[$x]["Confirmacion"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$data[$x]["SKU"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$data[$x]["Producto"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$data[$x]["tipodebolsa"]?></nobr></td>
            <td class="content_row_os"><?=$material?>&nbsp;</td>
            <td class="content_row_os"><?=$data[$x]["Impresion"]?></td>
            <td class="content_row_os"><nobr><?=$data[$x]["colordetela"]?></nobr></td>
            <td class="content_row_os"><?=$gramaje?></td>
            <td class="content_row_os"><?=printPrice($data[$x]["cantidad"])?></td>            
            <td class="content_row_os"><?=printPrice($data[$x]["precioneto"])?></td>
            <td class="content_row_os"><?=printPrice($data[$x]["precioneto"] *$data[$x]["cantidad"])?></td>
            <td class="content_row_os"><?=date('d/m/Y', $data[$x]["fechaultimafactura"])?></td>
            <td class="content_row_os"><?=date('d/m/Y', $data[$x]["fechaultimacotizacion"])?></td>
            <td class="content_row_os"><?=$data[$x]["cantidaddefacturasemitidas"]?></td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["rutcliente"]                   = $data[$x]["rutcliente"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["razonsocial"]                  = $data[$x]["razonsocial"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["nombre"]                       = $data[$x]["nombre"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["categoria"]                    = $data[$x]["categoria"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["canal"]                        = $data[$x]["canal"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cartera"]                      = $data[$x]["cartera"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_presupuesto"]             = ($clients[$x]["cust_presupuesto"] == 1) ? 'SI' : 'NO';
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Facturas"]                     = $data[$x]["Facturas"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fechadefactura"]               = $data[$x]["fechadefactura"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["vendedor"]                     = $data[$x]["vendedor"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Confirmacion"]                 = $data[$x]["Confirmacion"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["SKU"]                          = $data[$x]["SKU"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Producto"]                     = $data[$x]["Producto"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["tipodebolsa"]                  = $data[$x]["tipodebolsa"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["material"]                     = $material;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Impresion"]                    = $data[$x]["Impresion"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["colordetela"]                  = $data[$x]["colordetela"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["gramaje"]                      = $gramaje;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["precioneto"]                   = printPrice($data[$x]["precioneto"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cantidad"]                     = printPrice($data[$x]["cantidad"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["totalneto"]                    = printPrice($data[$x]["precioneto"] * $data[$x]["cantidad"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fechaultimafactura"]           = $data[$x]["fechaultimafactura"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fechaultimacotizacion"]        = $data[$x]["fechaultimacotizacion"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cantidaddefacturasemitidas"]   = $data[$x]["cantidaddefacturasemitidas"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["documento"]                    = $data[$x]["documento"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["sub_cat_name"]                 = $data[$x]["sub_cat_name"];
         
         //----------------------------------------------------------------------------------------------------------------------------

      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="15" align="center">
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
      <br>
   </td>
</tr>
</table>
<?php
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
//----------------------------------------------------------------------------------
if($_REQUEST["printxls"])
  $xlsfile = xls_createPresupuestos($CON);

if($xlsfile != "")
{
   $doctitle = "Presupuesto-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>