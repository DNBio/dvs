<?php 

include('config.php');
include('includes/functions.php');

// Load local classes automatically
spl_autoload_register(function($className) {
	include_once $_SERVER['DOCUMENT_ROOT'] . 'classes/' . $className . '.class.php';
});

// Which entity (UMR) are we pulling data from ?
// e.g. Centre Chine Corée Japon : 9l5iqktum
$searchInput = '';

// Initiate new Redis connection
$redis = new Redis();
$redis->connect($redisServerUrl, $redisServerPort);

// The data we are searching from is formatted as such :
// key(UMR id :: person's unique URL) // value(Person's full name) 
// e.g. : 9d7g0gzqrj::http://data.ehess.fr/individual/ncq0aocreh//Balloy, Benjamin
// (Redis Keys must be unique)
// To list all keys that match that specific UMR we will query using UMR id + wildcard
$searchMatch = $searchInput . '*';
$searchKeys = $redis->keys($searchMatch);

$results = array();

foreach ($searchKeys as $searchKey)
{
    $searchValue = $redis->get($searchKey);
    $singleResult = array(
        'key' => $searchKey,
        'value' => $searchValue
    );
    array_push($results,$singleResult);
    unset(
        $searchKey,
        $searchValue,
        $singleResult
    );
}

if (!empty($results))
{
    // Build the UUID array of all the persons we found
    // From Redis results
    // e.g. : from 'http://data.ehess.fr/individual/ncq0aocreh//Balloy, Benjamin'
    // to 'ncq0aocreh'
    $uuidArray = array();
    foreach($results as $result) {
        $explodedKey = explode('::',$result['key']);
        $explodedIdUrl = explode('/',$explodedKey[1]);
        $personUUID = end($explodedIdUrl);
        unset($explodedKey,$explodedIdUrl);
        array_push($uuidArray,$personUUID);
        unset($personUUID);
    }
    // unset redis results, we don't need it anymore
    unset($results);
    
    if(!empty($uuidArray))
    {
        $queries = array();
    
        foreach($uuidArray as $uuid) 
        {
            $queries[] = array (
                'context' => $vivoLinkedOpenDataPath,
                'id' => $uuid,
                'format' => $jsonLdFormat
            );
        }
        unset($uuidArray);
    } else {
        // The array of UUIDs is empty !
    }
    
    if( isset($queries)
      && !empty($queries) )
    {
        // Initiate the VIVO connection object
        $vivoConnection = new callVivo($vivoCredentials);
        
        // Attempt to query VIVO :
        // We send a batch of queries
        $vivoResults = $vivoConnection->executeSubquery(
            $queries,
            $debug
        );
        
        // Validate VIVO answers
        $vivoResults = $vivoConnection->validateVivoAnswer($vivoResults);
        
        unset($vivoConnection);
        
        if ($debug === true)
        {
            $messageString = count($vivoResults) . ' results found.';
            message($messageString);
            unset($messageString);
        }
        
        // Initiate the final results array
        $allCompleteInformations = array();
        
        // Later : check if VIVO results are OK !
        foreach ($vivoResults as $vivoResult)
        {
            // Convert VIVO objects to JSON properly
            $jsonResult = convertToJson($vivoResult);
            
            // Iterate through results : find the graphs we need
            foreach ($jsonResult as $graphs)
            {
                $completeInformations = array(
                    'id' => '',
                    'externalAuthId' => '',
                    'idref' => '',
                    'firstName' => '',
                    'lastName' => '',
                    'ehess:Position' => array(),
                    'mainImage' => '',
                    'email' => '',
                    'mostSpecificType' => array(),
                    'ehess:Appartenance' => array(),
                    'ehess:AppartenanceStatutaire' => array(),
                    'ehess:AppartenanceATitreDAssocie' => array(),
                    'overview' => ''
                );
                foreach ($graphs as $graph)
                {
                    // Check if that graph contains the person's externalAuthId
                    if( isset($graph['@type']) 
                        && is_array($graph['@type']) 
                        && in_array('foaf:Person', $graph['@type']) 
                        && isset($graph['label'])
                        && !is_array($graph['label'])
                       // manque redf:label !
                        && isset($graph['externalAuthId'])
                    )
                    {
                        $stringToBuildEmail = buildMailAdresses($graph['firstName']) 
                            . '.' 
                            . buildMailAdresses($graph['lastName'])
                            . '@ehess.fr';
                        
                        $completeInformations['id'] = $graph['@id'];
                        $completeInformations['externalAuthId'] = $graph['externalAuthId'];
                        $completeInformations['firstName'] = $graph['firstName'];
                        $completeInformations['lastName'] = $graph['lastName'];
                        $completeInformations['email'] = $stringToBuildEmail;
                    }
                    
                    // If the graph contains a 'mostSpecificType'
                    if( isset($graph['@type']) 
                        && is_array($graph['@type']) 
                        && in_array('foaf:Person', $graph['@type']) 
                        && isset($graph['mostSpecificType'])
                    )
                    {
                        if (is_array($graph['mostSpecificType']))
                        {
                            foreach($graph['mostSpecificType'] as $singeType)
                            {
                                array_push($completeInformations['mostSpecificType'], $singeType);
                            }
                        } else {
                            array_push($completeInformations['mostSpecificType'], $graph['mostSpecificType']);
                        }
                    }
                    
                    
                    /** APPARTENANCE            **/
                    /** APPARTENANCE STATUTAIRE **/
                    /** APPARTENANCE ASSOCIEE   **/

                    // If the graph type contains 'ehess:Appartenance'
                    if( isset($graph['@type']) 
                        && is_array($graph['@type']) 
                        && in_array('ehess:Appartenance', $graph['@type']) 
                        && !in_array('ehess:AppartenanceStatutaire', $graph['@type'])
                        && !in_array('ehess:AppartenanceATitreDAssocie', $graph['@type'])
                    )
                    {
                        //$completeInformations['ehess:Appartenance'][] = $graph['@id'];
                        // Search the entity real name from the UUID
                        // in REDIS existing data
                        $realName = entityRealName(
                            $graph['@id'],
                            $redisServerUrl, 
                            $redisServerPort
                        );
                        
                        array_push(
                            $completeInformations['ehess:Appartenance'], 
                            $realName
                        );
                        unset($realName);
                    }
                    
                    // If the graph type contains 'ehess:AppartenanceStatutaire'
                    if( isset($graph['@type']) 
                        && is_array($graph['@type']) 
                        && in_array('ehess:Appartenance', $graph['@type']) 
                        && in_array('ehess:AppartenanceStatutaire', $graph['@type'])
                    )
                    {
                        // Search the entity real name from the UUID
                        // in REDIS existing data
                        $realName = entityRealName(
                            $graph['@id'],
                            $redisServerUrl, 
                            $redisServerPort
                        );
                        array_push(
                            $completeInformations['ehess:AppartenanceStatutaire'], 
                            $realName
                        );
                        unset($realName);
                    }
                    
                    // If the graph type contains 'ehess:AppartenanceATitreDAssocie'
                    if( isset($graph['@type']) 
                        && is_array($graph['@type']) 
                        && in_array('ehess:Appartenance', $graph['@type']) 
                        && in_array('ehess:AppartenanceATitreDAssocie', $graph['@type'])
                    )
                    {
                        // Search the entity real name from the UUID
                        // in REDIS existing data
                        $realName = entityRealName(
                            $graph['@id'],
                            $redisServerUrl, 
                            $redisServerPort
                        );
                        array_push(
                            $completeInformations['ehess:AppartenanceATitreDAssocie'], 
                            $realName
                        );
                        unset($realName);
                    }
                    
                    
                    // If the graph type contains 'ehess:Position'
                    if( isset($graph['@type']) 
                        && is_array($graph['@type']) 
                        && in_array('ehess:Position', $graph['@type']) 
                        && is_array($graph['label'])
                    )
                    {
                        foreach($graph['label'] as $labelLanguage)
                        {
                            if (isset($labelLanguage['@language'])
                                && $labelLanguage['@language'] === $mainLanguage)
                            {
                                array_push(
                                    $completeInformations['ehess:Position'], 
                                    $labelLanguage['@value']
                                );
                            }
                        }
                        
                    }
                    
                    // If the graph contains the 'idref'
                    if( isset($graph['idref'])
                        && !empty($graph['idref']) 
                    )
                    {
                        //array_push($completeInformations['idref'],$graph['idref']);
                        $completeInformations['idref'] = $graph['idref'];
                    }
                    
                    /**** IMAGE ****/
                    // If the graph contains a 'mainImage'
                    if( isset($graph['@type']) 
                        && is_array($graph['@type']) 
                        && in_array('foaf:Person', $graph['@type']) 
                        && isset($graph['mainImage'])
                    )
                    {
                        // We will need to fetch the image URL
                        // First we need to pull the file metadata (Linked open data query)
                        // Second pull the thumbnail metadata (Linked open data query)
                        // We will then be able to construct the direct link
                        $imageReference = $graph['mainImage'];
                        
                        $explodedKey = explode(':',$imageReference);
                        $explodedIdUrl = explode('/',$explodedKey[1]);
                        $mainImageUUID = end($explodedIdUrl);
                        unset($explodedKey,$explodedIdUrl);
                        
                        $mainImageQuery = array(
                            'context' => '/individual',
                            'id' => $mainImageUUID,
                            'format' => $jsonLdFormat
                        );
                        unset($mainImageUUID);
                        
                        $vivoConnection = new callVivo($vivoCredentials);
                        
                        // Attempt to query VIVO :
                        // We send only one query, our first :
                        $vivoResults = $vivoConnection->getVivoResult(
                            $mainImageQuery,
                            $debug
                        );
                        
                        $jsonResultImage1 = convertToJson($vivoResults['content']);
                        
                        unset(
                            $vivoConnection,
                            $vivoResults
                        );
                        
                        // Dig into the results to fetch the thumbnail URL
                        foreach ($jsonResultImage1['@graph'] as $imageGraph)
                        {
                            if ( isset($imageGraph['filename'])
                                 && isset($imageGraph['thumbnailImage'])
                            ) 
                            {
                                $explodedKey = explode(':',$imageGraph['thumbnailImage']);
                                $explodedIdUrl = explode('/',$explodedKey[1]);
                                $mainThumbnailUUID = end($explodedIdUrl);
                                unset($explodedKey,$explodedIdUrl);
                                
                                // We have our thumbnail URL
                                // Thumbnail query
                                $thumbnailQuery = array(
                                    'context' => '/individual',
                                    'id' => $mainThumbnailUUID,
                                    'format' => $jsonLdFormat
                                );
                                
                                unset($mainThumbnailUUID);
                                // Let's query VIVO once again
                                $vivoConnection = new callVivo($vivoCredentials);
                        
                                // Attempt to query VIVO again :
                                // We send only one query, our second :
                                $vivoResults = $vivoConnection->getVivoResult(
                                    $thumbnailQuery,
                                    $debug
                                );

                                $jsonResultThumbnail = convertToJson($vivoResults['content']);

                                unset(
                                    $thumbnailQuery,
                                    $vivoConnection,
                                    $vivoResults
                                );
                                
                                foreach ($jsonResultThumbnail['@graph'] as $thumbnailGraph)
                                {
                                    if ( isset($thumbnailGraph['filename'])
                                         && isset($thumbnailGraph['downloadLocation'])
                                    ) 
                                    {
                                        $explodedKey = explode(':',$thumbnailGraph['downloadLocation']);
                                        $explodedIdUrl = explode('/',$explodedKey[1]);
                                        $thumbnailUUID = end($explodedIdUrl);
                                        unset($explodedKey,$explodedIdUrl);
                                        $finalImageUrl = $vivoURL
                                                 . '/file/'
                                                 . $thumbnailUUID 
                                                 . '/'
                                                 . $thumbnailGraph['filename'];
                                        $completeInformations['mainImage'] = $finalImageUrl;
                                        unset(
                                            $finalImageUrl,
                                            $thumbnailUUID
                                        );
                                    }
                                }
                                unset($jsonResultThumbnail);
                            }
                        }
                        unset($jsonResultImage1);
                        
                    }
                    
                    /*** OVERVIEW ***/
                    // If the graph contains the person's overview
                    if( isset($graph['@type']) 
                        && is_array($graph['@type']) 
                        && in_array('foaf:Person', $graph['@type']) 
                        && isset($graph['overview'])
                    )
                    {
                        $completeInformations['overview'] = $graph['overview'];
                    }
                }
                // Push into final array if the externalauthid is set
                if (!empty($completeInformations['externalAuthId']))
                {
                    array_push(
                        $allCompleteInformations,
                        $completeInformations
                    );   
                    //print_r($completeInformations);
                }
                unset($completeInformations);
                /*
                if (!empty($completeInformations['externalAuthId']))
                {
                    array_push(
                        $allCompleteInformations,
                        $completeInformations
                    );   
                    //print_r($completeInformations);
                }*/
                //unset($completeInformations);
            
            }
        }
        if ($debug === true)
        {
            $messageString = count($allCompleteInformations) . ' externalAuthId found.';
            message($messageString);
            unset($messageString);
        }
        unset($vivoResults);
        
        /*
        foreach($allCompleteInformations as $test) {
            print_r($test['ehess:Appartenance']);
            print "\n";
            print_r($test['mostSpecificType']);
            print "\n";
            print "\n";
        }
        */
        //print_r($allCompleteInformations);
        if (isset($allCompleteInformations) 
            && !empty($allCompleteInformations))
        {
            print_r($allCompleteInformations);
            // Export To CSV
            $csv = arrayToCSV($allCompleteInformations,$debug);
            outputCSV($csv,$path,$debug);
        }
        
    } else {
        // No queries built !
    }
    
} else {
    // No results from that search input !
}




