<?php

require_once "smartimg.php";

/*
 * check GD
*/
if((!extension_loaded('gd')) && (!function_exists('dl') || !dl('gd.so')))
	SmartImg::exit_error('Unable to load GD');

/*
 * check call
*/
if(!isset($_REQUEST['src']))
	SmartImg::exit_error('mandatory parameter src not given.');

$result = SmartImg::getImage(array(array(
		'src' => $_REQUEST['src'],
		'width' => (isset($_REQUEST['w']) ? $_REQUEST['w'] : null),
		'aspect' => (isset($_REQUEST['a']) ? $_REQUEST['a'] : null)
)));

$file=$_SERVER['DOCUMENT_ROOT'].$result[0]['src'];

$size = getimagesize($file);
$fp = fopen($file, "rb");
if ($size && $fp)
{
	header('Content-Type: ' . $size['mime']);
	header('Content-Length: ' . filesize($file));
	fpassthru($fp);
}
else
	http_response_code(404);
