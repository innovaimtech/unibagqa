<?
$_FRM["TBLH"] =& $workbook->add_format();
$_FRM["TBLH"]->set_size(9);
$_FRM["TBLH"]->set_bold(1);

$_FRM["TBLR"] =& $workbook->add_format();
$_FRM["TBLR"]->set_size(9);

$_FRM["TBLRB"] =& $workbook->add_format();
$_FRM["TBLRB"]->set_size(9);
$_FRM["TBLRB"]->set_border(1);
$_FRM["TBLRB"]->set_bg_color('black');

$_FRM["TBLLB"] =& $workbook->add_format();
$_FRM["TBLLB"]->set_size(9);
$_FRM["TBLLB"]->set_border(1);
$_FRM["TBLLB"]->set_pattern(1);
$_FRM["TBLLB"]->set_color('black');
$_FRM["TBLLB"]->set_fg_color('silver');
$_FRM["TBLLB"]->set_bg_color('black');

$_FRM["TBLCS"] =& $workbook->add_format();
$_FRM["TBLCS"]->set_size(9);
$_FRM["TBLCS"]->set_border(1);
$_FRM["TBLCS"]->set_pattern(1);
$_FRM["TBLCS"]->set_color('black');         // Color del texto
$_FRM["TBLCS"]->set_fg_color('silver');   // Fondo celeste suave
$_FRM["TBLCS"]->set_bg_color('black');      // Contraste
$_FRM["TBLCS"]->set_align('center');        // Centrado horizontal

$_FRM["TBLHCXL"] =& $workbook->add_format();
$_FRM["TBLHCXL"]->set_size(9);
$_FRM["TBLHCXL"]->set_border(1);
$_FRM["TBLHCXL"]->set_bg_color('black');
$_FRM["TBLHCXL"]->set_align('right');

$_FRM["FECHA"] =& $workbook->add_format();
$_FRM["FECHA"]->set_num_format('dd/mm/yyyy');

$_FRM["FECHAHORA"] =& $workbook->add_format();
$_FRM["FECHAHORA"]->set_num_format('dd/mm/yyyy HH:MM');

$_FRM["HORA"] =& $workbook->add_format();
$_FRM["HORA"]->set_num_format('hh:mm');
$_FRM["HORA"]->set_size(9);

$_FRM["DURACION"] =& $workbook->add_format();
$_FRM["DURACION"]->set_num_format('[h]:mm');

$_FRM["MONTO"] =& $workbook->add_format();
$_FRM["MONTO"]->set_num_format('#,##0');

$_FRM["DECIMAL4"] =& $workbook->add_format();
$_FRM["DECIMAL4"]->set_num_format('0.0000');

$_FRM["DECIMAL2"] =& $workbook->add_format();
$_FRM["DECIMAL2"]->set_num_format('0.00');

?>