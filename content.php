<?php
include_once "/opt/fpp/www/common.php";
$pluginName = basename(dirname(__FILE__));
$pluginConfigFile = $settings['configDirectory'] ."/plugin." .$pluginName;
    
if (file_exists($pluginConfigFile)) {
  $pluginSettings = parse_ini_file($pluginConfigFile);
}

//set defaults if nothing saved
if (strlen(urldecode($pluginSettings['remotePlaylist']))<1){
  WriteSettingToFile("remotePlaylist",urlencode(""),$pluginName);
}
if (strlen(urldecode($pluginSettings['hiddenPlaylist']))<1){
  WriteSettingToFile("hiddenPlaylist",urlencode(""),$pluginName);
}
if (strlen(urldecode($pluginSettings['baseUrl']))<1){
  WriteSettingToFile("baseUrl",urlencode(""),$pluginName);
}
if (strlen(urldecode($pluginSettings['apiKey']))<1){
  WriteSettingToFile("apiKey",urlencode(""),$pluginName);
}
if (strlen(urldecode($pluginSettings['requestFetchTime']))<1){
  WriteSettingToFile("requestFetchTime",urlencode("10"),$pluginName);
}

foreach ($pluginSettings as $key => $value) { 
  ${$key} = urldecode($value);
}

$remoteEnabled = urldecode($pluginSettings['remote_enabled']);
$remoteEnabled = $remoteEnabled == "true" ? true : false;

$remoteState = "<h4 id=\"remoteRunning\">Remote Updates are currently running</h4>";
if($remoteEnabled == 0) {
  $remoteState = "<h4 id=\"remoteStopped\">Remote Updates are currently stopped</h4>";
}

if (isset($_POST['updateBaseUrl'])) { 
  $baseUrl = trim($_POST['baseUrl']);
  WriteSettingToFile("baseUrl",$baseUrl,$pluginName);
  echo "<script type=\"text/javascript\">$.jGrowl('Remote Token Updated',{themeState:'success'});</script>";
}

if (isset($_POST['updateApiKey'])) { 
  $apiKey = trim($_POST['apiKey']);
  WriteSettingToFile("apiKey",$apiKey,$pluginName);
  echo "<script type=\"text/javascript\">$.jGrowl('Remote Token Updated',{themeState:'success'});</script>";
}

$playlists = "";
if (isset($_POST['updateRemotePlaylist'])) {
  $remotePlaylist = trim($_POST['remotePlaylist']);
  if (strlen($remotePlaylist)>=2){
    if(strlen($baseUrl)>1) {
      if(strlen($apiKey)>1) {
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
            //$playlist = null;
            $playlist = new \stdClass();
            $playlist->sequenceName = pathinfo($item['sequenceName'], PATHINFO_FILENAME);
            $playlist->sequenceDuration = $item['duration'];
            $playlist->playlistIndex = $index;
            array_push($playlists, $playlist);
          }else if($item['type'] == 'media') {
            //$playlist = null;
            $playlist = new \stdClass();
            $playlist->sequenceName = pathinfo($item['mediaName'], PATHINFO_FILENAME);
            $playlist->sequenceDuration = $item['duration'];
            $playlist->playlistIndex = $index;
            array_push($playlists, $playlist);
          }
          $index++;
        }
        $url = $baseUrl . "/syncRequestable";
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
        $response = json_decode( $result );
        if($response) {
          WriteSettingToFile("remotePlaylist",$remotePlaylist,$pluginName);
          echo "<script type=\"text/javascript\">$.jGrowl('Remote Playlist Updated!',{themeState:'success'});</script>";
        }else {
          echo "<script type=\"text/javascript\">$.jGrowl('Remote Playlist Update Failed!',{themeState:'danger'});</script>";
        }
      }else {
        echo "<script type=\"text/javascript\">$.jGrowl('Remote Token Not Found!',{themeState:'danger'});</script>";
      }
    }else {
      echo "<script type=\"text/javascript\">$.jGrowl('Base URL Not Found!',{themeState:'danger'});</script>";
    }
  }else {
    echo "<script type=\"text/javascript\">$.jGrowl('No Playlist was Selected!',{themeState:'danger'});</script>";
  }
}

