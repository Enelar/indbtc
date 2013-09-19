<?php

function http_request($uri, $time_out = 10, $headers = 0)
{
  //  return file_get_contents(trim($uri));
    // Initializing
    $ch = curl_init(); 

    // Set URI
    curl_setopt($ch, CURLOPT_URL, trim($uri));

    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPGET, 1);

    // 1 - if output is not needed on the browser
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // Time-out in seconds
    curl_setopt($ch, CURLOPT_TIMEOUT, $time_out);

    // Executing
    $result = curl_exec($ch);
    
    if ($result === false)
    {
      //echo (curl_error($ch))." Trying to access $uri GET";
    }
    // Closing the channel
    curl_close($ch);

    return $result;
}

function http_post_request($uri, $post_array, $time_out = 10, $headers = 0)
{
    // Initializing
    $ch = curl_init();

    // Set URI
    curl_setopt($ch, CURLOPT_URL, trim($uri));

    curl_setopt($ch, CURLOPT_HEADER, $headers);

    // 1 - if output is not needed on the browser
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // Time-out in seconds
    curl_setopt($ch, CURLOPT_TIMEOUT, $time_out);
    
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_array);

    // Executing
    $result = curl_exec($ch);

    // Closing the channel
    curl_close($ch);

    return $result;
}
