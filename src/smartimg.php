﻿<?php

define('REGEX_WIDTH', '/^[\d]+$/');
define('REGEX_ASPECT', '/^([\d]+):([\d]+)$/');

/**
 * 
 * @author pontomedon & mrmuh
 */
class SmartImg
{
	/*
	 * User Settings
	 */
	// the resolution breakpoints
	var $resolutions			= array(	1024,
											800,
											640,
											320,
											200,
											100);
	
	// the aspect breakpoints
	var $aspects 				= array(	'16:10',
											'16:9',
											'4:3',
											'1:1');
	
	// the directory for the original aspect
	const DEFAULT_ASPECT 		= "orig";
	// the directory for the original resolution
	const DEFAULT_RESOLUTION	= "orig";
	// the quality settings for jpeg images
	const JPEG_QUALITY			= 75;
	// whether or not to return the src when something goes wrong. useful for images outside of the docroot (urls, etc)
	const RETURN_SRC_ON_FAIL	= true;
	
	// the cache directory, relative to the docroot
	var $cacheRootDir			= "/demo/cache";
	
	/*
	 * --------------------------------------------------------------------------------
	 * STOP EDITING HERE
	 * --------------------------------------------------------------------------------
	 */
	
	/**
	 * array (size=1)
	 *   '<aspectStr>' => 
	 *     array (size=3)
	 *       'dir' => string
	 *       'width' => int
	 *       'height' => int
	 */
	private $_aspects;

	/**
	 * an sorted array of arrays like
	 * array (size=2)
     *     'width' => int
     *     'dir' => string
     * The first entry is automatically added as a default breakpoint
	 */
	private $_resolutions;
	
	/**
	 * Constructor
	 */
	function __construct()
	{
		// copy and sort the user defined breakpoints
		$resolutions = $this->resolutions;
		arsort($resolutions);
		
		// we put a default breakpoint as first entry
		$this->_resolutions[] = array(
				'width' => PHP_INT_MAX,
				'dir' => SmartImg::DEFAULT_RESOLUTION
		);
		// ... and copy all resolutions into our array
		foreach($resolutions as $resolution)
		{
			if(!preg_match(REGEX_WIDTH, $resolution))
				SmartImg::exit_error($resolution . ' is not a valid resolution breakpoint');
			$this->_resolutions[] = array(
					'width' => $resolution,
					'dir' => (string)$resolution
			);
		}

		// we put a default entry first...
		$this->_aspects[SmartImg::DEFAULT_ASPECT] = array(
				'dir' => SmartImg::DEFAULT_ASPECT,
				'width' => null,
				'height' => null
		);
		// and copy all aspects into our array.
		foreach($this->aspects as $aspect)
		{
			$matches = array();
			if(!preg_match(REGEX_ASPECT, $aspect, $matches) || count($matches) != 3)
				SmartImg::exit_error($aspect . ' is not a valid aspect breakpoint');
			$this->_aspects[$aspect] = array(
					'dir' => str_replace(':', '_', $aspect),
					'width' => (int)$matches[1],
					'height' => (int)$matches[2]
			);
		}
	}
	
	/**
	 * fits the largest rectangle with the given aspect ratio into the given image dimensions
	 * @param array $srcDimensions must have two integer components, width and height
	 * @param array $aspect as in $this->_aspects
	 * @return array with two integer components, width and height.
	 */
	private function getCroppedDimensions(array $srcDimensions, array $aspect)
	{
		$srcAspect = $srcDimensions[0] / $srcDimensions[1];
		$dstAspect = $aspect['width'] / $aspect['height'];
		
		if($srcAspect > $dstAspect)
		{
			// if srcAspect is larger than dstAspect, we have to cut left and right
			return array (
					(int)($srcDimensions[1] * $dstAspect),
					$srcDimensions[1]
			);
		}
		else
		{
			// if dstAspect is larget than srcAspect, we have to cut at the top and bottom
			return array (
					$srcDimensions[0],
					(int)($srcDimensions[0] / $dstAspect)
			);
		}
	}
	
