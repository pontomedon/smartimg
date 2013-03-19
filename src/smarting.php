﻿<?php

/**
 * 
 * @author pontomedon & mrmuh
 *
 * Data Structures used throughout this class:
 * ImageRequestBundle: Array:
 * - src	[required]	the path to the image
 * - width	[optional]	the desired width
 * - aspect	[optional]	the desired aspect ratio
 * 
 * Image: Array:
 * - src	[required]	the path to the image
 */
class SmartImg
{

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
	
	/**
	 * Constructor
	 */
	function __construct()
	{
	}
	
	/**
	 * @TODO: describe!
	 * 
	 * @param ImageRequestBundle $imageRequestBundle
	 * @return Image
	 */
	private function getResizedImage($imageRequestBundle)
	{
		// TODO: implement me
		return array('src' => $imageRequestBundle['src']);
	}
	
	/*
	 * --------------------------------------------------------------------------------
	 * public static functions, callable from the API
	 * --------------------------------------------------------------------------------
	 */
	
	/**
	 * Batch processing call for getting resized images
	 * 
	 * @param ImageRequestBundle[] $imageBundles
	 * @return Image[]
	 */
	public static function getImage($imageRequestBundles)
	{
		$smartImg = new SmartImg();
		
		$result = array();
		for($i=0; $i<count($imageRequestBundles); $i++)
			$result[] = $smartImg->getResizedImage($imageRequestBundles[$i]);
		
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
