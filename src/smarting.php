<?php

define('REGEX_WIDTH', '/^[\d]+$/');
define('REGEX_ASPECT', '/^[\d]+:[\d]+$/');

/**
 * 
 * @author pontomedon & mrmuh
 */
class SmartImg
{
	/*
	 * Settings
	 */
	var $resolutions		= array(	1024,
										768,
										640,
										320,
										200,
										100);
	var $aspects 			= array(	'16:10',
										'16:9',
										'4:3',
										'1:1');
	const DEFAULT_ASPECT 	= "orig";
	const DEFAULT_RESOLUTION= "orig";
	const JPEG_QUALITY		= 75;
	
	var $cacheRootDir		= "/demo/img/cache";
	
	/**
	 * Constructor
	 */
	function __construct()
	{
		
	}
	
	/**
	 * finds the next larger resolution breakpoint for the given width
	 * @param integer $width
	 * @return integer|string:	either the resolution breakpoint or defaultWidth if
	 * 							the requested width is larger than the largest breakpoint
	 */
	private function getWidthBreakpoint($width, $dstAspect, $srcDimensions)
	{
		// if no width was passed, we don't resize
		// TODO: depending on the aspect this could not work as aspected
		//if($width === null)
		//	return SmartImg::DEFAULT_RESOLUTION;
		
		/*
		 * take the aspect ratio into account to determine the possible width
		 */
		// get the src image dimensions
		$srcWidth = $srcDimensions[0];
		$srcHeight = $srcDimensions[1];			
			
		// determine ratios of src and desired aspect
		$srcRatio = $srcHeight/$srcWidth;
		$dstRatio = $dstAspect[1]/$dstAspect[0];
			
		// check if the dstRatio fits into the srcRatio
		if($dstRatio > $srcRatio){
			/*
			 * at this point we know that the height of the destination aspect is
			 * proportional higher than the source height by scaling factor s.
			 * E.g given a box A with an 2:1 aspect (r_0 = 0.5) and the desired 
			 * box B with an 1:1 aspect (r_1 = 1);
			 * The s is determined by the ratio s = r_0 / r_1; the width of B could 
			 * be determined by A.width * s;
			 */			
			$scalingFactor = $srcRatio / $dstRatio;
			$dstWidth = (int)($srcWidth * $scalingFactor);
			
			// if no width was passed, use original width
			if($width === null)
				$width = $srcWidth;
			else
				// for the breakpoint selection we use the minimum of both widths
				$width = min($dstWidth,$width);			
		}
		
	
		
		// copy and sort the resolutions array, just in case
		$resolutions = $this->resolutions;
		arsort($resolutions);
		
		// if the image is larger than the largest breakpoint, we don't resize
		if($width >= $resolutions[0])
			return SmartImg::DEFAULT_RESOLUTION;
		
		$resultingBreakpoint = $resolutions[0];
		foreach($resolutions as $currentBreakpoint)
		{
			if($currentBreakpoint >= $width)
				$resultingBreakpoint = $currentBreakpoint;
			else
				break;
		}
		return $resultingBreakpoint;
	}
	
	protected function getSanitizedAspect($aspect){
		return str_replace(":","_",$aspect);
	}
	
	protected function getAspectValues($aspect){
		return explode(":",$aspect);
	}
	
	/**
	 * @TODO: describe me
	 * @param string $aspect the aspect ration as string e.g. "1:1"
	 * @param array $aspectVal array containing the numeric values (write back!)
	 * @param string $aspectDir sanitized folder name (write back!)
	 * @return the aspect enum from resolutions
	 */
	protected function getAspect($aspect, $srcDimensions, &$aspectVal, &$aspectDir){
		
		// determine aspect from list
		if($aspect!=null && in_array($aspect,$this->aspects)){
			// convert aspect string to values
			$aspectVal = $this->getAspectValues($aspect);
		}else{
			// TODO find "nearest aspect"
			$aspectVal = array($srcDimensions[0], $srcDimensions[1]);
			$aspect = SmartImg::DEFAULT_ASPECT;
		}
			
		// get dirname of aspect
		$aspectDir = $this->getSanitizedAspect($aspect);
		
		return $aspect;
	}
	
	/**
	 * processes an image path, splitting it into directory and file
	 * @param string $srcPath	the full path
	 * @param string $srcDir	[out] the directory component, either an empty string or a path with a leading slash
	 * @param string $srcFile	[out] the file component, without a leading slash
	 */
	private function splitPath($srcPath, &$srcDir, &$srcFile)
	{
		// remove leading slash, if existent
		if($srcPath[0] === '/')
			$srcPath = substr($srcPath, 1);
		
		// default values
		$srcDir = '';
		$srcFile = $srcPath;
		
		// let's see if there's a remaining slash
		$lastSlash = strrpos($srcPath, '/');
		if($lastSlash !== false)
		{
			$srcDir = '/' . dirname($srcPath);
			$srcFile = basename($srcPath);
		}
	}
	
