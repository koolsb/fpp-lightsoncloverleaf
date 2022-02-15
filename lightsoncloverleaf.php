<?php
include_once "/opt/fpp/www/common.php";
$pluginName = basename(dirname(__FILE__));
$pluginPath = $settings['pluginDirectory']."/".$pluginName."/"; 
$logFile = $settings['logDirectory']."/".$pluginName.".log";
$pluginConfigFile = $settings['configDirectory'] . "/plugin." .$pluginName;
$pluginSettings = parse_ini_file($pluginConfigFile);

WriteSettingToFile("remote_enabled",urlencode("true"),$pluginName);
WriteSettingToFile("remote_restarting",urlencode("false"),$pluginName);

echo "Starting Remote Plugin\n";
logEntry("Starting Remote Plugin");

$apiKey = "";
$remotePlaylist = "";
$interruptSchedule = "";
$currentlyPlayingInRemote = "";
$nextScheduledInRemote= "";
$requestFetchTime = "";
$remoteSequencesCleared = false;

$baseUrl = urldecode($pluginSettings['baseUrl']);
$apiKey = urldecode($pluginSettings['apiKey']);
$remotePlaylist = urldecode($pluginSettings['remotePlaylist']);
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
    WriteSettingToFile("remote_enabled",urlencode("true"),$pluginName);
    WriteSettingToFile("remote_restarting",urlencode("false"),$pluginName);

    echo "Restarting Remote Plugin\n";
    logEntry("Restarting Remote Plugin");
    $baseUrl = urldecode($pluginSettings['baseUrl']);
    $apiKey = urldecode($pluginSettings['apiKey']);
    $remotePlaylist = urldecode($pluginSettings['remotePlaylist']);
    logEntry("Remote Playlist: ".$remotePlaylist);
    $requestFetchTime = intVal(urldecode($pluginSettings['requestFetchTime']));
    logEntry("Request Fetch Time: " . $requestFetchTime);
  }

  if ($remoteEnabled == 1) {
    $fppStatus = getFppStatus();
    $statusName = $fppStatus->status_name;
    if ($statusName != "idle") {
      $remoteSequencesCleared = false;
      $currentlyPlaying = pathinfo($fppStatus->current_sequence, PATHINFO_FILENAME);
      if ($currentlyPlaying == "") {
        //Might be media only, so check for current song
        $currentlyPlaying = pathinfo($fppStatus->current_song, PATHINFO_FILENAME);
      }
      updateCurrentlyPlaying($currentlyPlaying, $GLOBALS['currentlyPlayingInRemote'], $apiKey);
      updateNextScheduledSequence($fppStatus, $currentlyPlaying, $GLOBALS['nextScheduledInRemote'], $apiKey);
        $secondsRemaining = intVal($fppStatus->seconds_remaining);
      if ($secondsRemaining < $requestFetchTime) {
        logEntry($requestFetchTime . " seconds remaining, so fetching next request");
          $nextPlaylistInQueue = nextPlaylistInQueue($apiKey);
          $nextSequence = $nextPlaylistInQueue->Sequence;
          $nextSequenceIndex = $nextPlaylistInQueue->FPPIndex;
          if ($nextSequence != null) {
              logEntry("Queuing requested sequence " . $nextSequence);
              insertPlaylistAfterCurrent(rawurlencode($remotePlaylist), $nextSequenceIndex);
              sleep($requestFetchTime);
              updateCurrentlyPlaying($nextSequence, $GLOBALS['currentlyPlayingInRemote'], $remoteToken);
          } else {
            logEntry("No requests");
            sleep($requestFetchTime);
          }
      }
    } else {
      if ($remoteSequencesCleared == 0) {
        updateCurrentlyPlaying(" ", $GLOBALS['currentlyPlayingInRemote'], $apiKey);
        clearNextScheduledSequence($apiKey);
        $remoteSequencesCleared = true;
      }
    }
  }

  //usleep(250000);
  sleep(1);
}

