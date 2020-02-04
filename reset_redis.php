<?php

/*** DVS 0.1 run.php               ***/
/*** david.brett@ehess.fr          ***/
/*** web@ehess.fr                  ***/
/*** V.0.4 - October 2019          ***/
/*** COPYRIGHT EHESS/DNB           ***/
/***                               ***/
/*** Tests file                    ***/
/***                               ***/

include('config.php');
include('includes/functions.php');

// Load local classes automatically
spl_autoload_register(function($className) {
	include_once $_SERVER['DOCUMENT_ROOT'] . 'classes/' . $className . '.class.php';
});

// RESET Redis to start from scratch
resetRedis($redisServerUrl, $redisServerPort);

?>