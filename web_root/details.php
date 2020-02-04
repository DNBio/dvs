<?php
include('../config.php');
include('../includes/functions.php');
$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
        . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
// Sanitize user input
$sanitizedURL = filter_var(
    $url, 
    FILTER_SANITIZE_URL
);
$parts = parse_url($sanitizedURL);
if(!empty($parts['query']))
{
    // Sanitize user input
    $searchArgRaw = filter_var(
        $parts['query'], 
        FILTER_SANITIZE_STRING
    );
    if (mb_strlen($searchArgRaw) > 8
       && mb_strlen($searchArgRaw) < 12
    )
    {
        // Get the main entity name
        $searchMatch = '*' . 'http://data.ehess.fr/individual/' . $searchArgRaw;
        // Initiate new Redis connection
        $redis = new Redis();
        $redis->connect($redisServerUrl, $redisServerPort);
        $searchKey = $redis->keys($searchMatch);
        // Close redis connection
        $redis->close(); 
        unset($redis); 
    }
}
?>
<html>
<head>
<script type="text/javascript" src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<link href="https://fonts.googleapis.com/css?family=Montserrat:400,700|Teko:700&display=swap&subset=latin-ext" rel="stylesheet">
<link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body id="dvsDetails">
<?php 
if ( !empty($searchKey) )
{
    // Bug fix : sometimes we have multiple and unwanted
    // results on offset 0... 
    // TO BE FIXED !
    if (count($searchKey)>1 && mb_strlen($searchKey[1]) > 70)
    {
        $entitySimplifiedData = explode('::',$searchKey[1]);
        $umrName = $entitySimplifiedData[1];
        $umrAbreviation = $entitySimplifiedData[3];
        $umrUrl = $entitySimplifiedData[4];
    } else {
        $entitySimplifiedData = explode('::',$searchKey[0]);
        $umrName = $entitySimplifiedData[1];
        $umrAbreviation = $entitySimplifiedData[3];
        $umrUrl = $entitySimplifiedData[4];
    }

    // Display the title
    print '<div class="umrTable">' . "\n";
    print '<div class="umrTableLeft">';
    print '<h2>' . $umrAbreviation . ' : ' . $umrName . '</h2>';
    print '<span class="normal">' . $umrUrl . '</span>';
    print '</div>' . "\n";
    print '</div>' . "\n";

    // Display the data
    displayDetails(
        $searchArgRaw,
        $redisServerUrl, 
        $redisServerPort,
        $debug
    );
    print_r($searchKey);
}    
?>
</body>
</html>