$playlists = "";
if (isset($_POST['updateHiddenPlaylist'])) {
  $hiddenPlaylist = trim($_POST['hiddenPlaylist']);
  if (strlen($hiddenPlaylist)>=2){
    if(strlen($baseUrl)>1) {
      if(strlen($apiKey)>1) {
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
            //$playlist = null;
            $playlist = new \stdClass();
            $playlist->sequenceName = pathinfo($item['sequenceName'], PATHINFO_FILENAME);
            $playlist->sequenceDuration = $item['duration'];
            $playlist->playlistIndex = $index;
            array_push($playlists, $playlist);
          }else if($item['type'] == 'media') {
            //$playlist = null;
            $playlist = new \stdClass();
            $playlist->sequenceName = pathinfo($item['mediaName'], PATHINFO_FILENAME);
            $playlist->sequenceDuration = $item['duration'];
            $playlist->playlistIndex = $index;
            array_push($playlists, $playlist);
          }
          $index++;
        }
        $url = $baseUrl . "/syncHidden";
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
        $response = json_decode( $result );
        if($response) {
          WriteSettingToFile("hiddenPlaylist",$hiddenPlaylist,$pluginName);
          echo "<script type=\"text/javascript\">$.jGrowl('Hidden Playlist Updated!',{themeState:'success'});</script>";
        }else {
          echo "<script type=\"text/javascript\">$.jGrowl('Hidden Playlist Update Failed!',{themeState:'danger'});</script>";
        }
      }else {
        echo "<script type=\"text/javascript\">$.jGrowl('Remote Token Not Found!',{themeState:'danger'});</script>";
      }
    }else {
      echo "<script type=\"text/javascript\">$.jGrowl('Base URL Not Found!',{themeState:'danger'});</script>";
    }
  }else {
    echo "<script type=\"text/javascript\">$.jGrowl('No Playlist was Selected!',{themeState:'danger'});</script>";
  }
}

if (isset($_POST['updateRequestFetchTime'])) { 
  $requestFetchTime = trim($_POST['requestFetchTime']);
  WriteSettingToFile("requestFetchTime",$requestFetchTime,$pluginName);
  echo "<script type=\"text/javascript\">$.jGrowl('Request Fetch Time Updated',{themeState:'success'});</script>";
}

if (isset($_POST['stopRemote'])) {
  $remoteState = "<h4 id=\"remoteStopped\">Remote is currently stopped</h4>";
  WriteSettingToFile("remote_enabled",urlencode("false"),$pluginName);
}

if (isset($_POST['startRemote'])) {
  $remoteState = "<h4 id=\"remoteStopped\">Remote is currently running</h4>";
  WriteSettingToFile("remote_enabled",urlencode("true"),$pluginName);
}

$playlistDirectory= $settings['playlistDirectory'];
$remotePlaylistOptions = "";
$hiddenPlaylistOptions = "";
if(is_dir($playlistDirectory)) {
  if ($dirTemp = opendir($playlistDirectory)){
    while (($fileRead = readdir($dirTemp)) !== false) {
      if (($fileRead == ".") || ($fileRead == "..")){
        continue;
      }
      $fileRead = pathinfo($fileRead, PATHINFO_FILENAME);
      if ($fileRead == $remotePlaylist) {
        $remotePlaylistOptions .= "<option selected value=\"{$fileRead}\">{$fileRead}</option>";
      } elseif ($fileRead == $hiddenPlaylist) {
        $hiddenPlaylistOptions .= "<option selected value=\"{$fileRead}\">{$fileRead}</option>";
      } else {
        $remotePlaylistOptions .= "<option value=\"{$fileRead}\">{$fileRead}</option>";
        $hiddenPlaylistOptions .= "<option value=\"{$fileRead}\">{$fileRead}</option>";
      }
    }
    closedir($dirTemp);
  }
}

?>

