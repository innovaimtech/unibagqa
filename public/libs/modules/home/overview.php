<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
createEmbalajeProdStock($CON, 32);



$sql = " select user_pic
         from user
         where
         id = {$_SESSION["user_id"]}";
$userpic = $CON->select($sql);
$userpic = $userpic[0]["user_pic"];

if($userpic == "")
   $picpath = "./images/content/user.png";
else
   $picpath = "./images/user_pics/s{$userpic}";

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
/* Consulta para llenar grafico 1 */

   $servername = $_CONFIG["TEST"]["DATABASE"]["HOST"];
   $username   = $_CONFIG["TEST"]["DATABASE"]["USER"];
   $password   = $_CONFIG["TEST"]["DATABASE"]["PASS"] ;
   $dbname     = $_CONFIG["TEST"]["DATABASE"]["NAME"];
   
   // Crear conexión
   $conn = new mysqli($servername, $username, $password, $dbname);
   
   // Verificar conexión
   if ($conn->connect_error) {
       die("Conexión fallida: " . $conn->connect_error);
   }
   
   // $sql = "SELECT categoria, valor FROM datos";
   $sql = "select DATE_FORMAT(DATE_ADD( '1970-01-01', INTERVAL invc_date SECOND), '%Y') as categoria
                 ,round(sum(invc_total_netto),0) as valor
            from invoices_sell
            group by DATE_FORMAT(DATE_ADD( '1970-01-01', INTERVAL invc_date SECOND), '%Y')";
   
   $result = $conn->query($sql);
   
   $categorias = [];
   $valores = [];
   
   if ($result->num_rows > 0) {
       while ($row = $result->fetch_assoc()) {
           $categorias[] = $row['categoria'];
           $valores[] = $row['valor'];
       }
   } else {
       echo "0 resultados";
   }
   /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

   /*
   <script language="JavaScript" src="./libs/jscripts/chart.js"></script>
   */
   
   $conn->close();

//----------------------------------------------------------------------------------
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
      /* Ajusta el tamaño del canvas aquí */
      #miGraficoCircular 
      {
            max-width: 400px; /* Ancho máximo del canvas */
            max-height: 400px; /* Altura máxima del canvas */
      }
</style>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<tr>
   <td height="30">
      <b class="content_header"><?=$_LANG["MODULE"]["HME"][0]?> <?=$_SESSION["user_firstname"]?> <?=$_SESSION["user_lastname"]?></b>
   </td>
</tr>

<?php

$sql = "select * from user where id = {$_SESSION["user_id"]}";
$usuario = $CON->select($sql);
$usuario = $usuario[0];
if (empty($usuario["user_dashboard"]))
{
?>
<tr>
   <td class="content_headerline">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "900",0)?>
<table border="0" class="content_table" cellpadding="6" cellspacing="0" width="100%">
<colgroup>
   <col>
   <col width="940">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["HME"][1]?></td>
</tr>
<tr>
   <td class="content_row_clear" align="center"><img src="<?=$picpath?>" style="border:1px solid #AAAAAA;-moz-border-radius:5px;border-radius:5px;padding:3px"></td>
   <td class="content_row_clear">
      <table border="0" class="content_table" cellpadding="2" cellspacing="0" width="100%">
      <colgroup>
         <col width="110">
         <col>
      </colgroup>
      <tr>
         <td class="content_rowl" style="border-top:0px"><b><?=$_LANG["MODULE"]["HME"][2]?></b></td>
         <td class="content_row" style="border-top:0px"><?=$_SESSION["user_id"]?></td>
      </tr>
      <tr>
         <td class="content_rowl"><b><?=$_LANG["MODULE"]["HME"][3]?></b></td>
         <td class="content_row"><?=$_SESSION["user_name"]?></td>
      </tr>
      <tr>
         <td class="content_rowl"><b><?=$_LANG["MODULE"]["HME"][4]?></b></td>
         <td class="content_row"><?=$_SESSION["user_firstname"]?> <?=$_SESSION["user_lastname"]?></td>
      </tr>
      <tr>
         <td class="content_rowl"><b><?=$_LANG["MODULE"]["HME"][5]?></b></td>
         <td class="content_row"><?=$_SESSION["user_mail"]?></td>
      </tr>
      <tr>
         <td class="content_rowl"><b><?=$_LANG["MODULE"]["HME"][6]?></b></td>
         <td class="content_row">
            <?php
            if($_SESSION["user_type"] != "1")
               echo $_LANG["MODULE"]["HME"][7];
            else
               echo $_LANG["MODULE"]["HME"][8];
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><b><?=$_LANG["MODULE"]["HME"][9]?></b></td>
         <td class="content_row"><?=displayDate($_SESSION["user_login"])?></td>
      </tr>
      </table>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<?php
}
?>
<?=Nifty_printH("box1", "0", 0)?>
<table border="0" class="content_table" cellpadding="6" cellspacing="0" width="100%" >
<div class="container">
    <div class="table-container">
         <?php
               $cdatastyle =  'display:none';
               if (empty($usuario["user_dashboard"]))
                  $cdatastyle =  'display:none'; //
               else
               {
                     
                  $cdatastyle = 'style="border-top-width:3px;border-top-style:solid"';
               }
         ?>
         <table border="1" class="content_table" cellpadding="6" cellspacing="0" width="50%"  style=<?=$cdatastyle?>>
            <thead>
               <tr>
                  <th>Indicadores</th>
               </tr>
            </thead>
            <tbody>
               <tr>
                  <td>
                     <div>
                        <?$ruta = $usuario["user_dashboard"]?>
                        <iframe title="Finanzas" width="900" height="600" src="<?=$ruta?>" frameborder="0" allowFullScreen="true"></iframe>
                     </div>
                  </td>
               </tr>
            </tbody>
        </table>
    </div>
</div>
<?=Nifty_printF()?>
</table>
<br>