<?php

/*** DVS 0.1 config.php            ***/
/*** david.brett@ehess.fr          ***/
/*** web@ehess.fr                  ***/
/*** V.0.4 - October 2019          ***/
/*** COPYRIGHT EHESS/DNB           ***/
/***                               ***/
/*** Configuration file            ***/
/***                               ***/

/** 
 * Simplify vclass raw triplets into
 * a simple array of unique entity IDs
 *
 * @param string $data the raw triplets
 *
 * @return array of entity IDs.
 */
function simplifyVclassTriplets($dataset)
{
    // Initalize the array that will containe the IDs
    $result = array();
    
    foreach ($dataset as $data)
    {
        // Explode the triplets groups
        $allTriplets = explode(" .", $data);

        // We don't need that variable anymore (potentially heavy)
        unset($data);

        // explode each triplet groups into individual strings
        foreach ($allTriplets as $k => $v) {
            $explodedTriplets = explode(" ",$v);

            // The first string contains the entity ID,
            // we need to clean it to work with it later.
            $explodedTriplets[0] = str_replace("\n", '',$explodedTriplets[0]);
            $explodedTriplets[0] = str_replace('<http://data.ehess.fr/individual/', '',$explodedTriplets[0]);
            $explodedTriplets[0] = str_replace('>', '',$explodedTriplets[0]);

            // Push only the ID in the final array, if not empty
            if (!empty($explodedTriplets[0]))
            {
                array_push(
                    $result,
                    $explodedTriplets[0]
                );
            }

            // We don't need these variables in memory anymore (potentially heavy)
            unset(
                $explodedTriplets,
                $allTriplets[$k]
            );
        }
    }
    return $result;
}

/** 
 * Dig into VIVO subqueries data
 * to extract relevant informations
 *
 * @param array $subQueriesData raw VIVO subqueries data
 * @param array $classes the VIVO classes we are looking to extract
 * @param string $mainLanguage the language we want to use (when applicable)
 *
 * @return array of trimmed classes members.
 */
