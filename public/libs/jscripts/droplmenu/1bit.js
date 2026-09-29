var lastcurr = '0';
var lastulma = '';
var lastulbg = '';

//----------------------------------------------------------------------------------
$.each(['backgroundColor', 'borderBottomColor', 'borderLeftColor',
   'borderRightColor', 'borderTopColor', 'borderColor', 'color', 'outlineColor'],
function(i, attr) {
   $.fx.step[attr] = function(fx) {
      if (!fx.colorInit) {
         fx.start = getColor(fx.elem, attr);
         fx.end = getRGB(fx.end);
         fx.colorInit = true;
      }

      fx.elem.style[attr] = 'rgb(' +
         Math.max(Math.min(parseInt((fx.pos * (fx.end[0] - fx.start[0])) + fx.start[0], 10), 255), 0) + ',' +
         Math.max(Math.min(parseInt((fx.pos * (fx.end[1] - fx.start[1])) + fx.start[1], 10), 255), 0) + ',' +
         Math.max(Math.min(parseInt((fx.pos * (fx.end[2] - fx.start[2])) + fx.start[2], 10), 255), 0) + ')';
   };
});
//----------------------------------------------------------------------------------
function getRGB(color) {
      var result;

      // Check if we're already dealing with an array of colors
      if ( color && color.constructor == Array && color.length == 3 )
            return color;

      // Look for rgb(num,num,num)
      if (result = /rgb\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*\)/.exec(color))
            return [parseInt(result[1],10), parseInt(result[2],10), parseInt(result[3],10)];

      // Look for rgb(num%,num%,num%)
      if (result = /rgb\(\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*\)/.exec(color))
            return [parseFloat(result[1])*2.55, parseFloat(result[2])*2.55, parseFloat(result[3])*2.55];

      // Look for #a0b1c2
      if (result = /#([a-fA-F0-9]{2})([a-fA-F0-9]{2})([a-fA-F0-9]{2})/.exec(color))
            return [parseInt(result[1],16), parseInt(result[2],16), parseInt(result[3],16)];

      // Look for #fff
      if (result = /#([a-fA-F0-9])([a-fA-F0-9])([a-fA-F0-9])/.exec(color))
            return [parseInt(result[1]+result[1],16), parseInt(result[2]+result[2],16), parseInt(result[3]+result[3],16)];

      // Look for rgba(0, 0, 0, 0) == transparent in Safari 3
      if (result = /rgba\(0, 0, 0, 0\)/.exec(color))
            return colors['transparent'];

      // Otherwise, we're most likely dealing with a named color
      return colors[$.trim(color).toLowerCase()];
}
//----------------------------------------------------------------------------------
function getColor(elem, attr) {
      var color;

      do {
            color = $.curCSS(elem, attr);

            // Keep going until we find an element that has color, or we hit the body
            if ( color != '' && color != 'transparent' || $.nodeName(elem, "body") )
                  break;

            attr = "backgroundColor";
      } while ( elem = elem.parentNode );

      return getRGB(color);
};

//----------------------------------------------------------------------------------
var colors = {
   aqua:[0,255,255],
   azure:[240,255,255],
   beige:[245,245,220],
   black:[0,0,0],
   blue:[0,0,255],
   brown:[165,42,42],
   cyan:[0,255,255],
   darkblue:[0,0,139],
   darkcyan:[0,139,139],
   darkgrey:[169,169,169],
   darkgreen:[0,100,0],
   darkkhaki:[189,183,107],
   darkmagenta:[139,0,139],
   darkolivegreen:[85,107,47],
   darkorange:[255,140,0],
   darkorchid:[153,50,204],
   darkred:[139,0,0],
   darksalmon:[233,150,122],
   darkviolet:[148,0,211],
   fuchsia:[255,0,255],
   gold:[255,215,0],
   green:[0,128,0],
   indigo:[75,0,130],
   khaki:[240,230,140],
   lightblue:[173,216,230],
   lightcyan:[224,255,255],
   lightgreen:[144,238,144],
   lightgrey:[211,211,211],
   lightpink:[255,182,193],
   lightyellow:[255,255,224],
   lime:[0,255,0],
   magenta:[255,0,255],
   maroon:[128,0,0],
   navy:[0,0,128],
   olive:[128,128,0],
   orange:[255,165,0],
   pink:[255,192,203],
   purple:[128,0,128],
   violet:[128,0,128],
   red:[255,0,0],
   silver:[192,192,192],
   white:[255,255,255],
   yellow:[255,255,0],
   transparent: [255,255,255]
};

//----------------------------------------------------------------------------------
(function($) {
   if(!document.defaultView || !document.defaultView.getComputedStyle){ // IE6-IE8
      var oldCurCSS = $.curCSS;
      $.curCSS = function(elem, name, force){
         if(name === 'background-position'){
            name = 'backgroundPosition';
         }
         if(name !== 'backgroundPosition' || !elem.currentStyle || elem.currentStyle[ name ]){
            return oldCurCSS.apply(this, arguments);
         }
         var style = elem.style;
         if ( !force && style && style[ name ] ){
            return style[ name ];
         }
         return oldCurCSS(elem, 'backgroundPositionX', force) +' '+ oldCurCSS(elem, 'backgroundPositionY', force);
      };
   }
   
   var oldAnim = $.fn.animate;
   $.fn.animate = function(prop){
      if('background-position' in prop){
         prop.backgroundPosition = prop['background-position'];
         delete prop['background-position'];
      }
      if('backgroundPosition' in prop){
         prop.backgroundPosition = '('+ prop.backgroundPosition;
      }
      return oldAnim.apply(this, arguments);
   };
   
   function toArray(strg){
      strg = strg.replace(/left|top/g,'0px');
      strg = strg.replace(/right|bottom/g,'100%');
      strg = strg.replace(/([0-9\.]+)(\s|\)|$)/g,"$1px$2");
      var res = strg.match(/(-?[0-9\.]+)(px|\%|em|pt)\s(-?[0-9\.]+)(px|\%|em|pt)/);
      return [parseFloat(res[1],10),res[2],parseFloat(res[3],10),res[4]];
   }
   
   $.fx.step. backgroundPosition = function(fx) {
      if (!fx.bgPosReady) {
         var start = $.curCSS(fx.elem,'backgroundPosition');
         if(!start){//FF2 no inline-style fallback
            start = '0px 0px';
         }
         
         start = toArray(start);
         fx.start = [start[0],start[2]];
         var end = toArray(fx.end);
         fx.end = [end[0],end[2]];
         
         fx.unit = [end[1],end[3]];
         fx.bgPosReady = true;
      }
      //return;
      var nowPosX = [];
      nowPosX[0] = ((fx.end[0] - fx.start[0]) * fx.pos) + fx.start[0] + fx.unit[0];
      nowPosX[1] = ((fx.end[1] - fx.start[1]) * fx.pos) + fx.start[1] + fx.unit[1];           
      fx.elem.style.backgroundPosition = nowPosX[0]+' '+nowPosX[1];

   };
})(jQuery);