<!DOCTYPE html>
<html>
<head>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-giJF6kkoqNQ00vy+HMDP7azOuL0xtbfIcaT9wjKHr8RbDVddVHyTfAAsrekwKmP1"
    crossorigin="anonymous">
  <style>
    a {
      color: #D65A31;
    }
    #bodyWrapper {
      background-color: #20222e;
    }
    .pageContent {
      background-color: #171720;
    }
    .plugin-body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      color: rgb(238, 238, 238);
      background-color: rgb(0, 0, 0);
      font-size: 1rem;
      font-weight: 400;
      line-height: 1.5;
      padding-bottom: 2em;
      background-repeat: no-repeat;
      background-attachment: fixed;
      background-position: top center;
      background-size: auto 100%;
    }
    .card {
      background-color: rgba(59, 69, 84, 0.7);
      border-radius: 0.5em;
      margin: 1em 1em 1em 1em;
      padding: 1em 1em 1em 1em;
    }
    .card-body {
      background-color: rgba(59, 69, 84, 0);
    }
    .card-subtitle {
      font-size: .9rem;
    }
    .setting-item {
      padding-bottom: 2em;
    }
    .input-group {
      padding-top: .5em;
    }
    
    .hvr-underline-from-center {
      display: inline-block;
      vertical-align: middle;
      -webkit-transform: perspective(1px) translateZ(0);
      transform: perspective(1px) translateZ(0);
      box-shadow: 0 0 1px rgba(0, 0, 0, 0);
      position: relative;
      overflow: hidden;
    }
    .hvr-underline-from-center:before {
      content: "";
      position: absolute;
      z-index: -1;
      left: 51%;
      right: 51%;
      bottom: 0;
      background: #FFF;
      height: 4px;
      -webkit-transition-property: left, right;
      transition-property: left, right;
      -webkit-transition-duration: 0.3s;
      transition-duration: 0.3s;
      -webkit-transition-timing-function: ease-out;
      transition-timing-function: ease-out;
    }
    .hvr-underline-from-center:hover:before, .hvr-underline-from-center:focus:before, .hvr-underline-from-center:active:before {
      left: 0;
      right: 0;
    }
		#remoteRunning {
			color: #60F779;
		}
		#remoteStopped {
			color: #A72525;
		}
		#update {
      padding-bottom: 1em;
      font-weight: bold;
			color: #A72525;
		}
    #env {
      color: #A72525;
    }
    #warning {
      font-weight: bold;
      color: #A72525;
    }
  </style>
