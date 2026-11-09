<?php
declare(strict_types=1);
// Tablas mínimas del ERP en SQLite para las pruebas de stock y kardex (no es una prueba: run_all solo corre 0*.php).

function qaErpSqlite(): PDO
{
    $erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $erp->exec("CREATE TABLE item (id INTEGER PRIMARY KEY, item_title TEXT, item_number_prod TEXT DEFAULT '');
CREATE TABLE orders (id INTEGER PRIMARY KEY, req_number TEXT, req_company_id INT, req_shop_id INT);
CREATE TABLE orders_items (id INTEGER PRIMARY KEY, req_id INT, item_id INT, item_pos INT DEFAULT 0);
CREATE TABLE company_data (id INTEGER PRIMARY KEY, company_numformat_stockchange TEXT, company_numcounter_stockchange INT, company_numformat_storehouse TEXT, company_numcounter_storehouse INT);
CREATE TABLE company_shops_storehouses (id INTEGER PRIMARY KEY, st_name TEXT, st_status INT DEFAULT 1, st_shop_id INT DEFAULT 30010);
CREATE TABLE stockchanges (id INTEGER PRIMARY KEY AUTOINCREMENT, stk_num TEXT, stk_annotation TEXT, stk_issueid INT, stk_companyid INT, stk_shopid INT, stk_bookdate INT, stk_negative INT, stk_status INT, stk_crtdat INT, stk_crtusr INT, stk_upddat INT, stk_updusr INT, stk_fixedsthid INT, sth_fromprodotid INT DEFAULT 0, sth_fromrebo_agid INT DEFAULT 0, sth_order_id INT DEFAULT 0, sth_order_num TEXT, stk_cinumber TEXT, stk_vintnumber TEXT, sth_fromrebo_agid_grphash TEXT, sth_assign_equipoid INT DEFAULT 0, sth_assign_wrkid INT DEFAULT 0, sth_planta_id INT DEFAULT 0);
CREATE TABLE stockchanges_items (stk_id INT, item_id INT, item_pos INT, item_amount REAL, item_type TEXT, item_st_id INT, item_reboprod_amt REAL DEFAULT 0, item_reboprod_unitkg REAL DEFAULT 0);
CREATE TABLE item_shops_storehouses (item_id INT, shop_id INT, st_id INT, iss_order_amount REAL, iss_inventory_min REAL, iss_inventory REAL, iss_inventory_reserved REAL, iss_order_id INT);
CREATE TABLE storehousechanges (id INTEGER PRIMARY KEY AUTOINCREMENT, strc_company_id INT, strc_shop_id INT, strc_company_dest_id INT, strc_shop_dest_id INT, strc_status INT, strc_date INT, strc_desc TEXT, strc_number TEXT, strc_crtusr INT, strc_crtdat INT, strc_order_id INT, strc_order_id_dest INT, strc_order_num TEXT, strc_order_num_dest TEXT, strc_shopsent INT, strc_shopreceived INT, strc_custid INT, strc_rel_reqnum TEXT);
CREATE TABLE storehousechanges_items (strc_id INT, item_id INT, item_pos INT, item_amount REAL, item_type TEXT, item_st_id INT, item_st_dest_id INT);
CREATE TABLE tran_data (tran_id INT, tran_pos INT, tran_type TEXT, tran_number TEXT, tran_currstock REAL, tran_amount REAL, tran_company_id INT, tran_shop_id INT, tran_st_id INT, tran_issueid INT, item_id INT, item_title TEXT, item_type TEXT, tran_day INT, tran_month INT, tran_year INT, tran_crtusr INT, tran_crtdat INT, tran_order_id INT);
INSERT INTO company_data VALUES (20010,'IA',100,'TR',500);
INSERT INTO company_shops_storehouses (id,st_name) VALUES (8,'500 BODEGA'),(10,'700 BODEGA'),(14,'1000 RETAIL'),(16,'3000 PRODUCCION'),(1,'400 BODEGA');");
    return $erp;
}
