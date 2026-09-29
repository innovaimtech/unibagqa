<?php
class NPGSPF
{

   /*
   isTransferDocument = true -> no envia a SII.
   */
   var $TRANSFIERE = "false"; //  -> false: envia - true: no envia a SII.

   var $CON;
   var $_INVCCFG;
   var $_FILE_HASH;
   var $_FILE_NAME;
   var $_FILE_DIR;
   var $_FILE_FULL;
   var $_FILE_PREFIX;
   var $_FILE_SUBDIR;
   var $_FILE_NAME_JS;
   var $_FILE_FULL_JS;
   
   var $accountNumber1 = "1141201"; /* CLT : 01CTES_NACIONALES */   
   var $accountNumber2 = "2231101"; /* IMP : IVA               */       
   var $accountNumber3 = "3111101"; /* VTA : VENTAS            */  
   var $businessCenter = "EMPADMAD1000000"; 
   var $accountNumber4 = "1141201"; /* Boletas?con?Rut */   

   //----------------------------------------------------------------------------------
   function NPGSPF($CON, $_INVCCFG)
   {
      $this->CON        = $CON;
      $this->_INVCCFG   = $_INVCCFG;
      $this->_FILE_HASH = date('md').strtoupper(md5(microtime()));
   }
   // -----------------------------------------------------------------------------------------------
   function formatearRut($rut) {
      $partes = explode('-', $rut);
      if (count($partes) != 2) {
          return "Formato inv?lido";
      }
      $cuerpo = $partes[0];
      $dv = $partes[1];
      $cuerpo = str_pad($cuerpo, 8, '0', STR_PAD_LEFT);
      $cuerpo_formateado = substr($cuerpo, 0, 2) . '.' . substr($cuerpo, 2, 3) . '.' . substr($cuerpo, 5, 3);
      $rut_formateado = $cuerpo_formateado . '-' . $dv;
  
      return $rut_formateado;
   }
   //--------------------------------------------------------------------------------------------
   // FUNCTION PARA TRANSFERIR ARCHIVOS A ERP DEFONTANA
   //--------------------------------------------------------------------------------------------
   public function transferFileDF($filedir, $filename, $internid, $company, $siidoctype, $accion)
   {
      global $_CONFIG;

      switch ($accion) {
         case 'SaveSale':
             $tipo_documento = 'FVAELECT';
             break;
         case 'Save':
            $tipo_documento = 'GDVELECT';
            break;
         case 'SaveCreditNo':
            $tipo_documento = 'NCVELECT';
            break;
         case 'SaveGlossCre':
            $tipo_documento = 'NCVELECT';
            break;
         case 'SaveTypeCred':
            $tipo_documento = 'NCVELECT';
            break;           
         case 'SaveDebitNot':
            $tipo_documento = 'NDVELECT';
            break;
         default:
            $tipo_documento = "";
            break;
      }

      $sql = "insert into log_erp(proceso, estatus, detalle)
               values( 'Documentacion',9,'Error : INGRESO' )";
      $this->CON->no_result($sql);

      $_RET["result"]   = true;
      $_RET["message"]  = "Error interno";
      $_RET["doc1"]     = "";
      $_RET["doc2"]     = "";
      
      error_reporting(0);
      $file_content = file_get_contents($filedir.$filename);

      header('Content-Type: application/json'); // Para que el navegador lo reconozca como JSON
      $token = $this->ObtieneToken($company);

      $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = '{$accion}'";
      $url = $this->CON->select($sql);
      $url = $url[0]['url'];

      $ch = curl_init($url);
      
      $headers = [
         "Authorization: {$token['token_type']} {$token['access_token']}",
         "Content-Type: application/json"
      ];

      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);      
      curl_setopt($ch, CURLOPT_POST, true);                
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $file_content); 
      
      $respuestaJson = curl_exec($ch);

      $http_code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      $respuestaArray = json_decode($respuestaJson, true);
      $success = $respuestaArray['success'];
      $message = $respuestaArray['message'];
      $exp_message = $respuestaArray['exceptionMessage'];

      $sql = "insert into log_erp(proceso, estatus, detalle)
               values( 'Documentacion',9,'Error 1 : {$http_code} - {$message} - {$exp_message} <-- ')";
      $this->CON->no_result($sql);
      

      if( $http_code != 200)
      {
          $_RET["message"]  = "Error HTTP: ".$http_code."\n"."[".$message."]"."  [".$success."]."-".[".$exp_message."]";
          $_RET["result"]   = false;
          return $_RET;
      }
      if(curl_errno($ch))
      {
         $_RET["message"]  = curl_error($ch);
         $_RET["result"]   = false;
         return $_RET;
      }
      else
      {
         try
         {
            if ((int)$success == 1)
            {
               curl_close($ch);
               $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = 'ImprimePDF'";
               $url = $this->CON->select($sql);
               $api_url = $url[0]['url'];

               if (strpos($api_url, 'GetStandardPDFDocumentBase64') !== false)  // https://replapi.defontana.com/api/Sale/GetStandardPDFDocumentBase64
               {
                  /* Factura Normal */
                  $parametros = array(
                     'documentType'   => $tipo_documento,
                     'folio'          => $internid,
                     'siiUnit'        => 'S.I.I. - SANTIAGO NORTE',
                  );

                  $url_completa = $api_url . '?' . http_build_query($parametros);
                  $ch = curl_init($url_completa);
                  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                  $response = curl_exec($ch);
                  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                  curl_close($ch);
                  $data = json_decode($response, true);
               }
               else
               {
                  /* Factura Normal */
                  $parametros = array(
                     'documentType'   => $tipo_documento,
                     'folio'          => $internid,
                     'isCedible'      =>  true ? 'false' : 'true',
                  );

                  $url_completa = $api_url . '?' . http_build_query($parametros);
                  $ch = curl_init($url_completa);
                  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                  $response = curl_exec($ch);
                  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                  curl_close($ch);
                  $data = json_decode($response, true);
                  
                  /* Factura Cedible */
                  $parametros = array(
                     'documentType'   => $tipo_documento,
                     'folio'          => $internid,
                     'isCedible'      =>  true ? 'true' : 'false',
                  );
                  $url_completa = $api_url . '?' . http_build_query($parametros);
                  $ch = curl_init($url_completa);
                  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                  $response = curl_exec($ch);
                  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                  curl_close($ch);
                  $data2 = json_decode($response, true);
               }

               if ($data && isset($data['success']) && $data['success'] === true && isset($data['document']))
               {
                  if (isset($data['document'])) 
                  {
                     $hash        = strtoupper(md5(microtime()));

                     $pdffilename  = "{$siidoctype}_{$internid}_{$hash}_doc1.pdf";
                     $pdffilename2 = "{$siidoctype}_{$internid}_{$hash}_doc2.pdf";

                     $pdffiledir  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.electrpdf/";

                     $filePath     = $pdffilename;
                     $pdfBase64    = $data['document'];
                     $pdfData      = base64_decode($pdfBase64);
                     file_put_contents($pdffiledir.$filePath, $pdfData);

                     $filePath     = $pdffilename2;
                     $pdfBase64    = $data2['document'];
                     $pdfData2     = base64_decode($pdfBase64);
                     file_put_contents($pdffiledir.$filePath, $pdfData2);
                  }
               } 
               $_RET["message"]  = "Proceso exitoso.";
               $_RET["result"]   = true;
               $_RET["doc1"]     = $pdffilename;
               $_RET["doc2"]     = $pdffilename2;
               return $_RET;
            }
            else
            {
               $_RET["message"]  = $message;
               $_RET["result"]   = false;
               return $_RET;
            }
            
         }
         catch (Exception $e) {
            $_RET["message"]  = $e->getMessage();
            $_RET["result"]   = false;
            return $_RET;
         }
      }
      return $_RET;
   }
