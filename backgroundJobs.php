<?php
include_once "/opt/fpp/www/common.php";
$pluginName = basename(dirname(__FILE__));
$pluginPath = $settings['pluginDirectory']."/".$pluginName."/"; 
$logFile = $settings['logDirectory']."/".$pluginName.".log";
$pluginConfigFile = $settings['configDirectory'] . "/plugin." .$pluginName;
$pluginSettings = parse_ini_file($pluginConfigFile);

$remotePlaylistModified = 0;
$hiddenPlaylistModified = 0;
$sequenceModified = 0;
$sequenceCount = 0;

$baseUrl = urldecode($pluginSettings['baseUrl']);
$apiKey = urldecode($pluginSettings['apiKey']);
$remotePlaylist = urldecode($pluginSettings['remotePlaylist']);
$hiddenPlaylist = urldecode($pluginSettings['hiddenPlaylist']);
logEntry("Remote Playlist: ".$remotePlaylist);
$requestFetchTime = intVal(urldecode($pluginSettings['requestFetchTime']));
logEntry("Request Fetch Time: " . $requestFetchTime);

while(true) {

  $pluginSettings = parse_ini_file($pluginConfigFile);
  $remoteEnabled = urldecode($pluginSettings['remote_enabled']);
  $remoteEnabled = $remoteEnabled == "true" ? true : false;
  $remoteRestarting = urldecode($pluginSettings['remote_restarting']);
  $remoteRestarting = $remoteRestarting == "true" ? true : false;

  if($remoteRestarting == 1) {
    $baseUrl = urldecode($pluginSettings['baseUrl']);
    $apiKey = urldecode($pluginSettings['apiKey']);
    $remotePlaylist = urldecode($pluginSettings['remotePlaylist']);
    $hiddenPlaylist = urldecode($pluginSettings['hiddenPlaylist']);
    $remotePlaylistModified = 0;
    $hiddenPlaylistModified = 0;
  }

  if ($remoteEnabled == 1) {

    clearstatcache();

    //check if remote playlist has changed
    $remotePlaylistFile = "/home/fpp/media/playlists/" . $remotePlaylist . ".json";
    $lastModifyTime = filemtime($remotePlaylistFile);
    if ($lastModifyTime > $remotePlaylistModified) {
      updateRemotePlaylist($remotePlaylist, $apiKey, $lastModifyTime);
    }

    //check if hidden playlist has changed
    $hiddenPlaylistFile = "/home/fpp/media/playlists/" . $hiddenPlaylist . ".json";
    $lastModifyTime = filemtime($hiddenPlaylistFile);
    if ($lastModifyTime > $hiddenPlaylistModified) {
      updateHiddenPlaylist($hiddenPlaylist, $apiKey, $lastModifyTime);
    }

    //check if sequences have been added/removed
    $updateSequences = false;
    $sequences = listSequences();
    if (count($sequences) != $sequenceCount) {
      updateSequences($apiKey);
      $sequenceCount = count($sequences);
    } else {
      foreach($sequences as $sequence) {
        if (strtotime($sequence['mtime']) > $sequenceModified) {
          $updateSequences = true;
          $sequenceModified = strtotime($sequence['mtime']);
        }
      }
      if ($updateSequences) {
        updateSequences($apiKey);
      }
    }

  }

  sleep(2);
  //break;

}

function updateSequences($apiKey) {
  //get sequences
  $url = "http://127.0.0.1/api/sequence";
  $options = array(
    'http' => array(
      'method'  => 'GET'
      )
  );
  $context = stream_context_create( $options );
  $sequences = file_get_contents( $url, false, $context );
  $sequences = json_decode( $sequences, true );

  //post sequences
  $url = $GLOBALS['baseUrl'] . "/syncSequences";
  $options = array(
    'http' => array(
      'method'  => 'POST',
      'content' => json_encode( $sequences ),
      'header'=>  "Content-Type: application/json;\r\n" .
                  "Accept: application/json\r\n" .
                  "key: $apiKey\r\n"
      )
  );
  $context = stream_context_create( $options );
  $result = file_get_contents( $url, false, $context );
  echo $result;
  if($result) {
    logEntry("Sequences Updated Automatically");
  }else {
    logEntry("Sequence Update Failed");
  }
}

function listSequences() {
  $url = "http://127.0.0.1/api/files/sequences";
  $options = array(
    'http' => array(
      'method'  => 'GET'
      )
  );
  $context = stream_context_create( $options );
  $result = file_get_contents( $url, false, $context );
  $result = json_decode( $result, true );
  return $result['files'];
}

