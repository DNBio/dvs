<?php

/*** DVS 0.1 run.php               ***/
/*** david.brett@ehess.fr          ***/
/*** web@ehess.fr                  ***/
/*** V.0.1 - September 2019        ***/
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

// Used REDIS connection lib
// https://github.com/phpredis/phpredis

// DEV FEATURE
// Remove all keys from all Redis databases
// $redis = new Redis();
// $redis->connect($redisServerUrl, $redisServerPort);
// $redis->flushAll();
// $redis->close();
// unset($redis);

/************************/
/*** GET VCLASS ITEMS ***/
/************************/
/*
// First we need to retrieve the list of all EHESS Unites de recherche.
// We will use the VIVO ListRDF API as the query context
// to gather all items of the $vclass 'vivo#UniteDeRecherche'.
// For each item of the list we will then use the Linked open data API,
// to gather a simplified array of information.

// Build VIVO credentials to connect to VIVO 
$vivoCredentials = array (
    'url' => $vivoURL,
    'port' => $vivoPort,
    'display_url' => $vivoLinkedOpenDataPath,
    'listAPI_url' => $vivoListApiPath
);

// Populate the query data to append to our POST request
// eg. : 'vclass=http://data.ehess.fr/ontology/vivo#UniteDeRecherche'
$queryData = array (
    'context' => $vivoListApiPath,
    'ontolgyURL' => $ontlogyURL,
    'vclass' => $vClass1,
    'format' => $plainFormat
);

// Initiate VIVO connection object
$vivoConnection = new callVivo($vivoCredentials);

// Attempt to query VIVO :
// we need all entities belonging to the specific vclass.
$vivoResult = $vivoConnection->getVivoResult(
    $queryData,
    $debug
);

// Store the data, if errors are found, print the errors
if ( $vivoResult['errno'] != 0 ) {
    print 'Error #' 
        . $vivoResult['errno'] 
        . ' : bad url ? timeout, redirect loop...';
} elseif ( $vivoResult['http_code'] != 200 ) {
    print 'HTTP code ' 
        . $vivoResult['http_code'] 
        . ' : no page ? no permissions, no service...';
} else {
    $rawData = $vivoResult['content'];
}

// Unset old variables
unset(
    $vivoConnection,
    $queryData,
    $vivoResult
);

// Select the entities we are interested in
// from VIVO triplets
$entitiesID = simplifyVclassTriplets($rawData);

// Clean variables / destroy $vivoConnection object
unset(
    $rawData,
    $vivoConnection
);

// Build and execute the subqueries array from IDs
// Build "subquerier" object
$vivoSubqueries = new vclassItemsProcess($vivoCredentials);

// Transform the IDs array into VIVO query data
$subqueries = $vivoSubqueries->buildSubQueriesFromIds(
    $entitiesID,
    $jsonLdFormat
);

// Initialize the subqueries answers container
$subQueriesData = array();

if (!empty($subqueries))
{
    foreach ($subqueries as $k => $v)
    {
        // Attempt to query VIVO
        $subQueriesData[] = $vivoSubqueries->getVivoResult(
            $v,
            $debug
        );
        
        // We don't need these anymore
        unset($subqueries[$k]);
    }
    // Since it's empty, why take a chance ?
    unset($subqueries);
}

// Extract simplified data from VIVO subqueries :
// we only want the items belonging to the classes we are looking for.
$usefulData = extractVivoClasses(
    $subQueriesData,
    $classes,
    $mainLanguage
);

// test purposes...
// print_r($usefulData);

/****************************/
/*** END GET VCLASS ITEMS ***/
/****************************/

