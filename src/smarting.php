<?php

/**
 * 
 * @author pontomedon & mrmuh
 */
class SmartImg
{

	var $resolutions		= array(	1024,
										768,
										640,
										320,
										200,
										100);
	
	var $sourceAspect		= "original";
	var $aspects 			= array(	'16:10',
										'16:9',
										'4:3',
										'1:1');

	var $cachingPath		= "../demo/img/cache";
	var $cachingUrl			= "/demo/img/cache";
	
	/**
	 * Constructor
	 */
	function __construct()
	{
		
	}
	
	protected function getNextHigherResolution($resolutionToTest){
		// init with highest resolution
		$nextHigherResoltion = $this->resolutions[0];
				
		// iterate over all defined resolutions
		foreach($this->resolutions as $resolution){
			
			if($resolution >= $resolutionToTest)
				$nextHigherResoltion = $resolution;
			else
				// nextHigherResoltion holds correct size
				break;
		}
		
		return $nextHigherResoltion;
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
		if($aspect!=null && in_array($aspect,$this->aspects)){
			// convert aspect string to values
			$aspectVal = $this->getAspectValues($aspect);
		}else{
			$aspect = $this->sourceAspect;
		}
			
		// get dirname of aspect
		$aspectDir = $this->getSanitizedAspect($aspect);
		
		return $aspect;
	}
	
	/**
	 * @TODO: describe me
	 * @param string	$src		the absolute path to the image (may not be null)
	 * @param int		$width		the desired width
	 * @param string	$aspect		the desired aspect ratio
	 * @return string				the path to the resized image
	 */
	private function getResizedImage($src, $width=null, $aspect=null)
	{		
		// get document root to complete the absolute path
		$absPath =  $_SERVER['DOCUMENT_ROOT'].$src;
		
		// check for existence
		if(file_exists($absPath)) {

			// determine resolution breakpoint
			$widthBreakpoint = $this->getNextHigherResolution($width); 
			
			// determine aspect parameters
			// note: aspectVal and aspectDir are used for writing back from the method
			$aspect = $this->getAspect($aspect, $aspectVal, $aspectDir);
			
			
			/*
			 * check if desired image exists
			 */ 
			
			// use the cachedir for resized images
			// dir structure follows the following design:
			// [cachefolder][resolutionbreakpoint][aspect]
			
			// breakpoint folder
			$imagePath = $this->cachingPath."/".$widthBreakpoint;
			if(!file_exists($imagePath))
				mkdir($imagePath);
			
			// aspect folder
			$imagePath .= "/".$aspectDir;
			if(!file_exists($imagePath))
				mkdir($imagePath);
			
			
			// complete filename
			$imagePath .= "/".basename($src);
			
			// check if file exists
			if(file_exists($imagePath)){
				// return url!
				return $this->cachingUrl."/".$widthBreakpoint."/".$aspectDir."/".basename($src);
			}
			
			/*
			 * image does not exist -> process 
			 */
			
			// init imagine
			$imagine = new \Imagine\Gd\Imagine();
						
			// open original image
			$image = $imagine->open($absPath);
			// get size
			$size = $image->getSize();
			
			// resize by maintaining the aspect
			$newSize = $size->widen($widthBreakpoint);
			$image->resize($newSize);
			
			/*
			 *	Aspect is set -> crop image 
			 */
			if($aspect != $this->sourceAspect){
				
				// define box of cropped image
				$cropBox = new Imagine\Image\Box(	$newSize->getWidth(), 
													$newSize->getWidth() * ($aspectVal[1]/$aspectVal[0]));
				
				$image = $image->thumbnail($cropBox);		
				var_dump($size->getWidth() * ($aspectVal[1]/$aspectVal[0]));
			}
			
			
			// store processed image
			$image->save($imagePath);
			
			// return url			
			return $this->cachingUrl."/".$widthBreakpoint."/".$aspectDir."/".basename($src);
		}else{
			throw new Exception('Source image not found!');
		}	
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
			
			if($src !== null)
				$result[] = array('src' => $smartImg->getResizedImage($src, $width, $aspect));
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
 * setup autoload for imagine
 * Credit: http://www.phparch.com/2011/03/image-processing-with-imagine/
 */
function imagineLoader($class) {
	// relative path to lib
	$base = dirname(__FILE__);
	$base .= '/../lib/Imagine/lib/';
	
	// convert class name to path
	$path = $class;
	$path = str_replace('\\', DIRECTORY_SEPARATOR, $path) . '.php';
	$path = $base.$path;

	if (file_exists($path)) {
		include $path;
	}
}
spl_autoload_register('\imagineLoader');


/*
 * process request
 */
if(!isset($_REQUEST['method']))
	exit_error('mandatory parameter method not given.');
if(isset($_REQUEST['arg']) && isset($_REQUEST['args']))
	exit_error('arg and args may not be given at the same time.');
$method = $_REQUEST['method'];
if(!is_callable(array('SmartImg', $method)))
	exit_error('undefined method ' . $method);

if(isset($_REQUEST['arg']))
	echo json_encode(call_user_func(array('SmartImg', $method), json_decode($_REQUEST['arg'],true)));
else if(isset($_REQUEST['args']))
	echo json_encode(call_user_func_array(array('SmartImg', $method), json_decode($_REQUEST['args'],true)));
else
	echo json_encode(call_user_func(array('SmartImg', $method)));
