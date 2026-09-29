<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $_REQUEST["cust_company"]          = trim(addslashes($_REQUEST["cust_company"]));
   $_REQUEST["cust_name"]             = trim(addslashes($_REQUEST["cust_name"]));
   $_REQUEST["req_id_cc"]             = trim(addslashes($_REQUEST["req_id_cc"]));
   $_REQUEST["cust_street"]           = trim(addslashes($_REQUEST["cust_street"]));
   $_REQUEST["cust_phone"]            = trim(addslashes($_REQUEST["cust_phone"]));
   $_REQUEST["cust_cellphone"]        = trim(addslashes($_REQUEST["cust_cellphone"]));
   $_REQUEST["cust_fax"]              = trim(addslashes($_REQUEST["cust_fax"]));
   $_REQUEST["cust_email"]            = trim(addslashes($_REQUEST["cust_email"]));
   $_REQUEST["cust_website"]          = trim(addslashes($_REQUEST["cust_website"]));
   $_REQUEST["cust_type"]             = trim(addslashes($_REQUEST["cust_type"]));
   $_REQUEST["country"]               = (int)$_REQUEST["country"];
   $_REQUEST["regions"]               = (int)$_REQUEST["regions"];
   $_REQUEST["comunas"]               = (int)$_REQUEST["comunas"];
   $_REQUEST["provincias"]            = (int)$_REQUEST["provincias"];
   $_REQUEST["cust_sellerid"]         = (int)$_REQUEST["cust_sellerid"];
   $_REQUEST["cust_giroid"]           = (int)$_REQUEST["cust_giroid"];
   $_REQUEST["cust_paymentid"]        = (int)$_REQUEST["cust_paymentid"];
   $_REQUEST["cust_transportid"]      = (int)$_REQUEST["cust_transportid"];
   $_REQUEST["cust_plid"]             = (int)$_REQUEST["cust_plid"];
   $_REQUEST["cust_plfabid"]          = (int)$_REQUEST["cust_plfabid"];
   $_REQUEST["cust_convenio_act"]     = (int)$_REQUEST["cust_convenio_act"];
   $_REQUEST["cust_discount_spec"]    = (int)$_REQUEST["cust_discount_spec"];
   $_REQUEST["cust_delivery_price"]   = getPrice($_REQUEST["cust_delivery_price"]);
   $_REQUEST["cust_pricetolerance"]   = getPrice($_REQUEST["cust_pricetolerance"]);
   $_REQUEST["cust_catid"]            = (int)$_REQUEST["cust_catid"];
   $_REQUEST["cust_encuesta_disable"] = (int)$_REQUEST["cust_encuesta_disable"];
   $_REQUEST["cansegact"]             = (int)$_REQUEST["cansegact"];
   $_REQUEST["cust_seg_act"]          = (int)$_REQUEST["cust_seg_act"];
   $_REQUEST["cust_seg_days"]         = (int)trim($_REQUEST["cust_seg_days"]);
   $_REQUEST["cust_seg_date"]         = trim(addslashes($_REQUEST["cust_seg_date"]));
   $_REQUEST["cust_seg_date"]         = explode(".", $_REQUEST["cust_seg_date"]);
   $_REQUEST["cust_seg_date"]         = (int)mktime(3, 0, 0, $_REQUEST["cust_seg_date"][1], $_REQUEST["cust_seg_date"][0], $_REQUEST["cust_seg_date"][2]);
   $_REQUEST["cust_contacto"]         = trim(addslashes($_REQUEST["cust_contacto1"]." ".$_REQUEST["cust_contacto2"]));
   $_REQUEST["cust_montocredito"]     = (int)$_REQUEST["cust_montocredito"];
   $_REQUEST["cust_canal"]            = trim(addslashes($_REQUEST["cust_canal"]));
   $_REQUEST["cust_holding"]          = trim(addslashes($_REQUEST["cust_holding"]));
   $_REQUEST["cust_presupuesto"]      = (int)$_REQUEST["cust_presupuesto"];
   $_REQUEST["cust_permanente"]       = (int)$_REQUEST["cust_permanente"];
   $_REQUEST["cust_subrubro"]         = (int)$_REQUEST["cust_subrubro"];
   
   $sql = "select *
         from payments
         where
         pay_status > 0 and id = {$_REQUEST["cust_paymentid"]}";
         
   $payments = $CON->select($sql);
   $payments = $payments[0];
   if ( !(int)$payments["pay_monto_credito"] )
   {
      $_REQUEST["cust_montocredito"] = 0;
      $_REQUEST["cust_pricetolerance"] = 0;
   }

   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $sql_rut = str_replace(".", "", $_REQUEST["cust_rut"]);
      $sql = " select count(*) 'cc'
               from customer
               where
               cust_status > 0 and
               REPLACE(cust_rut,'.','') = '{$sql_rut}'";
      $check = $CON->select($sql);
      $check = (int)$check[0]["cc"];

      if(!(int)$check)
      {
         $sql = " insert into customer
                  (cust_company, cust_street, cust_phone, cust_fax, cust_email,
                  cust_website, cust_cellphone, cust_rut, cust_name,
                  cust_countryid, cust_regionid, cust_comunaid,
                  cust_sellerid, cust_giroid, cust_paymentid, cust_discount_spec,
                  cust_delivery_price, cust_transportid, cust_plid, cust_pricetolerance,
                  cust_crtusr, cust_crtdat, cust_provinciaid, cust_convenio_act, cust_type,
                  cust_plfabid, cust_catid, cust_encuesta_disable, cust_montocredito, cust_canal,cust_contacto,cust_presupuesto, cust_holding, cust_permanente,cust_subrubro)
                  VALUES
                  ('{$_REQUEST["cust_company"]}', 
                  '{$_REQUEST["cust_street"]}',
                  '{$_REQUEST["cust_phone"]}', 
                  '{$_REQUEST["cust_fax"]}',
                  '{$_REQUEST["cust_email"]}', 
                  '{$_REQUEST["cust_website"]}',
                  '{$_REQUEST["cust_cellphone"]}', 
                  '{$_REQUEST["cust_rut"]}', 
                  '{$_REQUEST["cust_name"]}',
                  {$_REQUEST["country"]}, 
                  {$_REQUEST["regions"]}, 
                  {$_REQUEST["comunas"]},
                  {$_REQUEST["cust_sellerid"]}, 
                  {$_REQUEST["cust_giroid"]},
                  {$_REQUEST["cust_paymentid"]},
                  {$_REQUEST["cust_discount_spec"]}, 
                  {$_REQUEST["cust_delivery_price"]},
                  {$_REQUEST["cust_transportid"]}, 
                  {$_REQUEST["cust_plid"]}, 
                  {$_REQUEST["cust_pricetolerance"]},
                  {$_SESSION["user_id"]}, {$currtme}, 
                  {$_REQUEST["provincias"]}, 
                  {$_REQUEST["cust_convenio_act"]},
                  '{$_REQUEST["cust_type"]}', 
                  {$_REQUEST["cust_plfabid"]}, 
                  {$_REQUEST["cust_catid"]},
                  {$_REQUEST["cust_encuesta_disable"]},
                  {$_REQUEST["cust_montocredito"]},
                  '{$_REQUEST["cust_canal"]}',
                  '{$_REQUEST["cust_contacto"]}',
                  {$_REQUEST["cust_presupuesto"]},
                  '{$_REQUEST["cust_holding"]}',
                  {$_REQUEST["cust_permanente"]},
                  {$_REQUEST["cust_subrubro"]}
                  )";
         $res = $CON->no_result($sql);
         /*
         $sqllastclient ="select id from customer order by id desc limit 1";
         $idlastclient = $CON->select($sqllastclient);
         $idlastclient = $idlastclient[0]["id"];
         */
         $idlastclient = mysql_insert_id();

         if (!empty($idlastclient)) 
         {
            $nombres   = trim(addslashes($_REQUEST["cust_contacto1"]));
            $apellidos = trim(addslashes($_REQUEST["cust_contacto2"]));
            $sql = "insert into customer_contacts
                        (add_cust_id, add_firstname, add_lastname, add_email, add_cellphone, add_phone,  add_tipo_cust) 
                     VALUES
                        ({$idlastclient}, '{$nombres}', '{$apellidos}', '{$_REQUEST["cust_email"]}', '{$_REQUEST["cust_cellphone"]}', '{$_REQUEST["cust_phone"]}', '2')";
            $res = $CON->no_result($sql);
            $cust_id_contact = mysql_insert_id();
            $sql = "update customer set cust_id_contact = {$cust_id_contact} where id = {$idlastclient}";
            $CON->no_result($sql);
         }
         if($res)
         {
            ?>
            <script language="JavaScript">
               location.href = 'index.php?mid=628&exec=edit&id=<?=$idlastclient?>';
            </script>
            <?php
         }
      }
      else
      {  
         $savemsg = getSaveMessage($res);
         /*
         ?>
         <script language="JavaScript">
            alert('EL RUT YA EXISTE PARA OTRO CLIENTE.\nEL DATO NO SE GUARDO!!!');
            location.href='/index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit';
         </script>
         <?php
         */
         exit;
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $id_contacto = (int)$_REQUEST["cust_id_contact"];
      $sql = "select * from customer_contacts where id = {$id_contacto}";
      $contacto  = $CON->select($sql);
      $contacto  = $contacto[0]["add_firstname"]." ".$contacto[0]["add_lastname"];

      $sql = " update customer
               set
               cust_company          = '{$_REQUEST["cust_company"]}',
               cust_name             = '{$_REQUEST["cust_name"]}',
               cust_street           = '{$_REQUEST["cust_street"]}',
               cust_phone            = '{$_REQUEST["cust_phone"]}',
               cust_cellphone        = '{$_REQUEST["cust_cellphone"]}',
               cust_fax              = '{$_REQUEST["cust_fax"]}',
               cust_email            = '{$_REQUEST["cust_email"]}',
               cust_website          = '{$_REQUEST["cust_website"]}',
               cust_rut              = '{$_REQUEST["cust_rut"]}',
               cust_countryid        =  {$_REQUEST["country"]},
               cust_regionid         =  {$_REQUEST["regions"]},
               cust_provinciaid      =  {$_REQUEST["provincias"]},
               cust_comunaid         =  {$_REQUEST["comunas"]},
               cust_sellerid         =  {$_REQUEST["cust_sellerid"]},
               cust_giroid           =  {$_REQUEST["cust_giroid"]},
               cust_paymentid        =  {$_REQUEST["cust_paymentid"]},
               cust_discount_spec    = {$_REQUEST["cust_discount_spec"]},
               cust_delivery_price   = {$_REQUEST["cust_delivery_price"]},
               cust_transportid      = {$_REQUEST["cust_transportid"]},
               cust_pricetolerance   = {$_REQUEST["cust_pricetolerance"]},
               cust_convenio_act     = {$_REQUEST["cust_convenio_act"]},
               cust_type             = '{$_REQUEST["cust_type"]}',
               cust_plid             = {$_REQUEST["cust_plid"]},
               cust_plfabid          = {$_REQUEST["cust_plfabid"]},
               cust_encuesta_disable = {$_REQUEST["cust_encuesta_disable"]},
               cust_catid            = {$_REQUEST["cust_catid"]},
               cust_updusr           = {$_SESSION["user_id"]},
               cust_montocredito     = {$_REQUEST["cust_montocredito"]},
               cust_canal            = '{$_REQUEST["cust_canal"]}',
               cust_upddat           = {$currtme},
               cust_id_crm           = '{$_REQUEST["cust_id_crm"]}',
               cust_contacto         = '{$contacto }',
               cust_id_contact       = {$id_contacto},
               cust_presupuesto      = {$_REQUEST["cust_presupuesto"]},
               cust_holding          = '{$_REQUEST["cust_holding"]}',
               cust_permanente       = {$_REQUEST["cust_permanente"]},
               cust_subrubro         = {$_REQUEST["cust_subrubro"]}
               where
               id = {$_REQUEST["id"]}";
      
      $res = $CON->no_result($sql);

      $sql = "update customer_contacts set add_cellphone = '{$_REQUEST["cust_cellphone"]}',
                                           add_phone = '{$_REQUEST["cust_phone"]}',
                                           add_email = '{$_REQUEST["cust_email"]}'
               where id = {$id_contacto} ";
      $res = $CON->no_result($sql);

      if($_REQUEST["cansegact"] && $_REQUEST["cust_seg_act"] && $_REQUEST["cust_seg_days"])
      {
         $cust_seg_alertdate = $_REQUEST["cust_seg_date"] + ($_REQUEST["cust_seg_days"] * 86400);
         $sql = " update customer
                  set
                  cust_seg_act         = 1,
                  cust_seg_days        = {$_REQUEST["cust_seg_days"]},
                  cust_seg_date        = {$_REQUEST["cust_seg_date"]},
                  cust_seg_alertdate   = {$cust_seg_alertdate}
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
      
      /* Actualiza informacion en API */

      /* Actualiza informacion en API */

      $sql = "select * from customer where id = {$_REQUEST["id"]}";
      $idcliente  = $CON->select($sql);
      $idcliente  = $idcliente[0];

      $sql        = "select nombre from comunas where id = {$idcliente['cust_comunaid']}";
      $comunas    = $CON->select($sql);
      $comuna     = $comunas[0]['nombre'];

      $sql        = "select * from regions where id = {$idcliente['cust_regionid']}";
      $regiones   = $CON->select($sql);
      $region     = $regiones[0]['name'];

      $sql        = "select * from country where id = {$idcliente['cust_countryid']}";
      $paises     = $CON->select($sql);
      $pais       = $paises[0]['country_name'];

      $sql         = "select * from parametros where tabla = 'CANAL' and codigo = {$_REQUEST["cust_canal"]}";
      $canal       = $CON->select($sql);
      $canal       = $canal[0];
      $canal       = $canal["descripcion"];

      $sql        = "select * from giros where id = {$idcliente['cust_giroid']}";
      $giros      = $CON->select($sql);
      $giro       = $giros[0]['giro_name'];

      $monto      = 0;
      $nombre     = $idcliente['cust_contacto'];
      $rut        = $idcliente['cust_rut'];
      $direccion  = $idcliente['cust_street'];
      $empresa    = $idcliente['cust_company'];
      $email      = $idcliente['cust_email'];
      $celular    = $idcliente['cust_cellphone'];
     
      $sql        = "SELECT cat_name from customer_cats where id = {$idcliente['cust_catid']}";
      $rubro      = $CON->select($sql);
      $rubro      = $rubro[0]["cat_name"];
   
      $sql = "select * from customer_sub_cats where id = {$idcliente['cust_subrubro']}";
      $subrubro = $CON->select($sql);
      $subrubro  = $subrubro[0]['sub_cat_name'];
      
      $sql       = "select * from customer_contacts where add_cust_id = {$_REQUEST["id"]} and add_email = '{$email}'";
      $fila      = $CON->select($sql);
      $fila      = $fila[0];
      
      if((int)$idcliente['cust_seg_act'])
          $fechaseg   = $idcliente['cust_seg_alertdate'];
      else         
          $fechaseg   = time();
      $iderp      = $idcliente['id'];

      $idcrm2     = $fila['add_id_crm'];
      $nombre     = $fila['add_firstname'].' '.$fila['add_lastname'];
      $email      = $fila['add_email'];
      $celular    = $fila['add_cellphone'];
      $vendedor1  = $fila['add_seller'];

      if((int)$vendedor1)
         $sql         = "select * from parametros where tabla = 'CRM_SELLER' and codigo = {$vendedor1}";
      else      
         $sql         = "select * from parametros where tabla = 'CRM_SELLER' and codigo = {$_REQUEST["cust_sellerid"]}";
      
      $vendedor    = $CON->select($sql);
      $vendedor    = $vendedor[0];
      $vendedor    = $vendedor["descripcion"];

      // echo("sql : ".$sql." ".$vendedor);

      // echo($idcrm2.' - '.$monto.' - '.$nombre.' - '.$rut.' - '.$direccion.' - '.$empresa.' - '.$email.' - '.$celular.' - '.$comuna.' - '.$region.' - '.$pais.' - '.$giro.' - '.$vendedor.' - '.$fechaseg.' - '.$canal.' - '.$rubro.' - '.$subrubro."<br>");
      CallApiCRM('contacto', $idcrm2, $monto,$nombre,$rut,$direccion,$empresa,$email,$celular,$comuna,$region,$pais,$giro,$vendedor,$fechaseg,$iderp,$canal,$rubro,$subrubro);

      /* fin api */


      if((int)$_REQUEST["disablesegact"])
      {
         $sql = " update customer
                  set
                  cust_seg_act         = 0,
                  cust_seg_days        = 0,
                  cust_seg_date        = 0,
                  cust_seg_alertdate   = 0
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
   }


   DeFontanaCliente($CON, $_REQUEST["id"]);

   $savemsg = getSaveMessage($res);
}
if($_REQUEST["id"] != "")
{
   $title = $_LANG["MODULE"]["CUST"][0];
   
   $sql = " select t1.*, 
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from customer t1
            LEFT OUTER JOIN user t2 ON t1.cust_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.cust_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $customer = $CON->select($sql);
}
else
{
   $title = $_LANG["MODULE"]["CUST"][1];
   $customer[0]["cust_countryid"] = 81;
}

if($_REQUEST["cpFromOffer"] != "")
{
   $sql = " select *
            from offers
            where
            id = {$_REQUEST["cpFromOffer"]}";
   $offer = $CON->select($sql);
   $offer = $offer[0];
   
   $customer[0]["cust_countryid"]   = $offer["req_cust_countryid"];
   $customer[0]["cust_regionid"]    = $offer["req_cust_regionid"];
   $customer[0]["cust_comunaid"]    = $offer["req_cust_comunaid"];
   $customer[0]["cust_provinciaid"] = $offer["req_cust_provinciaid"];
   $customer[0]["cust_rut"]         = $offer["req_cust_rut"];
   $customer[0]["cust_street"]      = $offer["req_cust_street"];
   $customer[0]["cust_company"]     = $offer["req_cust_company"];
   $customer[0]["cust_name"]        = $offer["req_cust_company"];
   $customer[0]["cust_phone"]       = $offer["req_cust_phone"];
   $customer[0]["cust_fax"]         = $offer["req_cust_fax"];
   $customer[0]["cust_email"]       = $offer["req_cust_email"];
   $customer[0]["cust_paymentid"]   = $offer["req_paymentid"];
}

//----------------------------------------------------------------------------------
$countries  = getCountries($CON);
$regions    = getRegions($CON);
$comunas    = getComunas($CON);
$provincias = getProvincias($CON);
$sellers    = getSellers($CON);
$giros      = getGiros($CON);

//----------------------------------------------------------------------------------
$sql = " select *
         from payments
         where
         pay_status > 0 
         order by pay_title";
$payments = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from transports
         where
         trans_status = 1
         order by trans_name asc";
$transports = $CON->select($sql);
//----------------------------------------------------------------------------------

$sql = " select t1.id, t1.pl_title
         from price_lists t1
         where
         t1.pl_status = 1
         order by t1.pl_title";
$pricelists = $CON->select($sql);


$tiene_crm = 'No';
if ($customer[0]["cust_id_crm"] != "")
   $tiene_crm = 'Si';

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
   $title = $_LANG["MODULE"]["CUST"][0];
else
   $title = $_LANG["MODULE"]["CUST"][1];

$_BLOCKDELETE = false;
if($_REQUEST["id"] != "")
{
   $sql = " select count(id) 'cc'
            from invoices_sell
            where
            invc_cust_id = {$_REQUEST["id"]} and
            invc_status > 0";
   $invcchk = $CON->select($sql);
   $sql = " select count(id) 'cc'
            from  invoices_notes_sell
            where
            note_cust_id = {$_REQUEST["id"]} and
            note_status > 0";
   $notechk = $CON->select($sql);
   $sql = " select count(id) 'cc'
            from  orders_delivery
            where
            dlv_cust_id = {$_REQUEST["id"]} and
            dlv_status > 0";
   $dlvchk = $CON->select($sql);
   if((int)$invcchk[0]["cc"] || (int)$notechk[0]["cc"] || (int)$dlvchk[0]["cc"])
      $_BLOCKDELETE = true;
}

$sql = " select id, pl_title
         from price_lists_fab
         where
         pl_status = 1 and
         pl_isdefault = 0
         order by pl_title";
$fabplists = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t1.id, t1.cat_name, t1.cat_crtdat
         from customer_cats t1
         where
         t1.cat_status > 0
         order by t1.cat_name";
$custcats = $CON->select($sql);
//---------------------------------------------------------------------------------
$sql = "SELECT id, sub_cat_name, sub_id_cat FROM customer_sub_cats where sub_cat_status > 0";
$subcats = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = "select * from parametros where tabla = 'CANAL' ";
$canales = $CON->select($sql);
//---------------------------------------------------------------------------------
$sql         = "select * from parametros where tabla = 'HOLDING' ";
$holdings    = $CON->select($sql);

//----------------------------------------------------------------------------------
function DeFontanaCliente($CON, $idCliente)
{
   $sql = "select * from customer where id = {$idCliente}";
   $cliente = $CON->select($sql);
   $cliente = $cliente[0];

   $rut = str_replace('.', '', $_REQUEST["cust_rut"]);
   $rut = formatearRut($rut);
   
   $token = ObtieneToken($CON, $_SESSION["user_company_id"]);
   if(!$token["success"])
   {
      $sql = "insert into log_erp(proceso, estatus, detalle)
              values('token',0,'no se pudo obtener token, en ingreso de cliente {$idCliente}')";
      $CON->no_result($sql);
   }
   else
   {

      $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = 'GetClients'";
      $url = $CON->select($sql);
      $url = $url[0]['url'];

      $parametros = array(
         'legalCode'     => $rut,
         'status'        => 1,
         'itemsPerPage'  => 10,
         'pageNumber'    => 1,
      );

      $url_completa = $url . '?' . http_build_query($parametros);
      $ch = curl_init($url_completa);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json',                                  
                                                  'Authorization: '.$token["token_type"].' '.$token["access_token"]  
                                                ));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
      $respuesta = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if (curl_errno($ch)) 
      {
         $sql = "insert into log_erp(proceso, estatus, detalle)
                values('clientes',0,'problema en GetClients {$idCliente}')";
        $CON->no_result($sql);
         return null;
      }
      $datos = json_decode($respuesta, true); 
      curl_close($ch);
?>
<br>
<?php


      if($datos["totalItems"]>0)
         $accion = "UpdateClient";
      else   
         $accion = "SaveClient";

      $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = '{$accion}'";
      $url = $CON->select($sql);
      $url = $url[0]['url'];
      $ch = curl_init($url);

      /*  $CON->no_result("insert into mensajes(texto) values('{$url}')");*/

      $rut      = str_replace('.', '', $_REQUEST["cust_rut"]);
      $rut      = formatearRut($rut);


      $razon    = $_REQUEST["cust_company"];
      $nombre   = $_REQUEST["cust_company"];
 
      $calle    = $_REQUEST["cust_street"];
      $calle    = substr($calle, 0, 200);

      $business = "";
      $email    = $_REQUEST["cust_email"];
      $phone    = $_REQUEST["cust_cellphone"];

      $comuna   = $CON->select("select nombre from comunas where id = {$_REQUEST["comunas"]}");
      $comuna   = $comuna[0]["nombre"];

      $country   = $CON->select("select country_name from country where id = {$_REQUEST["country"]}");
      $country   = $country[0]["country_name"];

      $rubroId  = $CON->select("select cat_name from customer_cats where id = {$_REQUEST["cust_catid"]}");
      $rubroId  = $rubroId[0]["cat_name"];
 
      $giro     = $CON->select("select * from giros where id = {$_REQUEST["cust_giroid"]}");
      $giro     = $giro[0]["giro_name"];

      $city     = $CON->select("select * from provincias where id = {$_REQUEST["provincias"]}");
      $city     = $city[0]["pro_name"];

      $razon    = LimpiaData($razon);
      $razon    = substr($razon, 0, 100);

      $nombre   = LimpiaData($nombre);
      $nombre = substr($nombre, 0, 50);

      $city     = LimpiaData($city);
      $comuna   = LimpiaData($comuna);
      $country  = LimpiaData($country);
      $rubroId  = LimpiaData($rubroId);
      $giro     = LimpiaData($giro);
      $calle    = LimpiaData($calle);

      $rubroId = "";

      if($accion=="SaveClient")
      {
         $data = array('legalCode'  => "$rut",
                     'fileid'       => "$rut",
                     'name'         => "$razon",
                     'address'      => "$calle",
                     'district'     => "$comuna",
                     'email'        => "$email",
                     'business'     => "$business",
                     'rubroId'      => "$rubroId",
                     'giro'         => "$giro",
                     'city'         => "$city",
                     'customFields' => []
                     );
      }
      else
      {
         $data = array('legalCode'  => "$rut",
                     'fileid'       => "$rut",
                     'name'         => "$razon",
                     'address'      => "$calle",
                     'district'     => "$comuna",
                     'email'        => "$email",
                     'business'     => "$business",
                     'rubroId'      => "$rubroId",
                     'giro'         => "$giro",
                     'city'         => "$city",
                     'customFields' => [],
                     'fantasyname'  => "$nombre",
                     'maxDiscount'  => 0,
                     'phone'        => "$phone",
                     'country'      => "$country");
      }
  
      $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

      curl_setopt($ch, CURLOPT_POST, 1); // Realiza una solicitud POST 
      curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // Datos JSON
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Devuelve la respuesta como una cadena 
      curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json; charset=UTF-8',                                  // Encabezado para JSON 
                                                  'Authorization: '.$token["token_type"].' '.$token["access_token"]  // Encabezado de autorización si es necesario 
                                                ));
      $respuestaJson = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $respuestaArray = json_decode($respuestaJson, true);
      $success = $respuestaArray['success'];
      $message = $respuestaArray['message'];
    
      if( $http_code != 200)
      {
          $sql = "insert into log_erp(proceso, estatus, detalle)
                     values('{$accion}',0,'Problema en Actualizacion de Cliente {$idCliente} - Error HTTP: {$http_code} : {$message}')";
          $CON->no_result($sql);
      }
      else
      {
         if(curl_errno($ch))
         {
            $_RET["message"]  = curl_error($ch);;
            $sql = "insert into log_erp(proceso, estatus, detalle)
                       values('{$accion}',0,'Problema en Actualizacion de Cliente {$idCliente} ({$rut}) - Error API 1: {$_RET["message"]}')";
            $CON->no_result($sql);
         }
            else
            {
               try
               {
                  if ($success)
                  {
                     $sql = "insert into log_erp(proceso, estatus, detalle)
                     values('{$accion}',1,'Se Actualiza correctamente Cliente {$idCliente} : {$rut}')";
                     $CON->no_result($sql);
                  }
                  else
                  {
                     $sql = "insert into log_erp(proceso, estatus, detalle)
                                values('{$accion}','0','Problema en Actualizacion de Cliente {$idCliente} -  ({$rut}) Error API 2: {$message}')";
                     $CON->no_result($sql);
                  }
               }
               catch (Exception $e) {
                  $sql = "insert into log_erp(proceso, estatus, detalle)
                            values('{$accion}',0,'Problema en Actualizacion de Cliente {$idCliente} - Error API 3: {$message}')";
                  $CON->no_result($sql);
               }
            }
      }

   }
}



