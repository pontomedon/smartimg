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
	var $resolutions	= array(	1024,
									768,
									640,
									320,
									200,
									100);
	var $aspects 		= array(	'16:10',
									'16:9',
									'4:3',
									'1:1');

	var $cacheRootDir	= "/demo/img/cache";
	
	/**
	 * Constructor
	 */
	function __construct()
	{
		
	}
	
	/**
	 * finds the next larger resolution breakpoint for the given width
	 * @param integer $width
	 * @return integer|string:	either the resolution breakpoint or the string 'orig' if
	 * 							the requested width is larger than the largest breakpoint
	 */
	private function getWidthBreakpoint($width)
	{
		// if no width was passed, we don't resize
		if($width === null)
			return 'orig';
		
		// copy and sort the resolutions array, just in case
		$resolutions = $this->resolutions;
		arsort($resolutions);
		
		// if the image is larger than the largest breakpoint, we don't resize
		if($width >= $resolutions[0])
			return 'orig';
		
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
	protected function getAspect($aspect, &$aspectVal, &$aspectDir){
		
		// determine aspect from list
		if($aspect!=null && in_array($aspect,$this->aspects))
			// convert aspect string to values
			$aspectVal = $this->getAspectValues($aspect);
		else
			$aspect = $this->sourceAspect;
			
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
			$srcDir = '/' . substr($srcPath, 0, $lastSlash);
			$srcFile = substr($srcPath, $lastSlash + 1);
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
		
		// determine resolution breakpoint
		$widthBreakpoint = $this->getWidthBreakpoint($width);
			
		// determine aspect parameters
		// note: aspectVal and aspectDir are used for writing back from the method
		$aspect = $this->getAspect($aspect, $aspectVal, $aspectDir);
		
		/*
		 * calculuate the cache path
		 * the cache folder is organized as follows:
		 * [cachefolder][aspect][resolutionbreakpoint]
		 */
		$cacheDir = $this->cacheRootDir . '/' . $aspectDir . '/' . $widthBreakpoint . $srcDir;
		$cachePath = $cacheDir . '/' . $filename;
		
		// check cache
		if(!$this->checkCache($cachePath, $srcPath))
		{
			if(!file_exists($_SERVER['DOCUMENT_ROOT'].$cacheDir))
				mkdir($_SERVER['DOCUMENT_ROOT'].$cacheDir, 0755, true);
			
			/*
			 * TODO: resize image instead of just copying
			 */
			copy($_SERVER['DOCUMENT_ROOT'].$srcPath, $_SERVER['DOCUMENT_ROOT'].$cachePath);
			
			
// 			// init imagine
// 			$imagine = new \Imagine\Gd\Imagine();
// 			// open original image
// 			$image = $imagine->open($absPath);
// 			// get size
// 			$size = $image->getSize();
// 			// resize by maintaining the aspect
// 			$newSize = $size->widen($widthBreakpoint);
// 			$image->resize($newSize);
// 			/*
// 			 *	Aspect is set -> crop image
// 			*/
// 			if($aspect != $this->sourceAspect){
// 			// define box of cropped image
// 				$cropBox = new Imagine\Image\Box(	$newSize->getWidth(),
// 													$newSize->getWidth() * ($aspectVal[1]/$aspectVal[0]));
// 				$image = $image->thumbnail($cropBox);
// 				var_dump($size->getWidth() * ($aspectVal[1]/$aspectVal[0]));
// 			}
// 			// store processed image
// 			$image->save($imagePath);
			
			
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
