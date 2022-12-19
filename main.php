<?php	
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

	include_once("constants.php");	
    include_once("config.php");

    $database = new database;
	$db = $database->connect();

    $count = $database->lists("spoof_log", false, false, "RAND", "", false, "count");
    $random = rand(50,100);
    if ($count > $random) {
        $limit = $random;
    } else {
        $limit = $count;
    }

    $c = 0;

    while ($c < $limit) {
        $row = $database->lists("spoof", false, 1, "RAND", "", false, "getRow");

        $returnedData = $database->lists("spoof_log", false, 1, "id", "ASC", false, "getRow");

        $rand_url = URL."externalLink?i&u=".$returnedData['ref']."_".$returnedData['user']."url=".urlencode($returnedData['url']);
        
        $headers[] = 'Accept: '.$row['accept']; 
        $headers[] = 'Connection: Keep-Alive'; 
        $headers[] = 'Upgrade-Insecure-Requests: 1';
        $headers[] = 'Accept-Encoding: '.$row['useraccept'];
        $useragent = $row['useragent'];
        $cookiee = $row['usercookie'];
        
        
        $process = curl_init($rand_url); 
        curl_setopt($process, CURLOPT_HTTPHEADER, $headers); 
        curl_setopt($process, CURLOPT_HEADER, 0); 
        curl_setopt($process, CURLOPT_USERAGENT, $useragent);
        curl_setopt($process, CURLOPT_COOKIE, $cookiee);
        curl_setopt($process, CURLOPT_REFERER, $rand_url);
        curl_setopt($process, CURLOPT_TIMEOUT, 30); 
        curl_setopt($process, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1); 
        
        curl_setopt($process, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);

        curl_exec($process); 
        curl_close($process);

        $database->delete("spoof_log", $returnedData['id'], "id");
        $c++;
    }
?>