function extractVivoClasses(
    $subQueriesData,
    $classes,
    $language)
{
    // initalize results container
    $usefulData = array();
    
    // Decipher VIVO data
    foreach ($subQueriesData as $k => $v)
    {
        $fullObject = convertToJson($v['content']);
        
        if (!empty($v)) {
            // Convert to JSON
            $fullObject = json_decode(
                json_encode(
                    json_decode($v['content']), 
                    true
                ),
                true
            );

            foreach ($classes as $class)
            {
                // Check for single class results
                // format the results
                // eg. : ehess:UniteDeRecherche
                foreach ($fullObject['@graph'] as $graphs)
                {
                    if (is_array($graphs['@type']))
                    {
                        if (in_array($class, $graphs['@type'])
                            && isset($graphs['abbreviation']) 
                        )
                        {
                            // Related by field process
                            if (isset($graphs['relatedBy'])
                                && is_array($graphs['relatedBy'])
                                && !empty($graphs['relatedBy'])
                            )
                            {
                                $relatedByCount = count($graphs['relatedBy']);
                                $relatedByList = $graphs['relatedBy'];
                            } else {
                                $relatedByCount = 0;
                                $relatedByList = array();
                            }

                            // Build the arrays
                            if (isset($graphs['label'][0]['@value']) 
                                && $graphs['label'][0]['@language'] === $language 
                            )
                            {
                                array_push(
                                    $usefulData,
                                    array(
                                        'type' => $class,
                                        'name' => $graphs['label'][0]['@value'],
                                        'abbreviation' => $graphs['abbreviation'],
                                        'id' => $graphs['@id'],
                                        'relatedCount' => $relatedByCount,
                                        'relatedBy' => $relatedByList
                                    )
                                );
                            } elseif (isset($graphs['label'][1]['@value']) 
                                && $graphs['label'][1]['@language'] === $language
                            )
                            {
                                array_push(
                                    $usefulData,
                                    array(
                                        'type' => $class,
                                        'name' => $graphs['label'][1]['@value'],
                                        'abbreviation' => $graphs['abbreviation'],
                                        'id' => $graphs['@id'],
                                        'relatedCount' => $relatedByCount,
                                        'relatedBy' => $relatedByList
                                    )
                                );
                            } elseif (isset($graphs['rdfs:label'])
                                && is_array($graphs['rdfs:label'])
                                && !empty($graphs['rdfs:label'])
                            ) 
                            {
                                // If rdfs:label contains an alternate language version
                                if (
                                    isset($graphs['rdfs:label'][0])
                                    && $graphs['rdfs:label'][0]['@language'] === $language 
                                )
                                {
                                    $graphLabel = $graphs['rdfs:label'][0]['@value'];
                                    array_push(
                                        $usefulData,
                                        array(
                                            'type' => $class,
                                            'name' => $graphLabel,
                                            'abbreviation' => $graphs['abbreviation'],
                                            'id' => $graphs['@id'],
                                            'relatedCount' => $relatedByCount,
                                            'relatedBy' => $relatedByList
                                        )
                                    );
                                } elseif ( isset($graphs['rdfs:label'][1])
                                    && $graphs['rdfs:label'][1]['@language'] === $language 
                                )
                                {
                                    $graphLabel = $graphs['rdfs:label'][1]['@value'];
                                    array_push(
                                        $usefulData,
                                        array(
                                            'type' => $class,
                                            'name' => $graphLabel,
                                            'abbreviation' => $graphs['abbreviation'],
                                            'id' => $graphs['@id'],
                                            'relatedCount' => $relatedByCount,
                                            'relatedBy' => $relatedByList
                                        )
                                    );
                                } else {
                                    $graphLabel = $graphs['rdfs:label']['@value'];
                                    array_push(
                                        $usefulData,
                                        array(
                                            'type' => $class,
                                            'name' => $graphLabel,
                                            'abbreviation' => $graphs['abbreviation'],
                                            'id' => $graphs['@id'],
                                            'relatedCount' => $relatedByCount,
                                            'relatedBy' => $relatedByList
                                        )
                                    );
                                }
                                
                            } else {
                                array_push(
                                    $usefulData,
                                    array(
                                        'type' => $class,
                                        'name' => $graphs['label']['@value'],
                                        'abbreviation' => $graphs['abbreviation'],
                                        'id' => $graphs['@id'],
                                        'relatedCount' => $relatedByCount,
                                        'relatedBy' => $relatedByList
                                    )
                                );
                            }
                        }  
                    }
                }
            }

        } else {
            // No subquery data to parse !
        }
    }
    
    return $usefulData;
}

/** 
 * Flush all keys from all Redis databases
 *
 * @return nothing.
 */
function resetRedis($redisServerUrl, $redisServerPort)
{
    $redisServerUrlSafe = filter_var(
        $redisServerUrl, 
        FILTER_SANITIZE_URL
    );
    $redisServerPortSafe = filter_var(
        $redisServerPort, 
        FILTER_SANITIZE_NUMBER_INT
    );
    // Initiate REDIS connection
    $redis = new Redis();
    $redis->connect(
        $redisServerUrlSafe, 
        $redisServerPortSafe
    );
    // Remove all keys from all Redis databases
    $redis->flushAll();
    // Close redis connection
    $redis->close();
}

/** 
 * Clean and flatten objects to proper JSON.
 * VIVO objects contains mixes of arrays and objects...
 *
 * @param array $data VIVO object
 *
 * @return array.
 */
function convertToJson($data) 
{
    // The hard way :( !
    $fullObject = json_decode(
        json_encode(
            json_decode($data),
            true
        ),
        true
    );
    return $fullObject;
}

/** 
 * Concatenate array values into a string.
 *
 * @param array $data array to flatten
 *
 * @return string.
 */
function concatenateArray($data) 
{
    $string = implode(" -|- ",$data);
    return $string;
}

