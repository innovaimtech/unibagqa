<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
?>
var hover_field_old_color = '';

var jqvoltimeout;

//----------------------------------------------------------------------------------
function jqLoadPlantaEquiposFromPlantaId(plantaid)
{
   var dataString = "plantaid=" +plantaid;
   
   $.ajax({
      type:       "POST",
      cache:      false,
      url:        "/libs/modules/prod_stockchanges_repuestos/jq.load.equipos.php",
      data:       dataString,
      dataType:   "html",
      success: function(res)
      {
         $("#sth_assign_equipoid").html(res);
      }
   });
}

//----------------------------------------------------------------------------------
function showColorbox(xurl, xtype, wsize, hsize, xscrolling)
{
   $.colorbox({
         width:wsize,
         height:hsize,
         iframe:true,
         href:xurl,
         closeButton:true,
         overlayClose:true,
         escKey:true,
         scrolling:xscrolling,
         opacity: 0.5
      }
   );
}

//----------------------------------------------------------------------------------
function moveProdAgendaItem(agid, xmode)
{
   var dataString = "agid=" +agid +"&xmode=" +xmode;
   $.ajax({
      type:       "POST",
      cache:      false,
      url:        "/libs/modules/prod_plan/jq.move.ag.php",
      data:       dataString,
      dataType:   "html",
      success: function(res)
      {
         $("#idx_jqout_move").html(res);
      }
   });
}

//----------------------------------------------------------------------------------
function jqLoadPlantaEquipoTypes(plantaid)
{
   var dataString = "plantaid=" +plantaid;

   document.getElementById('sql_equipotypeid').options.length = 1;
   document.getElementById('sql_equipoid').options.length = 1;
   
   $.ajax({
      type:       "POST",
      cache:      false,
      url:        "/libs/modules/prod_plan/jq.load.equipotypes.php",
      data:       dataString,
      dataType:   "html",
      success: function(res)
      {
         $("#sql_equipotypeid").html(res);
      }
   });
}


//----------------------------------------------------------------------------------
function jqLoadPlantaEquipos(eqtypeid)
{
   var dataString = "eqtypeid=" +eqtypeid;
   dataString = dataString +'&plantaid=' +$('#sql_plantaid').val();
   
   $.ajax({
      type:       "POST",
      cache:      false,
      url:        "/libs/modules/prod_plan/jq.load.equipos.php",
      data:       dataString,
      dataType:   "html",
      success: function(res)
      {
         $("#sql_equipoid").html(res);
      }
   });
}

//----------------------------------------------------------------------------------
function activateProdAgendaItem(agid, chkstate)
{
   var dataString = "agid=" +agid;
   if(chkstate)
      dataString = dataString +'&active=1';
   else
      dataString = dataString +'&active=0';
   $.ajax({
      type:       "POST",
      cache:      false,
      url:        "/libs/modules/prod_plan/jq.activate.ag.php",
      data:       dataString,
      dataType:   "html",
      success: function(res)
      {
         $("#idx_jqout").html(res);
      }
   });
}

//----------------------------------------------------------------------------------
function gotoModifyView(xview, xstr)
{
   xurl = '';
   
   if(xview == '0')
      xurl = '/iframe.fancy.php?' +xstr +'&module=workermodify';
   else if(xview == '1')
      xurl = '/iframe.fancy.php?' +xstr +'&module=workerincidencias';
   else if(xview == '2')
      xurl = '/iframe.fancy.php?' +xstr +'&module=workercambiosucursal';
   else if(xview == '3')
      xurl = '/iframe.fancy.php?' +xstr +'&module=workertermino';
   location.href = xurl;
}

function showFancyboxReload(xurl, xtype, wsize, hsize, xscrolling)
{
   $.fancybox(xurl,
   {
      'width'        : wsize,
      'height'       : hsize,
      'autoScale'    : false,
      'margin'       : 5,
      'transitionIn' : 'elastic',
      'transitionOut': 'fade',
      'type'         : xtype,
      'overlayShow'  : true,
      'scrolling'    : xscrolling,
      'centerOnScroll': true,
      'onClosed': function() {
         location.reload(true);
      }
   });
}

//----------------------------------------------------------------------------------
function setNextMonth(xsteps)
{
   var m1 = document.getElementById('sql_month1');
   var y1 = document.getElementById('sql_year1');
   var m2 = document.getElementById('sql_month2');
   var y2 = document.getElementById('sql_year2');

   var om1 = m1.options.length -1;
   var oy1 = y1.options.length -1;
   var om2 = m2.options.length -1;
   var oy2 = y2.options.length -1;
   if(xsteps == 1)
   {
      if(m1.selectedIndex +1 <= om1)
         m1.selectedIndex++;
      else
      {
         m1.selectedIndex = 0;
         y1.selectedIndex++;
      }
      if(m2.selectedIndex +1 <= om2)
         m2.selectedIndex++;
      else
      {
         m2.selectedIndex = 0;
         y2.selectedIndex++;
      }
   }
   if(xsteps == -1)
   {
      if(m1.selectedIndex -1 >= 0)
         m1.selectedIndex--;
      else
      {
         m1.selectedIndex = 11;
         y1.selectedIndex--;
      }
      if(m2.selectedIndex -1 >= 0)
         m2.selectedIndex--;
      else
      {
         m2.selectedIndex = 11;
         y2.selectedIndex--;
      }
   }
}