//------------------------------------------------------------------------------------------------------------------------
// FUNCTION PARA OBTENER TOKEN DE DEFONTANA
//-------------------------------------------------------------------------------------------------------------------------
public function ObtieneToken($company)
   {
      $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = 'Auth'";
      $url_base = $this->CON->select($sql);
      $url_base = $url_base[0]['url'];
   
      $parametros = array(
         'client'   => $company["company_invc_cliente"],
         'company'  => $company["company_invc_cliente"],
         'user'     => $company["company_invc_ftp_user"],
         'password' => $company["company_invc_ftp_pass"],
      );
      $url_completa = $url_base . '?' . http_build_query($parametros);
      $ch = curl_init($url_completa);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Devuelve la respuesta como una cadena
      $respuesta = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      if( $http_code != 200)
      {
         curl_close($ch);
         return null;
      }

      if (curl_errno($ch)) 
      {
         curl_close($ch);
         return null;
      } 
      else 
      {
         $datos = json_decode($respuesta, true);
      }
      curl_close($ch);
      return $datos;   
   }
   //
   // DELIVERY CONTALINE
   //----------------------------------------------------------------------------------
   public function createOrdersDelivery($dlvid, $printmode = 0, $useSubdetailid = 0, $subdetailrows = NULL)
   {
      $currtme          = time();
      $this->_FILE_DIR  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.electr/ordersdelivery/";

      //----------------------------------------------------------------------------------
      $sql = " update orders_delivery
               set
               dlv_itf_trndat = 0
               where
               id = {$dlvid}";
      $this->CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $sql = " select t1.*
               from orders_delivery t1
               where
               t1.id = {$dlvid}";
      $headdata = $this->CON->select($sql);
      $headdata = $headdata[0];

      //----------------------------------------------------------------------------------
      if((int)$printmode)
      {
         if((int)$useSubdetailid == 0)
         {
            $headdata["dlv_delivery_date"]   = $headdata["dlv_modedate"];
            $headdata["dlv_docnum"]          = $headdata["dlv_modenum"];
         }
         else
         {
            $sql = " select t1.*
                     from orders_delivery_redocs t1
                     where
                     t1.dlv_id   = {$dlvid} and
                     t1.id       = {$useSubdetailid}";
            $subdoc = $this->CON->select($sql);
            $subdoc = $subdoc[0];
            $headdata["dlv_delivery_date"]   = $subdoc["dlv_modedate"];
            $headdata["dlv_docnum"]          = $headdata["dlv_modenum"];
         }
      }

      //----------------------------------------------------------------------------------
      $company = getCompanies($this->CON, true, $headdata["dlv_company_id"]);
      $company = $company[0];
      $comp_rut = str_replace(".", "", $company["company_rut"]);

      //----------------------------------------------------------------------------------
      $this->_FILE_SUBDIR  = date('Y-m', $headdata["dlv_delivery_date"]);
      if(!file_exists($this->_FILE_DIR.$this->_FILE_SUBDIR))
      {
         $mkd = mkdir($this->_FILE_DIR.$this->_FILE_SUBDIR);
         chmod($this->_FILE_DIR.$this->_FILE_SUBDIR, 0777);
         if(!$mkd)
            return false;
      }

      //----------------------------------------------------------------------------------
      $this->_FILE_PREFIX  = 52;
      $this->_FILE_NAME    = "{$this->_FILE_PREFIX}_{$headdata["id"]}_{$comp_rut}_{$this->_FILE_HASH}.xml";
      $this->_FILE_FULL    = $this->_FILE_DIR.$this->_FILE_SUBDIR."/".$this->_FILE_NAME;

      $this->_FILE_NAME_JS = "{$this->_FILE_PREFIX}_{$headdata["id"]}_{$comp_rut}_{$this->_FILE_HASH}.json";
      $this->_FILE_FULL_JS = $this->_FILE_DIR.$this->_FILE_SUBDIR."/".$this->_FILE_NAME_JS;

      //----------------------------------------------------------------------------------
      unlink($this->_FILE_FULL);
      unlink($this->_FILE_FULL_JS);

      if($headdata["dlv_itf_file"] != "")
      {
         unlink($this->_FILE_DIR.$this->_FILE_SUBDIR."/".$headdata["dlv_itf_file"]);
         $cadenaOriginal = $headdata["dlv_itf_file"];
         $nuevosCaracteres = ".json";
         $numCaracteresReemplazar = 4;
         $longitudCadena = strlen($cadenaOriginal);
         $cadenaModificada = substr($cadenaOriginal, 0, $longitudCadena - $numCaracteresReemplazar) . $nuevosCaracteres;
         unlink($this->_FILE_DIR.$this->_FILE_SUBDIR."/".$cadenaModificada);
      }
      // $fp = fopen($this->_FILE_FULL, "w");
      $fpjson = fopen($this->_FILE_FULL_JS, "w");

      if($fpjson)
      {
         $resultados = $this->createOrdersDeliveryContentDF($this->CON, $dlvid, $printmode, $useSubdetailid, $subdetailrows, $fpjson);
         $fp         = $resultados[0];
         $fpjson     = $resultados[1];

         // fclose($fp);
         fclose($fpjson);
         chmod($this->_FILE_FULL, 0777);

         //----------------------------------------------------------------------------------
         $sql_file = trim(addslashes($this->_FILE_NAME));
         $sql_hash = trim(addslashes($this->_FILE_HASH));

         $sql = " update orders_delivery
                  set
                  dlv_itf_file     = '{$sql_file}',
                  dlv_itf_hash     = '{$sql_hash}',
                  dlv_itf_crtdat   = {$currtme},
                  dlv_itf_crtusr   = {$headdata["dlv_crtusr"]}
                  where
                  id = {$dlvid}";
         $this->CON->no_result($sql);
         //----------------------------------------------------------------------------------
         $trn = $this->transferFileDF($this->_FILE_DIR.$this->_FILE_SUBDIR."/", $this->_FILE_NAME_JS, $headdata["dlv_docnum"], $company, $this->_FILE_PREFIX, 'Save');
         //----------------------------------------------------------------------------------
         $currtme = time();
         if((int)$trn["result"]==0)
         {
            $dlv_sgntr_message  = trim(addslashes(mb_convert_encoding($trn["message"], "ISO-8859-1", "UTF-8"))); // trim(addslashes($trn["message"]));
            $dlv_sgntr_end      = 0;
            if($headdata["dlv_sgntr_check"] >= 2)
               $dlv_sgntr_end = 1;

            $sql = " update orders_delivery
                     set
                     dlv_itf_trndat      = 0,
                     dlv_sgntr_check     = dlv_sgntr_check + 1,
                     dlv_sgntr_end       = {$dlv_sgntr_end},
                     dlv_sgntr_message   = '{$dlv_sgntr_message}'
                     where
                     id = {$dlvid}";
            $this->CON->no_result($sql);

            return false;
         }
         //----------------------------------------------------------------------------------
         $dlv_sgntr_message  = trim(addslashes($trn["message"]));
         $dlv_sgntr_doc1     = trim(addslashes($trn["doc1"]));
         $dlv_sgntr_doc2     = trim(addslashes($trn["doc2"]));

         $sql = " update orders_delivery
                  set
                  dlv_itf_trndat       = {$currtme},
                  dlv_sgntr_end        = 1,
                  dlv_sgntr_doc1       = '{$dlv_sgntr_doc1}',
                  dlv_sgntr_doc2       = '{$dlv_sgntr_doc2}',
                  dlv_sgntr_message    = '{$dlv_sgntr_message}'
                  where
                  id = {$dlvid}";
         $this->CON->no_result($sql);

         return true;
      }
      return false;
   }
   //-------------------------------------------------------------------------------------------------------------------------
   public function createInvoiceSellNote($noteid)
   {
      $currtme          = time();
      $this->_FILE_DIR  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.electr/invoicesellnote/";

     //----------------------------------------------------------------------------------
      $sql = " update invoices_notes_sell
               set
               note_itf_trndat = 0
               where
               id = {$noteid}";
      $this->CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $sql = " select t1.*
               from invoices_notes_sell t1
               where
               t1.id = {$noteid}";
      $headdata = $this->CON->select($sql);
      $headdata = $headdata[0];

      $company = getCompanies($this->CON, true, $headdata["note_company_id"]);
      $company = $company[0];
      $comp_rut = str_replace(".", "", $company["company_rut"]);

      //----------------------------------------------------------------------------------
      $this->_FILE_SUBDIR  = date('Y-m', $headdata["note_date"]);
      if(!file_exists($this->_FILE_DIR.$this->_FILE_SUBDIR))
      {
         $mkd = mkdir($this->_FILE_DIR.$this->_FILE_SUBDIR);
         chmod($this->_FILE_DIR.$this->_FILE_SUBDIR, 0777);
         if(!$mkd)
            return false;
      }
      //----------------------------------------------------------------------------------
      if((int)$headdata["note_type"] == 1)
      {
         $this->_FILE_PREFIX = 61;
         if((int)$headdata["note_issueid"] == 1)
            $accion = "SaveCreditNo";
         else
            if((int)$headdata["note_issueid"] == 2)
            
               $accion = "SaveGlossCre ";
            else
                $accion = "SaveTypeCred";
      }
      else
      {
         $this->_FILE_PREFIX = 56;
         $accion = "SaveDebitNot";
      }

      //----------------------------------------------------------------------------------
      $this->_FILE_NAME = "{$this->_FILE_PREFIX}_{$headdata["id"]}_{$comp_rut}_{$this->_FILE_HASH}.xml";
      $this->_FILE_FULL = $this->_FILE_DIR.$this->_FILE_SUBDIR."/".$this->_FILE_NAME;

      $this->_FILE_NAME_JS = "{$this->_FILE_PREFIX}_{$headdata["id"]}_{$comp_rut}_{$this->_FILE_HASH}.json";
      $this->_FILE_FULL_JS = $this->_FILE_DIR.$this->_FILE_SUBDIR."/".$this->_FILE_NAME_JS;

      //----------------------------------------------------------------------------------
      unlink($this->_FILE_FULL);
      unlink($this->_FILE_FULL_JS);

      if($headdata["note_itf_file"] != "")
      {
         unlink($this->_FILE_DIR.$this->_FILE_SUBDIR."/".$headdata["note_itf_file"]);

         $cadenaOriginal = $headdata["note_itf_file"];
         $nuevosCaracteres = ".json";
         $numCaracteresReemplazar = 4;
         $longitudCadena = strlen($cadenaOriginal);
         $cadenaModificada = substr($cadenaOriginal, 0, $longitudCadena - $numCaracteresReemplazar) . $nuevosCaracteres;
         unlink($this->_FILE_DIR.$this->_FILE_SUBDIR."/".$cadenaModificada);
      }

      // $fp = fopen($this->_FILE_FULL, "w");
      $fpjson = fopen($this->_FILE_FULL_JS, "w");

      if($fpjson)
      {
         $resultados = $this->createInvoiceSellNoteContentDF($this->CON, $noteid, $fpjson);
         $fp     = $resultados[0];
         $fpjson = $resultados[1];

         // fclose($fp);
         fclose($fpjson);

         chmod($this->_FILE_FULL, 0777);

         //----------------------------------------------------------------------------------
         $sql_file = trim(addslashes($this->_FILE_NAME));
         $sql_hash = trim(addslashes($this->_FILE_HASH));
         $sql = " update invoices_notes_sell
                  set
                  note_itf_file     = '{$sql_file}',
                  note_itf_hash     = '{$sql_hash}',
                  note_itf_crtdat   = {$currtme},
                  note_itf_crtusr   = {$headdata["note_crtusr"]}
                  where
                  id = {$noteid}";
         $this->CON->no_result($sql);
         //----------------------------------------------------------------------------------
         $trn = $this->transferFileDF($this->_FILE_DIR.$this->_FILE_SUBDIR."/", $this->_FILE_NAME_JS, $headdata["note_docnumber"], $company, $this->_FILE_PREFIX, $accion);
         //----------------------------------------------------------------------------------
         $currtme = time();
         if(!$trn["result"])
         {
            $note_sgntr_message = trim(addslashes(mb_convert_encoding($trn["message"], "ISO-8859-1", "UTF-8")));
            $note_sgntr_end      = 0;
            if($headdata["note_sgntr_check"] >= 2)
               $note_sgntr_end = 1;

            $sql = " update invoices_notes_sell
                     set
                     note_itf_trndat      = 0,
                     note_sgntr_check     = note_sgntr_check + 1,
                     note_sgntr_end       = {$note_sgntr_end},
                     note_sgntr_message   = '{$note_sgntr_message}'
                     where
                     id = {$noteid}";
            $this->CON->no_result($sql);

            return false;
         }

         //----------------------------------------------------------------------------------
         $note_sgntr_message  = trim(addslashes($trn["message"]));
         $note_sgntr_doc1    = trim(addslashes($trn["doc1"]));
         $note_sgntr_doc2    = trim(addslashes($trn["doc2"]));

         $sql = " update invoices_notes_sell
                  set
                  note_itf_trndat      = {$currtme},
                  note_sgntr_end       = 1,
                  note_sgntr_doc1      = '{$note_sgntr_doc1}',
                  note_sgntr_doc2      = '{$note_sgntr_doc2}',
                  note_sgntr_message   = '{$note_sgntr_message}'
                  where
                  id = {$noteid}";
         $this->CON->no_result($sql);

         return true;
      }
      return false;
   }
   //----------------------------------------------------------------------------------
   public function createInvoiceSell($invcid, $isboleta = 0)
   {
      $currtme          = time();
      $this->_FILE_DIR  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.electr/invoicesell/";

      $_tablename = "invoices_sell";
      if($isboleta)
         $_tablename = "invoices_sell_bol";
         
      //----------------------------------------------------------------------------------
      $sql = " update {$_tablename}
               set
               invc_itf_trndat = 0
               where
               id = {$invcid}";
      $this->CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $sql = " select t1.*
               from {$_tablename} t1
               where
               t1.id = {$invcid}";
      $headdata = $this->CON->select($sql);
      $headdata = $headdata[0];

      $company = getCompanies($this->CON, true, $headdata["invc_company_id"]);
      $company = $company[0];
      $comp_rut = str_replace(".", "", $company["company_rut"]);
      
      //----------------------------------------------------------------------------------
      $this->_FILE_SUBDIR  = date('Y-m', $headdata["invc_date"]);
      if(!file_exists($this->_FILE_DIR.$this->_FILE_SUBDIR))
      {
         $mkd = mkdir($this->_FILE_DIR.$this->_FILE_SUBDIR);
         chmod($this->_FILE_DIR.$this->_FILE_SUBDIR, 0777);
         if(!$mkd)
            return false;
      }

      //----------------------------------------------------------------------------------
      if((int)$headdata["invc_taxes"])
      {
         $this->_FILE_PREFIX = 33;
         if($isboleta)
            $this->_FILE_PREFIX = 39;
      }
      else
      {
         $this->_FILE_PREFIX = 34;
         if($isboleta)
            $this->_FILE_PREFIX = 41;
      }

      //----------------------------------------------------------------------------------
      $this->_FILE_NAME = "{$this->_FILE_PREFIX}_{$headdata["id"]}_{$comp_rut}_{$this->_FILE_HASH}.xml";
      $this->_FILE_FULL = $this->_FILE_DIR.$this->_FILE_SUBDIR."/".$this->_FILE_NAME;

      $this->_FILE_NAME_JS = "{$this->_FILE_PREFIX}_{$headdata["id"]}_{$comp_rut}_{$this->_FILE_HASH}.json";
      $this->_FILE_FULL_JS = $this->_FILE_DIR.$this->_FILE_SUBDIR."/".$this->_FILE_NAME_JS;

      //----------------------------------------------------------------------------------
      unlink($this->_FILE_FULL);
      unlink($this->_FILE_FULL_JS);
      if($headdata["invc_itf_file"] != "")
      {
         unlink($this->_FILE_DIR.$this->_FILE_SUBDIR."/".$headdata["invc_itf_file"]);
         $cadenaOriginal = $headdata["invc_itf_file"];
         $nuevosCaracteres = ".json";
         $numCaracteresReemplazar = 4;
         $longitudCadena = strlen($cadenaOriginal);
         $cadenaModificada = substr($cadenaOriginal, 0, $longitudCadena - $numCaracteresReemplazar) . $nuevosCaracteres;
         unlink($this->_FILE_DIR.$this->_FILE_SUBDIR."/".$cadenaModificada);
      }
      // $fp = fopen($this->_FILE_FULL, "w");
      $fpjson = fopen($this->_FILE_FULL_JS, "w");
      if($fpjson)
      {
         $resultados = $this->createInvoiceSellContentDF($this->CON, $invcid, $isboleta, $fpjson);
         $fp         = $resultados[0];
         $fpjson = $resultados[1];
         
         // fclose($fp);
         fclose($fpjson);

         chmod($this->_FILE_FULL, 0777);

         //----------------------------------------------------------------------------------
         $sql_file = trim(addslashes($this->_FILE_NAME));
         $sql_hash = trim(addslashes($this->_FILE_HASH));
         
         $sql = " update {$_tablename}
                  set
                  invc_itf_file     = '{$sql_file}',
                  invc_itf_hash     = '{$sql_hash}',
                  invc_itf_crtdat   = {$currtme},
                  invc_itf_crtusr   = {$headdata["invc_crtusr"]}
                  where
                  id = {$invcid}";
         $this->CON->no_result($sql);

         // para DEFONTANA se utiliza este metodo
         $Folio   = $headdata["invc_docnumber"];
         //----------------------------------------------------------------------------------
         $trn = $this->transferFileDF($this->_FILE_DIR.$this->_FILE_SUBDIR."/", $this->_FILE_NAME_JS, $Folio, $company, $this->_FILE_PREFIX, 'SaveSale');
         //----------------------------------------------------------------------------------
         $currtme = time();
         if(!$trn["result"])
         {
            $invc_sgntr_message  = trim(addslashes(mb_convert_encoding($trn["message"], "ISO-8859-1", "UTF-8"))); // trim(addslashes($trn["message"]));
           
            $invc_sgntr_end      = 0;
            if($headdata["invc_sgntr_check"] >= 2)
               $invc_sgntr_end = 1;

            $sql = " update {$_tablename}
                     set
                     invc_itf_trndat      = 0,
                     invc_sgntr_check     = invc_sgntr_check + 1,
                     invc_sgntr_end       = {$invc_sgntr_end},
                     invc_sgntr_message   = '{$invc_sgntr_message}'
                     where
                     id = {$invcid}";
            $this->CON->no_result($sql);

            return false;
         }
         //----------------------------------------------------------------------------------
         $invc_sgntr_message  = trim(addslashes($trn["message"]));
         $invc_sgntr_doc1     = trim(addslashes($trn["doc1"]));
         $invc_sgntr_doc2     = trim(addslashes($trn["doc2"]));
         $sql = " update {$_tablename}
                  set
                  invc_itf_trndat      = {$currtme},
                  invc_sgntr_end       = 1,
                  invc_sgntr_doc1      = '{$invc_sgntr_doc1}',
                  invc_sgntr_doc2      = '{$invc_sgntr_doc2}',
                  invc_sgntr_message   = '{$invc_sgntr_message}'
                  where
                  id = {$invcid}";
         $this->CON->no_result($sql);

         return true;
      }
      return false;
      
   }
   //----------------------------------------------------------------------------------
   private function printBNCLine($fp, $_LINEARR, $ltrm, $taginit = "<Detalle>", $tagend = "</Detalle>")
   {
      fwrite($fp, "{$taginit}{$ltrm}");
      foreach(array_keys($_LINEARR) AS $posfield)

      {
         $_LINEARR[$posfield] = $this->cleanXMLData($_LINEARR[$posfield]);
         if($posfield == "CdgItem")
         {
            fwrite($fp, "<CdgItem>{$ltrm}");
            fwrite($fp, "<TpoCodigo>INT1</TpoCodigo>{$ltrm}");
            fwrite($fp, "<VlrCodigo>{$_LINEARR[$posfield]}</VlrCodigo>{$ltrm}");
            fwrite($fp, "</CdgItem>{$ltrm}");
         }
         else 
         {
            fwrite($fp, "<{$posfield}>{$_LINEARR[$posfield]}</{$posfield}>{$ltrm}");
         }
      }      
      fwrite($fp, "{$tagend}{$ltrm}");
      return $fp;
   }
   //----------------------------------------------------------------------------------
   public function cleanXMLData($val)
   {
      $val = str_replace(">", "", $val);
      $val = str_replace("<", "", $val);
      $val = str_replace("'", "", $val);
      $val = str_replace('"', "", $val);
      $val = str_replace("&", "", $val);
      $val = str_replace("?", "Ae", $val);
      $val = str_replace("?", "Ue", $val);
      $val = str_replace("?", "Oe", $val);
      $val = str_replace("?", "ae", $val);
      $val = str_replace("?", "ue", $val);
      $val = str_replace("?", "oe", $val);
      $val = str_replace("?", "ss", $val);
      $val = str_replace("?", "n", $val);
      $val = str_replace("?", "N", $val);
      $val = str_replace("?", "A", $val);
      $val = str_replace("?", "a", $val);
      $val = str_replace("?", "c", $val);
      $val = str_replace("?", "E", $val);
      $val = str_replace("?", "e", $val);
      $val = str_replace("?", "I", $val);
      $val = str_replace("?", "i", $val);
      $val = str_replace("?", "O", $val);
      $val = str_replace("?", "o", $val);
      $val = str_replace("?", "U", $val);
      $val = str_replace("?", "u", $val);

      $val = preg_replace("/[^a-zA-Z0-9 -_]/", "", $val);
   
      return $val;
   }