	/**
	 * finds the next larger resolution breakpoint for the given width.
	 * if an aspect is specified, we calculate the image dimensions after cropping 
	 * @param $width					the desired width of the image (may be null for original width)
	 * @param $srcDimensions			the dimensions of the image in question (array(width, height))
	 * @return array					an array as in $this->_resolutions
	 */
	private function getWidthBreakpoint($width = null, $srcDimensions)
	{
		// if no width was passed, we don't resize
		if($width === null)
			return $this->_resolutions[0];
		
		// the first breakpoint is always the default one with PHP_MAX_INT width, so if width
		// is larger than the largest user defined breakpoint, the default one will match.
		$resultingBreakpoint = $this->_resolutions[0];
		foreach($this->_resolutions as $currentBreakpoint)
		{
			if($currentBreakpoint['width'] >= $width)
				$resultingBreakpoint = $currentBreakpoint;
			else
				break;
		}
		
		// we don't upscale, so if the selected breakpoint is larger than the image, use the default breakpoint
		if($resultingBreakpoint['width'] >= $srcDimensions[0])
			$resultingBreakpoint = $this->_resolutions[0];
		
		return $resultingBreakpoint;
	}
	
	/**
	 * Parses a string of the form "<width>:<height>". If null or an invalid
	 * aspect string is passed, the default aspect (no change) is returned, where
	 * both width and height are set to null
	 * 
	 * @param string $aspectStr
	 * @return array (size=3)
	 *             'dir' => string '<width>_<height>' (length=5)
	 *             'width' => int <width>
	 *             'height' => int <height>
	 */
	private function parseAspect($aspectStr = null)
	{
		if($aspectStr !== null &&  array_key_exists($aspectStr, $this->_aspects))
			return $this->_aspects[$aspectStr];
		else
			return $this->_aspects[SmartImg::DEFAULT_ASPECT];
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
	 * Writes an image to the filesystem, setting the written file to the mtime specifed
	 * @param array $image			as returned by readImage()
	 * @param string $cachePath		relative to docroot
	 * @param int $mtime			a unix timestamp to set the mtime of the new file to
	 */
	private function putCache($image, $cachePath, $mtime)
	{
		$this->writeImage($image, $cachePath);
		// set mtime
		touch($_SERVER['DOCUMENT_ROOT'].$cachePath, $mtime);
	}
	
	/**
	 * reads an image from the file system and returns it.
	 * @param string $path		the path to the image (relative to docroot)
	 * @return null|array		an array like the following:
	 * 							array (size=2)
	 * 							    'resource' => resource,
	 * 							    'width' => int
	 * 							    'height' => int
	 * 								'extension' => string
	 * 							or null if the image doesn't exist
	 */
	private function readImage($path)
	{
		// check if the file exists
		if(!file_exists($_SERVER['DOCUMENT_ROOT'].$path))
			return null;

		$extension = strtolower(pathinfo($_SERVER['DOCUMENT_ROOT'].$path, PATHINFO_EXTENSION));

		switch ($extension)
		{
			case 'png':
				$img = @ImageCreateFromPng($_SERVER['DOCUMENT_ROOT'].$path);
				break;
			case 'gif':
				$img = @ImageCreateFromGif($_SERVER['DOCUMENT_ROOT'].$path);
				break;
			default:
				$img = @ImageCreateFromJpeg($_SERVER['DOCUMENT_ROOT'].$path);
				break;
		}
		return array(
				'resource' => $img,
				'width' => imagesx($img),
				'height' => imagesy($img),
				'extension' => $extension
		);
	}
	
	/**
	 * reads the dimensions of an image from the filesystem
	 * @param string $path			relative to docroot
	 * @return null|array			an array(width,height) or null, if the image is not found
	 */
	private function getImageDimensions($path)
	{
		// check if the file exists
		if(!file_exists($_SERVER['DOCUMENT_ROOT'].$path))
			return null;
		
		$size = getimagesize($_SERVER['DOCUMENT_ROOT'].$path);
		
		return array(
				$size[0],
				$size[1]
		);
	}
	
	/**
	 * creates an empty image with the specified dimension; also stores
	 * an extension that will be used when saving the image.
	 * @param int $width			the width of the new image
	 * @param int $height			the height of the new image
	 * @param string $extension		the extension of the new image
	 * @return array 				an image as returned by readImage()
	 */
	private function createImage($width, $height, $extension)
	{
		$img =  array(
					'resource' => imagecreatetruecolor($width, $height),
					'width' => $width,
					'height' => $height,
					'extension' => $extension
		);
		
		if($extension=='png')
		{
			imagealphablending($img['resource'], false);
			imagesavealpha($img['resource'],true);
			$transparent = imagecolorallocatealpha($img['resource'], 255, 255, 255, 127);
			imagefilledrectangle($img['resource'], 0, 0, $width, $height, $transparent);
		}
		
		return $img;
	}
	
	/**
	 * writes an image to the filesystem
	 * @param array $image 		an image as returned by readImage()
	 * @param string $path		the path to save the image relative to docroot
	 * @return boolean			<true> on success, <false> otherwise
	 */
	private function writeImage(array $image, $path)
	{
		switch ($image['extension'])
		{
			case 'png': return imagepng($image['resource'], $_SERVER['DOCUMENT_ROOT'].$path);
			case 'gif': return imagegif($image['resource'], $_SERVER['DOCUMENT_ROOT'].$path);
			default:
				imageinterlace($image['resource'], true);
				return imagejpeg($image['resource'], $_SERVER['DOCUMENT_ROOT'].$path, SmartImg::JPEG_QUALITY);
		}
	}
	
	/**
	 * Gets an image for the specified width and aspect
	 * @param string	$srcPath	the absolute path to the image (may not be null)
	 * @param int		$width		the desired width
	 * @param string	$aspectStr	the desired aspect ratio
	 * @return string				the path to the resized image
	 */
	private function getResizedImage($srcPath, $width=null, $aspectStr=null)
	{
		// check if the file exists
		if(!file_exists($_SERVER['DOCUMENT_ROOT'].$srcPath))
		{
			if(SmartImg::RETURN_SRC_ON_FAIL)
				return $srcPath;
			else
				return null;
		}
		
		// split src into srcDir and srcFile (write-back params!)
		$this->splitPath($srcPath, $srcDir, $filename);
		
		// parse the aspect
		$aspect = $this->parseAspect($aspectStr);
		
		$srcImage = null;
		$srcDimensions = null;
		$dstImage = null;
		
		/*
		 * Step 1: Crop Image if an aspect was specified.
		 * If a valid aspect was given, the source image will be cropped
		 * and the result will be stored in the cache
		 */
		if($aspect['width'] !== null && $aspect['height'] !== null)
		{
			// check if cropped, not scaled version exists
			$cacheDir = $this->cacheRootDir . '/' . $aspect['dir'] . '/' .  SmartImg::DEFAULT_RESOLUTION . $srcDir;
			$cachePath = $cacheDir . '/' . $filename;
			
			if($this->checkCache($cachePath, $srcPath)) // cache hit
				$srcDimensions = $this->getImageDimensions($cachePath);
			else // cache miss
			{
				// create cache directory
				if(!file_exists($_SERVER['DOCUMENT_ROOT'].$cacheDir))
					@mkdir($_SERVER['DOCUMENT_ROOT'].$cacheDir, 0755, true);
			
				// cropped, not scaled version does not exist, open original
				$srcImage = $this->readImage($srcPath);
				// get cropped dimensions
				$srcDimensions = $this->getCroppedDimensions(array($srcImage['width'],$srcImage['height']), $aspect);
				
				// create the destination image
				$dstImage = $this->createImage($srcDimensions[0], $srcDimensions[1], $srcImage['extension']);
				
				// fill the destination image
				imagecopy(	$dstImage['resource'],									// $dst_im
							$srcImage['resource'],									// $src_im
							0,														// $dst_x
							0,														// $dst_y
							($srcImage['width']-$srcDimensions[0])/2,				// $src_x
							($srcImage['height']-$srcDimensions[1])/2,				// $src_y
							$srcDimensions[0],										// $src_w
							$srcDimensions[1]);										// $src_h
				
				// and save it in the cache
				$this->putCache($dstImage, $cachePath, filemtime($_SERVER['DOCUMENT_ROOT'].$srcPath));
				
				// we can now close the original (unmodified) image...
				imagedestroy($srcImage['resource']);
				
				// and keep the cropped, full size image in memory as the srcImage for step 2
				$srcImage = $dstImage;
				$dstImage = null;
			}
		}
		
		/*
		 * get the src image dimensions, if it's not already set from the cache.
		 */
		if($srcDimensions === null)
			$srcDimensions = $this->getImageDimensions($srcPath);
		
		/*
		 * we never ever upscale, so we clamp the requested width with the actual image width
		 */
		if($width !== null)
			$width = min($width, $srcDimensions[0]);
		
		/*
		 * get the width breakproint
		 */
		$widthBreakpoint = $this->getWidthBreakpoint($width, $srcDimensions);
		
		/*
		 * now we can check if we ended up with just using the original image, and if yes, 
		 * just return it
		 */
		if($aspect['dir'] == SmartImg::DEFAULT_ASPECT && $widthBreakpoint['dir'] == SmartImg::DEFAULT_RESOLUTION)
		{
			// in case we allocated an image, free it
			if($srcImage !== null)
				imagedestroy($srcImage['resource']);
			
			return $srcPath;
		}
		
		/*
		 * get the final path in the cache
		 */
		$cacheDir = $this->cacheRootDir . '/' . $aspect['dir'] . '/' . $widthBreakpoint['dir'] . $srcDir;
		$cachePath = $cacheDir . '/' . $filename;
		
		/*
		 * Resize Image if a width was specified
		 */
		if($width !== null)
		{
			if(!$this->checkCache($cachePath, $srcPath))
			{
				// cache miss
				if(!file_exists($_SERVER['DOCUMENT_ROOT'].$cacheDir))
					@mkdir($_SERVER['DOCUMENT_ROOT'].$cacheDir, 0755, true);

				if($srcImage === null)
					$srcImage = $this->readImage($srcPath);
				
				$dstAspect = $srcImage['width']/$srcImage['height'];
				
				$dstDimensions = array(
						$widthBreakpoint['width'],
						(int)($widthBreakpoint['width']/$dstAspect)
				);
				
				$dstImage = $this->createImage($dstDimensions[0], $dstDimensions[1], $srcImage['extension']);
				
				imagecopyresampled(	$dstImage['resource'],	// $dst_image
									$srcImage['resource'],	// $src_image
									0,						// $dst_x
									0,						// $dst_y
									0,						// $src_x
									0,						// $src_y
									$dstDimensions[0],		// $dst_w
									$dstDimensions[1],		// $dst_h
									$srcImage['width'],		// $src_w
									$srcImage['height']);	// $src_h

				$this->putCache($dstImage, $cachePath, filemtime($_SERVER['DOCUMENT_ROOT'].$srcPath));

				// free both images
				imagedestroy($srcImage['resource']);
				imagedestroy($dstImage['resource']);
			}
		}
		
		return $cachePath;
	}
	
	/**
	 * deletes a file or directory, recursively if necessary
	 * @param string $path
	 * @return bool <true> if successful, false otherwise
	 */
	private function recursiveDelete($path)
	{
		if(is_dir($path))
		{
			$empty = true;
				
			$dir = opendir($path);
			while (($file = readdir($dir)) !== false)
			{
				if($file === '.' || $file === '..')
					continue;
				$empty = $empty && $this->recursiveDelete($path . '/' . $file);
			}
			closedir($dir);
				
			if($empty)
			{
				echo "rmdir(" . $path . ") ... ";
				$result = @rmdir($path);
				echo ($result ? "ok" : "failed") . "<br>\n";
				return $result;
			}
			echo "skipping " . $path . " (not empty)<br>\n";
			return false;
		}
		else if(is_file($path))
		{
			echo "unlink(" . $path . ") ... ";
			$result = @unlink($path);
			echo ($result ? "ok" : "failed") . "<br>\n";
			return $result;
		}
		echo "skipping " . $path . " (not a file or directory)<br>\n";
		return false;
	}
	
	/**
	 * cleans up a given dir or file, recursively if it's a dir
	 * @param string $cacheSubRoot the first two components of the cache path, i.e. the aspect and resolution folders
	 * @param string $path the rest of the path
	 * @return boolean <true> if the file given by path was deleted or <false> otherwise
	 */
	private function cleanupCache($cacheSubRoot, $path)
	{
		$cacheRoot = $_SERVER['DOCUMENT_ROOT'] . $this->cacheRootDir . '/';
		$cacheFile = $cacheRoot . $cacheSubRoot . '/' . $path;
		
		if(is_dir($cacheFile))
		{
			$empty = true;
			
			$dir = opendir($cacheFile);
			while (($file = readdir($dir)) !== false)
			{
				if($file === '.' || $file === '..')
					continue;
				if(($this->cleanupCache($cacheSubRoot, ($path !== '' ? ($path . '/') : '') . $file)) === false)
					$empty=false;
			}
			closedir($dir);
			
			if($empty)
				return $this->recursiveDelete($cacheFile);
			echo "skipping " . $cacheFile . " (not empty)<br>\n";
		}
		else if(is_file($cacheFile))
		{
			$srcFile = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;
			if(!is_file($srcFile) || filemtime($cacheFile) < filemtime($srcFile))
				return $this->recursiveDelete($cacheFile);
			echo "up-to-date: " . $cacheFile . "<br>\n";
		}
		
		return false;
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
			{
				if(SmartImg::RETURN_SRC_ON_FAIL)
					$result[] = array('src' => $srcPath);
				else
					$result[] = array('src' => null);
			}
		}
		
		return $result;
	}
	