function jqUnibagSetComuna(comunaid, xmode)
{
   var dataString = "comunaid=" +comunaid +"&xmode=" +xmode;
   $.ajax({
      type:       "POST",
      cache:      false,
      url:        "/libs/modules/orders_offers/jq.comuna.php",
      data:       dataString,
      dataType:   "html",
      success: function(res)
      {
         $("#idx_comunaout").html(res);
      }
   });
}

function avzCheckEmail(emailvalue)
{
   var filter = /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
   if(!filter.test(emailvalue))
      return false;
   return true;
}
   
function execUnibagMultiVolPrices(rowid, offerid)
{
   //$("#idx_volprices_jqoutput").html('ROWID:' +rowid);
   jqGetUnibagVolPrices(rowid, 'multi', offerid);
}

function validateRUTExists(obj, xrut, custid, xfieldname)
{
   if(Rut(document.getElementById(xfieldname), xrut))
   {
      xrut = document.getElementById(xfieldname).value;
      $("#idx_rutchk").html('Validando...');
      
      var dataString = "xrut=" +xrut +"&custid=" +custid;
      $.ajax({
         type:       "POST",
         cache:      false,
         url:        "/libs/modules/customers/jq.rutcheck.php",
         data:       dataString,
         dataType:   "html",
         success: function(res)
         {
            $("#idx_rutchk").html(res);
         }
      });
   }
   else
   {
      $("#idx_rutchk").html('<b class=msg_save_err><img src="/images/menu/icons/cross-circle-frame.png" style="vertical-align:bottom"> RUT incorrecto.</b>');
   }
}

function jqGetUnibagVolPrices(rowid, xmode, offerid)
{
   clearTimeout(jqvoltimeout);
   jqvoltimeout = setTimeout(function()
   {
      var itemid  = $('#item_id_' +rowid).val();
      var itemamt = $('#item_amount_' +rowid).val();
      if(itemid != null)
      {
         itemid = itemid.split('#');
         if(itemid[0].length)
         {
            itemid = itemid[0];

            var dataString = "rowid=" +rowid +"&itemid=" +itemid +"&xmode=" +xmode +"&itemamt=" +itemamt +"&offerid=" +offerid;
            $.ajax({
               type:       "POST",
               cache:      false,
               url:        "/libs/modules/orders_offers/jq.volprices.php",
               data:       dataString,
               dataType:   "html",
               success: function(res)
               {
                  $("#idx_volprices_jqoutput").html(res);
               }
            });
         }
      }
   }, 300);
}

function jqLoadConditions(textid, xfield)
{
   var dataString = "textid=" +textid;
   $.ajax({
      type:       "POST",
      cache:      false,
      url:        "/libs/modules/texts/jq.loadtext.php",
      data:       dataString,
      dataType:   "html",
      success: function(res)
      {
         $("#" +xfield).val(res);
         $("#" +xfield).next().remove();
         $("#" +xfield).ClassyEdit();
      }
   });
}

function unibLoadSpecCharFilters(catid)
{
   var dataString = "catid=" +catid;
   $.ajax({
      type:       "POST",
      cache:      false,
      url:        "/libs/modules/items/jquery.getcatcharactfilters.php",
      data:       dataString,
      dataType:   "html",
      success: function(res)
      {
         $("#idx_charact_jqres").html(res);
      }
   });
}

function myRound(value, places)
{
    var multiplier = Math.pow(10, places);

    return (Math.round(value * multiplier) / multiplier);
}
function removeSelStyle(obj)
{
   obj.size=1;
   obj.style.position='';
   obj.style.borderWidth='1px';
   obj.style.borderColor='';
   mark(obj, 1);
}
function addSelStyle(obj)
{
   obj.size=5;
   obj.style.borderWidth='2px';
   obj.style.borderColor='#333333';
   obj.style.position='absolute';
   mark(obj, 0);
}
function removeUnSelected(obj)
{
   for(var x = obj.options.length -1; x >= 0; x--)
      if(!obj.options[x].selected)
         obj.options[x] = null;
}
function showFancyboxAuto(xurl, xtype)
{
   $.fancybox(xurl,
   {
      'autoScale'    : true,
      'transitionIn' : 'elastic',
      'transitionOut': 'fade',
      'type'         : xtype,
      'overlayShow'  : true,
      'scrolling'    : 'no',
      'centerOnScroll': true
   });
}

