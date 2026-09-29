<?php

// Parses a color value to an array.
function color2rgb($color)
{
	$rgb = array();
		
	$rgb[] = 0xFF & ($color >> 16);
	$rgb[] = 0xFF & ($color >> 8);
	$rgb[] = 0xFF & ($color >> 0);

	return $rgb;
}

// Parses a color value to an array.
function color2rgba($color)
{
	$rgb = array();
		
	$rgb[] = 0xFF & ($color >> 16);
	$rgb[] = 0xFF & ($color >> 8);
	$rgb[] = 0xFF & ($color >> 0);
	$rgb[] = 0xFF & ($color >> 24);

	return $rgb;
}

// Adapted from http://homepage.smc.edu/kennedy_john/BELIPSE.PDF
function imagefilledellipseaa_Plot4EllipsePoints(&$im, $CX, $CY, $X, $Y, $color, $t)
{
	imagesetpixel($im, $CX+$X, $CY+$Y, $color); //{point in quadrant 1}
	imagesetpixel($im, $CX-$X, $CY+$Y, $color); //{point in quadrant 2}
	imagesetpixel($im, $CX-$X, $CY-$Y, $color); //{point in quadrant 3}
	imagesetpixel($im, $CX+$X, $CY-$Y, $color); //{point in quadrant 4}

	$aColor = color2rgba($color);
	$mColor = imagecolorallocate($im, $aColor[0], $aColor[1], $aColor[2]);
	if ($t == 1)
	{
	  	imageline($im, $CX-$X, $CY-$Y+1, $CX+$X, $CY-$Y+1, $mColor);
	  	imageline($im, $CX-$X, $CY+$Y-1, $CX+$X, $CY+$Y-1, $mColor);
	} else {
	  	imageline($im, $CX-$X+1, $CY-$Y, $CX+$X-1, $CY-$Y, $mColor);
	  	imageline($im, $CX-$X+1, $CY+$Y, $CX+$X-1, $CY+$Y, $mColor);
	}
	imagecolordeallocate($im, $mColor);
}

// Adapted from http://homepage.smc.edu/kennedy_john/BELIPSE.PDF
function imagefilledellipseaa(&$im, $CX, $CY, $Width, $Height, $color)
{

	$XRadius = floor($Width/2);
	$YRadius = floor($Height/2);

	$baseColor = color2rgb($color);

	$TwoASquare = 2*$XRadius*$XRadius;
	$TwoBSquare = 2*$YRadius*$YRadius;
	$X = $XRadius;
	$Y = 0;
	$XChange = $YRadius*$YRadius*(1-2*$XRadius);
	$YChange = $XRadius*XRadius;
	$EllipseError = 0;
	$StoppingX = $TwoBSquare*$XRadius;
	$StoppingY = 0;

	$alpha = 77;	
	$color = imagecolorexactalpha($im, $baseColor[0], $baseColor[1], $baseColor[2], $alpha);
	while ($StoppingX >= $StoppingY) // {1st set of points, y' > -1}
	{
		imagefilledellipseaa_Plot4EllipsePoints($im, $CX, $CY, $X, $Y, $color, 0);
		$Y++;
		$StoppingY += $TwoASquare;
		$EllipseError += $YChange;
	 	$YChange += $TwoASquare;
		if ((2*$EllipseError + $XChange) > 0)
		{
			$X--;
			$StoppingX -= $TwoBSquare;
			$EllipseError += $XChange;
			$XChange += $TwoBSquare;
		}
		
		// decide how much of pixel is filled.
		$filled = $X - sqrt(($XRadius*$XRadius - (($XRadius*$XRadius)/($YRadius*$YRadius))*$Y*$Y));
		$alpha = abs(90*($filled)+37);
		imagecolordeallocate($im, $color);
		$color = imagecolorexactalpha($im, $baseColor[0], $baseColor[1], $baseColor[2], $alpha);
	}
	// { 1st point set is done; start the 2nd set of points }

	$X = 0;
	$Y = $YRadius;
	$XChange = $YRadius*$YRadius;
	$YChange = $XRadius*$XRadius*(1-2*$YRadius);
	$EllipseError = 0;
	$StoppingX = 0;
	$StoppingY = $TwoASquare*$YRadius;
	$alpha = 77;	
	$color = imagecolorexactalpha($im, $baseColor[0], $baseColor[1], $baseColor[2], $alpha);
	
	while ($StoppingX <= $StoppingY) // {2nd set of points, y' < -1}
	{
		imagefilledellipseaa_Plot4EllipsePoints($im, $CX, $CY, $X, $Y, $color, 1);
		$X++;
		$StoppingX += $TwoBSquare;
		$EllipseError += $XChange;
		$XChange += $TwoBSquare;
		if ((2*$EllipseError + $YChange) > 0)
		{
			$Y--;
			$StoppingY -= $TwoASquare;
			$EllipseError += $YChange;
			$YChange += $TwoASquare;
		}
		
		// decide how much of pixel is filled.
		$filled = $Y - sqrt(($YRadius*$YRadius - (($YRadius*$YRadius)/($XRadius*$XRadius))*$X*$X));
		$alpha = abs(90*($filled)+37);
		imagecolordeallocate($im, $color);
		$color = imagecolorexactalpha($im, $baseColor[0], $baseColor[1], $baseColor[2], $alpha);
	}
}

?>