//----------------------------------------------------------------------------------
// print formular
//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function ocultarMostrarCelda(obj) 
   {
      var select = document.getElementById('cust_paymentid');
      var selectedOption = select.options[select.selectedIndex];
      var infoAdicional = selectedOption.getAttribute("data-info");
      if("1" == infoAdicional)
      {
         document.getElementById('credito').style.display = "";
      }
      else
      {
         document.getElementById('credito').style.display = "none";
      }
   }

   window.addEventListener('DOMContentLoaded', function () {
      ocultarMostrarCelda();
   });

   function custformcheck(obj)
   {
      if(document.getElementById('country').value == '81')
      {
         var rutchk = Rut(obj.cust_rut, obj.cust_rut.value);
         if(!rutchk)
            return false;
         
         var frmchk = checkform(new Array(obj.cust_rut, obj.cust_name, obj.cust_company, obj.cust_cellphone ));
         /* ,obj.cust_company,obj.cust_street,obj.comunas,obj.provincias,obj.regions,obj.cust_catid,obj.cust_giroid,obj.cust_sellerid,obj.cust_contacto, obj.cust_email, obj.cust_phone, obj.cust_cellphone));*/
      }
      else
         var frmchk = checkform(new Array(obj.cust_rut, obj.cust_name, obj.cust_company, obj.cust_cellphone ));
         /* var frmchk = checkform(new Array(obj.cust_rut, obj.cust_name,obj.cust_company,obj.cust_street,obj.cust_catid,obj.cust_giroid,obj.cust_sellerid,obj.cust_contacto, obj.cust_email, obj.cust_phone, obj.cust_cellphone)); */

      if(!frmchk)
         return false;
      else
      {
         if(obj.cust_email.value != '')
         {
            if(!avzCheckEmail(obj.cust_email.value))
            {
               alert('Email no valido.');
               return false;
            }
         }
      }

      obj.cust_phone.value       = obj.cust_phone.value.replace(/\D/g,'');
      obj.cust_cellphone.value   = obj.cust_cellphone.value.replace(/\D/g,'');

      if((obj.cust_phone.value != '' && obj.cust_phone.value.length < 9) ||
         (obj.cust_cellphone.value != '' && obj.cust_cellphone.value.length < 9))
      {
         alert('Telefono/Celular no valido.');
         return false;
      }

      return true;
   }

   function actualizarDatosContacto()
   {
      // Obtener el select y la opción seleccionada
      var select = document.getElementById("cust_id_contact");
      var optionSeleccionada = select.options[select.selectedIndex];

      // Obtener el correo desde el atributo data-mail
      var correo = optionSeleccionada.getAttribute("data-mail");
      var telefono = optionSeleccionada.getAttribute("data-phono");
      var celular = optionSeleccionada.getAttribute("data-cellphono");
      var idcrm = optionSeleccionada.getAttribute("data-idcrm");
      var idcontacto = optionSeleccionada.getAttribute("data-idcc");
      
      // Asignar el correo al campo de email
      document.getElementsByName("cust_email")[0].value = correo || "";
      document.getElementsByName("cust_phone")[0].value = telefono || "";
      document.getElementsByName("cust_cellphone")[0].value = celular || "";
      document.getElementsByName("cust_id_crm")[0].value = idcrm || "";
      document.getElementsByName("req_id_cc")[0].value = idcontacto || "";
      // document.getElementsByName("req_id_cc")[0].value = idcontacto || "";
   }

   function checkPw()
   {
      var cust_pass1 = document.getElementById('cust_pass1').value;
      var cust_pass2 = document.getElementById('cust_pass2').value;

      if(cust_pass1 != cust_pass2 && cust_pass1 != '' && cust_pass2 != '')
         alert("Contraseï¿½as no son iguales!");
   }
   <?php
   generateCountryJS($countries, $regions, $comunas, $provincias);
   ?>
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_cust" onsubmit="return custformcheck(this)">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="fromreq" value="<?=$_REQUEST["fromreq"]?>">
<input type="hidden" name="frommid" value="<?=$_REQUEST["frommid"]?>">
<input type="hidden" name="formadepago" value="<?=$_REQUEST["frommid"]?>">
<table cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed">
<colgroup>
   <col width="500" valign="top">
   <col width="15">
   <col valign="top">