function showFancybox(xurl, xtype, wsize, hsize, xscrolling)
{
   $.fancybox(xurl,
   {
      'width'        : wsize,
      'height'       : hsize,
      'autoScale'    : false,
      'transitionIn' : 'elastic',
      'transitionOut': 'fade',
      'type'         : xtype,
      'overlayShow'  : true,
      'scrolling'    : xscrolling,
      'centerOnScroll': true
   });
}
function setItemSellable(obj)
{
   if(obj.checked)
      var mode = '';
   else
      var mode = 'none';
      
   document.getElementById('idx_tr_venta1').style.display=mode;
   document.getElementById('idx_tr_venta2').style.display=mode;
   document.getElementById('idx_tr_venta3').style.display=mode;
   //document.getElementById('idx_tr_venta1c').style.display=mode;
   //document.getElementById('idx_tr_venta1x').style.display=mode;

   document.getElementById('idx_tr_venta1a').style.display='none';
   document.getElementById('idx_tr_venta1b').style.display='none';
   document.getElementById('idx_tr_venta1d').style.display='none';

   if(document.getElementById('item_sellprice_calc').checked && mode == '')
   {
      document.getElementById('idx_tr_venta1a').style.display='';
      if(document.getElementById('item_sellprice_calc_typef').checked)
      {
         document.getElementById('idx_tr_venta1b').style.display='none';
         document.getElementById('idx_tr_venta1d').style.display='';
      }
      else
      {
         document.getElementById('idx_tr_venta1b').style.display='';
         document.getElementById('idx_tr_venta1d').style.display='none';
      }
   }
}

function setItemSellCalc(obj)
{
   if(obj.checked)
   {
      document.getElementById('item_sellprice_netto').style.backgroundColor='#E1FFD6';
      document.getElementById('item_sellprice_netto').readOnly=true;
      document.getElementById('idx_tr_venta1a').style.display='';

      if(document.getElementById('item_sellprice_calc_typef').checked)
      {

         document.getElementById('idx_tr_venta1b').style.display='none';
         document.getElementById('idx_tr_venta1d').style.display='';
      }
      else
      {
         document.getElementById('idx_tr_venta1b').style.display='';
         document.getElementById('idx_tr_venta1d').style.display='none';
      }
   }
   else
   {
      document.getElementById('item_sellprice_netto').style.backgroundColor='';
      document.getElementById('item_sellprice_netto').readOnly=false;
      document.getElementById('idx_tr_venta1a').style.display='none';
      document.getElementById('idx_tr_venta1b').style.display='none';
      document.getElementById('idx_tr_venta1d').style.display='none';
   }
}

function iepanelbarhack()
{
   if (navigator.appVersion.indexOf("MSIE") != -1)
   {
      document.getElementById('obitpanel').style.position = 'absolute';
      document.getElementById('obitpanel').style.top = (document.body.scrollTop + 30) + 'px';
   }
}

function resize_iframe()
{
   var height=window.innerHeight;//Firefox
   if (document.body.clientHeight)
   {
      height=document.body.clientHeight;//IE
   }
   
   document.getElementById("idx_frame_content").style.height=parseInt(height-document.getElementById("idx_frame_content").offsetTop-2)+"px";
}

function calcWeightPrice(sobj, dobj)
{
   var price_per_kilo   = <?=$_SESSION["_CONF"]["conf_price_per_kilo"]?>;
   var amount_kilo      = parseFloat(sobj.value.replace(',','.'));

   if(!isNaN(amount_kilo))
   {
      var val_ges = amount_kilo * price_per_kilo;
      dobj.value  = Math.round(val_ges);
   }
   else
      dobj.value = '0';
}

function unmarkSthBoxes(formobj, chkname, shopid, stid, mode)
{
   for(var x = 0; x < formobj.elements.length; x++)
   {
      if(formobj.elements[x].name.indexOf('st_act_' +shopid)            != -1 ||
         formobj.elements[x].name.indexOf('iss_inventory_min_' +shopid) != -1 ||
         formobj.elements[x].name.indexOf('iss_order_amount_' +shopid)  != -1)
      {
         if(formobj.elements[x].type == 'radio' && formobj.elements[x].name != chkname)
            formobj.elements[x].checked = false;

         else if(formobj.elements[x].type == 'text')
            formobj.elements[x].style.display = 'none';
      }
   }

   if(mode == 'item')
   {
      document.getElementById('sp_stord_' +shopid +'_' +stid).style.display = '';
      document.getElementById('sp_stmin_' +shopid +'_' +stid).style.display = '';
   }
}

function JQFadeMenuTD(obj, bgcolor, color)
{
   obj.style.backgroundColor = bgcolor;
   obj.style.color = color;
}

function showOrderPartPosManualEdit(idx)
{
   var col1 = document.getElementById('idx_tdcol1_' +idx);
   var col2 = document.getElementById('item_id_' +idx);
   var col3 = document.getElementById('item_desc_' +idx);
   var col4 = document.getElementById('manual_pos_' +idx);

   col2.options.length  = 0;
   
   if(col1.style.display == '')
   {
      col1.style.display   = 'none';
      col2.style.display   = 'none';
      col3.style.display   = '';
      col4.value           = '1';
      col3.focus();

      var newIndex            = col2.options.length;
      var newOpt              = new Option('MAN');
      newOpt.value            = '9999999#manual';
      col2.options[newIndex]  = newOpt;
   }
   else
   {
      col1.style.display   = '';
      col2.style.display   = '';
      col3.style.display   = 'none';
      col4.value           = '0';
   }
}

function setItemInfos(idx, val)
{
   var valarr = val.split('#');

   document.getElementById('item_costprice_netto_' +idx).value       = valarr[2];
   document.getElementById('item_costprice_taxes_perc_' +idx).value  = valarr[3];

   if(valarr[4] != '0' && valarr[4] != '' && valarr[4] != '0,00')
      document.getElementById('item_amount_' +idx).value             = valarr[4];
   
}