/** 
 * Clean and shorten VIVO full URL into UUID.
 *
 * @param string $URL VIVO entity URL
 *
 * @return string.
 */
function shrinkToUUID($url) 
{
    // If the URL is valid...
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        // Keep only he last URL argument 
        // eg. : from // Example data URL : http://data.ehess.fr/individual/406mnj428_snordman_stat
        // to '406mnj428_snordman_stat'
        $explodedDataUrl = explode('/',$url);
        $dataUri = end($explodedDataUrl);
        unset($explodedDataUrl);
        
        // ... and from '406mnj428_snordman_stat'
        // to '406mnj428'
        $explodedDataString = explode('_',$dataUri);
        $uuid = $explodedDataString[0];
        unset($explodedDataString,$url);
        
        return $uuid;
    } else {
        // URL not valid !
    }
}

/** 
 * Translate UUID to entity real name
 * using REDIS data.
 *
 * @param string $url the entity URL
 *
 * @return string.
 */
function entityRealName(
    $url,
    $redisServerUrl, 
    $redisServerPort
) 
{
    // We will need to replace entities UUID
    // with real names. 
    // We will use existing redis data.
    
    // Initiate new Redis connection
    $redis = new Redis();
    $redis->connect($redisServerUrl, $redisServerPort);
    
    // To list all keys that match that specific UMR 
    // we will query using wildcard + UMR id
    // We must use the shrinkToUUID function to transform
    // the full URL to the simple uuid.
    $uuid = shrinkToUUID($url);
    $searchMatch = '*' . $uuid . '*';
    $searchKeys = $redis->keys($searchMatch);
    unset(
        $url,
        $uuid,
        $searchMatch,
        $redis
    );
    
    // If we find some results, clean REDIS string
    if ( !empty($searchKeys) )
    {
        $realName = '';
        foreach ($searchKeys as $result)
        {
            // Test if string contains the word 
            // that identifies the specific type of key we are looking for
            if (strpos($result, '::fullString') !== false)
            {
                $explodedKey = explode('::',$result);
                $realName = $explodedKey[1];
            }
        }
        return $realName;
    } else {
        // No redis record found for that UUID
    }
    unset($searchKeys);
}

/** 
 * Debug purposes :
 * Write a message in the CLI console output 
 * (later in a database ?)
 *
 * @return nothing.
 */
function message($message)
{
    print 'dvs > ' . $message . ' (peak memory usage : ' 
        . convert(memory_get_peak_usage(true)) 
        . ')' . "\n";
}

/** 
 * Debug purposes :
 * Set a timer to monitor script performances.
 *
 * @return string.
 */
function timer()
{
    $time = explode(' ', microtime());
    return $time[0]+$time[1];
}

/** 
 * Debug purposes :
 * Convert raw memory consumption
 * to human friendly value.
 *
 * @return string.
 */
function convert($size)
{
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}

/** 
 * Flattens the data into a CSV string.
 * By default, we use the tab ("\t") delimiter.
 *
 * @param bool $debug true/false for db logging.
 * @param object $data The PHP array containing the data to format.
 *
 * @return string.
 *
function arrayToCSV($array,$debug)
{
    $delimiter = "\t";
    $newline = "\n";
    $finalString = '';
    
    $header = 'externalAuthId' . $delimiter 
        . 'firstName' . $delimiter
        . 'lastName' . $delimiter
        . 'email' . $delimiter
        . $newline;
    
    $content = '';
    
    foreach ($array as $single)
    {
        $single = $single['externalAuthId']
            . $delimiter
            . $single['firstName']
            . $delimiter
            . $single['lastName']
            . $delimiter
            . $single['email']
            . $newline;
        
        $content .= $single;
    }
    $finalString = $header 
        . $content;

    unset($array);
    return $finalString;
}*/

/** 
 * V2
 * Flattens the data into a CSV string.
 * By default, we use the tab ("\t") delimiter.
 *
 * @param bool $debug true/false for db logging.
 * @param object $data The PHP array containing the data to format.
 *
 * @return string.
 */