function updateCurrentlyPlaying($currentlyPlaying, $currentlyPlayingInRemote, $apiKey) {
  if($currentlyPlaying != $currentlyPlayingInRemote) {
    updateNowPlaying($currentlyPlaying, $apiKey);
    logEntry("Updated current playing sequence to " . $currentlyPlaying);
    $GLOBALS['currentlyPlayingInRemote'] = $currentlyPlaying;
  }
}

function updateNextScheduledSequence($fppStatus, $currentlyPlaying, $nextScheduledInRemote, $remoteToken) {
  $currentPlaylist = $fppStatus->current_playlist->playlist;
  $playlistDetails = getPlaylistDetails(rawurlencode($currentPlaylist));
  $mainPlaylist = $playlistDetails->mainPlaylist;
  $nextScheduled = getNextSequence($mainPlaylist, $currentlyPlaying);
  if($nextScheduled != $nextScheduledInRemote && $currentPlaylist != $GLOBALS['remotePlaylist']) {
    updateNextScheduledSequenceInRemote($nextScheduled, $remoteToken);
    logEntry("Updated next scheduled sequence to " . $nextScheduled);
    $GLOBALS['nextScheduledInRemote'] = $nextScheduled;
  }
}

function clearNextScheduledSequence($apiKey) {
  updateNextScheduledSequenceInRemote(" ", $apiKey);
}

function getNextSequence($mainPlaylist, $currentlyPlaying) {
  $nextScheduled = "";
  for ($i = 0; $i < count($mainPlaylist); $i++) {
    if(pathinfo($mainPlaylist[$i]->sequenceName, PATHINFO_FILENAME) == $currentlyPlaying) {
      if($i+1 == count($mainPlaylist)) {
        $nextScheduled = $mainPlaylist[0]->sequenceName;
      }else {
        $nextScheduled = $mainPlaylist[$i+1]->sequenceName;
      }
    }
  }
  return pathinfo($nextScheduled, PATHINFO_FILENAME);
}

function getFppStatus() {
  $result=file_get_contents("http://127.0.0.1/api/fppd/status");
  return json_decode( $result );
}

function updateNowPlaying($currentlyPlaying, $apiKey) {
  $url = $GLOBALS['baseUrl'] . "/nowPlaying";
  $data = array(
    'sequence' => trim($currentlyPlaying)
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
}

function updateNextScheduledSequenceInRemote($nextScheduled, $apiKey) {
  $url = $GLOBALS['baseUrl'] . "/playingNext";
  $data = array(
    'sequence' => trim($nextScheduled)
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
}

function insertPlaylistAfterCurrent($remotePlaylistEncoded, $index) {
  $url = "http://127.0.0.1/api/command/Insert%20Playlist%20After%20Current/" . $remotePlaylistEncoded . "/" . $index . "/" . $index;
  $options = array(
    'http' => array(
      'method'  => 'GET'
      )
  );
  $context = stream_context_create( $options );
  $result = file_get_contents( $url, false, $context );
}

function getPlaylistDetails($remotePlaylistEncoded) {
  $url = "http://127.0.0.1/api/playlist/" . $remotePlaylistEncoded;
  $options = array(
    'http' => array(
      'method'  => 'GET'
      )
  );
  $context = stream_context_create( $options );
  $result = file_get_contents( $url, false, $context );
  return json_decode( $result );
}

function nextPlaylistInQueue($apiKey) {
  $url = $GLOBALS['baseUrl'] . "/nextPlaylistInQueue";
  $options = array(
    'http' => array(
      'method'  => 'GET',
      'header'=>  "key: $apiKey\r\n"
      )
  );
  $context = stream_context_create( $options );
  $result = file_get_contents( $url, false, $context );
  return json_decode( $result );
}

function logEntry($data) {

	global $logFile,$myPid;

	$data = $_SERVER['PHP_SELF']." : [".$myPid."] ".$data;
	
	$logWrite= fopen($logFile, "a") or die("Unable to open file!");
	fwrite($logWrite, date('Y-m-d h:i:s A',time()).": ".$data."\n");
	fclose($logWrite);
}

?>