function setItemInfosSell(idx, val)
{
   var valarr = val.split('#');

   document.getElementById('item_sellprice_netto_' +idx).value       = valarr[2];
   document.getElementById('item_sellprice_taxes_perc_' +idx).value  = valarr[3];
}

function setItemInfosStk(idx, val)
{
   var valarr = val.split('#');
   
   document.getElementById('item_costprice_netto_' +idx).value       = valarr[2];
   document.getElementById('item_costprice_taxes_perc_' +idx).value  = valarr[3];
   $('#item_sellprice_brutto_' +idx).val(valarr[5]);

   updateItemStorehouses(idx, valarr[0], valarr[1]);
}
function setItemInfosSth(idx, val)
{
   var valarr = val.split('#');
   updateItemStorehouses(idx, valarr[0], valarr[1]);
}
function setItemInfosShp(idx, val)
{
   var valarr = val.split('#');

   document.getElementById('item_costprice_netto_' +idx).value       = valarr[2];
   
   updateItemStorehouses(idx, valarr[0], valarr[1]);

   switchShpChargeMode(idx, valarr);
}
function switchShpChargeMode(idx, valarr)
{
   $('#item_charges_data_' +idx).val('');
   
   if(valarr[4] == '1')
   {
      $('#item_stid_' +idx).hide();
      $('#item_charges_' +idx).show();
   }
   else
   {
      $('#item_stid_' +idx).show();
      $('#item_charges_' +idx).hide();
   }
}
function setItemInfosOrderDelivery(idx, val)
{
   var valarr = val.split('#');

   document.getElementById('item_sellprice_netto_' +idx).value       = valarr[2];
   document.getElementById('item_sellprice_taxes_perc_' +idx).value  = valarr[3];
   document.getElementById('item_sellprice_brutto_' +idx).value      = valarr[5];
   
   updateItemStorehouses(idx, valarr[0], valarr[1]);
}

function setItemInfosOrder(idx, val)
{
   var valarr = val.split('#');

   document.getElementById('item_sellprice_netto_' +idx).value       = valarr[2];
   document.getElementById('item_sellprice_taxes_perc_' +idx).value  = valarr[3];
}
function setItemInfosOrderEmbal(idx, val)
{
   var valarr = val.split('#');

   document.getElementById('item_sellprice_netto_' +idx).value       = valarr[2];
   document.getElementById('item_sellprice_taxes_perc_' +idx).value  = valarr[3];
   document.getElementById('idx_embalval_' +idx).innerHTML           = valarr[5];
}
function setItemInfosOrderBrutto(idx, val)
{
   var valarr = val.split('#');

   document.getElementById('item_sellprice_netto_' +idx).value       = valarr[2];
   document.getElementById('item_sellprice_taxes_perc_' +idx).value  = valarr[3];
   document.getElementById('item_sellprice_brutto_' +idx).value      = valarr[6];
}

function setItemInfosOrderVenta(idx, val)
{
   var valarr = val.split('#');

   document.getElementById('item_sellprice_netto_' +idx).value       = valarr[2];
   document.getElementById('item_sellprice_taxes_perc_' +idx).value  = valarr[3];
   document.getElementById('item_sellprice_brutto_' +idx).value      = valarr[4];
   document.getElementById('idx_embalval_' +idx).innerHTML           = valarr[5];
}

function setItemInfosInvc(idx, val)
{
   var valarr = val.split('#');

   document.getElementById('item_sellprice_netto_' +idx).value       = valarr[2];
   document.getElementById('item_sellprice_taxes_perc_' +idx).value  = valarr[3];
   document.getElementById('item_sellprice_brutto_' +idx).value      = valarr[6];
}

function setItemInfosAcc(idx, val)
{
   var valarr = val.split('#');

   document.getElementById('item_costprice_brutto_' +idx).value      = valarr[2];
   document.getElementById('item_costprice_taxes_perc_' +idx).value  = valarr[3];
   document.getElementById('item_supplier_id_' +idx).value           = valarr[4];
   document.getElementById('idx_td_suppinfo_' +idx).innerHTML        = valarr[5];
}

function showCalcDetails(tridx)
{
   var stop    = 0;
   var counter = 0;
   
   while(stop == 0)
   {
      var obj = document.getElementById('idx_tr_' +tridx +'_' +counter);

      if(obj != null)
      {
         if(obj.style.display == 'none')
            obj.style.display = '';
         else
            obj.style.display = 'none';
      }
      else
      {
         stop = 1;
      }
         
      counter++;
   }
}

