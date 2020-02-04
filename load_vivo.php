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

// First we need to retrieve the list of all EHESS Unites de recherche.
// We will use the VIVO ListRDF API as the query context
// to gather all items of the $vclass 'vivo#UniteDeRecherche'.
// For each item of the list we will then use the Linked open data API,
// to gather a simplified array of information.

if ($debug === true)
{
    // Start timer
    $beginning = timer();
    
    $messageString = 'VIVO update script started.';
    message($messageString);
    unset($messageString);
    
    $messageString = 'Querying Vclasses details.';
    message($messageString);
    unset($messageString);
}

// Initiate VIVO connection object
$vivoConnection = new callVivo($vivoCredentials);

// Attempt to query VIVO :
// we need all entities belonging to the specific vclass.
$vivoResult = $vivoConnection->executeSubquery(
    $queryData,
    $debug
);
// Validate VIVO answers
$vivoResult = $vivoConnection->validateVivoAnswer($vivoResult);

// Unset old variables
unset(
    $vivoConnection,
    $queryData
);

if ($debug === true)
{
    $messageString = count($vivoResult) . ' results found.';
    message($messageString);
    unset($messageString);

    $messageString = 'Simplifying Vclass triplets.';
    message($messageString);
    unset($messageString);
}

// Select the entities we are interested in
// from VIVO triplets
$entitiesID = simplifyVclassTriplets($vivoResult);

// Clean variables / destroy $vivoConnection object
unset($vivoResult);

// Build and execute the subqueries array from IDs
// Build "subquerier" object
$vivoSubqueries = new vclassItemsProcess($vivoCredentials);

// Transform the IDs array into VIVO query data
$subqueries = $vivoSubqueries->buildSubQueriesFromIds(
    $entitiesID,
    $jsonLdFormat
);
// Destroy VIVO connection
unset($vivoSubqueries);

if ($debug === true)
{
    $messageString = 'Scanning each Vclass items.';
    message($messageString);
    unset($messageString);
}

// Initialize the subqueries answers container
$subQueriesData = array();

// Initiate VIVO connection object
$vivoConnection = new callVivo($vivoCredentials);
$subQueriesData = $vivoConnection->executeSubquery(
    $subqueries,
    $debug
);

// Destroy VIVO connection
unset(
    $vivoConnection,
    $subqueries
);

// Extract simplified data from VIVO subqueries :
// we only want the items belonging to the classes we are looking for.
$usefulData = extractVivoClasses(
    $subQueriesData,
    $classes,
    $mainLanguage
);

// We will reuse $subQueriesData later
// Let's nullify it.
unset($subQueriesData);

if ($debug === true)
{
    $messageString = count($usefulData) . ' items found.';
    message($messageString);
    unset($messageString);

    $messageString = 'Loading Vclass items into Redis.';
    message($messageString);
    unset($messageString);
}

// RESET Redis to start from scratch
resetRedis($redisServerUrl, $redisServerPort);

// Initiate new Redis connection
$redis = new Redis();
$redis->connect($redisServerUrl, $redisServerPort);

// Populate Redis List with ehess:UniteDeRecherche results
if (!empty($usefulData) && is_array($usefulData))
{
    foreach ($usefulData as $uniteDeRecherche) 
    {
        // First set of key : we need to fetch item ID from name or abbreviation
        // Prepare searchable Redis key string
        // add a distinctive string at the end of the key
        // to ease further researches.
        $nameAbb = $uniteDeRecherche['type']
            . '::' 
            . $uniteDeRecherche['name'] 
            . '::' 
            . strtoupper($uniteDeRecherche['name']) 
            . '::' 
            . strtoupper($uniteDeRecherche['abbreviation'])
            . '::'
            . $uniteDeRecherche['id']
            . '::fullString';
        
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

if ($debug === true)
{
    $messageString = 'Querying "RelatedBy" data...';
    message($messageString);
    unset($messageString);
}

// Build and execute the subqueries array from individuals
// Build "subquerier" object
$vivoProcess = new referencedByProcess(
    $vivoCredentials,
    $mainLanguage
);

// Prepare VIVO queries for each "relatedBy" item.
$subqueries = $vivoProcess->prepareIndividualQueryData(
    $usefulData,
    $jsonLdFormat,
    $debug
);

// Attempt to query VIVO
$subQueriesData = $vivoProcess->executeSubquery(
    $subqueries,
    $debug
);
unset($subqueries);

// Validate VIVO answers
$subQueriesData = $vivoProcess->validateVivoAnswer($subQueriesData);

if ($debug === true)
{
    $messageString = count($subQueriesData) . ' items are ready to be processed.';
    message($messageString);
    unset($messageString);
    
    $messageString = 'Begin individual data process...';
    message($messageString);
    unset($messageString);
}

$allIndividuals = $vivoProcess->individualDataProcess(
    $subQueriesData,
    $jsonLdFormat,
    $debug
);

// Destroy VIVO connection and other variables
unset(
    $vivoProcess,
    $subQueriesData
);

if ($debug === true)
{
    $messageString = 'Loading ' . count($allIndividuals) . ' individuals into Redis.';
    message($messageString);
    unset($messageString);
}

// Populate Redis with all people references
if (!empty($allIndividuals) && is_array($allIndividuals))
{
    foreach($allIndividuals as $individual)
    {
        // Prepare searchable REDIS keys
        // Example key//Value :
        // 9d7g0gzqrj::http://data.ehess.fr/individual/ncq0aocreh//Balloy, Benjamin
        
        // Simplify 'relatedBy' string
        $relatedByExploded = explode('/',$individual['relatedBy']);
        $relatedByLast = end($relatedByExploded);
        $relatedByLastExploded = explode('_',$relatedByLast);
        $relatedBySimple = reset($relatedByLastExploded);
        
        $redisIndividualKeyString = $relatedBySimple
            . '::'
            . $individual['id'];
        // and value :
        $redisIndividualKeyValueString = $individual['name'];
        
        // Push the key/value to Redis db
        $redis->set(
            $redisIndividualKeyString, 
            $redisIndividualKeyValueString
        );
    }
}

// Close redis connection
$redis->close();

if ($debug === true)
{
    // Stop timer and output result
    $time = round(timer()-$beginning,6);
    $return = 'Script executed in ' 
        . gmdate("i:s", $time);
    message($return);
    unset($time,$return);
}

?>