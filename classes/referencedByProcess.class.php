<?php

/*** DVS 0.1 vclassItemsProcess.class.php    ***/
/*** david.brett@ehess.fr          ***/
/*** web@ehess.fr                  ***/
/*** V.0.4 - October 2019          ***/
/*** COPYRIGHT EHESS/DNB           ***/
/***                               ***/
/*** Class used to process VIVO 'referencedBy' entities     ***/
/*** build and execute subqueries. ***/
/***                               ***/

class referencedByProcess extends callVivo {
    private $vivoCredentials;
    private $data;
    private $format;
    public $debug;
    
    /** 
     * Construct class object.
     *
     * @param array $data a list of entities IDs from a VIVO vclass.
     * @param string $language our main language
     *
     * @return nothing.
     */
    function __construct(
        array $vivoCredentials,
        $language
    ) 
    {
        // Build parent callVivo class
        parent::__construct($vivoCredentials);
        
        $this->vivoCredentials = $vivoCredentials;
        $this->language = $language;
	}
    
    /** 
     * Process personnal data from referencedBy field.
     *
     * @param array $usefulData query data to append to our POST request
     * @param string $format the data format we expect
     *
     * @return array.
     */
    function prepareIndividualQueryData(
        $data,
        $format,
        $debug
    )
    {
        if (!empty($data) && is_array($data))
        {
            
            $individualQueryData = array();

            foreach ($data as $uniteDeRecherche) 
            {   
                foreach($uniteDeRecherche['relatedBy'] as $relatedBy)
                {
                    // Keep only he last URL argument 
                    // eg. : from // Example data URL : http://data.ehess.fr/individual/406mnj428_snordman_stat
                    // to '406mnj428_snordman_stat'
                    $explodedDataUrl = explode('/',$relatedBy);
                    $dataUri = end($explodedDataUrl);
                    
                    // Populate the query data to append to our POST request
                    // eg. : 'https://vivo-qa.ehess.fr/display/406mnj428_snordman_stat?format=jsonld'
                    // https://vivo-qa.ehess.fr/display/n2pd1sf0oi/n2pd1sf0oi?format=jsonld
                    $individualQuery = array (
                        'context' => $this->vivoCredentials['display_url'],
                        'id' => $dataUri,
                        'format' => $format
                    );
                    array_push(
                        $individualQueryData,
                        $individualQuery
                    );
                    unset(
                        $explodedDataUrl,
                        $dataUri,
                        $individualQuery
                    );
                }
            }
            return $individualQueryData;
        }
    }
    
    /** 
     * Process personnal data from referencedBy fields.
     *
     * @param array $usefulData query data to append to our POST request
     * @param string $format the data format we expect
     *
     * @return array.
     */
    function individualDataProcess(
        $dataset,
        $format,
        $debug
    )
    {
        $allIndividuals = array();
        
        foreach ($dataset as $data)
        {
            $data = convertToJson($data);
            
            // Iterate through results : find the graphs we need
            foreach ($data as $graphs)
            {
                foreach ($graphs as $graph)
                {
                    $register = array();   
                        
                    // Check if that graph contains the person's label
                    if( isset($graph['@type']) 
                        && is_array($graph['@type']) 
                        && in_array('foaf:Person', $graph['@type']) 
                        && isset($graph['label'])
                        && !is_array($graph['label'])
                    )
                    {
                        // Name found, populate array
                        // with the person's origin and ID 
                        $register = array(
                            'id' => $graph['@id'],
                            'name' => $graph['label'],
                            'relatedBy' => $graph['relatedBy']
                        );
                        
                        array_push($allIndividuals,$register);
                        unset($register);
                    }
                    
                    // Check if that graph contains the person's rdfs:label
                    // in any languages
                    if( isset($graph['@type']) 
                        && is_array($graph['@type']) 
                        && in_array('foaf:Person', $graph['@type']) 
                        && isset($graph['rdfs:label'])
                    )
                    {
                        if (is_array($graph['rdfs:label']))
                        {
                            $graphLabel = null;
                            if( isset($graph['rdfs:label'][0]['@language'])
                                && $graph['rdfs:label'][0]['@language'] === $this->language)
                            {
                                $graphLabel = $graph['rdfs:label'][0]['@value'];
                            } elseif( isset($graph['rdfs:label'][1]['@language'])
                                && $graph['rdfs:label'][1]['@language'] === $this->language) 
                            {
                                $graphLabel = $graph['rdfs:label'][1]['@value'];
                            } else {
                                $graphLabel = 'NoGraphLabel';
                            }
                            
                            if (isset($graph['relatedBy']))
                            {
                                // Name found, populate array
                                // with the person's origin and ID 
                                $register = array(
                                    'id' => $graph['@id'],
                                    'name' => $graphLabel,
                                    'relatedBy' => $graph['relatedBy']
                                );
                                array_push($allIndividuals,$register);
                                unset($graphLabel,$register);
                            }

                        } else {
                            if (isset($graph['relatedBy']))
                            {
                                // Name found, populate array
                                // with the person's origin and ID 
                                $register = array(
                                    'id' => $graph['@id'],
                                    'name' => $graph['rdfs:label'],
                                    'relatedBy' => $graph['relatedBy']
                                );
                                array_push($allIndividuals,$register);
                                unset($register);
                            } else {
                                // Name found, populate array
                                // with the person's origin and ID 
                                $register = array(
                                    'id' => $graph['@id'],
                                    'name' => $graph['rdfs:label'],
                                    'relatedBy' => ''
                                );
                                array_push($allIndividuals,$register);
                                unset($register);
                            }
                        }
                    } 
                }
            }
        }
        return $allIndividuals;
    }
}