//----------------------------------------------------------------------------------
$(function(){
   $.extend($.fn.disableTextSelect = function() {
      return this.each(function(){
         if($.browser.mozilla){
            $(this).css('MozUserSelect','none');
         }else if($.browser.msie){//IE
            $(this).bind('selectstart',function(){return false;});
         }else{
            $(this).mousedown(function(){return false;});
         }
      });
   });
   $('.menu').disableTextSelect();
});

//----------------------------------------------------------------------------------
function markCurrent(newcurr)
{
   if(newcurr != lastcurr)
   {
      var str_old = '#idx_menutop_' +lastcurr;
      var str_new = '#idx_menutop_' +newcurr;
      var obj_old = document.getElementById('idx_menutop_' +lastcurr);
      var img_old = document.getElementById('idx_menuimg_' +lastcurr);
      
      var obj_new = document.getElementById('idx_menutop_' +newcurr);
      var img_new = document.getElementById('idx_menuimg_' +newcurr);

      //----------------------------------------------------------------------------------
      $(str_old +' a > b').css({'color': '#FFFFFF'});
      $(str_old +' a').css({'backgroundPosition': 'left 200px'});
      $(str_old +' a > b').css({ 'backgroundPosition': 'right 200px'});
      obj_old.className = '';

      //----------------------------------------------------------------------------------
      obj_new.className = 'current';

      $(str_new +' a > b').css({'color': '#FFFFFF'});
      $(str_new +' a').css('backgroundPosition', 'left 200px');
      $(str_new +' a > b').css('backgroundPosition', 'right 200px');
      
      $(str_new +' a').css({'backgroundPosition': 'left 0px'});
      $(str_new +' a > b').css({'backgroundPosition': 'right 0px'});
      $(str_new +' a > b').css({'color': '#111111'});

      //img_old.src = '/libs/jscripts/droplmenu/images/menu_entry.gif';
      
      lastcurr = newcurr;
   }
}

//----------------------------------------------------------------------------------
function hintentOver()
{
   $('#' +$(this).attr('ulref')).fadeIn('fast');
}
//----------------------------------------------------------------------------------
function hintentOut()
{
   $('#' +$(this).attr('ulref')).hide();
}
//----------------------------------------------------------------------------------
function manhintentOut(ulref)
{
   if(document.getElementById(ulref)!==undefined && document.getElementById(ulref)!==null)
      $('#' +ulref).hide();

   $('li [ulref=' +ulref +'] > a').css({'color':'#000000'});
   $('li [ulref=' +ulref +']').css({'border-bottom':'3px solid #2B6CB4'});

   if(ulref != lastulma)
   {
      $('li [ulref=' +lastulma +'] > a').css({'color':'#222222'});
      $('li [ulref=' +lastulma +']').css({'border-bottom':'0px'});
   }

   lastulma = ulref;
}

//----------------------------------------------------------------------------------
function relocateMenuEntry(topid, currid, ulref)
{
   if(topid != currid)
      markCurrent(topid);

   if(ulref != lastulma)
      manhintentOut(ulref);
}