function Rut(obj, texto)
{
   //if(texto == '')
   //   return true;
      
   var tmpstr = "";  
   for ( i=0; i < texto.length ; i++ )    
      if ( texto.charAt(i) != ' ' && texto.charAt(i) != '.' && texto.charAt(i) != '-' )
         tmpstr = tmpstr + texto.charAt(i);  
   texto = tmpstr;   
   largo = texto.length;   

   if ( largo < 2 )  
   {     
      alert("Debe ingresar el rut completo");
      return false;  
   }  

   for (i=0; i < largo ; i++ )   
   {        
      if ( texto.charAt(i) !="0" && texto.charAt(i) != "1" && texto.charAt(i) !="2" && texto.charAt(i) != "3" && texto.charAt(i) != "4" && texto.charAt(i) !="5" && texto.charAt(i) != "6" && texto.charAt(i) != "7" && texto.charAt(i) !="8" && texto.charAt(i) != "9" && texto.charAt(i) !="k" && texto.charAt(i) != "K" )
      {        
         alert("El valor ingresado no corresponde a un R.U.T valido");
         return false;     
      }  
   }  

   var invertido = "";  
   for ( i=(largo-1),j=0; i>=0; i--,j++ )    
      invertido = invertido + texto.charAt(i);  
   var dtexto = "";  
   dtexto = dtexto + invertido.charAt(0); 
   dtexto = dtexto + '-';  
   cnt = 0; 

   for ( i=1,j=2; i < largo; i++,j++ )   
   {
      if ( cnt == 3 )      
      {        
         dtexto = dtexto + '.';        
         j++;        
         dtexto = dtexto + invertido.charAt(i);       
         cnt = 1;    
      }     
      else     
      {           
         dtexto = dtexto + invertido.charAt(i);       
         cnt++;      
      }  
   }  

   invertido = "";   
   for ( i=(dtexto.length-1),j=0; i>=0; i--,j++ )     
      invertido = invertido + dtexto.charAt(i); 

   obj.value = invertido.toUpperCase()

   if ( revisarDigito2(texto) )     
      return true;   

   return false;
}

function revisarDigito2( crut )
{  
   largo = crut.length; 
   if ( largo < 2 )  
   {     
      alert("Debe ingresar el rut completo");
      return false;  
   }  
   if ( largo > 2 )     
      rut = crut.substring(0, largo - 1); 
   else     
      rut = crut.charAt(0);   
   dv = crut.charAt(largo-1); 
   revisarDigito( dv ); 

   if ( rut == null || dv == null )
      return 0 

   var dvr = '0'  
   suma = 0 
   mul  = 2 

   for (i= rut.length -1 ; i >= 0; i--)   
   {  
      suma = suma + rut.charAt(i) * mul      
      if (mul == 7)        
         mul = 2     
      else           
         mul++ 
   }  
   res = suma % 11   
   if (res==1)    
      dvr = 'k'   
   else if (res==0)     
      dvr = '0'   
   else  
   {     
      dvi = 11-res      
      dvr = dvi + "" 
   }
   if ( dvr != dv.toLowerCase() )   
   {     
      alert("EL rut es incorrecto");
      return false   
   }

   return true
}

function revisarDigito( dvr )
{  
   dv = dvr + ""  
   if ( dv != '0' && dv != '1' && dv != '2' && dv != '3' && dv != '4' && dv != '5' && dv != '6' && dv != '7' && dv != '8' && dv != '9' && dv != 'k'  && dv != 'K')   
   {     
      alert("Debe ingresar un digito verificador valido");     
      return false;  
   }  
   return true;
}

function openDocWindow(xurl, xwidth, xheight)
{
   var centerWidth   = (screen.width / 2)  - (xwidth / 2);
   var centerHeight  = (screen.height / 2) - (xheight / 2);
   var winA = window.open(xurl, 'NEW', "width="+xwidth+",height="+xheight+",top="+centerHeight+",left="+centerWidth+",scrollbars=1,resizeable=0,toolbar=0,location=0,menubar=0");
   winA.focus();
}

function openEMailSigWindow(uid)
{
   var centerWidth   = (screen.width / 2)  - (600 / 2);
   var centerHeight  = (screen.height / 2) - (400 / 2);
   var MyURL         = './libs/modules/users/signature.php?uid=' +uid;
   
   var winA = window.open(MyURL, 'NEW', "width=600,height=400,top="+centerHeight+",left="+centerWidth+",scrollbars=1,resizeable=0,toolbar=0,location=0,menubar=0");
   winA.focus();
}

function passwordChanged()
{
   var strength = document.getElementById('strength');
   var strongRegex = new RegExp("^(?=.{8,})(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*\W).*$", "g");
   var mediumRegex = new RegExp("^(?=.{7,})(((?=.*[A-Z])(?=.*[a-z]))|((?=.*[A-Z])(?=.*[0-9]))|((?=.*[a-z])(?=.*[0-9]))).*$", "g");
   var enoughRegex = new RegExp("(?=.{6,}).*", "g");
   var pwd = document.getElementById("user_pass");
   if (pwd.value.length==0) {
   strength.innerHTML = '<span style="color:red"><?=$_LANG["MODULE"]["LOGIN"][9]?></span>';
   } else if (false == enoughRegex.test(pwd.value)) {
   strength.innerHTML = '<span style="color:red"><?=$_LANG["MODULE"]["LOGIN"][9]?></span>';
   } else if (strongRegex.test(pwd.value)) {
   strength.innerHTML = '<span style="color:green"><?=$_LANG["MODULE"]["LOGIN"][11]?></span>';
   } else if (mediumRegex.test(pwd.value)) {
   strength.innerHTML = '<span style="color:green"><?=$_LANG["MODULE"]["LOGIN"][11]?></span>';
   } else {
   strength.innerHTML = '<span style="color:red"><?=$_LANG["MODULE"]["LOGIN"][9]?></span>';
   }
}

