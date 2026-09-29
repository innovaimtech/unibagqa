<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   if($_REQUEST["delimageidx"] != "")
   {
      $sql = " select t1.*
               from orders t1
               where
               t1.id = {$_REQUEST["id"]}";
      $tempdata = $CON->select($sql);
      $tempdata = $tempdata[0];

      if($tempdata["req_prod_adjfile_{$_REQUEST["delimageidx"]}"] != "")
      {
         $doc_dir = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.prod/";
         @unlink("{$doc_dir}{$tempdata["req_prod_adjfile_{$_REQUEST["delimageidx"]}"]}");
         $sql = " update orders
                  set
                  req_prod_adjfile_{$_REQUEST["delimageidx"]} = '',
                  req_prod_adjname_{$_REQUEST["delimageidx"]} = ''
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
   }

   if($_REQUEST["delimagesuppidx"] != "")
   {
      $sql = " select t1.*
               from orders t1
               where
               t1.id = {$_REQUEST["id"]}";
      $tempdata = $CON->select($sql);
      $tempdata = $tempdata[0];

      if($tempdata["req_solic_supp_file_{$_REQUEST["delimagesuppidx"]}"] != "")
      {
         $doc_dir = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.prod/";
         @unlink("{$doc_dir}{$tempdata["req_solic_supp_file_{$_REQUEST["delimagesuppidx"]}"]}");
         $sql = " update orders
                  set
                  req_solic_supp_file_{$_REQUEST["delimagesuppidx"]} = '',
                  req_solic_supp_name_{$_REQUEST["delimagesuppidx"]} = ''
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
   }

   $_REQUEST["req_solic_supp_id"]            = (int)$_REQUEST["req_solic_supp_id"];
   $_REQUEST["req_cliche_peli_solic_repeat_act"] = (int)$_REQUEST["req_cliche_peli_solic_repeat_act"];
   $_REQUEST["req_solic_devprints_cc"]       = (int)trim($_REQUEST["req_solic_devprints_cc"]);
   $_REQUEST["req_solic_supp_mailtext"]      = trim(addslashes($_REQUEST["req_solic_supp_mailtext"]));
   $_REQUEST["req_solic_supp_mailtitle"]     = trim(addslashes($_REQUEST["req_solic_supp_mailtitle"]));

   $_REQUEST["req_dsgnchk_desarrollo"]       = trim(addslashes($_REQUEST["req_dsgnchk_desarrollo"]));
   $_REQUEST["req_dsgnchk_rollo"]            = trim(addslashes($_REQUEST["req_dsgnchk_rollo"]));
   $_REQUEST["req_dsgnchk_tipobolsa"]        = trim(addslashes($_REQUEST["req_dsgnchk_tipobolsa"]));
   $_REQUEST["req_dsgnchk_clipeli_tipo"]     = trim(addslashes($_REQUEST["req_dsgnchk_clipeli_tipo"]));
   $_REQUEST["req_dsgnchk_barcode_text"]     = trim(addslashes($_REQUEST["req_dsgnchk_barcode_text"]));
   $_REQUEST["req_dsgnchk_barcode_type"]     = trim(addslashes($_REQUEST["req_dsgnchk_barcode_type"]));
   $_REQUEST["req_dsgnchk_z"]                = trim(addslashes($_REQUEST["req_dsgnchk_z"]));
   $_REQUEST["req_solic_supp_gesttype"]      = trim(addslashes($_REQUEST["req_solic_supp_gesttype"]));

   $_REQUEST["req_solic_supp_maqueta_pantone"]        = trim(addslashes($_REQUEST["req_solic_supp_maqueta_pantone"]));
   $_REQUEST["req_solic_supp_maqueta_areaimpresion"]  = trim(addslashes($_REQUEST["req_solic_supp_maqueta_areaimpresion"]));
   $_REQUEST["req_solic_supp_maqueta_cantidad"]       = (int)trim($_REQUEST["req_solic_supp_maqueta_cantidad"]);
   $_REQUEST["req_solic_supp_maqueta_medidas"]        = trim(addslashes($_REQUEST["req_solic_supp_maqueta_medidas"]));
   $_REQUEST["req_solic_supp_maquetaint_act"]         = (int)$_REQUEST["req_solic_supp_maquetaint_act"];
   $_REQUEST["req_dsgnchk_tipobolsa_otrosdesc"]       = trim(addslashes($_REQUEST["req_dsgnchk_tipobolsa_otrosdesc"]));
   $_REQUEST["req_solic_supp_seudonimo"]              = trim(addslashes($_REQUEST["req_solic_supp_seudonimo"]));

   if($_REQUEST["req_dsgnchk_valid_cc"] != "")
      $_REQUEST["req_dsgnchk_valid_cc"] = (int)$_REQUEST["req_dsgnchk_valid_cc"];
   else
      $_REQUEST["req_dsgnchk_valid_cc"] = -1;

   if($_REQUEST["req_dsgnchk_texto_ok"] != "")
      $_REQUEST["req_dsgnchk_texto_ok"]         = (int)$_REQUEST["req_dsgnchk_texto_ok"];
   else
      $_REQUEST["req_dsgnchk_texto_ok"] = -1;

   if($_REQUEST["req_dsgnchk_rev_tacas"] != "")
      $_REQUEST["req_dsgnchk_rev_tacas"]        = (int)$_REQUEST["req_dsgnchk_rev_tacas"];
   else
      $_REQUEST["req_dsgnchk_rev_tacas"] = -1;

   if($_REQUEST["req_dsgnchk_barcode_act"] != "")
      $_REQUEST["req_dsgnchk_barcode_act"]      = (int)$_REQUEST["req_dsgnchk_barcode_act"];
   else
      $_REQUEST["req_dsgnchk_barcode_act"] = -1;

   if($_REQUEST["req_dsgnchk_pieimprenta_act"] != "")
      $_REQUEST["req_dsgnchk_pieimprenta_act"]  = (int)$_REQUEST["req_dsgnchk_pieimprenta_act"];
   else
      $_REQUEST["req_dsgnchk_pieimprenta_act"] = -1;

   if($_REQUEST["req_dsgnchk_valid_pantone"] != "")
      $_REQUEST["req_dsgnchk_valid_pantone"]    = (int)$_REQUEST["req_dsgnchk_valid_pantone"];
   else
      $_REQUEST["req_dsgnchk_valid_pantone"] = -1;

   if($_REQUEST["req_dsgnchk_tele_vege"] != "")
      $_REQUEST["req_dsgnchk_tele_vege"]        = (int)$_REQUEST["req_dsgnchk_tele_vege"];
   else
      $_REQUEST["req_dsgnchk_tele_vege"] = -1;

   if($_REQUEST["req_dsgnchk_tela_tnt_pp"] != "")
      $_REQUEST["req_dsgnchk_tela_tnt_pp"]      = (int)$_REQUEST["req_dsgnchk_tela_tnt_pp"];
   else
      $_REQUEST["req_dsgnchk_tela_tnt_pp"] = -1;

   if($_REQUEST["req_dsgnchk_ok_material"] != "")
      $_REQUEST["req_dsgnchk_ok_material"]      = (int)$_REQUEST["req_dsgnchk_ok_material"];
   else
      $_REQUEST["req_dsgnchk_ok_material"] = -1;

   if($_REQUEST["req_dsgnchk_valid_medidas"] != "")
      $_REQUEST["req_dsgnchk_valid_medidas"]    = (int)$_REQUEST["req_dsgnchk_valid_medidas"];
   else
      $_REQUEST["req_dsgnchk_valid_medidas"] = -1;

   if($_REQUEST["req_dsgnchk_prg_recliclaje_pr"] != "")
      $_REQUEST["req_dsgnchk_prg_recliclaje_pr"] = (int)$_REQUEST["req_dsgnchk_prg_recliclaje_pr"];
   else
      $_REQUEST["req_dsgnchk_prg_recliclaje_pr"] = -1;

   $_REQUEST["req_prod_adjcomments_0"] = trim(addslashes($_REQUEST["req_prod_adjcomments_0"]));
   $_REQUEST["req_prod_adjcomments_1"] = trim(addslashes($_REQUEST["req_prod_adjcomments_1"]));
   $_REQUEST["req_prod_adjcomments_2"] = trim(addslashes($_REQUEST["req_prod_adjcomments_2"]));
   $_REQUEST["req_prod_adjcomments_3"] = trim(addslashes($_REQUEST["req_prod_adjcomments_3"]));
   $_REQUEST["req_prod_adjcomments_4"] = trim(addslashes($_REQUEST["req_prod_adjcomments_4"]));

   $_REQUEST["req_cliche_peli_solic_dat"] = trim(addslashes($_REQUEST["req_cliche_peli_solic_dat"]));
   $_REQUEST["req_cliche_peli_recep_dat"] = trim(addslashes($_REQUEST["req_cliche_peli_recep_dat"]));

   $sql_req_cliche_peli_solic_dat = 0;
   $sql_req_cliche_peli_recep_dat = 0;
   $sql_req_solic_supp_envio_maqueta_date = 0;

   //----------------------------------------------------------------------------------
   $sql = " select t1.* from orders t1 where t1.id = {$_REQUEST["id"]}";
   $validadata = $CON->select($sql);
   $validadata = $validadata[0];
   //----------------------------------------------------------------------------------
   /* Validacion de Solicitud de peliculas/cliche */
   if (!empty($_REQUEST["req_cliche_peli_solic_dat"])) {
      if ($validadata["req_cliche_peli_solic_dat"] == 0) 
      {
         $sql_req_cliche_peli_solic_dat = explode(".", $_REQUEST["req_cliche_peli_solic_dat"]);
         $sql_req_cliche_peli_solic_dat = (int)mktime(date('H'), date('i'), date('s'), $sql_req_cliche_peli_solic_dat[1], $sql_req_cliche_peli_solic_dat[0], $sql_req_cliche_peli_solic_dat[2]);
      } 
      else 
      {
         $sql_req_cliche_peli_solic_dat = $validadata["req_cliche_peli_solic_dat"];
      }
   }
   /* Fin Validación */
   /* Validacion de Recepcion de peliculas/cliche */
   if (!empty($_REQUEST["req_cliche_peli_recep_dat"])) {
      if ($validadata["req_cliche_peli_recep_dat"] == 0) 
      {
         $sql_req_cliche_peli_recep_dat = explode(".", $_REQUEST["req_cliche_peli_recep_dat"]);
         $sql_req_cliche_peli_recep_dat = (int)mktime(date('H'), date('i'), date('s'), $sql_req_cliche_peli_recep_dat[1], $sql_req_cliche_peli_recep_dat[0], $sql_req_cliche_peli_recep_dat[2]);
      } 
      else 
      {
         $sql_req_cliche_peli_recep_dat = $validadata["req_cliche_peli_recep_dat"];
      }
   }
   /* Fin Validación */
   if($sql_req_cliche_peli_solic_dat==0)
      $sql_req_cliche_peli_recep_dat = 0;
   
   if($_REQUEST["req_solic_supp_envio_maqueta_date"] != "")
   {
      $sql_req_solic_supp_envio_maqueta_date = explode(".", $_REQUEST["req_solic_supp_envio_maqueta_date"]);
      $sql_req_solic_supp_envio_maqueta_date = (int)mktime(15, 0, 0, $sql_req_solic_supp_envio_maqueta_date[1], $sql_req_solic_supp_envio_maqueta_date[0], $sql_req_solic_supp_envio_maqueta_date[2]);
   }
   if($_REQUEST["req_solic_supp_gesttype"] != "Maqueta")
      $sql_req_solic_supp_envio_maqueta_date = 0;

   // $_REQUEST["req_solic_devprints_pol284"] = getPrice(trim($_REQUEST["req_solic_devprints_pol284"]),2);
   // $_REQUEST["req_solic_devprints_pol170"] = getPrice(trim($_REQUEST["req_solic_devprints_pol170"]),2);
   $_REQUEST["req_solic_devprints_poltype"] = trim($_REQUEST["req_solic_devprints_poltype"]);

   $tmpposdata  = getOrderPos($CON, $_REQUEST["id"]);
   $tmpposdata = $tmpposdata[0];

   if($tmpposdata["fab_printtype"] != "FLEX")
      $_REQUEST["req_solic_devprints_poltype"] = "";

   $sql = " update orders
            set
            req_cliche_peli_solic_dat     = {$sql_req_cliche_peli_solic_dat},
            req_cliche_peli_recep_dat     = {$sql_req_cliche_peli_recep_dat},
            req_prod_adjcomments_0        = '{$_REQUEST["req_prod_adjcomments_0"]}',
            req_prod_adjcomments_1        = '{$_REQUEST["req_prod_adjcomments_1"]}',
            req_prod_adjcomments_2        = '{$_REQUEST["req_prod_adjcomments_2"]}',
            req_prod_adjcomments_3        = '{$_REQUEST["req_prod_adjcomments_3"]}',
            req_prod_adjcomments_4        = '{$_REQUEST["req_prod_adjcomments_4"]}',
            req_dsgnchk_desarrollo        = '{$_REQUEST["req_dsgnchk_desarrollo"]}',
            req_dsgnchk_rollo             = '{$_REQUEST["req_dsgnchk_rollo"]}',
            req_dsgnchk_barcode_act       = {$_REQUEST["req_dsgnchk_barcode_act"]},
            req_dsgnchk_tipobolsa         = '{$_REQUEST["req_dsgnchk_tipobolsa"]}',
            req_dsgnchk_pieimprenta_act   = {$_REQUEST["req_dsgnchk_pieimprenta_act"]},
            req_dsgnchk_valid_pantone     = {$_REQUEST["req_dsgnchk_valid_pantone"]},
            req_dsgnchk_tele_vege         = {$_REQUEST["req_dsgnchk_tele_vege"]},
            req_dsgnchk_tela_tnt_pp       = {$_REQUEST["req_dsgnchk_tela_tnt_pp"]},
            req_dsgnchk_texto_ok          = {$_REQUEST["req_dsgnchk_texto_ok"]},
            req_dsgnchk_ok_material       = {$_REQUEST["req_dsgnchk_ok_material"]},
            req_dsgnchk_clipeli_tipo      = '{$_REQUEST["req_dsgnchk_clipeli_tipo"]}',
            req_dsgnchk_valid_medidas     = {$_REQUEST["req_dsgnchk_valid_medidas"]},
            req_dsgnchk_valid_cc          = {$_REQUEST["req_dsgnchk_valid_cc"]},
            req_dsgnchk_rev_tacas         = {$_REQUEST["req_dsgnchk_rev_tacas"]},
            req_solic_supp_id             = {$_REQUEST["req_solic_supp_id"]},
            req_solic_supp_mailtext       = '{$_REQUEST["req_solic_supp_mailtext"]}',
            req_solic_supp_mailtitle      = '{$_REQUEST["req_solic_supp_mailtitle"]}',
            req_dsgnchk_barcode_text      = '{$_REQUEST["req_dsgnchk_barcode_text"]}',
            req_dsgnchk_barcode_type      = '{$_REQUEST["req_dsgnchk_barcode_type"]}',
            req_dsgnchk_z                 = '{$_REQUEST["req_dsgnchk_z"]}',
            req_dsgnchk_prg_recliclaje_pr = {$_REQUEST["req_dsgnchk_prg_recliclaje_pr"]},
            req_dsgnchk_tipobolsa_otrosdesc        = '{$_REQUEST["req_dsgnchk_tipobolsa_otrosdesc"]}',
            req_solic_supp_gesttype                = '{$_REQUEST["req_solic_supp_gesttype"]}',
            req_solic_supp_maqueta_pantone         = '{$_REQUEST["req_solic_supp_maqueta_pantone"]}',
            req_solic_supp_maqueta_areaimpresion   = '{$_REQUEST["req_solic_supp_maqueta_areaimpresion"]}',
            req_solic_supp_maqueta_cantidad        = {$_REQUEST["req_solic_supp_maqueta_cantidad"]},
            req_solic_supp_maqueta_medidas         = '{$_REQUEST["req_solic_supp_maqueta_medidas"]}',
            req_solic_supp_seudonimo               = '{$_REQUEST["req_solic_supp_seudonimo"]}',
            req_solic_supp_maquetaint_act          = {$_REQUEST["req_solic_supp_maquetaint_act"]},
            req_solic_supp_envio_maqueta_date      = {$sql_req_solic_supp_envio_maqueta_date},
            req_cliche_peli_solic_repeat_act       = {$_REQUEST["req_cliche_peli_solic_repeat_act"]},
            req_solic_devprints_cc                 = {$_REQUEST["req_solic_devprints_cc"]},
            req_solic_devprints_poltype            = '{$_REQUEST["req_solic_devprints_poltype"]}'
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   for($x = 0; $x < 5; $x++)
   {
      if($_FILES["req_prod_adjfile_{$x}"]["name"] != "" &&
         $_FILES["req_prod_adjfile_{$x}"]["tmp_name"] != "" &&
         $_FILES["req_prod_adjfile_{$x}"]["error"] == 0 &&
         $_FILES["req_prod_adjfile_{$x}"]["size"] > 0)
      {
         $doc_type = substr($_FILES["req_prod_adjfile_{$x}"]["name"], strrpos($_FILES["req_prod_adjfile_{$x}"]["name"], ".") +1);
         $orignam  = trim(addslashes($_FILES["req_prod_adjfile_{$x}"]["name"]));

         $doc_hash = md5(microtime());
         $doc_name = "{$_REQUEST["id"]}_{$doc_hash}.{$doc_type}";


         $doc_dir  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.prod/";
         $ftres    = move_uploaded_file($_FILES["req_prod_adjfile_{$x}"]["tmp_name"], "{$doc_dir}{$doc_name}");
         if($ftres)
         {
            $sql = " update orders
                     set
                     req_prod_adjfile_{$x} = '{$doc_name}',
                     req_prod_adjname_{$x} = '{$orignam}'
                     where
                     id = {$_REQUEST["id"]}";
            $CON->no_result($sql);
         }
      }
   }

   for($x = 0; $x < 5; $x++)
   {
      if($_FILES["req_solic_supp_file_{$x}"]["name"] != "" &&
         $_FILES["req_solic_supp_file_{$x}"]["tmp_name"] != "" &&
         $_FILES["req_solic_supp_file_{$x}"]["error"] == 0 &&
         $_FILES["req_solic_supp_file_{$x}"]["size"] > 0)
      {
         $doc_type = substr($_FILES["req_solic_supp_file_{$x}"]["name"], strrpos($_FILES["req_solic_supp_file_{$x}"]["name"], ".") +1);
         $orignam  = trim(addslashes($_FILES["req_solic_supp_file_{$x}"]["name"]));

         $doc_hash = md5(microtime());
         $doc_name = "{$_REQUEST["id"]}_supp_{$doc_hash}.{$doc_type}";
         $doc_dir  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.prod/";
         $ftres    = move_uploaded_file($_FILES["req_solic_supp_file_{$x}"]["tmp_name"], "{$doc_dir}{$doc_name}");
         if($ftres)
         {
            $sql = " update orders
                     set
                     req_solic_supp_file_{$x} = '{$doc_name}',
                     req_solic_supp_name_{$x} = '{$orignam}'
                     where
                     id = {$_REQUEST["id"]}";
            $CON->no_result($sql);
         }
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cust_name, t2.cust_email, t3.company_short, t4.shop_name, t1.req_cust_id, t2.cust_notes, t7.pay_title,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t8.user_firstname 'seller_firstname', t8.user_lastname 'seller_lastname',
                t9.user_firstname 'cashing_firstname', t9.user_lastname 'cashing_lastname',
                t10.trans_name
         from orders t1
         LEFT OUTER JOIN customer t2         ON t1.req_cust_id          = t2.id
         LEFT OUTER JOIN company_data t3     ON t1.req_company_id       = t3.id
         LEFT OUTER JOIN company_shops t4    ON t1.req_shop_id          = t4.id
         LEFT OUTER JOIN user t5             ON t1.req_updusr           = t5.id
         LEFT OUTER JOIN user t6             ON t1.req_crtusr           = t6.id
         LEFT OUTER JOIN payments t7         ON t1.req_paymentid        = t7.id
         LEFT OUTER JOIN user t8             ON t1.req_userid_seller    = t8.id
         LEFT OUTER JOIN user t9             ON t1.req_userid_cashing   = t9.id
         LEFT OUTER JOIN transports t10      ON t1.req_transportid      = t10.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.pro_name
         from customer t1
         LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
         LEFT OUTER JOIN provincias t5 ON t1.cust_provinciaid  = t5.id
         where
         t1.id = {$headdata["req_cust_id"]}";
$customer = $CON->select($sql);
$customer = $customer[0];

$posdata  = getOrderPos($CON, $_REQUEST["id"]);
$thispos = $posdata[0];

//----------------------------------------------------------------------------------
// if((int)$_REQUEST["finalize"])
//    generateDesignFabricationAvisoMail($CON, $headdata, $posdata);

//----------------------------------------------------------------------------------
$sql = " select add_name
         from tran_comments_vals
         where
         id = {$thispos["fab_mat_fabric_color"]}";
$fabric_color = $CON->select($sql);
$fabric_color = $fabric_color[0]["add_name"];

$sql = " select add_name
         from tran_comments_vals
         where
         id = {$thispos["fab_mat_manilla_color"]}";
$manilla_color = $CON->select($sql);
$manilla_color = $manilla_color[0]["add_name"];

//----------------------------------------------------------------------------------
$_ROWSPANLINES = 2;
for($x = 1; $x <= 5; $x++)
{
   if((int)$thispos["fab_print_colors_front_{$x}"] || (int)$thispos["fab_print_colors_back_{$x}"])
   {
      $_ROWSPANLINES++;
   }
}

//----------------------------------------------------------------------------------
if($thispos["fab_printtype"] == "FLEX")
   $colorscharactid = $_CONFIG["FLEX_TINTA_COLOR_CHARACTID"];
elseif($thispos["fab_printtype"] == "SERI")
   $colorscharactid = $_CONFIG["SERI_TINTA_COLOR_CHARACTID"];

//----------------------------------------------------------------------------------
$sql = " select t2.id, t2.add_name
         from tran_comments t1
         INNER JOIN tran_comments_vals t2 ON t2.add_com_id = t1.id
         where
         t1.id          = {$colorscharactid} and
         t2.add_status  = 1
         order by t2.add_name";
$paintcolors = $CON->select($sql);

//----------------------------------------------------------------------------------
if((int)$_REQUEST["finalize"])
{
   $sql = " update orders
            set
            req_prod_checklist_term = 1
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   generateDesignFabricationAvisoMail($CON, $headdata, $posdata);
}

//----------------------------------------------------------------------------------
$sql = " select req_prod_checklist_term
         from orders
         where
         id = {$_REQUEST["id"]}";
$_BLOCKED = $CON->select($sql);
$_BLOCKED = $_BLOCKED[0]["req_prod_checklist_term"];
if($_BLOCKED)
{
   $rdlo = " readonly ";
   $dabl = " disabled ";
}

//----------------------------------------------------------------------------------
$sql = " select *
         from supplier
         where
         supp_status > 0 and
         supp_clique_act > 0
         order by supp_short";
$supps = $CON->select($sql);

//----------------------------------------------------------------------------------
$suppselarr = Array();
if((int)$headdata["req_solic_supp_id"])
{
   $sql = " select *
            from supplier
            where
            id = {$headdata["req_solic_supp_id"]}";
   $selsuppdata = $CON->select($sql);
   $selsuppdata = $selsuppdata[0];

   $sql = " select *
            from supplier_contacts
            where
            add_supplier_id   = {$headdata["req_solic_supp_id"]} and
            add_email         like '%@%'";
   $suppcontacts = $CON->select($sql);

   if(strpos($selsuppdata["supp_email"], "@") !== false)
   {
      unset($temp);
      $temp["NAME"] = str_replace(",","", str_replace("'","", str_replace('"',"", $selsuppdata["supp_short"])));
      $temp["MAIL"] = $selsuppdata["supp_email"];
      array_push($suppselarr, $temp);
   }
   foreach($suppcontacts AS $suppcontact)
   {
      unset($temp);
      $temp["NAME"] = str_replace(",","", str_replace("'","", str_replace('"',"", $suppcontact["add_firstname"]." ".$suppcontact["add_lastname"])));
      $temp["MAIL"] = $suppcontact["add_email"];
      array_push($suppselarr, $temp);
   }
}

//----------------------------------------------------------------------------------
$_SEND_MODE    = false;
$_MONTAJE_MODE = false;
if((int)$headdata["req_cliche_peli_solic_dat"] == 0 && !(int)$headdata["req_cliche_peli_solic_repeat_act"])
   $_SEND_MODE = true;
elseif((int)$headdata["req_cliche_peli_solic_dat"] > 0 || (int)$headdata["req_cliche_peli_solic_repeat_act"])
   $_MONTAJE_MODE = true;

// echo $headdata["req_cliche_peli_solic_repeat_act"];
// $_SEND_MODE = false;
// echo "_SEND_MODE={$_SEND_MODE}, _MONTAJE_MODE={$_MONTAJE_MODE}, _BLOCKED={$_BLOCKED}<br>";

//----------------------------------------------------------------------------------
if((int)$_REQUEST["notifysupp"] && count($_REQUEST["rcpts"]))
{
   $_OVERWRITE_MAILS = Array();

   $title = "Solicitud ";
   if($thispos["fab_printtype"] == "FLEX")
      $title .= "cliché:";
   elseif($thispos["fab_printtype"] == "SERI")
      $title .= "película:";
   $title .= " {$headdata["req_number"]} - {$headdata["req_solic_supp_seudonimo"]}";

   if($headdata["req_solic_supp_mailtitle"] != "")
      $title = $headdata["req_solic_supp_mailtitle"];

   $body = "";
   $attachments = Array();

   for($x = 0; $x < count($_REQUEST["rcpts"]); $x++)
   {
      $rcptdata = $suppselarr[$_REQUEST["rcpts"][$x]];
      if($rcptdata["MAIL"] != "" && $rcptdata["NAME"] != "")
      {
         unset($_OVERWRITE_MAIL);
         $_OVERWRITE_MAIL["ADDR"] = $rcptdata["MAIL"];
         $_OVERWRITE_MAIL["NAME"] = $rcptdata["NAME"];
         $_OVERWRITE_MAILS[]      = $_OVERWRITE_MAIL;
      }
   }

   for($x = 0; $x < 5; $x++)
   {
      if($headdata["req_solic_supp_file_{$x}"] != "")
      {
         unset($attachment);

         $ftype              = substr($headdata["req_solic_supp_file_{$x}"], strrpos($headdata["req_solic_supp_file_{$x}"], ".")+1);
         $attachment["FILE"] = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.prod/{$headdata["req_solic_supp_file_{$x}"]}";

         if($headdata["req_solic_supp_name_{$x}"] != "")
            $attachment["NAME"] = $headdata["req_solic_supp_name_{$x}"];
         else
            $attachment["NAME"] = "Adjunto-".($x+1).".{$ftype}";
         $attachments[]      = $attachment;
      }
   }

   if($headdata["req_solic_devprints_poltype"] == "pol284")
      $poltype = "Polímero 2,84mm";
   elseif($headdata["req_solic_devprints_poltype"] == "pol170")
      $poltype = "Polímero 1,7mm";

   $body  = "<html width=100% bgcolor=#CCCCCC>
             <body width=100% bgcolor=#CCCCCC leftmargin='0' marginwidth='0' topmargin='0' marginheight='0' rightmargin='0' offset='0'>
             <center>
             <table width='100%' cellpadding='3' cellspacing='0' border='0' bgcolor=#CCCCCC>
             <tr>
             <td bgcolor=#CCCCCC style=padding:20px><center>
             <table width='700' cellpadding='3' cellspacing='0' border='0' bgcolor=#FFFFFF>
             <tr>
                <td align='center' bgcolor=#FFFFFF>
                   <br><br><img width=300 src='https://encuestas.unibag.cl/images/logo-unibag.png'><br><br>
                </td>
             </tr>
             <tr>
                <td bgcolor=#FFFFFF align='center' style='font-size:12px;font-family:Arial;color:#666666' class='text'>
                  <b>Estimados {$selsuppdata["supp_company"]},</b><br><br>
                  ".nl2br($headdata["req_solic_supp_mailtext"])."
                  <br><br>
                  <b style='font-size:14px'><u>".$poltype."</u></b>
                  <div style=height:20px></div>
                </td>
             </tr>
             </table>";

   $currtme = time();
   $sql = " update orders
            set
            req_cliche_peli_solic_dat = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   sendExternalMail($title, $body, "", "", "", "", $attachments);
   ?>
   <script language="Javascript">
      location.href = '/index.php?mid=1205&setstatus=2&exec=edit&id=<?=$_REQUEST["id"]?>';
   </script>
   <?php
   exit;
}
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="form_reqpos" id="form_reqpos" enctype="multipart/form-data"
<?if($dabl != "") echo "onsubmit='return false'"?>>
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="finalize" value="">
<input type="hidden" name="delimageidx" value="">
<input type="hidden" name="delimagesuppidx" value="">
<input type="hidden" name="notifysupp" value="">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<input type="hidden" name="jschk_obitpanel" id="jschk_obitpanel" value="<?=(int)$_SESSION["jschk_obitpanel"]?>">
<input type="hidden" name="jschk_currenturl" id="jschk_currenturl" value="<?=$_SERVER["REQUEST_URI"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col width="350">
   <col width="140">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos básicos</td>
</tr>
<tr>
   <td class="content_rowl">Número</td>
   <td class="content_row"><?=$headdata["req_number"]?></td>
   <td class="content_rowl">Cliente</td>
   <td class="content_row"><?=$customer["cust_company"]?></td>
</tr>
<tr>
   <td class="content_rowl" style="border-top:3px double #CCCCCC">Material</td>
   <td class="content_row" style="border-top:3px double #CCCCCC"><?=$thispos["fab_type"]?></td>
   <td class="content_rowl" style="border-top:3px double #CCCCCC">Producto</td>
   <td class="content_row" style="border-top:3px double #CCCCCC"><?=$thispos["item_title"]?></td>
</tr>
<tr>
   <td class="content_rowl">Medidas</td>
   <td class="content_row">
      <?=(int)$thispos["fab_med_width"]?>x<?=(int)$thispos["fab_med_height"]?> cm
      <?php
      if((int)$thispos["fab_med_fuelle"])
         echo ", Fuelle ".(int)$thispos["fab_med_fuelle"]." cm";
      ?>
   </td>
   <td class="content_rowl">Area impresión</td>
   <td class="content_row"><?=(int)$thispos["fab_print_width"]?>x<?=(int)$thispos["fab_print_height"]?> cm</td>
</tr>
<tr>
   <td class="content_rowl">Tipo impresión</td>
   <td class="content_row">
      <?php
      if($thispos["fab_printtype"] == "FLEX")
         echo "Flexografia";
      elseif($thispos["fab_printtype"] == "SERI")
         echo "Serigrafia";
      ?>
   </td>
   <td class="content_rowl">Manillas</td>
   <td class="content_row">
      <?php
      if((int)$thispos["fab_manilla_length"])
         echo (int)$thispos["fab_manilla_length"]." cm";
      else
         echo "- - -";
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Color tela</td>
   <td class="content_row"><?=$fabric_color?></td>
   <td class="content_rowl" valign="top" rowspan="<?=$_ROWSPANLINES?>">Descripción</td>
   <td class="content_row" valign="top" rowspan="<?=$_ROWSPANLINES?>"><?=$thispos["item_compdesc"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Color manillas</td>
   <td class="content_row"><?=$manilla_color?></td>
</tr>
<?php

for($x = 1; $x <= 5; $x++)
{
   if((int)$thispos["fab_print_colors_front_{$x}"] || (int)$thispos["fab_print_colors_back_{$x}"])
   {  ?>
      <tr>
         <td class="content_rowl" height="1">Color #<?=$x?></td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="100">
               <col>
            </colgroup>
            <tr>
               <td class="content_row_clear" width="100">
                  <?php
                  if((int)$thispos["fab_print_colors_front_{$x}"] && !(int)$thispos["fab_print_colors_back_{$x}"])
                     echo "Frente: ";
                  elseif(!(int)$thispos["fab_print_colors_front_{$x}"] && (int)$thispos["fab_print_colors_back_{$x}"])
                     echo "Dorso: ";
                  elseif((int)$thispos["fab_print_colors_front_{$x}"] && (int)$thispos["fab_print_colors_back_{$x}"])
                     echo "Frente/Dorso: ";
                  ?>
               </td>
               <td class="content_row_clear"><?=$thispos["fab_print_colordesc_{$x}"]?></td>
            </tr>
            </table>
         </td>
      </tr>
      <?php
   }
}
?>
<tr>
   <td class="content_rowl">Cantidad</td>
   <td class="content_row"><?=printPrice($thispos["item_amount"],2)?></td>
   <td class="content_rowl">Gramaje</td>
   <td class="content_row"><?=printPrice($thispos["fab_mat_gramms"])?></td>
</tr>
<tr>
   <td class="content_rowl">$ / Unitario</td>
   <td class="content_row"><?=printPrice($thispos["item_sellprice_netto"])?></td>
   <td class="content_rowl">$ / Código de barra</td>
   <td class="content_row">
      <?=printPrice($thispos["item_sellprice_barcode"])?>
      <?php
      if($thispos["item_sellprice_barcodenumber"] != "")
      {  ?>
         <span style="float:right">
            Código: <?=$thispos["item_sellprice_barcodenumber"]?>
         </span>
         <?php
      }
      ?>&nbsp;
   </td>
</tr>
<?php
if($thispos["fab_design_desc"] != "")
{  ?>
   <tr>
      <td class="content_rowl" valign="top" style="border-top:3px double #CCCCCC">Comentarios diseño</td>
      <td class="content_row" colspan="3" style="border-top:3px double #CCCCCC"><?=nl2br($thispos["fab_design_desc"])?></td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>

<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Solicitud</td>
</tr>
<tr>
   <td class="content_rowl">
      Fecha solicitud
      <?php
      if($thispos["fab_printtype"] == "FLEX")
         echo "cliché";
      elseif($thispos["fab_printtype"] == "SERI")
         echo "película";
      ?>
   </td>
   <td class="content_row">
      <input type="text" style="width:80px" id="req_cliche_peli_solic_dat" name="req_cliche_peli_solic_dat" <?=$rdlo?>
      class="text <?if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if($headdata["req_cliche_peli_solic_dat"] > 0) echo date('d.m.Y', $headdata["req_cliche_peli_solic_dat"])?>">
      <?if($headdata["req_cliche_peli_solic_dat"] > 0) echo date('H:i',$headdata["req_cliche_peli_solic_dat"]) ?>
   </td>
      <?php
      if($headdata["req_cliche_peli_solic_dat"] == 0)
      {  ?>
         <b class=msg_save_err>( La fecha de solicitud se llena automáticamente con el envio al proveedor )</b>
         <?php
      }
      ?>
      <span style="float:right">
         <input type="checkbox" name="req_cliche_peli_solic_repeat_act" value="1"
         <?if((int)$headdata["req_cliche_peli_solic_repeat_act"]) echo "checked"?>
         onclick="<?if($rdlo != "") echo "return false"?>"> Trabajo repetido
      </span>
   </td>
</tr>
<?php
if($thispos["fab_printtype"] == "SERI")
{  ?>
   <tr>
      <td class="content_rowl" valign="top">Seudónimo</td>
      <td class="content_row" valign="top">
         <textarea type="text" style="width:100%;height:52px" id="req_solic_supp_seudonimo" name="req_solic_supp_seudonimo" <?=$rdlo?>
         class="text"><?=stripslashes($headdata["req_solic_supp_seudonimo"])?></textarea>
      </td>
   </tr>
   <?php
}
?>
<tr>
   <td class="content_rowl">Proveedor</td>
   <td class="content_row">
      <select type="text" style="width:100%;" id="req_solic_supp_id" name="req_solic_supp_id" class="text"
      onchange="document.form_reqpos.submit()">
         <option value="">Seleccione un proveedor</option>
         <?php
         foreach($supps AS $supp)
         {  ?>
            <option value="<?=$supp["id"]?>" <?if($headdata["req_solic_supp_id"] == $supp["id"]) echo "selected"?>><?=$supp["supp_short"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<?php
$_UPPEDFILES = 0;
for($x = 0; $x < 5; $x++)
{
   if($headdata["req_solic_supp_file_{$x}"] != "")
      $_UPPEDFILES++;
}
$_MAXFILES = 5;
if($headdata["req_cliche_peli_solic_dat"] > 0 || (int)$headdata["req_cliche_peli_solic_repeat_act"])
   $_MAXFILES = $_UPPEDFILES;

for($x = 0; $x < $_MAXFILES; $x++)
{  ?>
   <tr>
      <td class="content_rowl">Adjuntar archivo #<?=($x + 1)?></td>
      <td class="content_row">
         <?php
         if($headdata["req_solic_supp_file_{$x}"] != "")
         {  ?>
            <table border="0" cellspacing="0" cellpadding="0">
            <tr>
               <td align="right" width="130" style="padding-right:5px">
                  <?php
                  $dlname  = $headdata["req_solic_supp_file_{$x}"];
                  if($headdata["req_solic_supp_name_{$x}"] != "")
                     $dlname = $headdata["req_solic_supp_name_{$x}"];

                  $xkey = md5($headdata["req_solic_supp_file_{$x}"]."_5gfffd".$dlname);
                  $pdflink = "/getprodfile.php?xkey={$xkey}&hash={$headdata["req_solic_supp_file_{$x}"]}&name={$dlname}&type=req_solic_supp_file";

                  printButton("Descargar archivo", "postnav", "javascript: deactivateFormChange()", "window.open('{$pdflink}')", "image");
                  ?>
               </td>
               <?php
               if(!(int)$headdata["req_cliche_peli_solic_dat"])
               {  ?>
                  <td align="right" width="130">
                     <?php
                     if($dabl == "")
                        printButton("Eliminar archivo", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_reqpos.delimagesuppidx.value = '{$x}'; submitForm(document.form_reqpos); } ", "cross-circle-frame");
                     ?>
                  </td>
                  <?php
               }
               ?>
            </tr>
            </table>
            <?php
         }
         else
         {  ?>
            <input type="file" style="width:100%" id="req_solic_supp_file_<?=$x?>" name="req_solic_supp_file_<?=$x?>"
            class="text" onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$dabl?>>
            <?php
         }
         ?>
      </td>
   </tr>
   <?php
}
?>
<tr>
   <td class="content_rowl">Impr. desarrollo</td>
   <td class="content_row">
      <input type="text" class="text" name="req_solic_devprints_cc" style="width:82px;text-align:center" <?=$rdlo?>
      value="<?if((int)$headdata["req_solic_devprints_cc"]) echo (int)$headdata["req_solic_devprints_cc"]?>">
      <?php
      if(!(int)$headdata["req_solic_devprints_cc"])
         echo "<b class=msg_save_err> (Campo obligatorio)</b>";
      ?>
   </td>
</tr>
<?php
if($thispos["fab_printtype"] == "FLEX")
{  ?>
   <tr>
      <td class="content_rowl">Polímero</td>
      <td class="content_row">
         <input type="radio" name="req_solic_devprints_poltype" value="pol284"
         <?if($headdata["req_solic_devprints_poltype"] == "pol284") echo "checked"?>> Polímero 2,84mm
         <input type="radio" name="req_solic_devprints_poltype" value="pol170"
         <?if($headdata["req_solic_devprints_poltype"] == "pol170") echo "checked"?>> Polímero 1,7mm
      </td>
   </tr>
   <?php
}
?>
<!--
<tr>
   <td class="content_rowl">Desarrollo Pol.2,84mm</td>
   <td class="content_row">
      <input type="text" class="text" name="req_solic_devprints_pol284" style="width:82px;text-align:center" <?=$rdlo?>
      value="<?if((float)$headdata["req_solic_devprints_pol284"]) echo printPrice($headdata["req_solic_devprints_pol284"],2)?>">
      <?php
      if(!(float)$headdata["req_solic_devprints_pol284"])
         echo "<b class=msg_save_err> (Campo obligatorio)</b>";
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Desarrollo Pol.1,7 mm</td>
   <td class="content_row">
      <input type="text" class="text" name="req_solic_devprints_pol170" style="width:82px;text-align:center" <?=$rdlo?>
      value="<?if((float)$headdata["req_solic_devprints_pol170"]) echo printPrice($headdata["req_solic_devprints_pol170"],2)?>">
      <?php
      if(!(float)$headdata["req_solic_devprints_pol170"])
         echo "<b class=msg_save_err> (Campo obligatorio)</b>";
      ?>
   </td>
</tr>
-->
<tr>
   <td class="content_rowl">Tipo</td>
   <td class="content_row">
      <input type="radio" name="req_solic_supp_gesttype" value="Montaje"
      onclick="$('.gesttype_maqueta').fadeOut(300)"
      <?if($headdata["req_solic_supp_gesttype"] == "Montaje" || $headdata["req_solic_supp_gesttype"] == "") echo "checked"?>> Montaje
      <input type="radio" name="req_solic_supp_gesttype" value="Maqueta"
      onclick="$('.gesttype_maqueta').fadeIn(300)"
      <?if($headdata["req_solic_supp_gesttype"] == "Maqueta") echo "checked"?>> Maqueta

      <input type="checkbox" <?=$dabl?> name="req_solic_supp_maquetaint_act" value="1"  <?if((int)$headdata["req_solic_supp_maquetaint_act"] == 1) echo "checked"?>> Maqueta interna
   </td>
</tr>
<tr class="gesttype_maqueta" style="<?if($headdata["req_solic_supp_gesttype"] != "Maqueta") echo "display: none"?>">
   <td class="content_rowl">Fecha envio maqueta</td>
   <td class="content_row">
      <input type="text" style="width:80px" id="req_solic_supp_envio_maqueta_date" name="req_solic_supp_envio_maqueta_date" <?=$rdlo?>
      class="text <?if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if($headdata["req_solic_supp_envio_maqueta_date"] > 0) echo date('d.m.Y', $headdata["req_solic_supp_envio_maqueta_date"]); else echo date("d.m.Y")?>">
   </td>
</tr>
<tr class="gesttype_maqueta" style="<?if($headdata["req_solic_supp_gesttype"] != "Maqueta") echo "display: none"?>">
   <td class="content_rowl">Color pantone</td>
   <td class="content_row">
      <input type="text" class="text" name="req_solic_supp_maqueta_pantone" style="width:100%"
      value="<?=$headdata["req_solic_supp_maqueta_pantone"]?>">
   </td>
</tr>
<tr class="gesttype_maqueta" style="<?if($headdata["req_solic_supp_gesttype"] != "Maqueta") echo "display: none"?>">
   <td class="content_rowl">Área impresión</td>
   <td class="content_row">
      <input type="text" class="text" name="req_solic_supp_maqueta_areaimpresion" style="width:100%"
      value="<?=$headdata["req_solic_supp_maqueta_areaimpresion"]?>">
   </td>
</tr>
<tr class="gesttype_maqueta" style="<?if($headdata["req_solic_supp_gesttype"] != "Maqueta") echo "display: none"?>">
   <td class="content_rowl">Cantidad</td>
   <td class="content_row">
      <input type="text" class="text" name="req_solic_supp_maqueta_cantidad" style="width:80px;text-align:center"
      value="<?if((int)$headdata["req_solic_supp_maqueta_cantidad"]) echo (int)$headdata["req_solic_supp_maqueta_cantidad"]?>">
   </td>
</tr>
<tr class="gesttype_maqueta" style="<?if($headdata["req_solic_supp_gesttype"] != "Maqueta") echo "display: none"?>">
   <td class="content_rowl">Medidas</td>
   <td class="content_row">
      <input type="text" class="text" name="req_solic_supp_maqueta_medidas" style="width:100%"
      value="<?=$headdata["req_solic_supp_maqueta_medidas"]?>">
   </td>
</tr>
<tr style="<?if((int)$headdata["req_cliche_peli_solic_repeat_act"]) echo "display: none"?>">
   <td class="content_rowl" style="border-top:2px solid #CCCCCC">Asunto email</td>
   <td class="content_row" style="border-top:2px solid #CCCCCC">
      <input type="text" style="width:100%;" id="req_solic_supp_mailtitle" name="req_solic_supp_mailtitle" <?=$rdlo?>
      placeholder="Si el campo queda vacio, se envia con N° CC + Seudónimo"
      class="text" value="<?=stripslashes($headdata["req_solic_supp_mailtitle"])?>">
   </td>
</tr>
<tr style="<?if((int)$headdata["req_cliche_peli_solic_repeat_act"]) echo "display: none"?>">
   <td class="content_rowl" valign="top">Texto email</td>
   <td class="content_row">
      <textarea type="text" style="width:100%;height:120px" id="req_solic_supp_mailtext" name="req_solic_supp_mailtext" <?=$rdlo?>
      class="text"><?=stripslashes($headdata["req_solic_supp_mailtext"])?></textarea>
      <?php
      if(count($suppselarr) && $headdata["req_cliche_peli_solic_dat"] == 0)
      {
         $sidx = 0;
         foreach($suppselarr AS $suppselarrow)
         {  ?>
            <nobr><input type="checkbox" name="rcpts[]" value="<?=$sidx?>" checked> <?=$suppselarrow["NAME"]?> (<?=$suppselarrow["MAIL"]?>)</nobr><br>
            <?php
            $sidx++;
         }
      }
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
if($_SEND_MODE)
{  ?>
   <?=Nifty_printH("boxopt_b", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <tr>
      <td align="left" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_reqpos)", "disk-black");
         ?>
      </td>
      <?php
      if(count($suppselarr))
      {  ?>
         <td align="right" width="130">
            <?php
            printButton("Enviar al proveedor" , "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_reqpos.notifysupp.value = '1';submitForm(document.form_reqpos); }", "disk-black");
            ?>
         </td>
         <?php
      }
      ?>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?php
}
?>

<div style="<?if($_SEND_MODE || $headdata["req_solic_supp_gesttype"] == "Maqueta") echo "display:none"?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
   <col width="130">
   <col width="350">
   <col width="140">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Montaje</td>
</tr>
<tr>
   <td class="content_rowl">
      Fecha recepción
      <?php
      if($thispos["fab_printtype"] == "FLEX")
         echo "cliché";
      elseif($thispos["fab_printtype"] == "SERI")
         echo "película";
      ?>
   </td>
   <td class="content_row">
      <input type="text" style="width:80px" id="req_cliche_peli_recep_dat" name="req_cliche_peli_recep_dat" <?=$rdlo?>
      class="text <?if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if($headdata["req_cliche_peli_recep_dat"] > 0) echo date('d.m.Y', $headdata["req_cliche_peli_recep_dat"])?>">
      <?if($headdata["req_cliche_peli_recep_dat"] > 0) echo date('H:i',$headdata["req_cliche_peli_recep_dat"]) ?>
   </td>
</tr>
<tr>
   <td class="content_row" colspan="4" style="padding:0px">
      <table border="0" cellspacing="0" cellpadding="3" width="100%">
      <colgroup>
         <col width="20%">
         <col width="20%">
         <col width="20%">
         <col width="20%">
         <col width="20%">
      </colgroup>
      <tr>
         <td align="center" class="content_row_os" style="background-color:#1AAAA6;color:white;text-shadow:none;font-weight:bold">Cliché</td>
         <td align="center" class="content_row_os" style="background-color:#1AAAA6;color:white;text-shadow:none;font-weight:bold">Cliché</td>
         <td align="center" class="content_row_os" style="background-color:#1AAAA6;color:white;text-shadow:none;font-weight:bold">Película</td>
         <td align="center" class="content_row_os" style="background-color:#1AAAA6;color:white;text-shadow:none;font-weight:bold">Película</td>
         <td align="center" class="content_row_os" style="background-color:#1AAAA6;color:white;text-shadow:none;font-weight:bold">Montaje</td>
      </tr>
      <tr>
         <?php
         for($x = 0; $x < 5; $x++)
         {  ?>
            <td align="center" class="content_rowl content_row_os" style="cursor:pointer"
            <?php
            if($headdata["req_prod_adjfile_{$x}"] != "")
            {
               $dlname  = $headdata["req_prod_adjfile_{$x}"];
               if($headdata["req_prod_adjname_{$x}"] != "")
                  $dlname = $headdata["req_prod_adjname_{$x}"];

               $xkey = md5($headdata["req_prod_adjfile_{$x}"]."_5gfffd".$dlname);
               $pdflink = "/getprodfile.php?xkey={$xkey}&hash={$headdata["req_prod_adjfile_{$x}"]}&name={$dlname}&type=req_prod_adjfile";
               ?>
               onclick="window.open('<?=$pdflink?>')"
               <?php
               $_HAS_FILES = true;
            } ?>>
            <?php
            if($headdata["req_prod_adjfile_{$x}"] != "")
            {
               $ftype = strtoupper(substr($headdata["req_prod_adjfile_{$x}"], strrpos($headdata["req_prod_adjfile_{$x}"], ".")+1));
               if($ftype == "PNG" || $ftype == "JPG" || $ftype == "JPEG" || $ftype == "BMP" ||$ftype == "GIF")
               {  ?>
                  <img src="/docs.prod/<?=$headdata["req_prod_adjfile_{$x}"]?>" width="100%">
                  <?php
               }
               else
               {  ?>
                  IMAGEN<br>NO DISPONIBLE<br>(SOLO DESCARGAR)
                  <?php
               }
            }
            else
            {  ?>
               <img src="/images/content/image.png" width="100%">
               <?php
            }
            ?>
            </td>
            <?php
         }
         ?>
      </tr>
      <tr>
      <?php
      for($x = 0; $x < 5; $x++)
      {  ?>
         <td class="content_row">
            <textarea type="text" style="width:100%" id="req_prod_adjcomments_<?=$x?>" name="req_prod_adjcomments_<?=$x?>"
            class="text" onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?> rows=3
            placeholder="Comentarios..."><?=stripslashes($headdata["req_prod_adjcomments_{$x}"])?></textarea>
         </td>
         <?php
      }
      ?>
      </tr>
      <?php
      if($dabl == "")
      {  ?>
         <tr>
            <?php
            for($x = 0; $x < 5; $x++)
            {  ?>
               <td align="center" class="content_row_os">
                  <?php
                  if($headdata["req_prod_adjfile_{$x}"] != "")
                  {  printButton("Eliminar archivo", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_reqpos.delimageidx.value = '{$x}'; submitForm(document.form_reqpos); } ", "cross-circle-frame", "100%");
                  }
                  else
                  {  ?>
                     <input type="file" style="width:100%" id="req_prod_adjfile_<?=$x?>" name="req_prod_adjfile_<?=$x?>"
                     class="text" onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$dabl?>>
                     <?php
                  }
                  ?>
               </td>
               <?php
            }
            ?>
         </tr>
         <?php
      }
      ?>
      </table>
   </td>
</tr>
<?php

//----------------------------------------------------------------------------------
$_COL1_COLOR = "red";
$_COL2_COLOR = "red";
$_COL3_COLOR = "red";
$_COL4_COLOR = "red";
$_COL5_COLOR = "red";
$_HASCHECKLIST = false;

if($headdata["req_dsgnchk_desarrollo"] != "" && $headdata["req_dsgnchk_rollo"] != "" && $headdata["req_dsgnchk_z"] != "")
   $_COL1_COLOR = "green";
if((int)$headdata["req_dsgnchk_valid_cc"] == 1 && (int)$headdata["req_dsgnchk_texto_ok"] == 1 &&
   (((int)$headdata["req_dsgnchk_barcode_act"] == 1 && $headdata["req_dsgnchk_barcode_text"] != "" && $headdata["req_dsgnchk_barcode_type"] != "") || (int)$headdata["req_dsgnchk_barcode_act"] == 0) )
   $_COL2_COLOR = "green";


if(($headdata["req_dsgnchk_tipobolsa"] != "" && strpos($headdata["req_dsgnchk_tipobolsa"], "Otros") === false) ||
   ($headdata["req_dsgnchk_tipobolsa"] != "" && strpos($headdata["req_dsgnchk_tipobolsa"], "Otros") !== false) && $headdata["req_dsgnchk_tipobolsa_otrosdesc"] != "")
   $_COL3_COLOR = "green";

if(((int)$headdata["req_dsgnchk_pieimprenta_act"] == 1 || (int)$headdata["req_dsgnchk_pieimprenta_act"] == 0) &&
   (int)$headdata["req_dsgnchk_ok_material"] == 1 &&
   ((int)$headdata["req_dsgnchk_tele_vege"] == 1 || (int)$headdata["req_dsgnchk_tela_tnt_pp"] == 1))
   $_COL4_COLOR = "green";

if($headdata["req_dsgnchk_clipeli_tipo"] != "" && (int)$headdata["req_dsgnchk_valid_medidas"] == 1 &&
   (int)$headdata["req_dsgnchk_valid_pantone"] == 1 && (int)$headdata["req_dsgnchk_rev_tacas"] == 1)
   $_COL5_COLOR = "green";

// if($_COL1_COLOR == "green" && $_COL2_COLOR == "green" && $_COL3_COLOR == "green" && $_COL4_COLOR == "green" && $_COL5_COLOR == "green")
$_HASCHECKLIST = true;

?>
<tr style="display:none">
   <td class="content_tbl_header" colspan="4">Checklist</td>
</tr>
<tr style="display:none">
   <td class="content_rowl" valign="top" colspan="4">
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="16%">
         <col width="21%">
         <col width="21%">
         <col width="21%">
         <col width="21%">
      </colgroup>
      <tr>
         <td class="content_row_os" align="center" style="color:<?=$_COL1_COLOR?>"><b>Checklist Tela</b></td>
         <td class="content_row_os" align="center" style="color:<?=$_COL2_COLOR?>"><b>Checklist Diseño en CC</b></td>
         <td class="content_row_os" align="center" style="color:<?=$_COL3_COLOR?>"><b>Checklist Tipo de Bolsa</b></td>
         <td class="content_row_os" align="center" style="color:<?=$_COL4_COLOR?>"><b>Checklist Tipo de Tela</b></td>
         <td class="content_row_os" align="center" style="color:<?=$_COL5_COLOR?>"><b>Checklist <?php
            if($thispos["fab_printtype"] == "FLEX")
               echo "Cliché";
            elseif($thispos["fab_printtype"] == "SERI")
               echo "Película";
            ?></b>
         </td>
      </tr>
      <tr>
         <td class="content_row_os" align="left" valign="top" style="background-color:#FFFFFF">
            Desarrollo<br>
            <input type="text" <?=$rdlo?> class="text" style="width:80%;text-align:center" name="req_dsgnchk_desarrollo" value="<?=$headdata["req_dsgnchk_desarrollo"]?>"><br><br>
            Rollo<br>
            <input type="text" <?=$rdlo?> class="text" style="width:80%;text-align:center" name="req_dsgnchk_rollo" value="<?=$headdata["req_dsgnchk_rollo"]?>"><br><br>
            Z<br>
            <input type="text" <?=$rdlo?> class="text" style="width:80%;text-align:center" name="req_dsgnchk_z" value="<?=$headdata["req_dsgnchk_z"]?>">
         </td>
         <td class="content_row_os" align="left" valign="top" style="background-color:#FFFFFF">
            <input type="checkbox" <?=$dabl?> name="req_dsgnchk_valid_cc" value="1"  <?if((int)$headdata["req_dsgnchk_valid_cc"] == 1) echo "checked"?>> Validación con CC<br>
            <input type="checkbox" <?=$dabl?> name="req_dsgnchk_texto_ok" value="1"  <?if((int)$headdata["req_dsgnchk_texto_ok"] == 1) echo "checked"?>> Textos <b>OK</b><br>
            <hr>
            <b>Código</b> de barra<br>
            <input type="radio" <?=$dabl?> name="req_dsgnchk_barcode_act" value="1" <?if((int)$headdata["req_dsgnchk_barcode_act"] == 1) echo "checked"?>
            onclick="$('#idx_inp_barcode').fadeIn(300);"> Si
            <input type="radio" <?=$dabl?> name="req_dsgnchk_barcode_act" value="0" <?if((int)$headdata["req_dsgnchk_barcode_act"] == 0) echo "checked"?>
            onclick="$('#idx_inp_barcode').fadeOut(300);"> No
            <div id="idx_inp_barcode" style="margin-top: 3px;<?if((int)$headdata["req_dsgnchk_barcode_act"] != 1) echo "display: none"?>">
               <input name="req_dsgnchk_barcode_text" name="req_dsgnchk_barcode_text" type="text" class="text"
               style="width:100%" placeholder="Código de barra" value="<?=$headdata["req_dsgnchk_barcode_text"]?>">
               <select class="text" style="width:100%;margin-top:3px" name="req_dsgnchk_barcode_type">
                  <option value="">Seleccione el tipo</option>
                  <option value="1D" <?if($headdata["req_dsgnchk_barcode_type"] == "1D") echo "selected"?>>Código 1D</option>
                  <option value="QR" <?if($headdata["req_dsgnchk_barcode_type"] == "QR") echo "selected"?>>Código QR</option>
               </select>
            </div>
         </td>
         <td class="content_row_os" align="left" valign="top" style="background-color:#FFFFFF">
            <input type="radio" onclick="$('#req_dsgnchk_tipobolsa_otrosdesc').fadeOut(300);" <?=$dabl?> name="req_dsgnchk_tipobolsa" value="Boutique"          <?if($headdata["req_dsgnchk_tipobolsa"] == "Boutique") echo "checked"?>> Bolsa <b>Boutique</b><br>
            <input type="radio" onclick="$('#req_dsgnchk_tipobolsa_otrosdesc').fadeOut(300);" <?=$dabl?> name="req_dsgnchk_tipobolsa" value="Promocional"       <?if($headdata["req_dsgnchk_tipobolsa"] == "Promocional") echo "checked"?>> Bolsa <b>Promocional</b><br>
            <input type="radio" onclick="$('#req_dsgnchk_tipobolsa_otrosdesc').fadeOut(300);" <?=$dabl?> name="req_dsgnchk_tipobolsa" value="Basurin"           <?if($headdata["req_dsgnchk_tipobolsa"] == "Basurin") echo "checked"?>> Bolsa <b>Basurin</b><br>
            <input type="radio" onclick="$('#req_dsgnchk_tipobolsa_otrosdesc').fadeOut(300);" <?=$dabl?> name="req_dsgnchk_tipobolsa" value="Saco"              <?if($headdata["req_dsgnchk_tipobolsa"] == "Saco") echo "checked"?>> Bolsa <b>Saco</b><br>
            <input type="radio" onclick="$('#req_dsgnchk_tipobolsa_otrosdesc').fadeOut(300);" <?=$dabl?> name="req_dsgnchk_tipobolsa" value="Troquel"           <?if($headdata["req_dsgnchk_tipobolsa"] == "Troquel") echo "checked"?>> Bolsa <b>Troquel</b><br>
            <input type="radio" onclick="$('#req_dsgnchk_tipobolsa_otrosdesc').fadeOut(300);" <?=$dabl?> name="req_dsgnchk_tipobolsa" value="Sobre"             <?if($headdata["req_dsgnchk_tipobolsa"] == "Sobre") echo "checked"?>> Bolsa <b>Sobre</b><br>
            <input type="radio" onclick="$('#req_dsgnchk_tipobolsa_otrosdesc').fadeOut(300);" <?=$dabl?> name="req_dsgnchk_tipobolsa" value="Sobre Ecommerce"   <?if($headdata["req_dsgnchk_tipobolsa"] == "Sobre Ecommerce") echo "checked"?>> Bolsa <b>Sobre Ecommerce</b><br>
            <input type="radio" onclick="$('#req_dsgnchk_tipobolsa_otrosdesc').fadeIn(300);"  <?=$dabl?> name="req_dsgnchk_tipobolsa" value="Otros"             <?if(strpos($headdata["req_dsgnchk_tipobolsa"], "Otros") !== false) echo "checked"?>> Otros</b>
            <textarea class="text" style="width:100%;height:40px;<?if(strpos($headdata["req_dsgnchk_tipobolsa"], "Otros") === false) echo "display: none"?>" placeholder="Descripción"
            name="req_dsgnchk_tipobolsa_otrosdesc" id="req_dsgnchk_tipobolsa_otrosdesc"><?=stripslashes($headdata["req_dsgnchk_tipobolsa_otrosdesc"])?></textarea>
         </td>
         <td class="content_row_os" align="left" valign="top" style="background-color:#FFFFFF">
            Pie imprenta<br>
            <input type="radio" <?=$dabl?> name="req_dsgnchk_pieimprenta_act" value="1" <?if((int)$headdata["req_dsgnchk_pieimprenta_act"] == 1) echo "checked"?>> Si
            <input type="radio" <?=$dabl?> name="req_dsgnchk_pieimprenta_act" value="0" <?if((int)$headdata["req_dsgnchk_pieimprenta_act"] == 0) echo "checked"?>> No
            <hr>
            <input type="checkbox" <?=$dabl?> name="req_dsgnchk_prg_recliclaje_pr" id="req_dsgnchk_prg_recliclaje_pr" value="1" <?if((int)$headdata["req_dsgnchk_prg_recliclaje_pr"] == 1) echo "checked"?>> Programa de reciclaje PR<br>
            <input type="checkbox" <?=$dabl?> name="req_dsgnchk_tele_vege" id="req_dsgnchk_tele_vege" value="1"     <?if((int)$headdata["req_dsgnchk_tele_vege"] == 1) echo "checked"?>
            onclick="if(this.checked) document.getElementById('req_dsgnchk_tela_tnt_pp').checked = false;"> Tela <b>Vegetal</b><br>
            <input type="checkbox" <?=$dabl?> name="req_dsgnchk_tela_tnt_pp" id="req_dsgnchk_tela_tnt_pp" value="1"   <?if((int)$headdata["req_dsgnchk_tela_tnt_pp"] == 1) echo "checked"?>
            onclick="if(this.checked) document.getElementById('req_dsgnchk_tele_vege').checked = false;"> Tela <b>TNT PP</b><br>
            <input type="checkbox" <?=$dabl?> name="req_dsgnchk_ok_material" value="1"   <?if((int)$headdata["req_dsgnchk_ok_material"] == 1) echo "checked"?>> OK <b>Materialidad</b><br>
         </td>
         <td class="content_row_os" align="left" valign="top" style="background-color:#FFFFFF">
            <input type="radio" name="req_dsgnchk_clipeli_tipo" value="Nuevo" <?=$dabl?>
            <?if($headdata["req_dsgnchk_clipeli_tipo"] == "Nuevo") echo "checked"?>> <?php
            if($thispos["fab_printtype"] == "FLEX")
               echo "Cliché <b>Nuevo</b>";
            elseif($thispos["fab_printtype"] == "SERI")
               echo "Película <b>Nuevo</b>";
            ?>
            <br>
            <input type="radio" name="req_dsgnchk_clipeli_tipo" value="Repetido" <?=$dabl?>
            <?if($headdata["req_dsgnchk_clipeli_tipo"] == "Repetido") echo "checked"?>> <?php
            if($thispos["fab_printtype"] == "FLEX")
               echo "Cliché <b>Repetido</b>";
            elseif($thispos["fab_printtype"] == "SERI")
               echo "Película <b>Repetido</b>";
            ?>
            <br><br>
            <input type="checkbox" <?=$dabl?> name="req_dsgnchk_valid_pantone" value="1" <?if((int)$headdata["req_dsgnchk_valid_pantone"] == 1) echo "checked"?>> Validación <b>Pantone</b><br>
            <input type="checkbox" name="req_dsgnchk_valid_medidas" value="1" <?=$dabl?>
            <?if((int)$headdata["req_dsgnchk_valid_medidas"] == 1) echo "checked"?>>
            Validación <b>Medidas</b><br>
            <input type="checkbox" <?=$dabl?> name="req_dsgnchk_rev_tacas" value="1" <?if((int)$headdata["req_dsgnchk_rev_tacas"] == 1) echo "checked"?>> Revisión de <b>Tacas</b>
         </td>
      </tr>
      </table>
   </td>
</tr>
<?php
//----------------------------------------------------------------------------------
if($dabl == "")
{
   $haspolytype = false;
   if($headdata["req_solic_devprints_poltype"] != "")
      $haspolytype = true;
   elseif($thispos["fab_printtype"] != "FLEX")
      $haspolytype = true;
   ?>
   <tr>
      <td class="content_rowl">&nbsp;</td>
      <td class="content_row">
         <?php
         printButton("Guardar", "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_reqpos);", "disk-black");
         ?>
      </td>
      <td class="content_rowl">&nbsp;</td>
      <td class="content_row">
         <?php
         if((int)$headdata["req_cliche_peli_solic_dat"] && (int)$headdata["req_cliche_peli_recep_dat"] && $_HAS_FILES && $_HASCHECKLIST &&
            (int)$headdata["req_solic_devprints_cc"] && $haspolytype)
            printButton("Enviar notificación", "postnav_save", "javascript: deactivateFormChange()", "document.form_reqpos.finalize.value='1';submitForm(document.form_reqpos);", "tick-circle-frame");
         else
            echo "<b class=msg_save_err>Registra los datos para enviar el aviso de fabricación.</b>";
         ?>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
</div>
<?php
if($_MONTAJE_MODE && !$_BLOCKED && $headdata["req_solic_supp_gesttype"] == "Maqueta")
{  ?>
   <?=Nifty_printH("boxopt_b", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <tr>
      <td align="left" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_reqpos)", "disk-black");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?php
}

if($thispos["fab_design_imagehash"] != "")
{  ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <tr>
      <td class="content_tbl_header">Imagen diseño</td>
   </tr>
   <tr>
      <td class="content_row">
         <img border="0" src="./docs.order/<?=$thispos["fab_design_imagehash"]?>" width="100%">
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}
?>
<br>
<input type="hidden" name="existing_id_0" value="<?=$thispos["item_id"]?>">
<input type="hidden" name="existing_pos_0" value="<?=$thispos["item_pos"]?>">
<input type="hidden" name="item_id_0" value="<?=$thispos["item_id"]?>#<?=$thispos["item_type"]?>">
<input type='submit' value='' style='position:absolute;top:0px;left:0px;width:1px;height:1px;border:none;background-color:#FFFFFF;border-color:#FFFFFF'>
</form>