function arrayToCSV($array,$debug)
{
    $delimiter = "\t";
    $newline = "\n";
    $finalString = '';
    
    $header = 'id' . $delimiter 
        . 'externalAuthId' . $delimiter 
        . 'idref' . $delimiter 
        . 'firstName' . $delimiter
        . 'lastName' . $delimiter
        . 'position' . $delimiter
        . 'mainImage' . $delimiter
        . 'email' . $delimiter
        . 'mostSpecificType' . $delimiter
        . 'appartenance' . $delimiter
        . 'appartenanceStatutaire' . $delimiter
        . 'appartenanceATitreDAssocie' . $delimiter
        . 'overview' . $delimiter
        . $newline;
    
    $content = '';
    
    foreach ($array as $single)
    {
        // Convert the 'overview' to avoid CSV encoding problems
        $overview = $single['overview'];
        $overview = str_replace("\n", "", $overview);
        $overview = str_replace("\r", "", $overview); 
        $overview = str_replace("\t", "", $overview);
        
        $single = $single['id']
            . $delimiter
            . $single['externalAuthId']
            . $delimiter
            . $single['idref']
            . $delimiter
            . $single['firstName']
            . $delimiter
            . $single['lastName']
            . $delimiter
            . concatenateArray($single['ehess:Position'])
            . $delimiter
            . $single['mainImage']
            . $delimiter
            . $single['email']
            . $delimiter
            . concatenateArray($single['mostSpecificType'])
            . $delimiter
            . concatenateArray($single['ehess:Appartenance'])
            . $delimiter
            . concatenateArray($single['ehess:AppartenanceStatutaire'])
            . $delimiter
            . concatenateArray($single['ehess:AppartenanceStatutaire'])
            . $delimiter
            . concatenateArray($single['ehess:AppartenanceATitreDAssocie'])
            . $delimiter
            . $overview
            . $newline;
        
        $content .= $single;
        unset($overview);
    }
    $finalString = $header 
        . $content;

    unset($array);
    return $finalString;
}

function buildMailAdresses($string) 
{
    $string = str_replace(' ', '', $string);
    $utf8 = array(
        '/[áàâãªä]/u' => 'a',
        '/[ÁÀÂÃÄ]/u' => 'A',
        '/[ÍÌÎÏ]/u' => 'I',
        '/[íìîï]/u' => 'i',
        '/[éèêë]/u' => 'e',
        '/[ÉÈÊË]/u' => 'E',
        '/[óòôõºö]/u' => 'o',
        '/[ÓÒÔÕÖ]/u' => 'O',
        '/[úùûü]/u' => 'u',
        '/[ÚÙÛÜ]/u' => 'U',
        '/ç/' => 'c',
        '/Ç/' => 'C',
        '/ñ/' => 'n',
        '/Ñ/' => 'N',
    );
    $string = preg_replace(
        array_keys($utf8), 
        array_values($utf8), 
        $string
    );
    return strtolower($string);
}

/** 
 * Writes CSV string to file.
 * By default, we use .txt format for the tab char to work on MS Excel.
 *
 * @param bool $debug true/false for db logging.
 * @param string $string the CSV formatted string.
 * @param string $path the path of the file.
 *
 * @return true.
 */
function outputCSV($string,$path,$debug)
{
    file_put_contents($path, $string);
    if ($debug === true)
    {
        $messageString = 'File ' . $path . ' generated.';
        message($messageString);
        unset($messageString);
    }
    unset($string,$path);
    return true;
}



/*******************************/
/*** PAGES DISPLAY FUNCTIONS ***/
/*******************************/

/** 
 * Display the search result
 * with proper HTML.
 *
 * @param string $searchKey raw Redis key
 * @param string $searchValue raw Redis key value
 *
 * @return nothing.
 */