	/**
	 * checks whether $cachePath exists and has the same mtime as $srcPath
	 * @param string $cachePath		absolute path to the cache file
	 * @param string $srcPath		absolute path to the source file
	 * @return boolean if $cachePath exists and has the same mtime as $srcPath
	 */
	private function checkCache($cachePath, $srcPath)
	{
		if(	file_exists($_SERVER['DOCUMENT_ROOT'].$cachePath) &&
			filemtime($_SERVER['DOCUMENT_ROOT'].$cachePath) == filemtime($_SERVER['DOCUMENT_ROOT'].$srcPath))
			return true;
		return false;
	}
	
	
	
	/**
	 * @TODO: describe me
	 * @param string	$srcPath	the absolute path to the image (may not be null)
	 * @param int		$width		the desired width
	 * @param string	$aspect		the desired aspect ratio
	 * @return string				the path to the resized image
	 */
	private function getResizedImage($srcPath, $width=null, $aspect=null)
	{
		// check if the file exists
		if(!file_exists($_SERVER['DOCUMENT_ROOT'].$srcPath))
			return null;
		
		// split src into srcDir and srcFile (write-back params!)
		$this->splitPath($srcPath, $srcDir, $filename);
		
		// determine src image dimensions
		$srcDimensions = GetImageSize($_SERVER['DOCUMENT_ROOT'].$srcPath);
		
		// determine aspect parameters
		// note: aspectVal and aspectDir are used for writing back from the method
		$aspect = $this->getAspect($aspect, $srcDimensions, $aspectVal, $aspectDir);
			
		
		// determine resolution breakpoint
		$widthBreakpoint = $this->getWidthBreakpoint($width, $aspectVal, $srcDimensions);
					
		/*
		 * calculate the cache path
		 * the cache folder is organized as follows:
		 * [cachefolder][aspect][resolutionbreakpoint]
		 */
		$cacheDir = $this->cacheRootDir . '/' . $aspectDir . '/' . $widthBreakpoint . $srcDir;
		$cachePath = $cacheDir . '/' . $filename;
		
		// check cache
		if(!$this->checkCache($cachePath, $srcPath))
		{
			if(!file_exists($_SERVER['DOCUMENT_ROOT'].$cacheDir))
				@mkdir($_SERVER['DOCUMENT_ROOT'].$cacheDir, 0755, true);
			
			// check if the breakpoint corresponds to the default flag
			$widthBreakpointValue = $widthBreakpoint;
			if($widthBreakpoint === SmartImg::DEFAULT_RESOLUTION)
				$widthBreakpointValue = $srcDimensions[0];
			

			/*
			 * Following resizing code stems from Adaptive Images by Matt Wilcox, licensed under a Creative Commons Attribution 3.0 Unported License.
			 */
			
			// the image exentsion
			$extension = strtolower(pathinfo($_SERVER['DOCUMENT_ROOT'].$srcPath, PATHINFO_EXTENSION));
			
			// get width and height
			$srcWidth = $srcDimensions[0];
  			$srcHeight = $srcDimensions[1];
			
			// first resize the image to the breakpoint maintaining the aspect
  			$srcRatio = $srcHeight/$srcWidth;
  			$dstRatio = $aspectVal[1]/$aspectVal[0];
  			
			$dstWidth = $widthBreakpointValue;
			$dstHeight = ceil($dstWidth * $dstRatio);
			$dstImg = ImageCreateTrueColor($dstWidth, $dstHeight); // re-sized image
			
			switch ($extension) {
				case 'png':
					$srcImg = @ImageCreateFromPng($_SERVER['DOCUMENT_ROOT'].$srcPath); // original image
					break;
				case 'gif':
					$srcImg = @ImageCreateFromGif($_SERVER['DOCUMENT_ROOT'].$srcPath); // original image
					break;
				default:
					$srcImg = @ImageCreateFromJpeg($_SERVER['DOCUMENT_ROOT'].$srcPath); // original image
					ImageInterlace($dstImg, true); // Enable interlacing (progressive JPG, smaller size file)
					break;
			}
			
			if($extension=='png'){
				imagealphablending($dstImg, false);
				imagesavealpha($dstImg,true);
				$transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
				imagefilledrectangle($dstImg, 0, 0, $dstWidth, $dstHeight, $transparent);
			}
			
			// TODO: describe me :D 
			// determine start coordinates in src image
			if($dstRatio>$srcRatio){
				$cropBoxWidth = (int)($srcWidth * ($srcRatio / $dstRatio));
				$cropBoxHeight = (int)($cropBoxWidth * $dstRatio);
			}else{
				$cropBoxWidth = (int)($srcWidth);
				$cropBoxHeight = (int)($srcWidth * $dstRatio);
			}
			
			// resizing the image to the breakpoint width (maintaining the src ratio)
			ImageCopyResampled($dstImg, $srcImg, 0, 0, (int)(($srcWidth - $cropBoxWidth) / 2),  (int)(($srcHeight - $cropBoxHeight) / 2), $dstWidth, $dstHeight, $cropBoxWidth, $cropBoxHeight); // do the resize in memory
			ImageDestroy($srcImg);
			
			// TODO first resize then crop; note: this is only possible in some cases ( srcAspect <= dstAspect)
			
			
			// save the new file in the appropriate path, and send a version to the browser
			switch ($extension) {
				case 'png':
					$gotSaved = ImagePng($dstImg, $_SERVER['DOCUMENT_ROOT'].$cachePath);
					break;
				case 'gif':
					$gotSaved = ImageGif($dstImg, $_SERVER['DOCUMENT_ROOT'].$cachePath);
					break;
				default:
					$gotSaved = ImageJpeg($dstImg, $_SERVER['DOCUMENT_ROOT'].$cachePath, SmartImg::JPEG_QUALITY);
					break;
			}
			ImageDestroy($dstImg);				
			
			// set mtime
			touch($_SERVER['DOCUMENT_ROOT'].$cachePath, filemtime($_SERVER['DOCUMENT_ROOT'].$srcPath));
		}
		
		return $cachePath;
	}
	
	
	