function checkSaveStatus()
{
   if(document.getElementById('jschk_formchange').value == '1' && document.getElementById('jschk_formchange_ignore').value == '0')
      return '<?=$_LANG["FORM"]["MESSAGE"][4]?>';
}

function addInputSubmitEvent(input)
{
   if(typeof(input.onchange) != 'function')
   {
      input.onchange = function(e)
      {
         document.getElementById('jschk_formchange').value = '1';
      };

      if(input.type == 'text' && (typeof(input.onkeydown) != 'function'))
      {
         input.onkeydown = function(e)
         {
            e = e || window.event;
            if (e.keyCode == 13)
            {
               deactivateFormChange();
            }
         };
      }
   }
}

function deactivateFormChange()
{
   document.getElementById('jschk_formchange_ignore').value = '1';
}

function addFormListeners(form_name)
{
   var obj = document.getElementsByName(form_name);
   obj = obj[0];
   
   var inputs = obj.getElementsByTagName('input');
   
   for (var j=0;j < inputs.length;j++)
      if(inputs[j].type != 'hidden' && inputs[j].type != 'submit' && inputs[j].type != 'button')
         addInputSubmitEvent(inputs[j]);

   var inputs = obj.getElementsByTagName('select');
   for (var j=0;j < inputs.length;j++)
      if(inputs[j].type != 'hidden' && inputs[j].type != 'submit' && inputs[j].type != 'button')
         addInputSubmitEvent(inputs[j]);

   var inputs = obj.getElementsByTagName('textarea');
   for (var j=0;j < inputs.length;j++)
      if(inputs[j].type != 'hidden' && inputs[j].type != 'submit' && inputs[j].type != 'button')
         addInputSubmitEvent(inputs[j]);
}

function submitForm(xform)
{
   deactivateFormChange();

   if(typeof xform.onsubmit == 'function')
   {
      if(xform.onsubmit())
         xform.submit();
   }
   else
      xform.submit();
}

function unhideWindow()
{
   document.getElementById('idx_loadinghide').style.display='';
   hideLoading();
   autofocus();
   execAfterLoad();
}
      
function showLoading()
{
   if (document.body.clientHeight)
      clientWidth = document.body.clientWidth;
   else
      clientWidth = window.innerWidth;

   var centerWidth   = Math.round((clientWidth / 2)  - (151 / 2));
   var centerHeight  = 300;
   document.write('<div style="position:relative"><div id="idx_loadingscreen" style="position:absolute; margin: 0px; left: ' +centerWidth +'px; top:' +centerHeight +'px; height: 38px; width: 151px; z-index: 1000">');
   document.write('<table border="0" cellpadding="0" cellspacing="0">');
   document.write('<tr>');
   document.write('<td><img style="border-radius:50%;opacity:0.3;" src="./images/content/loading.gif"></td>');
   document.write('</tr>');
   document.write('</table>');
   document.write('</div></div>');
}

function hideLoading()
{
   document.getElementById('idx_loadingscreen').style.display = 'none';
}

function checkamount(obj)
{
   var xc = 0;
   if(obj.value == "")
   {
      xc++;
      obj.style.backgroundColor  = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bgcolor")?>';
      obj.style.borderColor      = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bordercolor")?>';
      if(xc==1)
         obj.focus();
   }
   else
   {
      obj.style.backgroundColor  = '';
      obj.style.borderColor      = '';
   }

   var valobj  = obj;
   var itmval  = valobj.value;
   var ival    = parseInt(itmval);

   if(isNaN(ival) || String(ival) != itmval || ival < 0)
   {
      xc++;
      valobj.style.backgroundColor  = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bgcolor")?>';
      valobj.style.borderColor      = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bordercolor")?>';
      if(xc==1)
         valobj.focus();
   }
   else
   {
      valobj.style.backgroundColor  = '';
      valobj.style.borderColor      = '';
   }

   if(xc>0)
   {
      alert("<?=$_LANG["FORM"]["MESSAGE"][2]?>");
      return false;
   }

   return true;
}

function openSymbolWindow()
{
   var centerWidth   = (screen.width / 2)  - (650 / 2);
   var centerHeight  = (screen.height / 2) - (300 / 2);
   var MyURL         = './libs/modules/structure/symbols.php';
   
   var winA = window.open(MyURL, 'NEW', "width=650,height=300,top="+centerHeight+",left="+centerWidth+",scrollbars=1,resizeable=1,toolbar=0,location=0,menubar=0");
   winA.focus();
}

function openDirectoryWindow()
{
   var centerWidth   = (screen.width / 2)  - (650 / 2);
   var centerHeight  = (screen.height / 2) - (450 / 2);
   var MyURL         = './libs/modules/structure/directory.php';
   
   var winA = window.open(MyURL, 'NEW', "width=650,height=450,top="+centerHeight+",left="+centerWidth+",scrollbars=1,resizeable=0,toolbar=0,location=0,menubar=0");
   winA.focus();
}