//---------------------------------------------------------------------------------
// CREACION DEL CONTENIDO DE GUIAS DE DESPACHO EN XML Y JSON
//----------------------------------------------------------------------------------
private function createOrdersDeliveryContentDF($CON, $dlvid, $printmode = 0, $useSubdetailid = 0, $subdetailrows = NULL, $fpjson )
{
      $sql = " select t1.*, t3.req_number, t3.req_order_shipped, t4.company_short, t5.shop_name, t8.trans_name, t9.pay_title, t9.pay_cod_contable,
                       t10.user_firstname 'seller_firstname', t10.user_lastname 'seller_lastname'
               from orders_delivery t1
               LEFT OUTER JOIN orders t3           ON t1.dlv_order_id      = t3.id
               LEFT OUTER JOIN company_data t4     ON t1.dlv_company_id    = t4.id
               LEFT OUTER JOIN company_shops t5    ON t1.dlv_shop_id       = t5.id
               LEFT OUTER JOIN transports t8       ON t1.dlv_transportid   = t8.id
               LEFT OUTER JOIN payments t9         ON t1.dlv_paymentid     = t9.id
               LEFT OUTER JOIN user t10            ON t1.dlv_userid_seller = t10.id
               where
               t1.id = {$dlvid}";
      $headdata = $this->CON->select($sql);
      $headdata = $headdata[0];

      if(!(int)$headdata["id"])
      {
         $sql = " select t1.*, t4.company_short, t5.shop_name, t8.trans_name, t9.pay_title, t9.pay_cod_contable, 
                         t10.user_firstname 'seller_firstname', t10.user_lastname 'seller_lastname'
                  from orders_delivery t1
                  LEFT OUTER JOIN company_data t4     ON t1.dlv_company_id    = t4.id
                  LEFT OUTER JOIN company_shops t5    ON t1.dlv_shop_id       = t5.id
                  LEFT OUTER JOIN transports t8       ON t1.dlv_transportid   = t8.id
                  LEFT OUTER JOIN payments t9         ON t1.dlv_paymentid     = t9.id
                  LEFT OUTER JOIN user t10            ON t1.dlv_userid_seller = t10.id
                  where
                  t1.id = {$dlvid}";
         $headdata = $this->CON->select($sql);
         $headdata = $headdata[0];
      }
      //----------------------------------------------------------------------------------
      $headerdate = time();
      if($printmode && is_array($subdetailrows))
      {
         foreach($subdetailrows AS $numpos)
         {
            if($numpos["id"] == $useSubdetailid)
            {
               $useposdata = $numpos;
               $hassubdata = true;

               $headdata["dlv_delivery_date"]   = $useposdata["dlv_modedate"];
               $headerdate                      = $useposdata["dlv_modedate"];
               $headdata["dlv_modetext"]        = $useposdata["dlv_modetext"];
               $headdata["dlv_docnum"]          = $useposdata["dlv_modenum"];
            }
         }
      }
   
      if($headdata["dlv_mode"] <= 2)
      {
         //----------------------------------------------------------------------------------
         $sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.giro_name
                  from customer t1
                  LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
                  LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
                  LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
                  LEFT OUTER JOIN giros   t5 ON t1.cust_giroid    = t5.id
                  where
                  t1.id = {$headdata["dlv_cust_id"]}";
         $customer = $this->CON->select($sql);
         $customer = $customer[0];

         //----------------------------------------------------------------------------------
         if((int)$headdata["dlv_cust_delivid"])
         {
            $sql = " select t1.delivery_name 'cust_company', t1.delivery_street 'cust_street', t5.cust_rut, t2.country_name, t3.name, t4.nombre, t6.giro_name
                     from customer_deliveryaddr t1
                     LEFT OUTER JOIN country t2    ON t1.delivery_countryid = t2.id
                     LEFT OUTER JOIN regions t3    ON t1.delivery_regionid  = t3.id
                     LEFT OUTER JOIN comunas t4    ON t1.delivery_comunaid  = t4.id
                     LEFT OUTER JOIN customer t5   ON t1.cust_id            = t5.id
                     LEFT OUTER JOIN giros   t6    ON t5.cust_giroid        = t6.id
                     where
                     t1.id = {$headdata["dlv_cust_delivid"]}
                     order by t1.id asc";
            $deliveryaddr = $this->CON->select($sql);
            $deliveryaddr = $deliveryaddr[0];
    
            $destino_street = $deliveryaddr["cust_street"];
            $destino_comuna = $deliveryaddr["nombre"];
            $destino_region = $deliveryaddr["name"];
         }
         else
         {
            $destino_street = $customer["cust_street"];
            $destino_comuna = $customer["nombre"];
            $destino_region = $customer["name"];
         }
      }
      else
      {
         $sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.giro_name
                  from supplier t1
                  LEFT OUTER JOIN country t2 ON t1.supp_countryid = t2.id
                  LEFT OUTER JOIN regions t3 ON t1.supp_regionid  = t3.id
                  LEFT OUTER JOIN comunas t4 ON t1.supp_comunaid  = t4.id
                  LEFT OUTER JOIN giros   t5 ON t1.supp_giroid    = t5.id
                  where
                  t1.id = {$headdata["dlv_supplier_id"]}";
         $supplier = $this->CON->select($sql);
         $supplier = $supplier[0];
         
         $customer["cust_company"]  = $supplier["supp_company"];
         $customer["cust_rut"]      = $supplier["supp_rut"];
         $customer["cust_street"]   = $supplier["supp_street"];
         $customer["cust_phone"]    = $supplier["supp_phone"];
         $customer["name"]          = $supplier["name"];
         $customer["cust_fax"]      = $supplier["supp_fax"];
         $customer["nombre"]        = $supplier["nombre"];
         $customer["cust_email"]    = $supplier["supp_email"];
         $customer["country_name"]  = $supplier["country_name"];
         $customer["giro_name"]     = $supplier["giro_name"];

         $destino_street = $customer["cust_street"];
         $destino_comuna = $customer["nombre"];
         $destino_region = $customer["name"];
      }

      $company = getCompanies($this->CON, true, $headdata["dlv_company_id"]);
      $company = $company[0];
      $shop    = getShops($this->CON, false, false, 0, $headdata["dlv_shop_id"]);
      $shop    = $shop[0];

      //----------------------------------------------------------------------------------
      if($customer["name"] == "METROPOLITANA DE SANTIAGO")
         $customer["name"] = "SANTIAGO";
      if($customer["name"] != "SANTIAGO")
         $customer["name"] = $customer["nombre"];
         
      //----------------------------------------------------------------------------------
      if($destino_region == "METROPOLITANA DE SANTIAGO")
         $destino_region = "SANTIAGO";
      if($destino_region != "SANTIAGO")
         $destino_region = $destino_comuna;

      //----------------------------------------------------------------------------------
      if($company["region"] == "METROPOLITANA DE SANTIAGO")
         $company["region"] = "SANTIAGO";
      if($company["region"] != "SANTIAGO")
         $company["region"] = $company["comuna"];

      //----------------------------------------------------------------------------------
      if($shop["region"] == "METROPOLITANA DE SANTIAGO")
         $shop["region"] = "SANTIAGO";
      if($shop["region"] != "SANTIAGO")
         $shop["region"] = $shop["comuna"];

      //----------------------------------------------------------------------------------
      $posdata = getOrderDeliveryPos($this->CON, $dlvid);

      if($headdata["dlv_invoice_generated"] > 0)
      {
         $dlv_date = date('Y-m-d', $headerdate);
         $a1 = date('d-m-Y', $headerdate);
      }
      else
      {
         $dlv_date = date('Y-m-d', $headdata["dlv_delivery_date"]);
         $a1 = date('d-m-Y', $headdata["dlv_delivery_date"]);
      }

      //----------------------------------------------------------------------------------
      $_DOCREFS = Array();
      if($headdata["dlv_oc_number"] != "" && (int)$headdata["dlv_oc_dat"])
      {
         $temp["DOCNUM"]   = $headdata["dlv_oc_number"];
         $temp["DOCTYPE"]  = "801";
         $temp["DOCDAT"]   = date('Y-m-d', $headdata["dlv_oc_dat"]);
         $temp["DOCCAUSE"] = "";
         $_DOCREFS[]       = $temp;
      }
      if($headdata["dlv_invoice_generated"] > 0)
      {
         $sql = " select *
                  from invoices_sell
                  where
                  id = {$headdata["dlv_invoice_generated"]}";
         $refinvoice = $this->CON->select($sql);
         $refinvoice = $refinvoice[0];
         if($refinvoice["invc_docnumber"] != "" && (int)$refinvoice["invc_date"])
         {
            $temp["DOCNUM"]   = $refinvoice["invc_docnumber"];
            if((int)$refinvoice["invc_taxes"])
               $temp["DOCTYPE"] = "33";
            else
               $temp["DOCTYPE"] = "34";
            $temp["DOCDAT"]   = date('Y-m-d', $refinvoice["invc_date"]);
            //$temp["DOCCAUSE"] = "FACTURA";
            $temp["DOCCAUSE"] = "";
            $_DOCREFS[]       = $temp;
         }
      }

      //----------------------------------------------------------------------------------
      $dlvmode = 1;
      switch($headdata["dlv_mode"])
      {
         case 0: $dlvmode = 1; break;
         case 1: $dlvmode = 1; break;
         case 2: $dlvmode = 6; break;
         case 3: $dlvmode = 6; break;
         case 4: $dlvmode = 6; break;
      }
      
      //----------------------------------------------------------------------------------
      $ltrm = "\n";

      $FchDia = (int)date('d', $headdata["dlv_delivery_date"]);
      $FchMes = (int)date('m', $headdata["dlv_delivery_date"]);
      $FchAno = (int)date('Y', $headdata["dlv_delivery_date"]);

      //----------------------------------------------------------------------------------
      fwrite($fpjson, '{'.$ltrm);
      //----------------------------------------------------------------------------------
      $TipoDTE = $this->_FILE_PREFIX;
      $Folio   = $headdata["dlv_docnum"];
      $FchEmis = $dlv_date;
		//----------------------------------------------------------------------------------
		$RUTEmisor      = str_replace(".", "", $company["company_rut"]);
		$RznSoc         = $this->cleanXMLData($company["company_name"]);
		$GiroEmis       = $this->cleanXMLData(substr($shop["shop_giro"],0,39));
		$DirOrigen      = $this->cleanXMLData($shop["shop_street"]);
		$CmnaOrigen     = $this->cleanXMLData($shop["comuna"]);
		$CiudadOrigen   = $this->cleanXMLData($shop["region"]);
		//----------------------------------------------------------------------------------
		$RUTRecep    = str_replace(".", "", $customer["cust_rut"]);
      $RUTRecep    = $this->formatearRut($RUTRecep);
		$RznSocRecep = $this->cleanXMLData(substr($customer["cust_company"],0,99));
		$GiroRecep   = $this->cleanXMLData(substr($customer["giro_name"],0,39));
		$Contacto    = $this->cleanXMLData($customer["cust_phone"]);
		$DirRecep    = $this->cleanXMLData(substr($destino_street,0,50));
		$CmnaRecep   = $this->cleanXMLData(substr($destino_comuna,0,19));
		$CiudadRecep = $this->cleanXMLData(substr($destino_region,0,19));
      $vendedor    = $this->cleanXMLData(substr($headdata["dlv_userid_seller"],0,19));
      if($vendedor == "0")
         $vendedor = 'VENDEDOR';

       /* DEFONATANA */

      fwrite($fpjson, '"documentType": "GDVELECT",'.$ltrm);
      fwrite($fpjson, '"firstFolio": '.$Folio.','.$ltrm);
      fwrite($fpjson, '"lastFolio": '.$Folio.','.$ltrm);
      fwrite($fpjson, '"externalDocumentID": "",'.$ltrm);
      fwrite($fpjson, '"emissionDate": {'.$ltrm);
      fwrite($fpjson, '"day": '.$FchDia.','.$ltrm);
      fwrite($fpjson, '"month": '.$FchMes.','.$ltrm);
      fwrite($fpjson, '"year": '.$FchAno.' '.$ltrm);
      fwrite($fpjson, '},'.$ltrm);
      fwrite($fpjson, '"firstFeePaid": {'.$ltrm);
      fwrite($fpjson, '"day": '.$FchDia.','.$ltrm);
      fwrite($fpjson, '"month": '.$FchMes.','.$ltrm);
      fwrite($fpjson, '"year": '.$FchAno.' '.$ltrm);
      fwrite($fpjson, '},'.$ltrm);

      /* FIN LINEA DEFONTANA */

      //----------------------------------------------------------------------------------
      if((int)$headdata["dlv_taxes"])
      {
         $MntNeto    = (int)$headdata["dlv_total_netto"];
         $MntExe     = 0;
         $TasaIVA    = (int)$_SESSION["_CONF"]["conf_taxes"];
         $IVA        = (int)$headdata["dlv_total_taxes"];
         $MntTotal   = (int)$headdata["dlv_total_brutto"];
         $_INDIC_EXENCION = "";
      }
      else
      {
         $MntNeto    = 0;
         $MntExe     = (int)$headdata["dlv_total_brutto"];
         $TasaIVA    = 0;
         $IVA        = 0;
         $MntTotal   = (int)$headdata["dlv_total_brutto"];   
         $_INDIC_EXENCION = "1";
      }
      if($headdata["pay_cod_contable"]=="")
         $headdata["pay_cod_contable"] = "";

      fwrite($fpjson, '"clientFile": "'.$RUTRecep.'",'.$ltrm);
      fwrite($fpjson, '"contactIndex": "'.$DirRecep.'",'.$ltrm);

      if($headdata["pay_cod_contable"]=="")
         fwrite($fpjson, '"paymentCondition": "CONTADO" ,'.$ltrm);
      else
         fwrite($fpjson, '"paymentCondition": "'.$headdata["pay_cod_contable"].'" ,'.$ltrm);
      fwrite($fpjson, '"sellerFileId": "'.$vendedor.'",'.$ltrm);
      fwrite($fpjson, '"clientAnalysis": {'.$ltrm);
           fwrite($fpjson, '"accountNumber": "'.$this->accountNumber1.'",'.$ltrm);
           fwrite($fpjson, '"businessCenter": "'.$this->businessCenter.'",'.$ltrm);
           fwrite($fpjson, '"classifier01": "",'.$ltrm);
           fwrite($fpjson, '"classifier02": ""'.$ltrm);
      fwrite($fpjson, '},'.$ltrm);

      fwrite($fpjson, '"billingCoin": "PESO",'.$ltrm);
      fwrite($fpjson, '"billingRate": 1,'.$ltrm);
      fwrite($fpjson, '"shopId": "Local",'.$ltrm);
      fwrite($fpjson, '"priceList": "1",'.$ltrm);
      fwrite($fpjson, '"giro": "'.$GiroRecep.'",'.$ltrm);
      fwrite($fpjson, '"district": "'.$DirRecep.'",'.$ltrm);
      fwrite($fpjson, '"city": "'.$CiudadRecep.'",'.$ltrm);
      fwrite($fpjson, '"contact": -1,'.$ltrm);
      fwrite($fpjson,'"attachedDocuments": [],'.$ltrm);

      fwrite($fpjson,'"originStorage": {'.$ltrm);
      fwrite($fpjson,'"code": "BODEGACENTRAL",'.$ltrm);
      fwrite($fpjson,'"motive": "BODEGACENTRAL",'.$ltrm);
      fwrite($fpjson,'"storageAnalysis": {'.$ltrm);
      fwrite($fpjson,'"accountNumber": "'.$this->accountNumber3.'",'.$ltrm);
      fwrite($fpjson,'"businessCenter": "'.$this->businessCenter.'",'.$ltrm);
      fwrite($fpjson,'"classifier01": "",'.$ltrm);
      fwrite($fpjson,'"classifier02": ""'.$ltrm);
      fwrite($fpjson,'}'.$ltrm);
      fwrite($fpjson,'},'.$ltrm);

      fwrite($fpjson,'"destinationStorage": {'.$ltrm);
      fwrite($fpjson,'"code": "BODEGACENTRAL",'.$ltrm);
      fwrite($fpjson,'"motive": "BODEGACENTRAL",'.$ltrm);
      fwrite($fpjson,'"storageAnalysis": {'.$ltrm);
      fwrite($fpjson,'"accountNumber": "",'.$ltrm);
      fwrite($fpjson,'"businessCenter": "",'.$ltrm);
      fwrite($fpjson,'"classifier01": "",'.$ltrm);
      fwrite($fpjson,'"classifier02": ""'.$ltrm);
      fwrite($fpjson,'}'.$ltrm);
      fwrite($fpjson,'},'.$ltrm);

      fwrite($fpjson,'"dispatchInfo": {'.$ltrm);
      fwrite($fpjson,'"assetsType": "'.$dlvmode.'",'.$ltrm);
      fwrite($fpjson,'"dispatchType": "1",'.$ltrm);
      fwrite($fpjson,'"transactionType": "1",'.$ltrm);
      fwrite($fpjson,'"isTransferDispatch": true'.$ltrm);
      fwrite($fpjson,'},'.$ltrm);
     
      fwrite($fpjson,'"details": ['.$ltrm);
      //----------------------------------------------------------------------------------
      $itemlines  = 0;
      $nlinea     = 1;
      if($printmode)
      {
         if($headdata["dlv_modetext"] != "")
         {
            $marr = explode("\n", $headdata["dlv_modetext"]);
            foreach($marr AS $mrow)
            {
               unset($_LINEARR);

               $_LINEARR["NroLinDet"]              = $nlinea;
               $_LINEARR["CdgItem"]                = "";
               $_LINEARR["NmbItem"]                = substr(trim($mrow), 0, 80);
               $_LINEARR["QtyItem"]                = "";
               $_LINEARR["UnmdItem"]               = "";
               $_LINEARR["PrcItem"]                = "";
               $_LINEARR["MontoItem"]              = 0;
               $nlinea++;
            }
         }
         else
         {
            if($headdata["dlv_invoice_generated"] > 0)
            {
               $sql = " select invc_docnumber
                        from invoices_sell
                        where
                        id = {$headdata["dlv_invoice_generated"]} and
                        invc_status > 0";
               $refinvcdoc = $this->CON->select($sql);
               $refinvcdoc = $refinvcdoc[0]["invc_docnumber"];
            }
            else
            {
               $sql = " select t1.invc_docnumber
                        from invoices_sell t1
                        INNER JOIN invoices_sell_parts t2 ON t1.id = t2.part_invc_id
                        where
                        t1.invc_status > 0 and
                        t2.part_dlv_id = {$headdata["id"]}";
               $refinvcdoc = $this->CON->select($sql);
               $refinvcdoc = $refinvcdoc[0]["invc_docnumber"];
            }

            unset($_LINEARR);

            $_LINEARR["NroLinDet"]              = $nlinea;
            $_LINEARR["CdgItem"]                = "";
            $_LINEARR["NmbItem"]                = "GUIA SIN VALOR COMERCIAL";  // DEBO BUSCAR EL CODIGO
            $_LINEARR["QtyItem"]                = "";
            $_LINEARR["UnmdItem"]               = "";
            $_LINEARR["PrcItem"]                = "";
            $_LINEARR["MontoItem"]              = 0;
            $nlinea++;
            $_LINEARR["NroLinDet"]              = $nlinea;
            $_LINEARR["NmbItem"]                = "EMITIDA PARA RESPALDO FACTURA {$refinvcdoc}";
            $nlinea++;
         }
      }

      $fin = "},";
      for($y = 0; $y < count($posdata) && $posdata != false; $y++)
      {
         if( count($posdata) - $y == 1)
         {
            $fin = "}";
         }
         if($posdata[$y]["item_amount_shipped"] > 0)
         {
            $item_price                = $posdata[$y]["item_sellprice_netto_dsc"] / $posdata[$y]["item_amount"];

            $item_number_prod          = reformatProdNumber($posdata[$y]["item_number_prod"]);
            $item_title                = $posdata[$y]["item_title"];
            $unit_name                 = $posdata[$y]["unit_name"];
            $item_amount               = getPrice(printPrice($posdata[$y]["item_amount_shipped"],2),2);
            $item_sellprice_netto      = getPrice(printPrice($posdata[$y]["item_sellprice_netto"]));
            $item_sellprice_brutto     = getPrice(printPrice($posdata[$y]["item_sellprice_brutto"]));
            $dscarr                    = explode("-",$posdata[$y]["_dsc_str"]);
            $item_sellprice_netto_dsc  = getPrice(printPrice($posdata[$y]["item_sellprice_netto_dsc"]));
            $item_sellprice_brutto_dsc = getPrice(printPrice($posdata[$y]["item_sellprice_brutto_dsc"]));
            $item_sell_discount        = round(($item_amount * $item_sellprice_netto) - $item_sellprice_netto_dsc,2);
            $item_sell_discount_perc   = round($item_sell_discount / ($item_amount * $item_sellprice_netto) * 100,2);
            $item_sell_discount_brutto       = round(($item_amount * $item_sellprice_brutto) - $item_sellprice_brutto_dsc,2);
            $item_sell_discount_perc_brutto  = round($item_sell_discount_brutto / ($item_amount * $item_sellprice_brutto) * 100,2);

            fwrite($fpjson,'{'.$ltrm);
            fwrite($fpjson,'"type": "A",'.$ltrm);
            fwrite($fpjson,'"isExempt": false,'.$ltrm);
            fwrite($fpjson,'"code": "'.$item_number_prod.'",'.$ltrm);
            fwrite($fpjson,'"count": '.$item_amount.','.$ltrm);

            if((int)$headdata["dlv_order_id"])
            {
               $sql = " select t1.*, t4.cust_name,  t2.*
                        from orders t1
                        INNER JOIN orders_items t2 ON t1.id = t2.req_id
                        INNER JOIN customer t4     ON t1.req_cust_id = t4.id
                        where
                        t1.id = {$headdata["dlv_order_id"]} and
                        t1.req_isfabricate = 1";
               $orderinfo = $CON->select($sql);
               $orderinfo = $orderinfo[0];

               if((int)$orderinfo["id"] && (int)$orderinfo["item_id"] == $posdata[$y]["item_id"] && !$_HASFABITEMFOUND)
               {
                  $lindesc  = $posdata[$y]["item_title"]."/";
                  $lindesc .= $orderinfo["fab_type"]."/";
                  $lindesc .= (int)$orderinfo["fab_mat_gramms"]."/";
                  $lindesc .= (int)$orderinfo["fab_med_width"]."x".(int)$orderinfo["fab_med_height"]."x".(int)$orderinfo["fab_med_fuelle"]."/";
                  $lindesc .= $orderinfo["cust_name"]."/";
                  if($orderinfo["fab_design_name"] != "")
                     $lindesc .= $orderinfo["fab_design_name"]."/";
                  $lindesc .= $orderinfo["req_number"];
                  $item_title = mb_convert_case($lindesc, MB_CASE_UPPER, "ISO-8859-1");
                  $_HASFABITEMFOUND = true;
               }
            }

            if($posdata[$y]["item_type"] == "manual" && strpos($posdata[$y]["item_title"], "\n") !== false)
            {
               $titlearr = explode("\n", $posdata[$y]["item_title"]);

               $subdesc = "";
               for($tx = 1; $tx < count($titlearr); $tx++)
                  $subdesc .= str_replace("\r", "", str_replace("\n", " ", $titlearr[$tx]))." ";
    
               unset($_LINEARR);
               $_LINEARR["NroLinDet"]              = $nlinea;
               $_LINEARR["CdgItem"]                = "";
               if($_INDIC_EXENCION != "")
                  $_LINEARR["IndExe"] = $_INDIC_EXENCION;
               $_LINEARR["NmbItem"]                = substr(trim($titlearr[0]), 0, 80);
               $_LINEARR["DscItem"]                = $subdesc;
               $_LINEARR["QtyItem"]                = $item_amount;
               $_LINEARR["UnmdItem"]               = $unit_name;

               if((int)$headdata["dlv_isinvcbrutto"])
               {
                  $_LINEARR["PrcItem"]             = $item_sellprice_brutto;
                  if($item_sell_discount_perc_brutto > 0.00)
                     $_LINEARR["DescuentoPct"]        = $item_sell_discount_perc_brutto;
                  if($item_sell_discount_brutto > 0.00)
                     $_LINEARR["DescuentoMonto"]      = $item_sell_discount_brutto;
                  $_LINEARR["MontoItem"]           = $item_sellprice_brutto_dsc;
               }
               else
               {
                  $_LINEARR["PrcItem"]             = $item_sellprice_netto;
                  if($item_sell_discount_perc > 0.00)
                     $_LINEARR["DescuentoPct"]        = $item_sell_discount_perc;
                  if($item_sell_discount > 0.00)
                     $_LINEARR["DescuentoMonto"]      = $item_sell_discount;
                  $_LINEARR["MontoItem"]           = $item_sellprice_netto_dsc;
               }

               /* DEFONANATA */
               
               fwrite($fpjson,'"productName": "'.$this->cleanXMLData(substr(trim($titlearr[0]), 0, 80)).'",'.$ltrm);
               fwrite($fpjson,'"productNameBarCode": "'.$this->cleanXMLData(substr(trim($titlearr[0]), 0, 80)).'",'.$ltrm);
               fwrite($fpjson,'"price": '.$item_sellprice_netto.','.$ltrm);
               fwrite($fpjson,'"comment": "",'.$ltrm);
               fwrite($fpjson,'"discount": {'.$ltrm);
               fwrite($fpjson,'"type": 0,'.$ltrm);
               fwrite($fpjson,'"value": 0'.$ltrm);
               fwrite($fpjson,'},'.$ltrm);

               fwrite($fpjson,'"unit": "'.$unit_name.'",'.$ltrm);
               fwrite($fpjson,'"analysis": {'.$ltrm);
               fwrite($fpjson,'"accountNumber": "'.$this->accountNumber3.'",'.$ltrm);
               fwrite($fpjson,'"businessCenter": "'.$this->businessCenter.'",'.$ltrm);
               fwrite($fpjson,'"classifier01": "",'.$ltrm);
               fwrite($fpjson,'"classifier02": ""'.$ltrm);
               fwrite($fpjson,'},'.$ltrm);

               fwrite($fpjson,'"useBatch": false,'.$ltrm);
               fwrite($fpjson,'"batchInfo": [],'.$ltrm);

               fwrite($fpjson,'"serialStart": "",'.$ltrm);
               fwrite($fpjson,'"serialSufix": "",'.$ltrm);
               fwrite($fpjson,'"serialPrefix": ""'.$ltrm);
               fwrite($fpjson,$fin.$ltrm);
                 
               /* FIN DEFONTANA */
               $nlinea++;
            }
            else
            {
               unset($_LINEARR);
               $_LINEARR["NroLinDet"]              = $nlinea;
               $_LINEARR["CdgItem"]                = $item_number_prod;
               if($_INDIC_EXENCION != "")
                  $_LINEARR["IndExe"] = $_INDIC_EXENCION;
               $_LINEARR["NmbItem"]                = substr($item_title, 0, 80);
               $_LINEARR["QtyItem"]                = $item_amount;
               $_LINEARR["UnmdItem"]               = $unit_name;

               if((int)$headdata["dlv_isinvcbrutto"])
               {
                  $_LINEARR["PrcItem"]             = $item_sellprice_brutto;
                  if($item_sell_discount_perc_brutto > 0.00)
                     $_LINEARR["DescuentoPct"]        = $item_sell_discount_perc_brutto;
                  if($item_sell_discount_brutto > 0.00)
                     $_LINEARR["DescuentoMonto"]      = $item_sell_discount_brutto;
                  $_LINEARR["MontoItem"]           = $item_sellprice_brutto_dsc;
               }
               else
               {
                  $_LINEARR["PrcItem"]             = $item_sellprice_netto;
                  if($item_sell_discount_perc > 0.00)
                     $_LINEARR["DescuentoPct"]        = $item_sell_discount_perc;
                  if($item_sell_discount > 0.00)
                     $_LINEARR["DescuentoMonto"]      = $item_sell_discount;
                  $_LINEARR["MontoItem"]           = $item_sellprice_netto_dsc;
               }
    
               /* DEFONANATA */

               fwrite($fpjson,'"productName": "'.$this->cleanXMLData(substr($item_title, 0, 80)).'",'.$ltrm);
               fwrite($fpjson,'"productNameBarCode": "'.$this->cleanXMLData(substr($item_title, 0, 80)).'",'.$ltrm);
               fwrite($fpjson,'"price": '.$item_sellprice_netto.','.$ltrm);
               fwrite($fpjson,'"comment": "",'.$ltrm);
               fwrite($fpjson,'"discount": {'.$ltrm);
               fwrite($fpjson,'"type": 0,'.$ltrm);
               fwrite($fpjson,'"value": 0'.$ltrm);
               fwrite($fpjson,'},'.$ltrm);
               fwrite($fpjson,'"unit": "'.$unit_name.'",'.$ltrm);
               fwrite($fpjson,'"analysis": {'.$ltrm);
               fwrite($fpjson,'"accountNumber": "'.$this->accountNumber3.'",'.$ltrm);
               fwrite($fpjson,'"businessCenter": "'.$this->businessCenter.'",'.$ltrm);
               fwrite($fpjson,'"classifier01": "",'.$ltrm);
               fwrite($fpjson,'"classifier02": ""'.$ltrm);
               fwrite($fpjson,'},'.$ltrm);
               fwrite($fpjson,'"useBatch": false,'.$ltrm);
               fwrite($fpjson,'"batchInfo": [],'.$ltrm);
               fwrite($fpjson,'"serialStart": "",'.$ltrm);
               fwrite($fpjson,'"serialSufix": "",'.$ltrm);
               fwrite($fpjson,'"serialPrefix": ""'.$ltrm);
               fwrite($fpjson,$fin.$ltrm);
                    
               /* FIN DEFONTANA */

               $nlinea++;
            }
         }
      }
      fwrite($fpjson,'],'.$ltrm);
      
      //----------------------------------------------------------------------------------
      if($headdata["dlv_discount_amount_netto"] != 0.00)
         $_DISCOUNT_GLB += $headdata["dlv_discount_amount_netto"];
      if($headdata["dlv_total_discount"] != 0.00)
         $_DISCOUNT_GLB += ($headdata["dlv_total_discount"] * -1);

      if(count($_DOCREFS))
      {
         $refcc = 1;
         foreach($_DOCREFS AS $ref)
         {
            unset($_REF_LINEARR);
            $_REF_LINEARR["NroLinRef"]    = $refcc;
            $_REF_LINEARR["TpoDocRef"]    = $ref["DOCTYPE"];
            $_REF_LINEARR["FolioRef"]     = $ref["DOCNUM"];
            $_REF_LINEARR["FchRef"]       = $ref["DOCDAT"];
            $_REF_LINEARR["RazonRef"]     = $ref["DOCCAUSE"];
            $refcc++;
         }
      }

      fwrite($fpjson,'"saleTaxes": ['.$ltrm);
      fwrite($fpjson,'{'.$ltrm);
      fwrite($fpjson,'"code": "IVA",'.$ltrm);
      fwrite($fpjson,'"value": 19.00,'.$ltrm);
      fwrite($fpjson,'"taxAnalysis": {'.$ltrm);
      fwrite($fpjson,'"accountNumber": "'.$this->accountNumber2.'",'.$ltrm);
      fwrite($fpjson,'"businessCenter": "",'.$ltrm);
      fwrite($fpjson,'"classifier01": "",'.$ltrm);
      fwrite($fpjson,'"classifier02": ""'.$ltrm);
      fwrite($fpjson,'}'.$ltrm);
      fwrite($fpjson,'}'.$ltrm);
      fwrite($fpjson,'],'.$ltrm);
      fwrite($fpjson,'"ventaRecDesGlobal": ['.$ltrm);
      fwrite($fpjson,'{'.$ltrm);
      fwrite($fpjson,'"amount": 1,'.$ltrm);
      fwrite($fpjson,'"modifierClass": "",'.$ltrm);
      fwrite($fpjson,'"name": "",'.$ltrm);
      fwrite($fpjson,'"percentage": 0,'.$ltrm);
      fwrite($fpjson,'"value": 0'.$ltrm);
      fwrite($fpjson,'}'.$ltrm);
      fwrite($fpjson,'],'.$ltrm);
      fwrite($fpjson,'"gloss": "'.$this->cleanXMLData($headdata["dlv_annotation"]).'",'.$ltrm);
      fwrite($fpjson,'"customFields": [],'.$ltrm);
      fwrite($fpjson,'"isTransferDocument": '.$this->TRANSFIERE.$ltrm);
      fwrite($fpjson,'}'.$ltrm);

    
      return array($fpjson);

}   
//---------------------------------------------------------------------------------
// CREACION DEL CONTENIDO DE LAS FACTURAS EN XML Y JSON
//----------------------------------------------------------------------------------
private function createInvoiceSellContentDF($CON, $invcid, $isboleta = 0, $fpjson)
   {
      $_tablename = "invoices_sell";
      if($isboleta)
         $_tablename = "invoices_sell_bol";
         
      //----------------------------------------------------------------------------------
      $sql = " select t1.*, t2.cust_name, t3.company_short, t4.shop_name,
                      t2.cust_notes,  t7.pay_title, t7.pay_cod_contable, t8.trans_name, t2.cust_notes,
                      t9.user_firstname 'seller_firstname', t9.user_lastname 'seller_lastname',
                      t10.user_firstname 'cashing_firstname', t10.user_lastname 'cashing_lastname',
                      t7.pay_sii_code
               from {$_tablename} t1
               LEFT OUTER JOIN customer t2      ON t1.invc_cust_id         = t2.id
               LEFT OUTER JOIN company_data t3  ON t1.invc_company_id      = t3.id
               LEFT OUTER JOIN company_shops t4 ON t1.invc_shop_id         = t4.id
               LEFT OUTER JOIN payments t7      ON t1.invc_paymentid       = t7.id
               LEFT OUTER JOIN transports t8    ON t1.invc_transportid     = t8.id
               LEFT OUTER JOIN user t9          ON t1.invc_userid_seller   = t9.id
               LEFT OUTER JOIN user t10         ON t1.invc_userid_cashing  = t10.id
               where
               t1.id = {$invcid}";
      $headdata = $this->CON->select($sql);
      $headdata = $headdata[0];

     //----------------------------------------------------------------------------------
      $sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.giro_name
               from customer t1
               LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
               LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
               LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
               LEFT OUTER JOIN giros   t5 ON t1.cust_giroid    = t5.id
               where
               t1.id = {$headdata["invc_cust_id"]}";
      $customer = $this->CON->select($sql);
      $customer = $customer[0];

      //----------------------------------------------------------------------------------
      $company = getCompanies($this->CON, true, $headdata["invc_company_id"]);
      $company = $company[0];
      
      $shop    = getShops($this->CON, false, false, 0, $headdata["invc_shop_id"]);
      $shop    = $shop[0];

      $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = 'SaveClient'";
      $savecliente = $this->CON->select($sql);
      $savecliente = $savecliente[0];
      $informa_sii = $savecliente["valor"] == 0 ? 'true' : 'false';

      //----------------------------------------------------------------------------------
      if((int)$headdata["invc_fromid"])
      {
         $sql = " select invc_giro
                  from invoices_order
                  where
                  id = {$headdata["invc_fromid"]}";
         $vdata = $this->CON->select($sql);
         $customer["giro_name"] = $vdata[0]["invc_giro"];
      }

      //----------------------------------------------------------------------------------
      if($customer["name"] == "METROPOLITANA DE SANTIAGO")
         $customer["name"] = "SANTIAGO";
      if($customer["name"] != "SANTIAGO")
         $customer["name"] = $customer["nombre"];

      //----------------------------------------------------------------------------------
      if($company["region"] == "METROPOLITANA DE SANTIAGO")
         $company["region"] = "SANTIAGO";
      if($company["region"] != "SANTIAGO")
         $company["region"] = $company["comuna"];

      //----------------------------------------------------------------------------------
      if($shop["region"] == "METROPOLITANA DE SANTIAGO")
         $shop["region"] = "SANTIAGO";
      if($shop["region"] != "SANTIAGO")
         $shop["region"] = $shop["comuna"];

      //----------------------------------------------------------------------------------
      if((int)$headdata["invc_cust_delivid"])
      {
         $sql = " select t1.*, t2.country_name, t3.name, t4.nombre
                  from customer_deliveryaddr t1
                  LEFT OUTER JOIN country t2 ON t1.delivery_countryid = t2.id
                  LEFT OUTER JOIN regions t3 ON t1.delivery_regionid  = t3.id
                  LEFT OUTER JOIN comunas t4 ON t1.delivery_comunaid  = t4.id
                  where
                  t1.id = {$headdata["invc_cust_delivid"]}
                  order by t1.id asc";
         $deliveryaddr = $this->CON->select($sql);
         $deliveryaddr = $deliveryaddr[0];

         if($deliveryaddr["name"] == "REGION METROPOLITANA")
            $deliveryaddr["name"] = "SANTIAGO";
         if($deliveryaddr["name"] != "SANTIAGO")
            $deliveryaddr["name"] = $deliveryaddr["nombre"];

         $destino_street = $deliveryaddr["delivery_street"];
         $destino_comuna = $deliveryaddr["nombre"];
         $destino_region = $deliveryaddr["name"];
      }
      else
      {
         $destino_street = $customer["cust_street"];
         $destino_comuna = $customer["nombre"];
         $destino_region = $customer["name"];
      }
      $customer["cust_street"]   = $destino_street;
      $customer["nombre"]        = $destino_comuna;
      $customer["name"]          = $destino_region;

      
      $posdata    = Array();
      $_DOCREFS   = Array();
      if($isboleta)
      {

         $sql = " select t1.*, t2.item_title, t2.item_invoice_note, t2.item_number_prod, t3.unit_name, t4.cat_id,
                      t2.item_weight, t2.item_weight_price,
                      t2.item_sell_withotheritems, t2.item_sell_nodsc, t2.item_sell_amountmin, t9.cat_dsc_off,
                      t9.cat_dsc_maxperc, t9.cat_sellprice_min
                  from invoices_sell_bol_parts_items t1
                  INNER JOIN item t2                           ON t1.item_id = t2.id
                  LEFT OUTER JOIN item_units t3                ON t2.item_unit = t3.id
                  LEFT OUTER JOIN item_productcats t4          ON t1.item_id = t4.item_id
                  LEFT OUTER JOIN invoices_sell_bol_parts t6       ON ( t1.invc_id = t6.part_invc_id and t1.part_id = t6.id )
                  LEFT OUTER JOIN productcats t9               ON ( t4.cat_id = t9.id )
                  where
                  t1.invc_id = {$invcid} and
                  t1.item_type = 'item'
                  UNION ALL
                  select t1.*, t1.item_desc 'item_title', '' AS 'item_invoice_note', '' AS 'item_number_prod', '' AS 'unit_name', '' AS 'cat_id',
                      '' AS 'item_weight', '' AS 'item_weight_price',
                      '' AS 'item_sell_withotheritems', '' AS 'item_sell_nodsc', '' AS 'item_sell_amountmin', '' AS 'cat_dsc_off',
                      '' AS 'cat_dsc_maxperc', '' AS 'cat_sellprice_min'
                  from invoices_sell_bol_parts_items t1
                  LEFT OUTER JOIN invoices_sell_bol_parts t6   ON ( t1.invc_id = t6.part_invc_id and t1.part_id = t6.id )
                  where
                  t1.invc_id = {$invcid} and
                  t1.item_type = 'manual'
                  order by 4";
         $posdata = $CON->select($sql);

      }
      else
      {
         $invcparts  = getInvoiceSellParts($this->CON, $invcid);
         for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
         {
            $partposdata = getInvoiceSellPartsItems($this->CON, $invcid, $invcparts[$x]["id"]);

            if(count($partposdata) && $partposdata != false && $invcparts[$x]["part_dlv_id"] > 0)
            {
               $_DOCREFS[$invcparts[$x]["part_dlv_id"]]["DOCNUM"]    = $invcparts[$x]["dlv_docnum"];
               $_DOCREFS[$invcparts[$x]["part_dlv_id"]]["DOCTYPE"]   = "52";
               $_DOCREFS[$invcparts[$x]["part_dlv_id"]]["DOCDAT"]    = date('Y-m-d', $invcparts[$x]["dlv_delivery_date"]);
               $_DOCREFS[$invcparts[$x]["part_dlv_id"]]["DOCCAUSE"]  = "";
            }
            
            for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
            {
               $partposdata[$y]["_partdata"] = $invcparts[$x];
               $posdata[] = $partposdata[$y];
            }

            if((int)$invcparts[$x]["part_dlv_id"])
            {
               $sql = " select dlv_docnum
                        from orders_delivery
                        where
                        id = {$invcparts[$x]["part_dlv_id"]}";
               $dlvdoc = $this->CON->select($sql);
               if($dlvdoc[0]["dlv_docnum"] != "")
                  $_DLVNUMSTR .= $dlvdoc[0]["dlv_docnum"].",";
            }
         }
      }
      
      //----------------------------------------------------------------------------------
      $_DLVNUMSTR = substr($_DLVNUMSTR, 0, -1);
      //----------------------------------------------------------------------------------
      $ltrm = "\n";
     
      //----------------------------------------------------------------------------------
      $TipoDTE = $this->_FILE_PREFIX;
      $Folio   = $headdata["invc_docnumber"];
      $FchEmis = date('Y-m-d', $headdata["invc_date"]);

      $FchDia = (int)date('d', $headdata["invc_date"]);
      $FchMes = (int)date('m', $headdata["invc_date"]);
      $FchAno = (int)date('Y', $headdata["invc_date"]);

      $FchVence = date('Y-m-d', $headdata["invc_estpay_date"]);
      $FchDiaV = (int)date('d', $headdata["invc_estpay_date"]);
      $FchMesV = (int)date('m', $headdata["invc_estpay_date"]);
      $FchAnoV = (int)date('Y', $headdata["invc_estpay_date"]);


      //----------------------------------------------------------------------------------
      fwrite($fpjson, '{'.$ltrm);

      if($isboleta)
      {
         fwrite($fpjson, '"documentType": "BOLETA",'.$ltrm);
      }
      else
      {
         fwrite($fpjson, '"documentType": "FVAELECT",'.$ltrm);
      }

      /* DEFONATANA */
      fwrite($fpjson, '"firstFolio": '.$Folio.','.$ltrm);
      fwrite($fpjson, '"lastFolio": '.$Folio.','.$ltrm);
      fwrite($fpjson, '"externalDocumentID": "",'.$ltrm);
      fwrite($fpjson, '"emissionDate": {'.$ltrm);
      fwrite($fpjson, '"day": '.$FchDia.','.$ltrm);
      fwrite($fpjson, '"month": '.$FchMes.','.$ltrm);
      fwrite($fpjson, '"year": '.$FchAno.' '.$ltrm);
      fwrite($fpjson, '},'.$ltrm);
      fwrite($fpjson, '"firstFeePaid": {'.$ltrm);
      fwrite($fpjson, '"day": '.$FchDiaV.','.$ltrm);
      fwrite($fpjson, '"month": '.$FchMesV.','.$ltrm);
      fwrite($fpjson, '"year": '.$FchAnoV.' '.$ltrm);
      fwrite($fpjson, '},'.$ltrm);
      /* FIN LINEA DEFONTANA */
		
		//----------------------------------------------------------------------------------
		$RUTEmisor      = str_replace(".", "", $company["company_rut"]);
		$RznSoc         = $this->cleanXMLData($company["company_name"]);
		$GiroEmis       = $this->cleanXMLData(substr($shop["shop_giro"],0,39));
		$DirOrigen      = $this->cleanXMLData($shop["shop_street"]);
		$CmnaOrigen     = $this->cleanXMLData($shop["comuna"]);
		$CiudadOrigen   = $this->cleanXMLData($shop["region"]);
		//----------------------------------------------------------------------------------
		$RUTRecep    = str_replace(".", "", $customer["cust_rut"]);
      $RUTRecep    = $this->formatearRut($RUTRecep);
		$RznSocRecep = $this->cleanXMLData(substr($customer["cust_company"],0,99));
		$GiroRecep   = $this->cleanXMLData(substr($customer["giro_name"],0,39));
		$Contacto    = $this->cleanXMLData($customer["cust_phone"]);
		$DirRecep    = $this->cleanXMLData(substr($customer["cust_street"],0,59));
		$CmnaRecep   = $this->cleanXMLData(substr($customer["nombre"],0,19));
		$CiudadRecep = $this->cleanXMLData(substr($customer["name"],0,19));
      $vendedor    = $this->cleanXMLData(substr($headdata["invc_userid_seller"],0,19));
      //----------------------------------------------------------------------------------
      if($isboleta)
      {
         $CdgIntRecep = sprintf("%05s", $headdata["id"]);
         if($RUTRecep == "")
            $RUTRecep = "66666666-6";
         if($RznSocRecep == "")
            $RznSocRecep = "CLIENTE PUBLICO";
      }

      fwrite($fpjson, '"clientFile": "'.$RUTRecep.'",'.$ltrm);
      fwrite($fpjson, '"contactIndex":"'.$DirRecep.'",'.$ltrm);
      fwrite($fpjson, '"rutMandante": "",'.$ltrm);
      fwrite($fpjson, '"paymentCondition": "'.$headdata["pay_cod_contable"].'" ,'.$ltrm);
      fwrite($fpjson, '"sellerFileId": "'.$vendedor.'",'.$ltrm);
      
      //----------------------------------------------------------------------------------
      if((int)$headdata["invc_taxes"])
      {
         $MntNeto    = (int)$headdata["invc_total_netto"];
         $MntExe     = 0;
         $TasaIVA    = (int)$_SESSION["_CONF"]["conf_taxes"];
         $IVA        = (int)$headdata["invc_total_taxes"];
         $MntTotal   = (int)$headdata["invc_total_brutto"];
         $_INDIC_EXENCION = "";
      }
      else
      {
         $MntNeto    = 0;
         $MntExe     = (int)$headdata["invc_total_taxes_exclude"];
         $TasaIVA    = 0;
         $IVA        = 0;
         $MntTotal   = (int)$headdata["invc_total_brutto"];   
         $_INDIC_EXENCION = "1";
      }

      fwrite($fpjson, '"clientAnalysis": {'.$ltrm);
          fwrite($fpjson, '"accountNumber": "'.$this->accountNumber1.'",'.$ltrm);
          fwrite($fpjson, '"businessCenter": "",'.$ltrm);
          fwrite($fpjson, '"classifier01": "",'.$ltrm);
          fwrite($fpjson, '"classifier02": ""'.$ltrm);
      fwrite($fpjson, '},'.$ltrm);

      fwrite($fpjson,'"billingCoin": "PESO",'.$ltrm);
      fwrite($fpjson,'"billingRate": 1,'.$ltrm);
      fwrite($fpjson,'"shopId": "Local",'.$ltrm);
      fwrite($fpjson,'"priceList": "1",'.$ltrm);
      fwrite($fpjson,'"giro": "'.$GiroRecep.'",'.$ltrm);
      fwrite($fpjson,'"district": "'.$CmnaRecep.'",'.$ltrm);
      fwrite($fpjson,'"city": "'.$CiudadRecep.'",'.$ltrm);
      fwrite($fpjson,'"contact": -1,'.$ltrm);

      fwrite($fpjson,'"attachedDocuments": ['.$ltrm);

      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_references
               where
               tran_id     = {$invcid} and
               tran_type   = 'invc' and
               ref_type    != '' and
               ref_number  != '' and
               ref_date    != '' ";
               if($isboleta)
                  $sql .= " and 1 = 2 ";
               $sql .= " order by id asc";
      $trnrefs = $CON->select($sql);
      if(count($trnrefs) && $trnrefs != false)
      {
         $fin = '},';
         foreach($trnrefs AS $trnref => $valor_fin )
         {
            $ref_type   = $valor_fin["ref_type"];
            $ref_number = $valor_fin["ref_number"];
            $ref_date   = explode(".", $valor_fin["ref_date"]);
            $dia_ref   = (int)$ref_date[0];
            $mes_ref   = (int)$ref_date[1];
            $ano_ref   = (int)$ref_date[2];

            if ($valor_fin === end($trnrefs))
            {
               $fin = '}';
            }
            fwrite($fpjson,'{'.$ltrm);
            fwrite($fpjson,'"date": '.$ltrm);
               fwrite($fpjson,'{'.$ltrm);
                     fwrite($fpjson,'"day": '.$dia_ref.','.$ltrm);
                     fwrite($fpjson,'"month": '.$mes_ref.','.$ltrm);
                     fwrite($fpjson,'"year": '.$ano_ref.''.$ltrm);                                
               fwrite($fpjson,'},'.$ltrm);                     
               fwrite($fpjson,'"documentTypeId": "'.$ref_type.'",'.$ltrm);	
               fwrite($fpjson,'"folio": "'.$ref_number.'",'.$ltrm);
               fwrite($fpjson,'"reason": " "'.$ltrm);
            fwrite($fpjson,$fin.$ltrm);
         }
      }

      fwrite($fpjson,'],'.$ltrm);
      fwrite($fpjson,'"storage": {'.$ltrm);
      fwrite($fpjson,'"code": "",'.$ltrm);
      fwrite($fpjson,'"motive": "",'.$ltrm);
      fwrite($fpjson,'"storageAnalysis": {'.$ltrm);
      fwrite($fpjson,'"accountNumber": "",'.$ltrm);
      fwrite($fpjson,'"businessCenter": "",'.$ltrm);
      fwrite($fpjson,'"classifier01": "",'.$ltrm);
      fwrite($fpjson,'"classifier02": ""'.$ltrm);
      fwrite($fpjson,"}".$ltrm);
      fwrite($fpjson,"},".$ltrm);

		//----------------------------------------------------------------------------------

      fwrite($fpjson,'"details": ['.$ltrm);

		$itemlines  = 0;
      $nlinea     = 1;

      $_DISCOUNT_GLB = 0.00;
      $fin = "},";
      for($y = 0; $y < count($posdata) && $posdata != false; $y++)
      {
         if( count($posdata) - $y == 1)
         {
            $fin = "}";
         }

         fwrite($fpjson,'{'.$ltrm);
         fwrite($fpjson,'"type": "A",'.$ltrm);
         fwrite($fpjson,'"isExempt": false,'.$ltrm);

         $item_price                = $posdata[$y]["item_sellprice_netto_dsc"] / $posdata[$y]["item_amount"];
         $item_number_prod          = reformatProdNumber($posdata[$y]["item_number_prod"]);
         $item_title                = $posdata[$y]["item_title"];
         $unit_name                 = $posdata[$y]["unit_name"];
         $item_amount               = getPrice(printPrice($posdata[$y]["item_amount"],2),2);
         $item_sellprice_netto      = getPrice(printPrice($posdata[$y]["item_sellprice_netto"]));
         $item_sellprice_brutto     = getPrice(printPrice($posdata[$y]["item_sellprice_brutto"]));
         $dscarr                    = explode("-",$posdata[$y]["_dsc_str"]);
         $item_sellprice_netto_dsc  = getPrice(printPrice($posdata[$y]["item_sellprice_netto_dsc"]));
         $item_sellprice_brutto_dsc = getPrice(printPrice($posdata[$y]["item_sellprice_brutto_dsc"]));
         $item_sell_discount        = round(($item_amount * $item_sellprice_netto) - $item_sellprice_netto_dsc,2);
         $item_sell_discount_perc   = round($item_sell_discount / ($item_amount * $item_sellprice_netto) * 100,2);
         $item_sell_discount_brutto       = round(($item_amount * $item_sellprice_brutto) - $item_sellprice_brutto_dsc,2);
         $item_sell_discount_perc_brutto  = round($item_sell_discount_brutto / ($item_amount * $item_sellprice_brutto) * 100,2);

         if((int)$posdata[$y]["_partdata"]["part_req_id"])
         {
            $sql = " select t1.*, t4.cust_name, t2.*
                     from orders t1
                     INNER JOIN orders_items t2 ON t1.id = t2.req_id
                     INNER JOIN customer t4     ON t1.req_cust_id = t4.id
                     where
                     t1.id = {$posdata[$y]["_partdata"]["part_req_id"]} and
                     t1.req_isfabricate = 1";
            $orderinfo = $CON->select($sql);
            $orderinfo = $orderinfo[0];
            if((int)$orderinfo["id"] && (int)$orderinfo["item_id"] == $posdata[$y]["item_id"] && !$_HASFABITEMFOUND)
            {
               $lindesc  = $posdata[$y]["item_title"]."/";
               $lindesc .= $orderinfo["fab_type"]."/";
               $lindesc .= (int)$orderinfo["fab_mat_gramms"]."/";
               $lindesc .= (int)$orderinfo["fab_med_width"]."x".(int)$orderinfo["fab_med_height"]."x".(int)$orderinfo["fab_med_fuelle"]."/";
               $lindesc .= $orderinfo["cust_name"]."/";
               if($orderinfo["fab_design_name"] != "")
                  $lindesc .= $orderinfo["fab_design_name"]."/";
               $lindesc .= $orderinfo["req_number"];
               $item_title  = mb_convert_case($lindesc, MB_CASE_UPPER, "ISO-8859-1");
               $_HASFABITEMFOUND = true;

            }
         }

         $_IGNORELINE = false;
         if($item_sellprice_netto_dsc < 0.00)
         {
            $_IGNORELINE   = true;

            if($MntNeto > 0.00)
               $_DISCOUNT_GLB += ($item_sellprice_netto_dsc *-1);
         }
         elseif($MntNeto == 0.00)
         {
            $_DISCOUNT_GLB += $item_sellprice_netto_dsc;
         }

         if(!$_IGNORELINE)
         {
            if($posdata[$y]["item_type"] == "manual" && strpos($posdata[$y]["item_title"], "\n") !== false)
            {
               $titlearr = explode("\n", $posdata[$y]["item_title"]);

               $subdesc = "";
               for($tx = 1; $tx < count($titlearr); $tx++)
                  $subdesc .= str_replace("\r", "", str_replace("\n", " ", $titlearr[$tx]))." ";

               unset($_LINEARR);
               $_LINEARR["NroLinDet"]              = $nlinea;
               $_LINEARR["CdgItem"]                = "";
               if($_INDIC_EXENCION != "")
                  $_LINEARR["IndExe"] = $_INDIC_EXENCION;
               $_LINEARR["NmbItem"]                = substr(trim($titlearr[0]), 0, 80);
               $_LINEARR["DscItem"]                = $subdesc;
               $_LINEARR["QtyItem"]                = $item_amount;
               $_LINEARR["UnmdItem"]               = $unit_name;
               if((int)$headdata["invc_isinvcbrutto"])
               {
                  $_LINEARR["PrcItem"]             = $item_sellprice_brutto;
                  if($item_sell_discount_perc_brutto > 0.00)
                     $_LINEARR["DescuentoPct"]        = $item_sell_discount_perc_brutto;
                  if($item_sell_discount_brutto > 0.00)
                     $_LINEARR["DescuentoMonto"]      = $item_sell_discount_brutto;
                  $_LINEARR["MontoItem"]           = $item_sellprice_brutto_dsc;
               }
               else
               {
                  $_LINEARR["PrcItem"]             = $item_sellprice_netto;
                  if($item_sell_discount_perc > 0.00)
                     $_LINEARR["DescuentoPct"]        = $item_sell_discount_perc;
                  if($item_sell_discount > 0.00)
                     $_LINEARR["DescuentoMonto"]      = $item_sell_discount;
                  $_LINEARR["MontoItem"]           = $item_sellprice_netto_dsc;
               }

               fwrite($fpjson,'"code": " ",'.$ltrm);
               fwrite($fpjson,'"count":'.$item_amount.','.$ltrm);
               fwrite($fpjson,'"productName": "'.$this->cleanXMLData(substr(trim($titlearr[0]), 0, 80).'",'.$ltrm));
               fwrite($fpjson,'"productNameBarCode": "'.$this->cleanXMLData(substr(trim($titlearr[0]), 0, 80)).'",'.$ltrm);
               fwrite($fpjson,'"comment": " ",'.$ltrm);
               fwrite($fpjson,'"price": '.$item_sellprice_netto.','.$ltrm);
               fwrite($fpjson,'"discount": {'.$ltrm);
               fwrite($fpjson,'"type": 0,'.$ltrm);
               fwrite($fpjson,'"value": 0'.$ltrm);
               fwrite($fpjson,'},'.$ltrm);
               fwrite($fpjson,'"unit": "'.$unit_name.'",'.$ltrm);
               fwrite($fpjson,'"analysis": {'.$ltrm);
               if($isboleta)
                  fwrite($fpjson,'"accountNumber": "'.$this->accountNumber4.'",'.$ltrm); 
               else
                  fwrite($fpjson,'"accountNumber": "'.$this->accountNumber3.'",'.$ltrm);                
               fwrite($fpjson,'"businessCenter": "'.$this->businessCenter.'",'.$ltrm);
               fwrite($fpjson,'"classifier01": "",'.$ltrm);
               fwrite($fpjson,'"classifier02": ""'.$ltrm);
               fwrite($fpjson,'},'.$ltrm);
               fwrite($fpjson,'"useBatch": false,'.$ltrm);
               fwrite($fpjson,'"batchInfo": ['.$ltrm);
               fwrite($fpjson,']'.$ltrm);
               fwrite($fpjson,$fin.$ltrm);
               $nlinea++;
            }
            else
            {
               $DscItem = "";
               if((int)$posdata[$y]["item_order_pos"] > -1)
               {
                  $sql = " select t1.item_compdesc, t3.req_number
                           from orders_items t1
                           INNER JOIN orders t2 ON t1.req_id = t2.id
                           INNER JOIN offers t3 ON t2.req_offerid = t3.id
                           where
                           t1.req_id      = {$posdata[$y]["_partdata"]["part_req_id"]} and
                           t1.item_id     = {$posdata[$y]["item_id"]} and
                           t1.item_pos    = {$posdata[$y]["item_order_pos"]}";
                  $item_compdesc = $CON->select($sql);
                  $offernumber   = trim($item_compdesc[0]["req_number"]);
                  $item_compdesc = $this->cleanXMLData(strip_tags(trim($item_compdesc[0]["item_compdesc"])));
                  if($offernumber != "" && $item_compdesc != "")
                     $DscItem = "Cotizacion: {$offernumber}, {$item_compdesc}";
               }

               if(trim($posdata[$y]["item_adddesc"]) != "")
               {
                  $DscItem = $this->cleanXMLData(strip_tags(trim($posdata[$y]["item_adddesc"])));
               }
               
               unset($_LINEARR);
               $_LINEARR["NroLinDet"]              = $nlinea;
               $_LINEARR["CdgItem"]                = $item_number_prod;
               if($_INDIC_EXENCION != "")
                  $_LINEARR["IndExe"] = $_INDIC_EXENCION;
               $_LINEARR["NmbItem"]                = substr($item_title, 0, 80);
               if($DscItem != "")
                  $_LINEARR["DscItem"] = $DscItem;
               $_LINEARR["QtyItem"]                = $item_amount;
               $_LINEARR["UnmdItem"]               = $unit_name;

               if((int)$headdata["invc_isinvcbrutto"])
               {
                  $_LINEARR["PrcItem"]             = $item_sellprice_brutto;
                  if($item_sell_discount_perc_brutto > 0.00)
                     $_LINEARR["DescuentoPct"]        = $item_sell_discount_perc_brutto;
                  if($item_sell_discount_brutto > 0.00)
                     $_LINEARR["DescuentoMonto"]      = $item_sell_discount_brutto;
                  $_LINEARR["MontoItem"]           = $item_sellprice_brutto_dsc;
               }
               else
               {
                  $_LINEARR["PrcItem"]             = $item_sellprice_netto;
                  if($item_sell_discount_perc > 0.00)
                     $_LINEARR["DescuentoPct"]        = $item_sell_discount_perc;
                  if($item_sell_discount > 0.00)
                     $_LINEARR["DescuentoMonto"]      = $item_sell_discount;
                  $_LINEARR["MontoItem"]           = $item_sellprice_netto_dsc;
               }

               fwrite($fpjson,'"code": "'.$item_number_prod.'",'.$ltrm);
               fwrite($fpjson,'"count":'.$item_amount.','.$ltrm);
               fwrite($fpjson,'"productName": "'.$this->cleanXMLData(substr($item_title, 0, 80)).'",'.$ltrm);
               fwrite($fpjson,'"productNameBarCode": "'.$this->cleanXMLData(substr($item_title, 0, 80)).'",'.$ltrm);
               fwrite($fpjson,'"comment": " ",'.$ltrm);
               fwrite($fpjson,'"price": '.$item_sellprice_netto.','.$ltrm);
               fwrite($fpjson,'"discount": {'.$ltrm);
               fwrite($fpjson,'"type": 0,'.$ltrm);
               fwrite($fpjson,'"value": 0'.$ltrm);
               fwrite($fpjson,'},'.$ltrm);
               fwrite($fpjson,'"unit": "'.$unit_name.'",'.$ltrm);
               fwrite($fpjson,'"analysis": {'.$ltrm);
               if($isboleta)
                  fwrite($fpjson,'"accountNumber": "'.$this->accountNumber4.'",'.$ltrm); 
               else
                  fwrite($fpjson,'"accountNumber": "'.$this->accountNumber3.'",'.$ltrm);  
               fwrite($fpjson,'"businessCenter": "'.$this->businessCenter.'",'.$ltrm);
               fwrite($fpjson,'"classifier01": "",'.$ltrm);
               fwrite($fpjson,'"classifier02": ""'.$ltrm);
               fwrite($fpjson,'},'.$ltrm);
               fwrite($fpjson,'"useBatch": false,'.$ltrm);
               fwrite($fpjson,'"batchInfo": []'.$ltrm);
               fwrite($fpjson,$fin.$ltrm);
               $nlinea++;
            }
         }
      }
      
      fwrite($fpjson,'],'.$ltrm);

      if($MntNeto == 0.00 && $nlinea == 1)
      {
         $item_price                = $posdata[0]["item_sellprice_netto_dsc"] / $posdata[0]["item_amount"];
         $item_number_prod          = reformatProdNumber($posdata[0]["item_number_prod"]);
         $item_title                = $posdata[0]["item_title"];
         $unit_name                 = $posdata[0]["unit_name"];
         $item_amount               = 1;
         $item_sellprice_netto      = 0;
         $item_sellprice_netto_dsc  = 0;
         $item_sell_discount        = 0;
         $item_sell_discount_perc   = 0;
         
         unset($_LINEARR);
         $_LINEARR["NroLinDet"]              = $nlinea;
         $_LINEARR["CdgItem"]                = $item_number_prod;
         if($_INDIC_EXENCION != "")
            $_LINEARR["IndExe"] = $_INDIC_EXENCION;
         $_LINEARR["NmbItem"]                = substr($item_title, 0, 80);
         $_LINEARR["QtyItem"]                = $item_amount;
         $_LINEARR["UnmdItem"]               = $unit_name;
         $_LINEARR["PrcItem"]                = $item_sellprice_netto;
         if($item_sell_discount_perc > 0.00)
            $_LINEARR["DescuentoPct"] = $item_sell_discount_perc;
         if($item_sell_discount > 0.00)
            $_LINEARR["DescuentoMonto"] = $item_sell_discount;
         
         $_LINEARR["MontoItem"] = $item_sellprice_netto_dsc;

      }

      if($headdata["invc_total_discount"] != 0.00)
         $_DISCOUNT_GLB += ($headdata["invc_total_discount"] * -1);
      if($headdata["invc_discount_amount_netto"] != 0.00)
         $_DISCOUNT_GLB += $headdata["invc_discount_amount_netto"];

      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_references
               where
               tran_id     = {$invcid} and
               tran_type   = 'invc' and
               ref_type    != '' and
               ref_number  != '' and
               ref_date    != '' ";
      if($isboleta)
         $sql .= " and 1 = 2 ";
      $sql .= " order by id asc";
      $trnrefs = $CON->select($sql);
      if(count($trnrefs) && $trnrefs != false)
      {
         foreach($trnrefs AS $trnref)
         {
            $ref_type   = $trnref["ref_type"];
            $ref_number = $trnref["ref_number"];
            $ref_date   = explode(".", $trnref["ref_date"]);
            $ref_date   = $ref_date[2]."-".$ref_date[1]."-".$ref_date[0];

            $temp["DOCNUM"]   = $ref_number;
            $temp["DOCTYPE"]  = $ref_type;
            $temp["DOCDAT"]   = $ref_date;
            $temp["DOCCAUSE"] = "";
            $_DOCREFS[]       = $temp;
         }
      }

      if(count($_DOCREFS))
      {
         $refcc = 1;
         foreach($_DOCREFS AS $ref)
         {
            unset($_REF_LINEARR);
            $_REF_LINEARR["NroLinRef"]    = $refcc;
            $_REF_LINEARR["TpoDocRef"]    = $ref["DOCTYPE"];
            $_REF_LINEARR["FolioRef"]     = $ref["DOCNUM"];
            $_REF_LINEARR["FchRef"]       = $ref["DOCDAT"];
            //$_REF_LINEARR["CodRef"]       = "";
            $_REF_LINEARR["RazonRef"]     = $ref["DOCCAUSE"];
            
            $refcc++;
         }
      }

      fwrite($fpjson,'"saleTaxes": ['.$ltrm);
      fwrite($fpjson,'{'.$ltrm);
      fwrite($fpjson,'"code": "IVA",'.$ltrm);
      fwrite($fpjson,'"value": 19,'.$ltrm);
      fwrite($fpjson,'"taxeAnalysis": {'.$ltrm);
          fwrite($fpjson,'"accountNumber": "'.$this->accountNumber2.'",'.$ltrm);
          fwrite($fpjson,'"businessCenter": "",'.$ltrm);
          fwrite($fpjson,'"classifier01": "",'.$ltrm);
          fwrite($fpjson,'"classifier02": ""'.$ltrm);
      fwrite($fpjson,'}'.$ltrm);
      fwrite($fpjson,'}'.$ltrm);
      fwrite($fpjson,'],'.$ltrm);
      fwrite($fpjson,'"ventaRecDesGlobal": [],'.$ltrm);
      fwrite($fpjson,'"gloss": "'.$this->cleanXMLData($headdata["invc_desc"]).'",'.$ltrm);

      fwrite($fpjson,'"customFields": ['.$ltrm);
      if(count($_DOCREFS))
      {
         $refcc = 0;
         $total = count($_DOCREFS);
         foreach($_DOCREFS AS $ref)
         {
            $refcc++;
            if ($refcc === $total)
               $_fin = "}";
            else
               $_fin = "},";
            unset($_REF_LINEARR);
            $_REF_LINEARR["NroLinRef"]    = $refcc;
            $_REF_LINEARR["TpoDocRef"]    = $ref["DOCTYPE"];
            $_REF_LINEARR["FolioRef"]     = $ref["DOCNUM"];
            $_REF_LINEARR["FchRef"]       = $ref["DOCDAT"];
            $_REF_LINEARR["RazonRef"]     = $ref["DOCCAUSE"];
            fwrite($fpjson,'{'.$ltrm);
            fwrite($fpjson,'"name": "'.$refcc.'",'.$ltrm);
            fwrite($fpjson,'"name": "'.$ref["DOCNUM"].'"'.$ltrm);
            fwrite($fpjson,$_fin.$ltrm);
         }
      }
      fwrite($fpjson,'],'.$ltrm);  
      fwrite($fpjson,'"isTransferDocument": '.$this->TRANSFIERE.$ltrm);
      fwrite($fpjson,'}'.$ltrm);
      return array($fpjson);
   
   }

