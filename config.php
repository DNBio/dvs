<?php

/*** DVS 0.1 config.php            ***/
/*** david.brett@ehess.fr          ***/
/*** web@ehess.fr                  ***/
/*** V.0.4 - October 2019          ***/
/*** COPYRIGHT EHESS/DNB           ***/
/***                               ***/
/*** Configuration file            ***/
/***                               ***/

// Should we output any error we find ?
// Can be true or false
$debug = true;

if ($debug === true)
{
    // Should PHP output any error ?
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Vivo absolute URL
// No trailing slash at the end !
// eg. : 'http://vivo.cornell.edu'
$vivoURL = '';

// Vivo port
// NOT IMPLEMENTED YET !
// eg. : 443
$vivoPort = '';

// VIVO Linked open data relative path
// No trailing slash at the end !
// Default should be "/display"
$vivoLinkedOpenDataPath = '/display';

// VIVO ListRDF API relative path
// No trailing slash at the end !
// Default should be "/listrdf"
$vivoListApiPath = '/listrdf';

// Local VIVO ontolgy URL
// No trailing slash at the end !
// eg. : http://vivoweb.org/ontology
$ontlogyURL = '';

// Vivo Ontology classes to search the ListRDF API
// eg. : vivo#xyz
$vClasses = array(
    'vivo#xyz'
);

// EHESS Ontology classes to use 
// when extracting VIVO data
// eg. : vivo:abbreviation
$classes = array(
    'vivo:abbreviation'
);

// Working languages (when applicable)
// eg. : 'en'
$mainLanguage = 'fr';
$altLanguage = 'en';

// VIVO encodings
$plainFormat = 'text/plain';
$jsonFormat = 'application/json';
$jsonLdFormat = 'jsonld';

// REDIS configuration
// Redis server URL
// eg. : 127.0.0.1
$redisServerUrl = '127.0.0.1';
// Redis port
// default : 6379
$redisServerPort = 6379;

// Do not modifiy below this point
// Unless you know what you are doing...

// Build VIVO credentials to connect to VIVO 
$vivoCredentials = array (
    'url' => $vivoURL,
    'port' => $vivoPort,
    'display_url' => $vivoLinkedOpenDataPath,
    'listAPI_url' => $vivoListApiPath
);

// Populate the query data to append to our initial POST request
// eg. : 'vclass=http://data.ehess.fr/ontology/vivo#UniteDeRecherche'
$queryData = array();
foreach ($vClasses as $vclass)
{
    $vClassArray = array(
        'context' => $vivoListApiPath,
        'ontolgyURL' => $ontlogyURL,
        'vclass' => $vclass,
        'format' => $plainFormat
    );
    array_push($queryData,$vClassArray);
}

// Temporary
// CSV path
$path = 'output.txt';