</colgroup>
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "100%", 0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="120">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["CUST"][2]?> I</td>
      </tr>
      <tr>
         <td class="content_rowl" height="32"><?=$_LANG["MODULE"]["CUST"][19]?></td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td class="content_row_clear" width="130">
                  <input name="cust_rut" id="cust_rut" type="text" class="text" style="width:120px" value="<?=$customer[0]["cust_rut"]?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1);validateRUTExists(this, this.value, '<?=(int)$_REQUEST["id"]?>', 'cust_rut')">
               </td>
               <td class="content_row_clear" align="left" id="idx_rutchk"></td>
            </tr>
            </table>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Razon Social *</td>
         <td class="content_row">
            <input name="cust_company" type="text" class="text" style="width:360px" value="<?=$customer[0]["cust_company"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>      
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][3]?></td>
         <td class="content_row">
            <input name="cust_name" type="text" class="text" style="width:360px" value="<?=$customer[0]["cust_name"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="32"><?=$_LANG["MODULE"]["CUST"][6]?></td>
         <td class="content_row">
            <input name="cust_street" type="text" class="text" style="width:360px" value="<?=$customer[0]["cust_street"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="32">Comuna *</td>
         <td class="content_row">
            <select class="text" style="width:360px" name="comunas" id="comunas" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="jqUnibagSetComuna(this.value, 'customer')">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               $allcomunas = getAllComunas($CON);
               foreach($allcomunas AS $comuna)
               {  ?>
                  <option value="<?=$comuna["id"]?>"
                  <?php if($comuna["id"] == $customer[0]["cust_comunaid"]) echo "selected"?>><?=$comuna["nombre"]?></option>
                  <?php
               }
               /*
               if((int)$customer[0]["cust_provinciaid"])
               {
                  foreach($comunas as $comuna)
                  {
                     if($comuna["prov_id"] == $customer[0]["cust_provinciaid"])
                     {  ?>
                        <option value="<?=$comuna["id"]?>"
                        <?php if($comuna["id"] == $customer[0]["cust_comunaid"]) echo "selected"?>><?=$comuna["nombre"]?></option>
                        <?php
                     }
                  }
               }
               */
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="32">Provincia</td>
         <td class="content_row">
            <select class="text" style="width:360px" name="provincias" id="provincias" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setComunas(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$customer[0]["cust_regionid"])
               {
                  foreach($provincias as $provincia)
                  {
                     if($provincia["region_id"] == $customer[0]["cust_regionid"])
                     {  ?>
                        <option value="<?=$provincia["id"]?>"
                        <?php if($provincia["id"] == $customer[0]["cust_provinciaid"]) echo "selected"?>><?=$provincia["pro_name"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="32">Region</td>
         <td class="content_row" width="130">
            <select class="text" style="width:360px" name="regions" id="regions" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setProvincias(this.value);">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$customer[0]["cust_countryid"])
               {
                  foreach($regions as $region)
                  {
                     if($region["id_pais"] == $customer[0]["cust_countryid"])
                     {  ?>
                        <option value="<?=$region["id"]?>"
                        <?php if($region["id"] == $customer[0]["cust_regionid"]) echo "selected"?>><?=$region["name"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="32">Pais</td>
         <td class="content_row" width="130">
            <select class="text" style="width:360px" name="country" id="country"
            onchange="setRegions(this.value)"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($countries as $country)
               {  ?>
                  <option value="<?=$country["id"]?>"
                  <?php if($country["id"] == $customer[0]["cust_countryid"]) echo "selected"?>><?=$country["country_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="32">Canal</td>
         <td class="content_row" width="130">
            <select class="text" style="width:360px" name="cust_canal" id="cust_canal"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($canales as $canal)
               {  ?>
                  <option value="<?=$canal["codigo"]?>"
                  <?php if($canal["codigo"] == $customer[0]["cust_canal"]) echo "selected"?>><?=$canal["descripcion"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Activar credito</td>
         <td class="content_row">
            <input name="cust_convenio_act" type="checkbox"
            value="1" <?php if((int)$customer[0]["cust_convenio_act"]) echo "checked"?>>
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
         <td class="content_row"><?php if($customer[0]["cust_crtusr"] != "") echo "{$customer[0]["crt_firstname"]} {$customer[0]["crt_lastname"]}"?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
         <td class="content_row"><?php if($customer[0]["cust_crtusr"] != "") echo displayDate($customer[0]["cust_crtdat"])?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
         <td class="content_row"><?php if($customer[0]["cust_updusr"] != "") echo "{$customer[0]["upd_firstname"]} {$customer[0]["upd_lastname"]}"?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
         <td class="content_row"><?php if($customer[0]["cust_updusr"] != "") echo displayDate($customer[0]["cust_upddat"])?>&nbsp;</td>
      </tr>
      <tr>
         <td class="content_rowl" height="32">Seguimiento</td>
         <td class="content_row">
            <?php
            if((int)$customer[0]["cust_seg_act"])
            {  ?>
               <input type="hidden" name="cansegact" value="0">
               <input type="hidden" name="disablesegact" value="0">
               <?=date("d.m.Y", $customer[0]["cust_seg_alertdate"])?>&nbsp;
               <input type="button" class="button" value="Desactivar"
               onclick="document.xform_cust.disablesegact.value='1';submitForm(document.xform_cust)">
               <?php
            }
            else
            {  ?>
               <input type="hidden" name="cansegact" value="1">
               <input type="checkbox" name="cust_seg_act" value="1"
               onclick="if(this.checked) $('#idx_segopts').fadeIn(300, function() { $('#cust_seg_days').focus(); }); else $('#idx_segopts').fadeOut(300);"> Activar
               <span id="idx_segopts" style="display:none">
                  <input type="text" name="cust_seg_days" id="cust_seg_days" value="" class="text" style="width:60px;text-align:center"> Dias desde
                  <input type="text" name="cust_seg_date" value="<?=date("d.m.Y")?>" readonly class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency" style="width:80px;">
               </span>
               <?php
            }
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Encuestas</td>
         <td class="content_row">
            <input name="cust_encuesta_disable" type="checkbox"
            value="1" <?php if((int)$customer[0]["cust_encuesta_disable"]) echo "checked"?>>
            Desactivar envios de encuestas
         </td>
      </tr>
      <tr>
        <td class="content_rowl" height="32">Cliente en presupuesto</td>
        <td class="content_row">
           <input type="radio" name="cust_presupuesto" value="1"
               <?php if((int)$customer[0]["cust_presupuesto"] == 1) echo "checked" ?>>SI
           <input type="radio" name="cust_presupuesto" value="0"
               <?php if((int)$customer[0]["cust_presupuesto"] == 0) echo "checked" ?>>NO
         </td>
      </tr>      
      </table>
      <tr>
         <td class="content_rowl">(*) Campos serán obligatorio al momento de realizar cotización </td>
      </tr>
      <?=Nifty_printF()?>
   </td>
   <td></td>
   <td valign="top">
      <?=Nifty_printH("box1", "100%",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="140">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["CUST"][2]?> II</td>
      </tr>
         <tr>
            <td class="content_rowl">Rubro *</td>
            <td class="content_row">
                  <select class="text" style="width:330px" name="cust_catid" id="cust_catid"
                     onchange="cargarSubRubros2(this.value)">
                     <option value="">&lt;Seleccione&gt;</option>
                     <?php foreach($custcats as $custcat) { ?>
                        <option value="<?=$custcat["id"]?>"
                           <?php if($custcat["id"] == $customer[0]["cust_catid"]) echo "selected"; ?>>
                           <?=$custcat["cat_name"]?>
                        </option>
                     <?php } ?>
                  </select>
            </td>
         </tr>
         <script>         
         function cargarSubRubros2(rid)
            {
               var rubro = document.getElementById('cust_catid');
               var subrubro = document.getElementById('cust_subrubro');
               subrubro.options.length = 1;
               <?php
               foreach($custcats AS $r)
               {  ?>
                  if(rid == '<?=$r["id"]?>')
                  {  <?php
                     foreach($subcats AS $sr)
                     {
                        if($sr["sub_id_cat"] == $r["id"])
                        {  ?>
                           var newIndex = subrubro.options.length;
                           var newOpt = new Option('<?=$sr["sub_cat_name"]?>');
                           newOpt.value = '<?=$sr["id"]?>';
                           subrubro.options[newIndex] = newOpt;
                           <?php
                        }
                     }
                     ?>
                  }
                  <?php
               }
               ?>
            }
         </script>
         <tr>
            <td class="content_rowl">Sub Rubro</td>
            <td class="content_row">
               <select class="text" style="width:330px" name="cust_subrubro" id="cust_subrubro">
                  <option value="">&lt;Seleccione&gt;</option>
                  <?php foreach($subcats as $sr) { ?>
                     <option value="<?=$sr["id"]?>" 
                           <?php if($sr["id"] == $customer[0]["cust_subrubro"]) echo "selected"; ?>>
                           <?=$sr["sub_cat_name"]?>
                     </option>
                  <?php } ?>
               </select>
            </td>
         </tr>
      </tr>
      <tr>
         <td class="content_rowl">Precios productos</td>
         <td class="content_row">
            <select class="text" style="width:330px" name="cust_plid" id="cust_plid" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">PRECIOS BASICOS</option>
               <?php
               foreach($pricelists as $pricelist)
               {  ?>
                  <option value="<?=$pricelist["id"]?>"
                  <?php if($pricelist["id"] == $customer[0]["cust_plid"]) echo "selected"?>><?=$pricelist["pl_title"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Precios fabricaccion</td>
         <td class="content_row">
            <select class="text" style="width:330px" name="cust_plfabid" id="cust_plfabid" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">PRECIOS BASICOS</option>
               <?php
               foreach($fabplists as $fabplist)
               {  ?>
                  <option value="<?=$fabplist["id"]?>"
                  <?php if($fabplist["id"] == $customer[0]["cust_plfabid"]) echo "selected"?>><?=$fabplist["pl_title"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      
      <tr>
         <td class="content_rowl">Giro *</td>
         <td class="content_row">
            <select class="text" style="width:330px" name="cust_giroid" id="cust_giroid" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($giros as $giro)
               {  ?>
                  <option value="<?=$giro["id"]?>"
                  <?php if($giro["id"] == $customer[0]["cust_giroid"]) echo "selected"?>><?=$giro["giro_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>

      
      <tr>
         <td class="content_rowl">Holding</td>
         <td class="content_row">
            <select class="text" style="width:330px" name="cust_holding" id="cust_holding" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($holdings as $hold)
               {  ?>
                  <option value="<?=$hold["codigo"]?>"
                  <?php if($hold["codigo"] == $customer[0]["cust_holding"]) echo "selected"?>><?=$hold["descripcion"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>      

      
      <tr>
         <td class="content_rowl">Vendedor *</td>
         <td class="content_row">
            <select class="text" style="width:330px" name="cust_sellerid" id="cust_sellerid" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($sellers as $seller)
               {  ?>
                  <option value="<?=$seller["id"]?>"
                  <?php if($seller["id"] == $customer[0]["cust_sellerid"]) echo "selected"?>><?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <?php
            $bloquea = ($_SESSION["user_type"] != "1") ? 'disabled' : '';
         ?>
         <td class="content_rowl">Forma de pago</td>
         <td class="content_row">
            <select class="text" style="width:330px" name="cust_paymentid" id="cust_paymentid" onchange="ocultarMostrarCelda(this.value)" onmousedown="markfield(this,0)" onblur="markfield(this,1)" 
               <?=$bloquea?>>
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($payments as $payment)
               {  ?>
                  <option value="<?=$payment["id"]?>" data-info=<?=$payment["pay_monto_credito"]?>
                     <?php if($payment["id"] == $customer[0]["cust_paymentid"]) echo "selected"?>><?=$payment["pay_title"]?>
                  </option>
                  
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <?php
          $visiblecredito = "display:none";
          if( (int)$customer[0]["cust_pricetolerance"] )
              $visiblecredito = "";
      ?>
      <tr id = "credito" name = "credito" style=<?=$visiblecredito?>>
         <td class="content_rowl">Monto de Crédito</td>
         <td class="content_row">
            <input name="cust_pricetolerance" id="cust_pricetolerance" type="text" class="text" style="width:330px" value="<?=printPrice($customer[0]["cust_pricetolerance"],0)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="32">Transportista</td>
         <td class="content_row">
            <select class="text" style="width:330px" name="cust_transportid" id="cust_transportid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($transports as $transport)
               {  ?>
                  <option value="<?=$transport["id"]?>"
                  <?php if($transport["id"] == $customer[0]["cust_transportid"]) echo "selected"?>><?=$transport["trans_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <?php
         $customercontacts = "select * from customer_contacts cc where cc.add_cust_id ={$_REQUEST["id"]} and cc.add_tipo_cust = 2";
         $customercontacts = $CON->select($customercontacts);

         if($_REQUEST["id"] == "")
         {
         ?>
         <tr>
            <td class="content_rowl" height="32">Contacto Comercial *</td>
            <td class="content_row">
               <input placeholder="Nombre" name="cust_contacto1" id="cust_contacto1" type="text" class="text" style="width:150px" value=""
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <input placeholder="Apellidos" name="cust_contacto2" id="cust_contacto2" type="text" class="text" style="width:170px" value=""
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <?php
         }else
         {
         $sql = "select * from customer_contacts where id = {$customer[0]["cust_contacto"]}";
         $contactocomercial = $CON->select($sql);
         ?>
            <tr>
            <td class="content_rowl" height="32">Contacto Comercial *</td>
            <td class="content_row">
               <select name="cust_id_contact" id="cust_id_contact" class="text" style="width:330px"
                     onmousedown="markfield(this,0)" onblur="markfield(this,1)"
                     onchange="actualizarDatosContacto()">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($customercontacts AS $cont) {
                     // Solo mostrar las opciones si el ID no es el mismo que el del contacto seleccionado
                     // if ($cont["id"] != $customer[0]["cust_contacto"])
                     {
                     ?>
                     <option value="<?=$cont["id"]?>" <?if($cont["id"] == $customer[0]["cust_id_contact"]) echo "selected"?>
                              data-idcrm="<?=$cont["add_id_crm"]?>" 
                              data-cellphono="<?=$cont["add_cellphone"]?>" 
                              data-phono="<?=$cont["add_phone"]?>" 
                              data-mail="<?=$cont["add_email"]?>"
                              data-idcc="<?=$cont["id"]?>"
                              data-name-contact="<?=$cont["add_firstname"] . ' ' . $cont["add_lastname"]?>">
                           <?=$cont["add_firstname"] . ' ' . $cont["add_lastname"]?>
                     </option>
                     <?php
                     }
                  }
                  ?>
               </select>
            </td>
         </tr>
         <?php
         }
      ?>
      <input type="hidden" name="req_id_cc" id="req_id_cc" >
      <tr style="display:none">
         <td class="content_rowl">Credito maximo</td>
         <td class="content_row">
            $ <input name="cust_pricetolerance2" type="text" class="text" style="width:100px"
            value="<?if($customer[0]["cust_pricetolerance"] != 0) echo printPrice($customer[0]["cust_pricetolerance"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="32">Mail Contacto *</td>
         <td class="content_row">
            <input name="cust_email" type="text" class="text" style="width:330px" value="<?=$customer[0]["cust_email"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="32">Telefono Empresa *</td>
         <td class="content_row">
            <input name="cust_phone" type="text" class="text" style="width:330px" value="<?=$customer[0]["cust_phone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="32"><?=$_LANG["MODULE"]["CUST"][18]?></td>
         <td class="content_row">
            <input name="cust_cellphone" type="text" class="text" style="width:330px" value="<?=$customer[0]["cust_cellphone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Whatsapp</td>
         <td class="content_row">
            <input name="cust_fax" type="text" class="text" style="width:330px" value="<?=$customer[0]["cust_fax"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][12]?></td>
         <td class="content_row">
            <input name="cust_website" type="text" class="text" style="width:330px" value="<?=$customer[0]["cust_website"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
            <td class="content_rowl" height="32">Id CRM</td>
            <td class="content_row">
               <input name="cust_id_crm" id="cust_id_crm" type="text" class="text" style="width:330px" value="<?=$customer[0]["cust_id_crm"]?>" readonly
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
      </tr>

      <tr>
        <td class="content_rowl" height="32">Cliente Permanentes</td>
        <td class="content_row">
           <input type="radio" name="cust_permanente" value="1"
               <?php if((int)$customer[0]["cust_permanente"] == 1) echo "checked" ?>>SI
           <input type="radio" name="cust_permanente" value="0"
               <?php if((int)$customer[0]["cust_permanente"] == 0) echo "checked" ?>>NO
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
   </td>
</tr>
</table>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {
      if($_SESSION[$_sesmodulename]["registerback"] != "")
      {
         $backdata = explode("-", $_SESSION[$_sesmodulename]["registerback"]);
         ?>
         <td align="left" width="130">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$backdata[0]}&exec=edit&id={$backdata[1]}", "", "arrow-180");
            ?>
         </td>
         <?php
      }
      else
      {  ?>
         <td align="left" width="130">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
            ?>
         </td>
         <?php
      }
      ?>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         if(!$_BLOCKDELETE)
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_cust)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<div id="idx_comunaout" style="display:none"></div>
<br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_cust');" ?>