//---------------------------------------------------------------------------------
// CREACION DEL CONTENIDO DE LAS NOTAS DE CREDITO Y DEBITOS XML Y JSON
//----------------------------------------------------------------------------------
private function createInvoiceSellNoteContentDF($CON, $noteid, $fpjson)
   {
      $sql = " select t1.*, t2.cust_name, t3.company_short, t4.shop_name,
                     t2.cust_notes,  t7.pay_title, t7.pay_cod_contable, t8.iss_code, 
                     t9.user_firstname 'seller_firstname', t9.user_lastname 'seller_lastname'
              from invoices_notes_sell t1
              LEFT OUTER JOIN customer t2      ON t1.note_cust_id      = t2.id
              LEFT OUTER JOIN company_data t3  ON t1.note_company_id   = t3.id
              LEFT OUTER JOIN company_shops t4 ON t1.note_shop_id      = t4.id
              LEFT OUTER JOIN payments t7      ON t1.note_paymentid    = t7.id
              LEFT OUTER JOIN invoices_notes_sell_issues t8 ON t1.note_issueid = t8.id
              LEFT OUTER JOIN user t9          ON t1.note_userid_seller = t9.id
              where
              t1.id = {$noteid}";
      $headdata = $CON->select($sql);
      $headdata = $headdata[0];

      $notenum = explode("-", $headdata["note_invcnumber"]);
      $factura = $notenum[0];

      $sql = " select distinct item_invc_id
               from invoices_notes_sell_items
               where
               note_id = {$noteid} and
               item_invc_id > 0";
      $pinvcids = $CON->select($sql);
      $pinvcid = $pinvcids[0]["item_invc_id"];

      if($headdata["note_type_contype"] == 0)
      {
         $sql = " select t9.user_firstname, t9.user_lastname
                  from invoices_sell t1
                  INNER JOIN user t9  ON t1.invc_userid_seller = t9.id
                  where
                  t1.id = {$pinvcid}";
         $seller = $CON->select($sql);
         $seller = $seller[0];
      }
      else
      {
         $seller["user_firstname"]  = $headdata["seller_firstname"];
         $seller["user_lastname"]   = $headdata["seller_lastname"];
      }

      //----------------------------------------------------------------------------------
      $sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.giro_name
               from customer t1
               LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
               LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
               LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
               LEFT OUTER JOIN giros   t5 ON t1.cust_giroid    = t5.id
               where
               t1.id = {$headdata["note_cust_id"]}";
      $customer = $this->CON->select($sql);
      $customer = $customer[0];

      $company = getCompanies($this->CON, true, $headdata["note_company_id"]);
      $company = $company[0];
      $shop    = getShops($this->CON, false, false, 0, $headdata["note_shop_id"]);
      $shop    = $shop[0];

      //----------------------------------------------------------------------------------
      if($customer["name"] == "METROPOLITANA DE SANTIAGO")
         $customer["name"] = "SANTIAGO";
      if($customer["name"] != "SANTIAGO")
         $customer["name"] = $customer["nombre"];

      //----------------------------------------------------------------------------------
      if($company["region"] == "METROPOLITANA DE SANTIAGO")
         $company["region"] = "SANTIAGO";
      if($company["region"] != "SANTIAGO")
         $company["region"] = $company["comuna"];

      //----------------------------------------------------------------------------------
      if($shop["region"] == "METROPOLITANA DE SANTIAGO")
         $shop["region"] = "SANTIAGO";
      if($shop["region"] != "SANTIAGO")
         $shop["region"] = $shop["comuna"];
         
      //----------------------------------------------------------------------------------
      $ltrm = "\n";
    
      //----------------------------------------------------------------------------------
      $TipoDTE = $this->_FILE_PREFIX;
      $Folio   = $headdata["note_docnumber"];
      $FchEmis = date('Y-m-d', $headdata["note_date"]);
		
		//----------------------------------------------------------------------------------
		$RUTEmisor      = str_replace(".", "", $company["company_rut"]);
		$RznSoc         = $this->cleanXMLData($company["company_name"]);
		$GiroEmis       = $this->cleanXMLData(substr($shop["shop_giro"],0,39));
		$DirOrigen      = $this->cleanXMLData($shop["shop_street"]);
		$CmnaOrigen     = $this->cleanXMLData($shop["comuna"]);
		$CiudadOrigen   = $this->cleanXMLData($shop["region"]);
	
		//----------------------------------------------------------------------------------
		$RUTRecep    = str_replace(".", "", $customer["cust_rut"]);
      $RUTRecep    = $this->formatearRut($RUTRecep);

		$RznSocRecep = $this->cleanXMLData(substr($customer["cust_company"],0,99));
		$GiroRecep   = $this->cleanXMLData(substr($customer["giro_name"],0,39));
		$Contacto    = $this->cleanXMLData($customer["cust_phone"]);
		$DirRecep    = $this->cleanXMLData(substr($customer["cust_street"],0,59));
		$CmnaRecep   = $this->cleanXMLData(substr($customer["nombre"],0,19));
		$CiudadRecep = $this->cleanXMLData(substr($customer["name"],0,19));
      //----------------------------------------------------------------------------------
      
      $FchDia = (int)date('d', $headdata["note_date"]);
      $FchMes = (int)date('m', $headdata["note_date"]);
      $FchAno = (int)date('Y', $headdata["note_date"]);

      fwrite($fpjson, '{'.$ltrm);

      if($headdata["note_type"]==2)  // NOTA DE DEBITO
      {
         fwrite($fpjson, '"debitNoteTypeId": "NDVELECT",'.$ltrm);
         fwrite($fpjson, '"debitNoteType": '.$headdata["iss_code"].','.$ltrm);
         fwrite($fpjson, '"documentType": "FVAELECT",'.$ltrm);
         fwrite($fpjson, '"firstFolio": '.$Folio.','.$ltrm);
         fwrite($fpjson, '"clientAnalysis": {'.$ltrm);
         fwrite($fpjson, '"accountNumber": "'.$this->accountNumber1.'",'.$ltrm);
         fwrite($fpjson, '"businessCenter": "",'.$ltrm);
         fwrite($fpjson, '"classifier01": "",'.$ltrm);
         fwrite($fpjson, '"classifier02": ""'.$ltrm);
         fwrite($fpjson, '},'.$ltrm);
         fwrite($fpjson, '"folio": '.$factura.','.$ltrm);
         fwrite($fpjson, '"gloss": "'.$headdata["note_desc"].'",'.$ltrm);
         fwrite($fpjson, '"emissionDate": {'.$ltrm);
         fwrite($fpjson, '"day": '.$FchDia.','.$ltrm);
         fwrite($fpjson, '"month": '.$FchMes.','.$ltrm);
         fwrite($fpjson, '"year": '.$FchAno.' '.$ltrm);
         fwrite($fpjson, '},');
      }
      else
      {
         if($headdata["iss_code"]==1) /* Anula Documento */
         {

              fwrite($fpjson, '"creditNoteTypeId": "NCVELECT",'.$ltrm);
              fwrite($fpjson, '"firstFolio": '.$Folio.','.$ltrm);
              fwrite($fpjson, '"documentType": "FVAELECT",'.$ltrm);
              fwrite($fpjson, '"folio": '.$factura.', '.$ltrm);
              fwrite($fpjson, '"externalDocumentID": "",'.$ltrm);
              fwrite($fpjson, '"gloss": "'.$headdata["note_desc"].'",'.$ltrm);
         }
         else
         if($headdata["iss_code"]==2) /* modifica texto Documento */
         {

            fwrite($fpjson, '"creditNoteTypeId": "NCVELECT",'.$ltrm);
            fwrite($fpjson, '"firstFolio": '.$Folio.','.$ltrm);
            fwrite($fpjson, '"documentType": "FVAELECT",'.$ltrm);
            fwrite($fpjson, '"folio": '.$factura.', '.$ltrm);
            fwrite($fpjson, '"modifiedClientFileId": "'.$RUTRecep.'",'.$ltrm);
            fwrite($fpjson, '"modifiedContactIndex": "'.$DirRecep.'",'.$ltrm);
            fwrite($fpjson, '"modifiedGiro": "'.$GiroRecep.'",'.$ltrm);
            fwrite($fpjson, '"modifiedDistrict": "'.$CmnaRecep.'",'.$ltrm);
            fwrite($fpjson, '"externalDocumentID": "",'.$ltrm);
            fwrite($fpjson, '"emissionDate": {'.$ltrm);
            fwrite($fpjson, '"day": '.$FchDia.','.$ltrm);
            fwrite($fpjson, '"month": '.$FchMes.','.$ltrm);
            fwrite($fpjson, '"year": '.$FchAno.''.$ltrm);
            fwrite($fpjson, '},'.$ltrm);
            fwrite($fpjson, '"modifiedGloss": "'.$headdata["note_desc"].'",'.$ltrm);
         }
         else
         {  /* corrige monto  Documento */
            fwrite($fpjson, '"creditNoteTypeId": "NCVELECT",'.$ltrm);
            fwrite($fpjson, '"firstFolio": '.$Folio.','.$ltrm);
            fwrite($fpjson, '"creditNoteType": 3,'.$ltrm);
            fwrite($fpjson, '"documentType": "FVAELECT",'.$ltrm);
            fwrite($fpjson, '"folio": '.$factura.','.$ltrm);
            fwrite($fpjson, '"externalDocumentID": "",'.$ltrm);
            fwrite($fpjson, '"gloss": "'.$headdata["note_desc"].'",'.$ltrm);
            fwrite($fpjson, '"emissionDate": {'.$ltrm);
            fwrite($fpjson, '"day": '.$FchDia.','.$ltrm);
            fwrite($fpjson, '"month": '.$FchMes.','.$ltrm);
            fwrite($fpjson, '"year": '.$FchAno.''.$ltrm);
            fwrite($fpjson, '},'.$ltrm);
         }
      }

      //----------------------------------------------------------------------------------
      if((int)$headdata["note_taxes"])
      {
         $MntNeto    = (int)$headdata["note_total_netto"];
         $MntExe     = 0;
         $TasaIVA    = (int)$_SESSION["_CONF"]["conf_taxes"];
         $IVA        = (int)$headdata["note_total_taxes"];
         $MntTotal   = (int)$headdata["note_total_brutto"];
         $_INDIC_EXENCION = "";
      }
      else
      {
         $MntNeto    = 0;
         $MntExe     = (int)$headdata["note_total_taxes_exclude"];
         $TasaIVA    = 0;
         $IVA        = 0;
         $MntTotal   = (int)$headdata["note_total_brutto"];   
         $_INDIC_EXENCION = "1";
      }
      
		//----------------------------------------------------------------------------------
      $posdata = getInvoiceSellNoteItems($this->CON, $noteid);

      $itemlines  = 0;
      $nlinea     = 1;

      if (in_array((int)$headdata["iss_code"], [1, 3])) 
      {
         fwrite($fpjson, '"details": ['.$ltrm);

         $fin = "},";

            for($y = 0; $y < count($posdata) && $posdata != false; $y++)
            {
               if( count($posdata) - $y == 1)
               {
                  $fin = "}";
               }
               
               $item_price                = $posdata[$y]["item_sellprice_netto_dsc"] / $posdata[$y]["item_amount"];
               $item_number_prod          = reformatProdNumber($posdata[$y]["item_number_prod"]);
               $item_title                = $this->cleanXMLData($posdata[$y]["item_title"]);
               $unit_name                 = $this->cleanXMLData($posdata[$y]["unit_name"]);
               $item_amount               = getPrice(printPrice($posdata[$y]["item_amount"],2),2);
               $item_sellprice_netto      = getPrice(printPrice($posdata[$y]["item_sellprice_netto"]));
               $item_sellprice_brutto     = getPrice(printPrice($posdata[$y]["item_sellprice_brutto"]));
               $dscarr                    = explode("-",$posdata[$y]["_dsc_str"]);
               $item_sellprice_netto_dsc  = getPrice(printPrice($posdata[$y]["item_sellprice_netto_dsc"]));
               $item_sellprice_brutto_dsc = getPrice(printPrice($posdata[$y]["item_sellprice_brutto_dsc"]));
               $item_sell_discount        = round(($item_amount * $item_sellprice_netto) - $item_sellprice_netto_dsc,2);
               $item_sell_discount_perc   = round($item_sell_discount / ($item_amount * $item_sellprice_netto) * 100,2);
               $item_sell_discount_brutto       = round(($item_amount * $item_sellprice_brutto) - $item_sellprice_brutto_dsc,2);
               $item_sell_discount_perc_brutto  = round($item_sell_discount_brutto / ($item_amount * $item_sellprice_brutto) * 100,2);

               //----------------------------------------------------------------------------------
               if($posdata[$y]["item_invc_docnumber"] != "")
               {
                  if($headdata["note_type_contype"] == 0 || $headdata["note_type_contype"] == 4)
                  {
                     $_REFINVCS[$posdata[$y]["item_invc_docnumber"]] = 1;
                  }
                  if($headdata["note_type_contype"] == 1 || $headdata["note_type_contype"] == 5)
                  {
                     $_REFNOTACRED[$posdata[$y]["item_invc_docnumber"]] = 1;
                  }
                  if($headdata["note_type_contype"] == 2 || $headdata["note_type_contype"] == 6)
                  {
                     $_REFNOTADEB[$posdata[$y]["item_invc_docnumber"]] = 1;
                  }
                  if($headdata["note_type_contype"] == 7)
                  {
                     $_REFBOL[$posdata[$y]["item_invc_docnumber"]] = 1;
                  }
                  if($headdata["note_type_contype"] == 8)
                  {
                     $_REFBOLEELC[$posdata[$y]["item_invc_docnumber"]] = 1;
                  }
               }

               $exectoStr = ($headdata["note_taxes"] == 0) ? "true" : "false";

               //----------------------------------------------------------------------------------
               if($posdata[$y]["item_type"] == "manual" && strpos($posdata[$y]["item_title"], "\n") !== false)
               {

                  $titlearr = explode("\n", $posdata[$y]["item_title"]);
                  unset($_LINEARR);

                  $subdesc = "";
                  for($tx = 1; $tx < count($titlearr); $tx++)
                     $subdesc .= str_replace("\r", "", str_replace("\n", " ", $titlearr[$tx]))." ";

                  $_LINEARR["NroLinDet"]        = $nlinea;
                  $_LINEARR["CdgItem"]          = "";
                  if($_INDIC_EXENCION != "")
                     $_LINEARR["IndExe"] = $_INDIC_EXENCION;
                  $_LINEARR["NmbItem"]          = substr(trim($titlearr[0]), 0, 80);
                  $_LINEARR["DscItem"]          = $subdesc;
                  $_LINEARR["QtyItem"]          = $item_amount;
                  $_LINEARR["UnmdItem"]         = $unit_name;

                  if((int)$headdata["note_isinvcbrutto"])
                  {
                     $_LINEARR["PrcItem"]             = $item_sellprice_brutto;
                     if($item_sell_discount_perc_brutto > 0.00)
                        $_LINEARR["DescuentoPct"]        = $item_sell_discount_perc_brutto;
                     if($item_sell_discount_brutto > 0.00)
                        $_LINEARR["DescuentoMonto"]      = $item_sell_discount_brutto;
                     $_LINEARR["MontoItem"]           = $item_sellprice_brutto_dsc;
                  }
                  else
                  {
                     $_LINEARR["PrcItem"]             = $item_sellprice_netto;
                     if($item_sell_discount_perc > 0.00)
                        $_LINEARR["DescuentoPct"]        = $item_sell_discount_perc;
                     if($item_sell_discount > 0.00)
                        $_LINEARR["DescuentoMonto"]      = $item_sell_discount;
                     $_LINEARR["MontoItem"]           = $item_sellprice_netto_dsc;
                  }

                  if($headdata["note_type"]==2)
                  {
                     fwrite($fpjson, '{'.$ltrm);
                        fwrite($fpjson, '"type": "A",'.$ltrm);
                        fwrite($fpjson, '"isExempt": '.$exectoStr.','.$ltrm);
                        fwrite($fpjson, '"code": "",'.$ltrm);
                        fwrite($fpjson, '"count": '.$item_amount.','.$ltrm);
                        fwrite($fpjson, '"comment": "",'.$ltrm);
                        fwrite($fpjson, '"productName": "'.substr(trim($titlearr[0]), 0, 80).'",'.$ltrm);
                        fwrite($fpjson, '"productNameBarCode": "'.substr(trim($titlearr[0]), 0, 80).'",'.$ltrm);
                        fwrite($fpjson, '"price": ".$item_sellprice_brutto.",'.$ltrm);
                        fwrite($fpjson, '"discount": {'.$ltrm);
                        fwrite($fpjson, '"type": 0,'.$ltrm);
                        fwrite($fpjson, '"value": 0'.$ltrm);
                        fwrite($fpjson, '},'.$ltrm);
                        fwrite($fpjson, '"unit": "'.$unit_name.'",'.$ltrm);
                        fwrite($fpjson, '"analysis": {'.$ltrm);
                        fwrite($fpjson, '"accountNumber": "'.$this->accountNumber3.'",'.$ltrm);
                        fwrite($fpjson, '"businessCenter": "",'.$ltrm);
                        fwrite($fpjson, '"classifier01": "",'.$ltrm);
                        fwrite($fpjson, '"classifier02": ""'.$ltrm);
                        fwrite($fpjson, '}'.$ltrm);
                        fwrite($fpjson,$fin.$ltrm);
                  } 
                  else
                  {
                        if($headdata["iss_code"]==1)
                        {
                           fwrite($fpjson, '"line":'.$y.','.$ltrm);
                           fwrite($fpjson, '"comment": "'.substr(trim($titlearr[0]), 0, 80).'",'.$ltrm);
                        }
                        if($headdata["iss_code"]==3)
                        {
                           fwrite($fpjson, '"type": "S",'.$ltrm);
                           fwrite($fpjson, '"isExempt": '.$exectoStr.','.$ltrm);
                           fwrite($fpjson, '"code": "RT1",'.$ltrm);
                           fwrite($fpjson, '"count": 1,'.$ltrm);
                           fwrite($fpjson, '"comment": "",'.$ltrm);
                           fwrite($fpjson, '"productName": "REVISION TECNICA",'.$ltrm);
                           fwrite($fpjson, '"productNameBarCode": "REVISION TECNICA",'.$ltrm);
                           fwrite($fpjson, '"price": 100,'.$ltrm);
                           fwrite($fpjson, '"discount": {'.$ltrm);
                           fwrite($fpjson, '"type": 0,'.$ltrm);
                           fwrite($fpjson, '"value": 0'.$ltrm);
                           fwrite($fpjson, '},'.$ltrm);
                           fwrite($fpjson, '"unit": "LT",'.$ltrm);
                           fwrite($fpjson, '"analysis": {'.$ltrm);
                           fwrite($fpjson, '"accountNumber": "'.$this->accountNumber3.'",'.$ltrm);
                           fwrite($fpjson, '"businessCenter": "'.$this->businessCenter.'",'.$ltrm);
                           fwrite($fpjson, '"classifier01": "",'.$ltrm);
                           fwrite($fpjson, '"classifier02": ""'.$ltrm);
                           fwrite($fpjson, '}'.$ltrm);
                        }
                        fwrite($fpjson, '{'.$ltrm);
                        fwrite($fpjson, '"line": '.$nlinea.','.$ltrm);
                        fwrite($fpjson, '"comment": "'.substr(trim($titlearr[0]), 0, 80).'"'.$ltrm);
                        fwrite($fpjson,$fin.$ltrm);

                  }
                  $nlinea++;
               }
               else
               {
                  unset($_LINEARR);
                  $_LINEARR["NroLinDet"]        = $nlinea;
                  $_LINEARR["CdgItem"]          = $item_number_prod;
                  if($_INDIC_EXENCION != "")
                     $_LINEARR["IndExe"] = $_INDIC_EXENCION;
                  $_LINEARR["NmbItem"]          = substr($item_title, 0, 80);
                  $_LINEARR["QtyItem"]          = $item_amount;
                  $_LINEARR["UnmdItem"]         = $unit_name;
                  if((int)$headdata["note_isinvcbrutto"])
                  {
                     $_LINEARR["PrcItem"]             = $item_sellprice_brutto;
                     if($item_sell_discount_perc_brutto > 0.00)
                        $_LINEARR["DescuentoPct"]        = $item_sell_discount_perc_brutto;
                     if($item_sell_discount_brutto > 0.00)
                        $_LINEARR["DescuentoMonto"]      = $item_sell_discount_brutto;
                     $_LINEARR["MontoItem"]           = $item_sellprice_brutto_dsc;
                  }
                  else
                  {
                     $_LINEARR["PrcItem"]             = $item_sellprice_netto;
                     if($item_sell_discount_perc > 0.00)
                        $_LINEARR["DescuentoPct"]        = $item_sell_discount_perc;
                     if($item_sell_discount > 0.00)
                        $_LINEARR["DescuentoMonto"]      = $item_sell_discount;
                     $_LINEARR["MontoItem"]           = $item_sellprice_netto_dsc;
                  }
            

                  if($headdata["note_type"]==2)
                  {
                     fwrite($fpjson, '{'.$ltrm);
                     fwrite($fpjson, '"type": "A",'.$ltrm);
                     fwrite($fpjson, '"isExempt": '.$exectoStr.','.$ltrm);
                     fwrite($fpjson, '"code": "'.$item_number_prod.'",'.$ltrm);
                     fwrite($fpjson, '"count": '.$item_amount.','.$ltrm);
                     fwrite($fpjson, '"comment": "",'.$ltrm);
                     fwrite($fpjson, '"productName": "'.substr($item_title, 0, 80).'",'.$ltrm);
                     fwrite($fpjson, '"productNameBarCode": "'.substr($item_title, 0, 80).'",'.$ltrm);
                     fwrite($fpjson, '"price": "'.$item_sellprice_netto.'",'.$ltrm);
                     fwrite($fpjson, '"discount": {'.$ltrm);
                     fwrite($fpjson, '"type": 0,'.$ltrm);
                     fwrite($fpjson, '"value": 0'.$ltrm);
                     fwrite($fpjson, '},'.$ltrm);
                     fwrite($fpjson, '"unit": "'.$unit_name.'",'.$ltrm);
                     fwrite($fpjson, '"analysis": {'.$ltrm);
                     fwrite($fpjson, '"accountNumber": "'.$this->accountNumber3.'",'.$ltrm);
                     fwrite($fpjson, '"businessCenter": "'.$this->businessCenter.'",'.$ltrm);
                     fwrite($fpjson, '"classifier01": "",'.$ltrm);
                     fwrite($fpjson, '"classifier02": ""'.$ltrm);
                     fwrite($fpjson, '}'.$ltrm);
                     fwrite($fpjson,$fin.$ltrm);
                  } 
                  else
                  {
                     if (in_array($headdata["iss_code"], [1]))
                     {
                        fwrite($fpjson, '{'.$ltrm);
                        fwrite($fpjson, '"line": '.$nlinea.','.$ltrm);
                        fwrite($fpjson, '"comment": "'.substr($item_title, 0, 80).'"'.$ltrm);
                        // fwrite($fpjson,$fin.$ltrm);
                     }
                     if (in_array($headdata["iss_code"], [3]))
                     {
                        fwrite($fpjson, '{'.$ltrm);
                        fwrite($fpjson, '"type": "A",'.$ltrm);
                        fwrite($fpjson, '"isExempt": '.$exectoStr.','.$ltrm);
                        fwrite($fpjson, '"code": "'.$item_number_prod.'",'.$ltrm);
                        fwrite($fpjson, '"count": '.$item_amount.','.$ltrm);
                        fwrite($fpjson, '"comment": "",'.$ltrm);
                        fwrite($fpjson, '"productName": "'.substr($item_title, 0, 80).'",'.$ltrm);
                        fwrite($fpjson, '"productNameBarCode": "'.substr($item_title, 0, 80).'",'.$ltrm);
                        fwrite($fpjson, '"price": "'.$item_sellprice_netto.'",'.$ltrm);
                        fwrite($fpjson, '"discount": {'.$ltrm);
                        fwrite($fpjson, '"type": 0,'.$ltrm);
                        fwrite($fpjson, '"value": 0'.$ltrm);
                        fwrite($fpjson, '},'.$ltrm);
                        fwrite($fpjson, '"unit": "'.$unit_name.'",'.$ltrm);
                        fwrite($fpjson, '"analysis": {'.$ltrm);
                        fwrite($fpjson, '"accountNumber": "'.$this->accountNumber3.'",'.$ltrm);
                        fwrite($fpjson, '"businessCenter": "'.$this->businessCenter.'",'.$ltrm);
                        fwrite($fpjson, '"classifier01": "",'.$ltrm);
                        fwrite($fpjson, '"classifier02": ""'.$ltrm);
                        fwrite($fpjson, '}'.$ltrm);
                     }
                     fwrite($fpjson, $fin.$ltrm);
                  }
                  $nlinea++;
               }
               
            }
            fwrite($fpjson,"],".$ltrm);
      }

      if($headdata["note_type"]==1)
      {
         if($headdata["iss_code"]==1)
         {
            fwrite($fpjson, '"emissionDate": {'.$ltrm);
            fwrite($fpjson, '"day": '.$FchDia.','.$ltrm);
            fwrite($fpjson, '"month": '.$FchMes.','.$ltrm);
            fwrite($fpjson, '"year": '.$FchAno.''.$ltrm);
            fwrite($fpjson, '},'.$ltrm);
            fwrite($fpjson, '"customFields": [],'.$ltrm);
         }
      }

      $_DOCREFS = Array();
      if(count($_REFBOL))
      {
         foreach(array_keys($_REFBOL) AS $bolnum)
         {
            $bolnum = explode("-", $bolnum);
            $temp["DOCNUM"]   = $bolnum[0];
            $temp["DOCTYPE"] = "35";
            $temp["DOCDAT"]   = $bolnum[1];
            //$temp["DOCCAUSE"] = "BOLETA";
            $temp["DOCCAUSE"] = "";
            $_DOCREFS[]       = $temp;
         }
      }
      if(count($_REFBOLEELC))
      {
         foreach(array_keys($_REFBOLEELC) AS $bolnum)
         {
            $bolnum = explode("-", $bolnum);
            $temp["DOCNUM"]   = $bolnum[0];
            $temp["DOCTYPE"] = "39";
            $temp["DOCDAT"]   = $bolnum[1];
            $temp["DOCCAUSE"] = "";
            $_DOCREFS[]       = $temp;
         }
      }
      if(count($_REFINVCS))
      {
         foreach(array_keys($_REFINVCS) AS $invcnum)
         {
            $invcnum = explode("-", $invcnum);
            $temp["DOCNUM"]   = $invcnum[0];

            if($headdata["note_type_contype"] == 0)
            {
               if($headdata["note_taxes"])
                  $temp["DOCTYPE"] = "33";
               else
                  $temp["DOCTYPE"] = "34";
            }
            elseif($headdata["note_type_contype"] == 4)
            {
               if($headdata["note_taxes"])
                  $temp["DOCTYPE"] = "30";
               else
                  $temp["DOCTYPE"] = "32";
            }
            
            $temp["DOCDAT"]   = $invcnum[1];
            $temp["DOCCAUSE"] = "";
            $_DOCREFS[]       = $temp;
         }
      }
      if(count($_REFNOTACRED))
      {
         foreach(array_keys($_REFNOTACRED) AS $notenum)
         {
            $notenum = explode("-", $notenum);
            $temp["DOCNUM"]   = $notenum[0];

            if($headdata["note_type_contype"] == 1)
               $temp["DOCTYPE"] = "61";
            elseif($headdata["note_type_contype"] == 5)
               $temp["DOCTYPE"] = "60";

            $temp["DOCDAT"]   = $notenum[1];
            //$temp["DOCCAUSE"] = "NOTA DE CREDITO";
            $temp["DOCCAUSE"] = "";
            $_DOCREFS[]       = $temp;
         }
      }
      if(count($_REFNOTADEB))
      {
         foreach(array_keys($_REFNOTADEB) AS $notenum)
         {
            $notenum = explode("-", $notenum);
            $temp["DOCNUM"]   = $notenum[0];

            if($headdata["note_type_contype"] == 2)
               $temp["DOCTYPE"] = "56";
            elseif($headdata["note_type_contype"] == 6)
               $temp["DOCTYPE"] = "55";

            $temp["DOCDAT"]   = $notenum[1];
            $temp["DOCCAUSE"] = "";
            $_DOCREFS[]       = $temp;
         }
      }

      if(count($_DOCREFS))
      {
         $refcc = 1;
         foreach($_DOCREFS AS $ref)
         {
            $xarr = explode(".", $ref["DOCDAT"]);
            $ref["DOCDAT"] = $xarr[2]."-".$xarr[1]."-".$xarr[0];
            
            unset($_REF_LINEARR);
            $_REF_LINEARR["NroLinRef"]    = $refcc;
            $_REF_LINEARR["TpoDocRef"]    = $ref["DOCTYPE"];
            $_REF_LINEARR["FolioRef"]     = $ref["DOCNUM"];
            $_REF_LINEARR["FchRef"]       = $ref["DOCDAT"];
            $_REF_LINEARR["CodRef"]       = $headdata["iss_code"];
            $_REF_LINEARR["RazonRef"]     = $ref["DOCCAUSE"];
            $refcc++;
         }
      }

      //----------------------------------------------------------------------------------
      if($headdata["note_type"]==2)
      {
         fwrite($fpjson, '"saleTaxes": ['.$ltrm);
         fwrite($fpjson, '{'.$ltrm);
         fwrite($fpjson, '"code": "IVA",'.$ltrm);
         fwrite($fpjson, '"value": 19,'.$ltrm);
         fwrite($fpjson, '"taxeAnalysis": {'.$ltrm);
         fwrite($fpjson, '"accountNumber": "'.$this->accountNumber2.'",'.$ltrm);
         fwrite($fpjson, '"businessCenter": "",'.$ltrm);
         fwrite($fpjson, '"classifier01": "",'.$ltrm);
         fwrite($fpjson, '"classifier02": ""'.$ltrm);
         fwrite($fpjson, '}'.$ltrm);
         fwrite($fpjson, '}'.$ltrm);
         fwrite($fpjson, '],'.$ltrm);
         fwrite($fpjson, '"storage": {'.$ltrm);
         fwrite($fpjson, '"code": "",'.$ltrm);
         fwrite($fpjson, '"motive": "",'.$ltrm);
         fwrite($fpjson, '"storageAnalysis": {'.$ltrm);
         fwrite($fpjson, '"accountNumber": "",'.$ltrm);
         fwrite($fpjson, '"businessCenter": "",'.$ltrm);
         fwrite($fpjson, '"classifier01": "",'.$ltrm);
         fwrite($fpjson, '"classifier02": ""'.$ltrm);
         fwrite($fpjson, '}'.$ltrm);
         fwrite($fpjson, '},'.$ltrm);
         fwrite($fpjson, '"customFields": [],'.$ltrm);
         fwrite($fpjson, '"isTransferDocument": '.$this->TRANSFIERE.','.$ltrm);
         fwrite($fpjson, '"ventaRecDesGlobal": []'.$ltrm);
         fwrite($fpjson, '}'.$ltrm);
      }
      else
      {
         if($headdata["iss_code"]==3)
         { 
            fwrite($fpjson, '"saleTaxes": [],'.$ltrm);
            fwrite($fpjson, '"modifiedGloss": "",'.$ltrm);
            fwrite($fpjson, '"isTransferDocument": '.$this->TRANSFIERE.','.$ltrm);
            fwrite($fpjson, '"customFields": [],'.$ltrm);
            fwrite($fpjson, '"ventaRecDesGlobal": []'.$ltrm);
         }
         else   
             fwrite($fpjson, '"isTransferDocument": '.$this->TRANSFIERE.$ltrm);
         fwrite($fpjson, '}'.$ltrm);
      }
      
      return array($fpjson);
   }

}