function openProductcatWindow(itemid, tbl_suffix)
{
   var centerWidth   = (screen.width / 2)  - (650 / 2);
   var centerHeight  = (screen.height / 2) - (450 / 2);
   var MyURL         = './libs/modules/productcats/select.php?id=' +itemid +'&tbl_suffix=' +tbl_suffix;
   
   var winA = window.open(MyURL, 'NEW', "width=650,height=450,top="+centerHeight+",left="+centerWidth+",scrollbars=1,resizeable=0,toolbar=0,location=0,menubar=0");
   winA.focus();
}

function openWorkshopcatWindow(itemid)
{
   var centerWidth   = (screen.width / 2)  - (650 / 2);
   var centerHeight  = (screen.height / 2) - (450 / 2);
   var MyURL         = './libs/modules/workshop_cats/select.php?id=' +itemid;
   
   var winA = window.open(MyURL, 'NEW', "width=650,height=450,top="+centerHeight+",left="+centerWidth+",scrollbars=1,resizeable=0,toolbar=0,location=0,menubar=0");
   winA.focus();
}

function setProductcats(tblmode)
{
   var objarr  = document.getElementsByName('catids[]');
   var idstr   = '';
   for(var x = 0; x < objarr.length; x++)
      if(objarr[x].checked)
         idstr = idstr + objarr[x].value + '_';

   idstr = idstr.substring(0, idstr.length -1);
   opener.document.js_item_form.item_catids.value = idstr;
   opener.document.getElementById('cat_iframe').src = './libs/modules/productcats/list.php?tblmode=' +tblmode +'&catids=' +idstr;
   window.close();
}

function markCol(obj, color)
{
   obj.style.backgroundColor  = color;
}

function mark(obj, mode)
{
   if(mode)
      obj.style.backgroundColor = '';
   else
      obj.style.backgroundColor = '<?=$_SESSION["_PAGE"]->getEffectVal("js_content_hover")?>';
}

function markbtn(obj, mode)
{
   if(mode)
      obj.style.backgroundColor = '';
   else
      obj.style.backgroundColor = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_btn_hover_on")?>';
}

function markfield(obj, mode)
{
   if(mode)
      obj.style.backgroundColor = '';
   else
   {
      hover_field_old_color      = obj.style.backgroundColor;
      obj.style.backgroundColor  = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_inp_hover_on")?>';
   }
}

function markSelBoxes(formobj, chkname, stat)
{
   for(var x = 0; x < formobj.elements.length; x++)
   {
      if(formobj.elements[x].type == 'checkbox' && formobj.elements[x].name == chkname)
         formobj.elements[x].checked = stat.checked;
   }
}

function checkuserform(obj)
{
   for(x=0; x < obj.length; x++)
   {
      obj[x].style.backgroundColor  = '';
      obj[x].style.borderColor      = '';
   }
   if(document.all.user_pass1.value != document.all.user_pass2.value)
   {
      document.all.user_pass1.style.backgroundColor  = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bgcolor")?>';
      document.all.user_pass1.style.borderColor      = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bordercolor")?>';
      document.all.user_pass2.style.backgroundColor  = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bgcolor")?>';
      document.all.user_pass2.style.borderColor      = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bordercolor")?>';
      document.all.user_pass1.focus();

      alert("<?=$_LANG["FORM"]["MESSAGE"][2]?>");
      return false;
   }
   return checkform(obj);
}

function checkstructform(obj)
{
   document.all.menu_doc_file.style.backgroundColor  = '';
   document.all.menu_doc_file.style.borderColor      = '';
   document.all.menu_link_mod.style.backgroundColor  = '';
   document.all.menu_link_mod.style.borderColor      = '';

   var chk = checkform(obj);
   
   if(chk)
   {
      var menu_sel  = 0;
      var menu_lnks = document.getElementsByName('menu_link');
      
      for(var x = 0; x < menu_lnks.length; x++)
         if(menu_lnks[x].checked)
            menu_sel = menu_lnks[x].value;
   
      if(menu_sel == 2 && document.all.showItem.value == '' && document.all.menu_doc_file.value == '')
      {
         document.all.menu_doc_file.style.backgroundColor  = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bgcolor")?>';
         document.all.menu_doc_file.style.borderColor      = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bordercolor")?>';
         document.all.menu_doc_file.focus();
         
         alert("<?=$_LANG["FORM"]["MESSAGE"][2]?>");
         return false;
      }
   
      if(menu_sel == 1 && document.all.menu_link_mod.value == '')
      {
         document.all.menu_link_mod.style.backgroundColor  = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bgcolor")?>';
         document.all.menu_link_mod.style.borderColor      = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bordercolor")?>';
         document.all.menu_link_mod.focus();
         
         alert("<?=$_LANG["FORM"]["MESSAGE"][2]?>");
         return false;
      }
   }
   
   return chk;
}

function checkmsgform(obj)
{
   tinyMCE.triggerSave();
   var chk = checkform(obj);
   
   if(chk)
   {
      chk = false;
      var grp_objs = document.getElementsByName('grpids[]');
      var usr_objs = document.getElementsByName('usrids[]');

      for(var x = 0; x < usr_objs.length; x++)
         if(usr_objs[x].checked)
            chk = true;

      for(var x = 0; x < grp_objs.length; x++)
         if(grp_objs[x].checked)
            chk = true;

      if(!chk)
         alert('<?=$_LANG["MODULE"]["MSG"][39]?>');
         
      return chk;
   }
   
   return false;
}