/**********************/
/*** POPULATE REDIS ***/
/**********************/
/*
$redis = new Redis();
$redis->connect($redisServerUrl, $redisServerPort);

// Test Redis server
// $test = $redis->ping('hello');
// print $test;

// Populate Redis List with ehess:UniteDeRecherche results
if (!empty($usefulData) && is_array($usefulData))
{
    foreach ($usefulData as $uniteDeRecherche) 
    {
        // First set of key : we need to fetch item ID from name or abbreviation
        // Prepare searchable Redis key string
        $nameAbb = $uniteDeRecherche['type']
            . '::' 
            . $uniteDeRecherche['name'] 
            . '::' 
            . strtoupper($uniteDeRecherche['name']) 
            . '::' 
            . strtoupper($uniteDeRecherche['abbreviation']);
        
        // Prepare searchable Redis value string
        $idCount = $uniteDeRecherche['id'] 
            . '::' 
            . $uniteDeRecherche['relatedCount'];
        
        // Push the key/value to Redis db
        $redis->set($nameAbb, $idCount);
        
        // Second set of key : list the associated members from org ID
        // Prepare searchable Redis key string
        $typeId = $uniteDeRecherche['type']
            . '::' 
            . $uniteDeRecherche['id'];
        
        foreach($uniteDeRecherche['relatedBy'] as $relatedBy)
        {
            $redis->rpush($typeId,$relatedBy);
        }
        
        // Clean the strings, don't leave it to chance
        unset($nameAbb,$idCount,$typeId);
    }
}

// Close redis connection
// $redis->close();
// unset($redis);

/**************************/
/*** END POPULATE REDIS ***/
/**************************/

/********************************/
/*** VCLASS ITEM REDIS SEARCH ***/
/********************************/
/*
$redis = new Redis();
$redis->connect($redisServerUrl, $redisServerPort);

// Display all vclass Redis data
// $list = $redis->lRange('ehess:UniteDeRecherche', 0, -1);
// print_r($list);
// unset($list);

// Search test
$searchArg = 'cral';

$searchMatch = '*' . strtoupper($searchArg) . '*';
$searchKey = $redis->keys($searchMatch);
$searchValue = $redis->get($searchKey[0]);
print_r($searchKey);
print_r($searchValue);

unset(
    $searchArg,
    $searchMatch,
    $searchKey,
    $searchValue
);

unset($list);

// Close redis connection
redis->close();

/************************************/
/*** END VCLASS ITEM REDIS SEARCH ***/
/************************************/

/*********************************/
/*** INDIVIDUAL QUERY & IMPORT ***/
/*********************************/

// Example data URL : http://data.ehess.fr/individual/406mnj428_snordman_stat
$dataUrl = 'http://data.ehess.fr/individual/406mnj428_snordman_stat';
$explodedDataUrl = explode('/',$dataUrl);
$dataUri = end($explodedDataUrl);

// Populate the query data to append to our POST request
// eg. : 'https://vivo-qa.ehess.fr/display/406mnj428_snordman_stat?format=jsonld'
$individualQueryData = array (
    'context' => $vivoLinkedOpenDataPath,
    'id' => $dataUri,
    'format' => $jsonLdFormat
);

// Build VIVO credentials to connect to VIVO 
$vivoCredentials = array (
    'url' => $vivoURL,
    'port' => $vivoPort,
    'display_url' => $vivoLinkedOpenDataPath,
    'listAPI_url' => $vivoListApiPath
);

// Initiate VIVO connection object
$vivoConnection = new callVivo($vivoCredentials);

// Attempt to query VIVO :
// we need all entities belonging to the specific vclass.
$vivoResult = $vivoConnection->getVivoResult(
    $individualQueryData,
    $debug
);

// Destroy VIVO object
unset($vivoConnection);

// Store the data, if errors are found, print the errors
if ( $vivoResult['errno'] != 0 ) {
    print 'Error #' 
        . $vivoResult['errno'] 
        . ' : bad url ? timeout, redirect loop...';
} elseif ( $vivoResult['http_code'] != 200 ) {
    print 'HTTP code ' 
        . $vivoResult['http_code'] 
        . ' : no page ? no permissions, no service...';
} else {
    $rawData = $vivoResult['content'];
}

// Unset old variables
unset(
    $individualQueryData,
    $vivoResult
);

// The object contains a mix of arrays and objects...
// Let's clean and flatten to array the hard way !
$fullObject = json_decode(
    json_encode(
        json_decode($rawData), 
        true
    ),
    true
);

$allNames = array();
foreach ($fullObject['@graph'] as $graphs)
{
    if( isset($graphs['@type'][0]) 
        && $graphs['@type'][0] === 'foaf:Person' )
    {
        array_push($allNames,$graphs['label']);
    }
}

print_r($allNames);

?>