	public static function renderAll($dir)
	{
		
	}
	
	public static function cleanup()
	{
		$smartImg = new SmartImg();
		
		// iterate over aspects
		$cacheDH = opendir($_SERVER['DOCUMENT_ROOT'] . $smartImg->cacheRootDir);
		while (($aspectDir = readdir($cacheDH)) !== false)
		{
			if($aspectDir === '.' || $aspectDir === '..')
				continue;
			
			// check if $aspectDir is a valid aspect
			$aspectExists = false;
			foreach($smartImg->_aspects as $aspect)
			{
				if($aspect['dir'] == $aspectDir)
				{
					$aspectExists = true;
					break;
				}
			}
			
			if($aspectExists)
			{
				// iterate over resolutions
				$aspectDH = opendir($_SERVER['DOCUMENT_ROOT'] . $smartImg->cacheRootDir . '/' . $aspectDir);
				while (($resolutionDir = readdir($aspectDH)) !== false)
				{
					if($resolutionDir === '.' || $resolutionDir === '..')
						continue;
					
					$resolutionExists = false;
					foreach($smartImg->_resolutions as $resolution)
					{
						if($resolution['dir'] == $resolutionDir)
						{
							$resolutionExists = true;
							break;
						}
					}
					
					if($resolutionExists)
						$smartImg->cleanupCache($aspectDir . '/' . $resolutionDir, '');
					else
						$smartImg->recursiveDelete($_SERVER['DOCUMENT_ROOT'] . $smartImg->cacheRootDir . '/' . $aspectDir . '/' . $resolutionDir);
				}
				closedir($aspectDH);
			}
			else
				$smartImg->recursiveDelete($_SERVER['DOCUMENT_ROOT'] . $smartImg->cacheRootDir . '/' . $aspectDir);
		}
		closedir($cacheDH);
		return "cleanup finished";
	}
	
	public static function test($args = null)
	{
		return array(	'Hello' => 'World',
						'args' => $args
		);
	}
	
	/*
	 * --------------------------------------------------------------------------------
	 * helper
	 * --------------------------------------------------------------------------------
	 */
	public static function exit_error($message)
	{
		http_response_code(400);
		echo $message;
		die;
	}
}