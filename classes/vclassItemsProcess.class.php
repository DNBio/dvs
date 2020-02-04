<?php

/*** DVS 0.1 vclassItemsProcess.class.php    ***/
/*** david.brett@ehess.fr          ***/
/*** web@ehess.fr                  ***/
/*** V.0.4 - October 2019          ***/
/*** COPYRIGHT EHESS/DNB           ***/
/***                               ***/
/*** Class used to process VIVO vclass items,     ***/
/*** build and execute subqueries. ***/
/***                               ***/

class vclassItemsProcess extends callVivo {
    private $vivoCredentials;
    private $data;
    private $jsonFormat;
    public $debug;
    
    /** 
     * Construct class object.
     *
     * @param array $data a list of entities IDs from a VIVO vclass.
     *
     * @return nothing.
     */
    function __construct(
        array $vivoCredentials
    ) 
    {
        // Build parent callVivo class
        parent::__construct($vivoCredentials);
        
        $this->vivoCredentials = $vivoCredentials;
	}
    
    /** 
     * Build subqueries from vclass entities IDs array.
     *
     * @param array $dataArray query data to append to our POST request
     * @param string $format the data format we expect
     *
     * @return array.
     */
    function buildSubQueriesFromIds(
        $dataArray,
        $format
    ) 
    {
        if (!empty($dataArray)) 
        {
            // Initiate the POST queries array
            $subQueries = array();
            
            // Process the array
            foreach ($dataArray as $k => $v)
            {
                // Make sure the ids are well formatted
                $sanitizedId = preg_replace( '/[^a-z0-9 ]/i', '', $v);
                
                // Populate the query data to append to our POST request
                // eg. : 'http://vivoweb.org/display/99mzgiy4k'
                $subQueryData = array (
                    'context' => $this->vivoCredentials['display_url'],
                    'id' => $sanitizedId,
                    'format' => $format
                );
                
                // Store the subquery
                array_push(
                    $subQueries,
                    $subQueryData
                );
                
                // We don't need these variables anymore
                unset(
                    $sanitizedId,
                    $subQueryData,
                    $this->data[$k]
                );
            }
            
            // Return the subqueries array
            return $subQueries;
            
        } else {
            // debug : no IDs provided ! 
        }
    }
}