function displayUniteDeRecherche($results)
{
    // eg : ehess:UniteDeRecherche::Centre de recherche sur les arts et le langage::CENTRE DE RECHERCHE SUR LES ARTS ET LE LANGAGE::CRAL / http://data.ehess.fr/individual/99mzgiy4k::250
    
    print '<div class="umrContainer">' . "\n";
    
    print '<div class="umrHeader">';
    print 'Résultat de la recherche :';
    print '</div>' . "\n";
    
    foreach ($results as $result)
    {
        $explodedSearchKey = explode('::',$result['key']);
        $explodedSearchValue = explode('::',$result['value']);

        $umrName = $explodedSearchKey[1];
        $umrAbreviation = $explodedSearchKey[3];
        $umrId = $explodedSearchValue[0];
        $umrRelated = $explodedSearchValue[1];
        
        // Use the UUID as unique identifier 
        // to post data 
        $explodedIdUrl = explode('/',$umrId);
        $umrUUID = end($explodedIdUrl);
        
        print '<div class="umrTable">' . "\n";
    
        print '<div class="umrTableLeft">';
        print '<h2>' . $umrAbreviation . ' : ' . $umrName . '</h2>';
        print '<span class="normal">' . $umrId . '</span>';
        print '</div>' . "\n";

        print '<div class="umrTableRight">';
        print '<span class="normal">Fiches VIVO associées : </span><h3>' 
            . $umrRelated . '</h3>';
        print '</div>' . "\n";

        print '</div>' . "\n";

        print '<div class="umrFooter">';
        print '<span class="button"><a href="/details?' . $umrUUID . '">Afficher les données du ' . $umrAbreviation . '</a></span>';
        print '</div>' . "\n";
        
        unset(
            $explodedSearchKey,
            $explodedSearchValue,
            $umrName,
            $umrAbreviation,
            $umrId,
            $umrRelated,
            $explodedIdUrl,
            $umrUUID
        );
    }
    
    print '</div>';
}

/** 
 * Display the details results
 * with proper HTML.
 *
 * @param string the unique id of the entity
 *
 * @return nothing.
 */
function displayDetails(
    $id,
    $redisServerUrl, 
    $redisServerPort,
    $debug
)
{
    if (is_string($id)
        && mb_strlen($id) == 9) 
    {
        // Sanitize user input
        $sanitizedId = filter_var(
            $id, 
            FILTER_SANITIZE_STRING
        );
        // Initiate new Redis connection
        $redis = new Redis();
        $redis->connect($redisServerUrl, $redisServerPort);

        // The data we are searching from is formatted as such :
        // key(UMR id :: person's unique URL) // value(Person's full name) 
        // e.g. : 9d7g0gzqrj::http://data.ehess.fr/individual/ncq0aocreh//Balloy, Benjamin
        // (Redis Keys must be unique)
        // To list all keys that match that specific UMR we will query using UMR id + wildcard
        $searchMatch = $sanitizedId . '*';
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
        
        $dataset = array();
        foreach($results as $result)
        {
            $dataset[$result['key']] = $result['value'];
        }
        asort($dataset);
       
        print '<form id="details" method="post" action="detailsExport.php">' . "\n";
        foreach ($dataset as $key=>$value) 
        {
            // Use the UUID as unique identifier in the form
            $explodedKey = explode('::',$key);
            $explodedIdUrl = explode('/',$explodedKey[1]);
            $personUUID = end($explodedIdUrl);
            
            // Simplify details
            $explodedValue = explode('::',$value);
            
            print   '<div class="detailRow">' . "\n";
            print   '<input type="checkbox" id = "'
                    . $personUUID 
                    . '" value="'
                    . $personUUID 
                    . '" checked />';
            print   '<label for="' 
                    . $personUUID 
                    . '">'
                    . $value 
                    . '</label>';
            print   '<div class="detailsSmall">'
                    . $explodedKey[1]
                    . '</div>' . "\n";
            print   '</div>' . "\n";
            
            unset(
                $explodedKey,
                $explodedIdUrl,
                $personUUID
            );
        }
        print '<input id="selectFiches" type="submit" value="Sélctionner ces fiches">' . "\n";
        print '</form>';
        unset($dataset);
    }
}