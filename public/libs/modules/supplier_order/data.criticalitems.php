<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

?>
<input type="radio" name="crtitemsmode" value="1" id="crtitemsmode1" <?php if($_REQUEST["crtitemsmode"] == 1) echo " checked "?>
onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=criticalitems&id=<?=$_REQUEST["id"]?>&crtitemsmode=1'">
<a class="link" href="javascript:document.getElementById('crtitemsmode1').onclick()">Artículos bajo stock critico</a>

<input type="radio" name="crtitemsmode" id="crtitemsmode2" value="2" <?php if($_REQUEST["crtitemsmode"] == 2) echo " checked "?>
onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=criticalitems&id=<?=$_REQUEST["id"]?>&crtitemsmode=2'">
<a class="link" href="javascript:document.getElementById('crtitemsmode2').onclick()">Artículos con notas de venta</a>
<br><br>
<?php
if((int)$_REQUEST["crtitemsmode"] == 1)
   require_once("data.criticalitems.stock.php");
if((int)$_REQUEST["crtitemsmode"] == 2)
   require_once("data.criticalitems.orders.php");