<?php
//----------------------------------------------------------------------------------
$err = 0;
$companies = getCompanies($CON);
foreach($companies AS $comp)
{
   $custcredit = checkCustomerCreditLimit($CON, $_REQUEST["id"], 0, $comp["id"]);

   if($custcredit["BLOCKED"])
   {  ?>
      <table border="0" cellpadding="0" cellspacing="0" width="980">
      <tr>
         <td style="border-radius:5px;border:3px solid red">
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="120">
               <col width="190">
               <col width="120">
               <col width="190">
               <col width="120">
               <col>
            </colgroup>
            <tr>
               <td class="content_rowl" style="background-color:#FFDBDB">Limite Credito</td>
               <td class="content_row">$ <?=printPrice($custcredit["LIMIT"])?></td>
               <td class="content_rowl" style="background-color:#FFDBDB">Deuda Cliente</td>
               <td class="content_row">$ <?=printPrice($custcredit["AMOUNT"])?></td>
               <td class="content_rowl" style="background-color:#FFDBDB">Superado</td>
               <td class="content_row">$ <?=printPrice($custcredit["DIFF"])?></td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <br>
      <?php
      $err++;
   }
}
if(!$err)
{  ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td style="border-radius:5px;border:3px solid green">
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <tr>
            <td class="content_row_clear"><b class=msg_save_ok>El cliente esta dentro del limite de credito.</b></td>
         </tr>
         </table>
      </td>
   </tr>
   </table>
   <br>
   <?php
}
?>