	/*
	 * --------------------------------------------------------------------------------
	 * public static functions, callable from the API
	 * --------------------------------------------------------------------------------
	 */
	
	/**
	 * Batch processing call for getting resized images
	 * 
	 * Data Structures used here:
	 * ImageRequestBundle: Array:
	 * - src	[required]	the path to the image
	 * - width	[optional]	the desired width
	 * - aspect	[optional]	the desired aspect ratio
	 * Image: Array:
	 * - src	[required]	the path to the image
	 * 
	 * @param ImageRequestBundle[] $imageBundles
	 * @return Image[]
	 */
	public static function getImage($imageRequestBundles)
	{
		$smartImg = new SmartImg();
		
		$result = array();
		for($i=0; $i<count($imageRequestBundles); $i++)
		{
			$src	= isset($imageRequestBundles[$i]['src'])	? $imageRequestBundles[$i]['src']		: null;
			$width	= isset($imageRequestBundles[$i]['width'])	? $imageRequestBundles[$i]['width']		: null;
			$aspect	= isset($imageRequestBundles[$i]['aspect'])	? $imageRequestBundles[$i]['aspect']	: null;
			
			// if width or aspect are invalid, ignore them.
			if(!preg_match(REGEX_WIDTH, $width))
				$width = null;
			if(!preg_match(REGEX_ASPECT, $aspect))
				$aspect = null;
			
			if($src !== null && strcmp($src,'') != 0)
			{
				if($width === null && $aspect === null)
					$result[] = array('src' => $src); // shortcut
				else
					$result[] = array('src' => $smartImg->getResizedImage($src, $width, $aspect));
			}
			else
				$result[] = array('src' => null);
		}
		
		return $result;
	}
	
	public static function test($args = null)
	{
		return array(	'Hello' => 'World',
						'args' => $args
		);
	}
}

/*
 * utility methods
 */
function exit_error($message)
{
	http_response_code(400);
	echo $message;
	die;
}

/*
 * check GD
 */
if((!extension_loaded('gd')) && (!function_exists('dl') || !dl('gd.so')))
	exit_error('Unable to load GD');

/*
 * check call
 */
if(!isset($_REQUEST['method']))
	exit_error('mandatory parameter method not given.');
if(isset($_REQUEST['arg']) && isset($_REQUEST['args']))
	exit_error('arg and args may not be given at the same time.');
$method = $_REQUEST['method'];
if(!is_callable(array('SmartImg', $method)))
	exit_error('undefined method ' . $method);

/*
 * execute call
 */
if(isset($_REQUEST['arg']))
	echo json_encode(call_user_func(array('SmartImg', $method), json_decode($_REQUEST['arg'],true)));
else if(isset($_REQUEST['args']))
	echo json_encode(call_user_func_array(array('SmartImg', $method), json_decode($_REQUEST['args'],true)));
else
	echo json_encode(call_user_func(array('SmartImg', $method)));