</head>
<body>
  <div class="container-fluid plugin-body">
    <div class="container-fluid" style="padding-top: 2em;">
      <div class="card">
        <div class="card-body"><div class="justify-content-md-center row" style="padding-bottom: 1em;">
          <div class="col-md-auto">
            <h1>Lights on Cloverleaf</h1>
          </div>
        </div>
        <div class="justify-content-md-center row" style="padding-bottom: 1em;">
          <div class="col-md-auto">
            <? echo $remoteState; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="container-fluid">
      <div class="card">
        <div class="card-body">
          <!-- Base URL -->
          <div class="justify-content-md-center row setting-item">
            <div class="col-md-6">
							<div class="card-title h5">
								Base URL
							</div>
							<div class="mb-2 text-muted card-subtitle h6">
								Enter the base URL of your server
							</div>
						</div>
            <div class="col-md-6">
              <form method="post">
                <div class="input-group">
                  <input type="text" class="form-control" name="baseUrl" id="baseUrl" placeholder="Base URL" value=<? echo "$baseUrl "; ?>>
                  <span class="input-group-btn">
                    <button id="updateBaseUrl" name="updateBaseUrl" class="btn mr-md-3 hvr-underline-from-center btn-primary" type="submit">Update</button>
                  </span>
                </div>
              </form>
            </div>
          </div>
          <!-- Token -->
          <div class="justify-content-md-center row setting-item">
            <div class="col-md-6">
							<div class="card-title h5">
								API Key
							</div>
							<div class="mb-2 text-muted card-subtitle h6">
								Enter the same API key as set on your server
							</div>
						</div>
            <div class="col-md-6">
              <form method="post">
                <div class="input-group">
                  <input type="text" class="form-control" name="apiKey" id="apiKey" placeholder="API Key" value=<? echo "$apiKey "; ?>>
                  <span class="input-group-btn">
                    <button id="updateApiKey" name="updateApiKey" class="btn mr-md-3 hvr-underline-from-center btn-primary" type="submit">Update</button>
                  </span>
                </div>
              </form>
            </div>
          </div>
          <!-- Requestable Playlist -->
          <div class="justify-content-md-center row setting-item">
            <div class="col-md-6">
              <div class="card-title h5">
                Requestable Playlist
              </div>
              <div class="mb-2 text-muted card-subtitle h6">
                This is the playlist that contains all the sequences to be requested by your viewers
              </div>
            </div>
            <div class="col-md-6">
              <form method="post">
                <div class="input-group">
                  <select class="form-select" id="remotePlaylist" name="remotePlaylist">
                    <option selected value=""></option>
                    <? echo "$remotePlaylistOptions "; ?>
                  </select>
                  <span class="input-group-btn">
                    <button id="updateRemotePlaylist" name="updateRemotePlaylist" class="btn mr-md-3 hvr-underline-from-center btn-primary" type="submit">Update</button>
                  </span>
                </div>
              </form>
            </div>
          </div>
          <!-- Hidden Playlist -->
          <div class="justify-content-md-center row setting-item">
            <div class="col-md-6">
              <div class="card-title h5">
                Hidden Playlist
              </div>
              <div class="mb-2 text-muted card-subtitle h6">
                This is the playlist that contains all the sequences to be hidden from the website
              </div>
            </div>
            <div class="col-md-6">
              <form method="post">
                <div class="input-group">
                  <select class="form-select" id="hiddenPlaylist" name="hiddenPlaylist">
                    <option selected value=""></option>
                    <? echo "$hiddenPlaylistOptions "; ?>
                  </select>
                  <span class="input-group-btn">
                    <button id="updateHiddenPlaylist" name="updateHiddenPlaylist" class="btn mr-md-3 hvr-underline-from-center btn-primary" type="submit">Update</button>
                  </span>
                </div>
              </form>
            </div>
          </div>
          <!-- Request Fetch Time -->
          <div class="justify-content-md-center row setting-item">
            <div class="col-md-6">
							<div class="card-title h5">
								Request Fetch Time
							</div>
							<div class="mb-2 text-muted card-subtitle h6">
								This sets when the plugin checks for the next request (default is 10 seconds)
							</div>
						</div>
            <div class="col-md-3">
              <form method="post">
                <div class="input-group">
                  <input type="number" class="form-control" name="requestFetchTime" id="requestFetchTime" value=<? echo "$requestFetchTime "; ?>>
                  <span class="input-group-btn">
                    <button id="updateRequestFetchTime" name="updateRequestFetchTime" class="btn mr-md-3 hvr-underline-from-center btn-primary" type="submit">Update</button>
                  </span>
                </div>
              </form>
            </div>
            <div class="col-md-3">
            </div>
          </div>
          <?php if($remoteEnabled == 1) { ?>
          <!-- Stop Remote -->
          <div class="justify-content-md-center row setting-item">
            <div class="col-md-6">
              <div class="card-title h5">
                Stop Plugin 
              </div>
              <div class="mb-2 text-muted card-subtitle h6">
                <span id="warning">WARNING! </span>This will immediately stop the
                plugin and no requests/votes will be fetched!
              </div>
            </div>
            <div class="col-md-6">
            <form method="post">
                <button class="btn mr-md-3 hvr-underline-from-center btn-danger" id="stopRemote" name="stopRemote" type="submit">
                  Stop Plugin
                </button>
              </form>
            </div>
          </div>
          <?php } else { ?>
          <!-- Start Remote -->
          <div class="justify-content-md-center row setting-item">
            <div class="col-md-6">
              <div class="card-title h5">
                Start Plugin 
              </div>
              <div class="mb-2 text-muted card-subtitle h6">
                Start the remote plugin
              </div>
            </div>
            <div class="col-md-6">
            <form method="post">
                <button class="btn mr-md-3 hvr-underline-from-center btn-danger" id="startRemote" name="startRemote" type="submit">
                  Start Plugin
                </button>
              </form>
            </div>
          </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js" integrity="sha384-ygbV9kiqUc6oa4msXn9868pTtWMgiQaeYH7/t7LECLbyPA2x65Kgf80OJFdroafW" crossorigin="anonymous"></script>
</body>
</html>