function updateRemotePlaylist($remotePlaylist, $apiKey, $newTime) {
  $playlists = array();
  $remotePlaylistEncoded = rawurlencode($remotePlaylist);
  $url = "http://127.0.0.1/api/playlist/${remotePlaylistEncoded}";
  $options = array(
    'http' => array(
      'method'  => 'GET'
      )
  );
  $context = stream_context_create( $options );
  $result = file_get_contents( $url, false, $context );
  $response = json_decode( $result, true );
  $mainPlaylist = $response['mainPlaylist'];
  $index = 1;
  foreach($mainPlaylist as $item) {
    if($item['type'] == 'both' || $item['type'] == 'sequence') {
      $playlist = new \stdClass();
      $playlist->sequenceName = pathinfo($item['sequenceName'], PATHINFO_FILENAME);
      $playlist->sequenceDuration = $item['duration'];
      $playlist->playlistIndex = $index;
      array_push($playlists, $playlist);
    }else if($item['type'] == 'media') {
      $playlist = new \stdClass();
      $playlist->sequenceName = pathinfo($item['mediaName'], PATHINFO_FILENAME);
      $playlist->sequenceDuration = $item['duration'];
      $playlist->playlistIndex = $index;
      array_push($playlists, $playlist);
    }
    $index++;
  }
  $url = $GLOBALS['baseUrl'] . "/syncRequestable";
  $data = array(
    'playlists' => $playlists
  );
  $options = array(
    'http' => array(
      'method'  => 'POST',
      'content' => json_encode( $data ),
      'header'=>  "Content-Type: application/json; charset=UTF-8\r\n" .
                  "Accept: application/json\r\n" .
                  "key: $apiKey\r\n"
      )
  );
  $context = stream_context_create( $options );
  $result = file_get_contents( $url, false, $context );
  if($result) {
    $GLOBALS['remotePlaylistModified'] = $newTime;
    logEntry("Remote Playlist Updated Automatically");
  }else {
    logEntry("Remote Playlist Automatic Update Failed");
  }
}

function updateHiddenPlaylist($hiddenPlaylist, $apiKey, $newTime) {
  $playlists = array();
  $hiddenPlaylistEncoded = rawurlencode($hiddenPlaylist);
  $url = "http://127.0.0.1/api/playlist/${hiddenPlaylistEncoded}";
  $options = array(
    'http' => array(
      'method'  => 'GET'
      )
  );
  $context = stream_context_create( $options );
  $result = file_get_contents( $url, false, $context );
  $response = json_decode( $result, true );
  $mainPlaylist = $response['mainPlaylist'];
  $index = 1;
  foreach($mainPlaylist as $item) {
    if($item['type'] == 'both' || $item['type'] == 'sequence') {
      $playlist = new \stdClass();
      $playlist->sequenceName = pathinfo($item['sequenceName'], PATHINFO_FILENAME);
      $playlist->sequenceDuration = $item['duration'];
      $playlist->playlistIndex = $index;
      array_push($playlists, $playlist);
    }else if($item['type'] == 'media') {
      $playlist = new \stdClass();
      $playlist->sequenceName = pathinfo($item['mediaName'], PATHINFO_FILENAME);
      $playlist->sequenceDuration = $item['duration'];
      $playlist->playlistIndex = $index;
      array_push($playlists, $playlist);
    }
    $index++;
  }
  $url = $GLOBALS['baseUrl'] . "/syncHidden";
  $data = array(
    'playlists' => $playlists
  );
  $options = array(
    'http' => array(
      'method'  => 'POST',
      'content' => json_encode( $data ),
      'header'=>  "Content-Type: application/json; charset=UTF-8\r\n" .
                  "Accept: application/json\r\n" .
                  "key: $apiKey\r\n"
      )
  );
  $context = stream_context_create( $options );
  $result = file_get_contents( $url, false, $context );
  if($result) {
    $GLOBALS['hiddenPlaylistModified'] = $newTime;
    logEntry("Hidden Playlist Updated Automatically");
  }else {
    logEntry("Hidden Playlist Automatic Update Failed");
  }
}

function logEntry($data) {

	global $logFile,$myPid;

	$data = $_SERVER['PHP_SELF']." : [".$myPid."] ".$data;
	
	$logWrite= fopen($logFile, "a") or die("Unable to open file!");
	fwrite($logWrite, date('Y-m-d h:i:s A',time()).": ".$data."\n");
	fclose($logWrite);
}