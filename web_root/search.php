<?php
include('../config.php');
include('../includes/functions.php');

// Only process POST reqeusts.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if post data exceeds 2 chars
    if(mb_strlen($_POST["centre"])>2)
    {
        // Sanitize user input
        $searchArgRaw = filter_var(
            $_POST["centre"], 
            FILTER_SANITIZE_STRING
        );

        // Check that data was provided.
        if (empty($searchArgRaw)) {
            // Set a 400 (bad request) response code and exit.
            http_response_code(400);
            echo "Merci de fournir un argument de recherche valide.";
            exit;
        }

        // Initiate new Redis connection
        $redis = new Redis();
        $redis->connect($redisServerUrl, $redisServerPort);

        $searchMatch = '*' . strtoupper($searchArgRaw) . '*';
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

        // Display result
        displayUniteDeRecherche($results);

        unset(
            $searchArg,
            $searchMatch,
            $searchKey,
            $searchValue
        );
    }
} else {
    // Not a POST request, set a 403 (forbidden) response code.
    http_response_code(403);
    echo "There was a problem with your submission, please try again.";
}

?>