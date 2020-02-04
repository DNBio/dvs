<?php

/*** DVS 0.1 callVivo.class.php    ***/
/*** david.brett@ehess.fr          ***/
/*** web@ehess.fr                  ***/
/*** V.0.4 - October 2019          ***/
/*** COPYRIGHT EHESS/DNB           ***/
/***                               ***/
/*** Class used to query VIVO,     ***/
/*** and return the answer.        ***/
/***                               ***/

class callVivo {
    private $vivoCredentials;
    private $expectedEncoding;
    private $queryData;
    public $debug;
    
    /** 
     * Construct class object.
     *
     * @param array $vivoCredentials VIVO credentials.
     *
     * @return nothing.
     */
    function __construct(array $vivoCredentials) 
    {
		$this->vivoCredentials = $vivoCredentials;
	}
    
    /** 
     * Query VIVO and fetch the results.
     *
     * @param array $queryData Our actual VIVO query parameters.
     * @param bool $debug true/false for db logging.
     *
     * @return array VIVO answer or error messages.
     */
    function getVivoResult(
        array $queryData,
        bool $debug)
    {
        
        // Make sure the VIVO url and the query context is set
        if (!is_null($this->vivoCredentials['url']) || !is_null($queryData['context']))
        {
            // Select the VIVO API we will use,
            // by building the URL we will be targeting.
            // eg. : http://vivoweb.org/listrdf
            $contextURL = filter_var(
                $this->vivoCredentials['url'], 
                FILTER_SANITIZE_URL
            ) . filter_var(
                $queryData['context'], 
                FILTER_SANITIZE_URL
            );

            // FOR THE LIST API
            if ($queryData['context'] === '/listrdf') 
            {
                // $expectedEncoding must be sanitized and built
                // eg : array ("Accept:text/plain")
                $acceptHeader = "Accept: " . $queryData['format'];
                
                // The list API needs POST data to be sent along the request.
                // Initiate the POST query string
                $queryDataString = null;
                
                $queryDataString = 'vclass=' .
                    $queryData['ontolgyURL'] . 
                    '/' .
                    $queryData['vclass'];
                // Sanitize the final string
                $queryDataString = filter_var(
                    $queryDataString, 
                    FILTER_SANITIZE_URL
                );
                
                // Make sure our query data string exists
                if (!is_null($queryDataString)) 
                {
                    // Populate CURL params, include our request via POST
                    $options = array(
                        CURLOPT_CUSTOMREQUEST  => "POST",   // set request type post or get
                        CURLOPT_POST           => true,     // set to POST
                        CURLOPT_RETURNTRANSFER => true,     // return web page
                        CURLOPT_HEADER         => false,    // don't return headers
                        CURLOPT_POSTFIELDS     => $queryDataString,   // this are the post vars
                        CURLOPT_HTTPHEADER     => array ($acceptHeader),
                        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
                        CURLOPT_ENCODING       => "",       // handle all encodings
                        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
                        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
                        CURLOPT_TIMEOUT        => 120,      // timeout on response
                        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
                    );

                    // Initiate CURL with target URL
                    $ch      = curl_init($contextURL);

                    // Attach CURL params
                    curl_setopt_array( $ch, $options );

                    // Execute and try to fetch data or errors
                    $content = curl_exec( $ch );
                    $err     = curl_errno( $ch );
                    $errmsg  = curl_error( $ch );
                    $result  = curl_getinfo( $ch );

                    // Close CURL
                    curl_close( $ch );

                    // Return query metadata/data
                    $result['errno']   = $err;
                    $result['errmsg']  = $errmsg;
                    $result['content'] = $content;
                    return $result;
                }
            }
            
            // FOR THE LINKED OPEN DATA
            // eg. https://vivo-qa.ehess.fr/display/99mzgiy4k?format=jsonld
            if ($queryData['context'] === '/individual') 
            {
                $contextURL = $contextURL 
                    . '/' 
                    . $queryData['id'] 
                    . '?format='
                    . $queryData['format'];
                
                // Populate CURL params, include our request via POST
                $options = array(
                    CURLOPT_CUSTOMREQUEST  => "GET",    // set request type post or get
                    CURLOPT_POST           => false,    // set to GET
                    CURLOPT_RETURNTRANSFER => true,     // return web page
                    CURLOPT_HEADER         => false,    // don't return headers
                    CURLOPT_FOLLOWLOCATION => true,     // follow redirects
                    CURLOPT_ENCODING       => "",       // handle all encodings
                    CURLOPT_AUTOREFERER    => true,     // set referer on redirect
                    CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
                    CURLOPT_TIMEOUT        => 120,      // timeout on response
                    CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
                );

                // Initiate CURL with target URL
                $ch      = curl_init($contextURL);

                // Attach CURL params
                curl_setopt_array( $ch, $options );

                // Execute and try to fetch data or errors
                $content = curl_exec( $ch );
                $err     = curl_errno( $ch );
                $errmsg  = curl_error( $ch );
                $result  = curl_getinfo( $ch );

                // Close CURL
                curl_close( $ch );

                // Return query metadata/data
                $result['errno']   = $err;
                $result['errmsg']  = $errmsg;
                $result['content'] = $content;
                
                return $result;
                
            }

        } else {
            // Debug message : the VIVO url and/or the query context are null 
            print 'Empty vars !';
        }
    }
    
    function validateVivoAnswer($vivoResults)
    {
        $rawData = array();
        foreach ($vivoResults as $vivoResult) 
        {
            // Store the data, if errors are found, print the errors
            if ( isset($vivoResult['errno']) && $vivoResult['errno'] != 0 ) {
                print 'Error #' 
                    . $vivoResult['errno'] 
                    . ' : bad url ? timeout, redirect loop...';
            } elseif ( isset($vivoResult['http_code']) &&  $vivoResult['http_code'] != 200 ) {
                print 'HTTP code ' 
                    . $vivoResult['http_code'] 
                    . ' : no page ? no permissions, no service...';
            } else {
                array_push($rawData,$vivoResult['content']);
            }
        }
        return $rawData;
    }
    
    function executeSubquery(
        $subqueries,
        $debug
    )
    {
        // Proceed to VIVO queries
        if (!empty($subqueries))
        {
            $subQueriesData = array();
            foreach ($subqueries as $k => $v)
            {
                // Attempt to query VIVO
                $subQueriesData[] = $this->getVivoResult(
                    $v,
                    $debug
                );
                // We don't need these anymore
                unset($subqueries[$k]);
            }
            return $subQueriesData;
        }
    }
}
