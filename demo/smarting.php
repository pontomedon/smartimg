<?php

/**
 * 
 * @author pontomedon & mrmuh
 *
 */
class SmartImg{

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
	 * @param array $images
	 */
	function __construct($images){
		
		// TODO implement logic
		//var_dump($images);
		
		// test output
		$this->outputResult($images);
		die;
	}
	
	function outputResult($resultArray){
		echo json_encode($resultArray);
	}
}


/*
 * process ajax request
 */

// check if images is set
if(!isset($_POST["images"]))
	die;

// decode image array
$images = json_decode($_POST["images"],true);

// init smart img for handling the request
$smartimg = new SmartImg($images);
die;

?>