function checkform(obj)
{
   var xc = 0;
   for(x=0; x < obj.length; x++)
   {
      if(obj[x].value == '')
      {
         xc++;
         obj[x].style.backgroundColor  = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bgcolor")?>';
         obj[x].style.borderColor      = '<?=$_SESSION["_PAGE"]->getEffectVal("js_form_error_bordercolor")?>';
         if(xc==1)
            obj[x].focus();
      }
      else
      {
         obj[x].style.backgroundColor  = '';
         obj[x].style.borderColor      = '';
      }
   }
   if(xc>0)
   {
      alert("<?=$_LANG["FORM"]["MESSAGE"][2]?>");
      return false;
   }
   return true;
}

function askDel(myurl)
{
   if(confirm("<?=$_LANG["FORM"]["MESSAGE"][3]?>"))
   {
      deactivateFormChange();
      
      if(myurl != '')
         location.href = myurl;
      else
         return true;
   }
   return false;
}

function askDelx(xtext)
{
   if(confirm(xtext))
   {
      deactivateFormChange();
      return true;
   }
   return false;
}

function autofocus()
{
   var stop = false;
   for(var x=0;x < document.forms.length && !stop; x++)
      if(document.forms[x].className == "fokusfirst")
         for(var y=0;y < document.forms[x].elements.length && !stop; y++)
            if(document.forms[x].elements[y].type == 'text' || document.forms[x].elements[y].type == 'select-one')
            {
               if(document.forms[x].elements[y].id != 'idx_xf_itemsearch')
               {
                  document.forms[x].elements[y].focus();
                  stop = true;
               }
            }
}

function setMenuEntry(itmid)
{
   var obj = document.getElementById('idmen_' +itmid);
   
   if(obj.src.substring(obj.src.length -9) == 'entry.gif')
      obj.src = './images/layout/menu_entry_on.gif';
   else
      obj.src = './images/layout/menu_entry.gif';
}

function setEntryType(idx)
{
   switch(idx)
   {
      case 0:
         document.all.doc_upload.style.display     = 'none';
         document.all.doc_behavior.style.display   = 'none';
         document.all.doc_desc.style.display       = 'none';
         document.all.mod_path.style.display       = 'none';
         document.all.mod_params.style.display     = 'none';
         document.all.mod_trancode.style.display   = 'none';
         document.all.menu_symbol.style.display    = 'none';
         break;
      case 1:
         document.all.doc_upload.style.display     = 'none';
         document.all.doc_behavior.style.display   = 'none';
         document.all.doc_desc.style.display       = 'none';
         document.all.mod_path.style.display       = '';
         document.all.mod_params.style.display     = '';
         document.all.mod_trancode.style.display   = '';
         document.all.menu_symbol.style.display    = '';
         break;
      case 2:
         document.all.doc_upload.style.display     = '';
         document.all.doc_behavior.style.display   = '';
         document.all.doc_desc.style.display       = '';
         document.all.mod_path.style.display       = 'none';
         document.all.mod_params.style.display     = 'none';
         document.all.mod_trancode.style.display   = 'none';
         document.all.menu_symbol.style.display    = '';
         break;
   }
}

function showObject(tid)
{
   if(tid.style.display == '')
      tid.style.display = 'none';
   else
      tid.style.display = '';
}

function calculateDays(start, end)
{
   var retdays = 0;
   
   if(start != '' && end != '')
   {
      //-----------------------------------------------------------------------------------
      var start_day     = start.substring(0,2);
      var start_month   = start.substring(3,5);
      var start_year    = parseInt(start.substring(6,10));

      if(start_day.substring(0,1) == "0")
         start_day = start_day.substring(1,2);

      if(start_month.substring(0,1) == "0")
         start_month = start_month.substring(1,2);
         
      start_day   = parseInt(start_day);
      start_month = parseInt(start_month);
      
      var dStart = new Date();
      dStart.setFullYear(start_year, start_month -1, start_day);
      dStart.setMilliseconds(0);
      dStart.setSeconds(0);
      dStart.setMinutes(0);
      dStart.setHours(0);

      //-----------------------------------------------------------------------------------
      var end_day     = end.substring(0,2);
      var end_month   = end.substring(3,5);
      var end_year    = parseInt(end.substring(6,10));

      if(end_day.substring(0,1) == "0")
         end_day = end_day.substring(1,2);

      if(end_month.substring(0,1) == "0")
         end_month = end_month.substring(1,2);
         
      end_day   = parseInt(end_day);
      end_month = parseInt(end_month);
      
      var dEnd   = new Date();
      
      dEnd.setFullYear(end_year, end_month -1, end_day);
      dEnd.setMilliseconds(0);
      dEnd.setSeconds(0);
      dEnd.setMinutes(0);
      dEnd.setHours(0);
      
      while(dStart <= dEnd)
      {
         if(dStart.getDay() != 0 && dStart.getDay() != 6)
            retdays++;
         dStart.setDate(dStart.getDate() +1);
      }
   }

   return retdays;
}