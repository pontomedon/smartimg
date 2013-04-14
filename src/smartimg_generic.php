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
if(!isset($_REQUEST['method']))
	SmartImg::exit_error('mandatory parameter method not given.');
if(isset($_REQUEST['arg']) && isset($_REQUEST['args']))
	SmartImg::exit_error('arg and args may not be given at the same time.');
$method = $_REQUEST['method'];
if(!is_callable(array('SmartImg', $method)))
	SmartImg::exit_error('undefined method ' . $method);

/*
 * execute call
*/
if(isset($_REQUEST['arg']))
	echo json_encode(call_user_func(array('SmartImg', $method), json_decode($_REQUEST['arg'],true)));
else if(isset($_REQUEST['args']))
	echo json_encode(call_user_func_array(array('SmartImg', $method), json_decode($_REQUEST['args'],true)));
else
	echo json_encode(call_user_func(array('SmartImg', $method)));