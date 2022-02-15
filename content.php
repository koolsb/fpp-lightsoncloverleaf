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
if (strlen(urldecode($pluginSettings['apiKey']))<1){
  WriteSettingToFile("apiKey",urlencode(""),$pluginName);
}
if (strlen(urldecode($pluginSettings['requestFetchTime']))<1){
  WriteSettingToFile("requestFetchTime",urlencode("10"),$pluginName);
}

foreach ($pluginSettings as $key => $value) { 
  ${$key} = urldecode($value);
}

$remoteFppEnabled = urldecode($pluginSettings['remote_fpp_enabled']);
$remoteFppEnabled = $remoteFppEnabled == "true" ? true : false;
$autoRestartPlugin = urldecode($pluginSettings['autoRestartPlugin']);
$autoRestartPlugin = $autoRestartPlugin == "true" ? true : false;

$url = "http://127.0.0.1/api/plugin/fpp-lightsoncloverleaf/updates";
$options = array(
  'http' => array(
    'method'  => 'POST',
    'header'=>  "Content-Type: application/json; charset=UTF-8\r\n" .
                "Accept: application/json\r\n"
    )
);
$context = stream_context_create( $options );
$result = file_get_contents( $url, false, $context );
$response = json_decode( $result, true );
if ($response['updatesAvailable'] == 1) {
  $showUpdateDiv = "display:block";
}else{
  $showUpdateDiv = "display:none";
}

$playlistDirectory= $settings['playlistDirectory'];
$playlistOptions = "";
if(is_dir($playlistDirectory)) {
  if ($dirTemp = opendir($playlistDirectory)){
    while (($fileRead = readdir($dirTemp)) !== false) {
      if (($fileRead == ".") || ($fileRead == "..")){
        continue;
      }
      $fileRead = pathinfo($fileRead, PATHINFO_FILENAME);
      $playlistOptions .= "<option value=\"{$fileRead}\">{$fileRead}</option>";
    }
    closedir($dirTemp);
  }
}

$playlists = "";
if (isset($_POST['updateRemotePlaylist'])) {
  $remotePlaylist = trim($_POST['remotePlaylist']);
  if (strlen($remotePlaylist)>=2){
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
          $playlist = null;
          $playlist->playlistName = pathinfo($item['sequenceName'], PATHINFO_FILENAME);
          $playlist->playlistDuration = $item['duration'];
          $playlist->playlistIndex = $index;
          array_push($playlists, $playlist);
        }else if($item['type'] == 'media') {
          $playlist = null;
          $playlist->playlistName = pathinfo($item['mediaName'], PATHINFO_FILENAME);
          $playlist->playlistDuration = $item['duration'];
          $playlist->playlistIndex = $index;
          array_push($playlists, $playlist);
        }
        $index++;
      }
      $url = $baseUrl . "/syncPlaylists";
      $data = array(
        'playlists' => $playlists
      );
      $options = array(
        'http' => array(
          'method'  => 'POST',
          'content' => json_encode( $data ),
          'header'=>  "Content-Type: application/json; charset=UTF-8\r\n" .
                      "Accept: application/json\r\n" .
                      "remotetoken: $apiKey\r\n"
          )
      );
      $context = stream_context_create( $options );
      $result = file_get_contents( $url, false, $context );
      $response = json_decode( $result );
      if($response) {
        WriteSettingToFile("remotePlaylist",$remotePlaylist,$pluginName);
        if($autoRestartPlugin == 1 && $remoteFppEnabled == 1) {
          WriteSettingToFile("remote_fpp_enabled",urlencode("false"),$pluginName);
          WriteSettingToFile("remote_fpp_restarting",urlencode("true"),$pluginName);
        }
        echo "<script type=\"text/javascript\">$.jGrowl('Remote Playlist Updated!',{themeState:'success'});</script>";
      }else {
        echo "<script type=\"text/javascript\">$.jGrowl('Remote Playlist Update Failed!',{themeState:'danger'});</script>";
      }
    }else {
      echo "<script type=\"text/javascript\">$.jGrowl('Remote Token Not Found!',{themeState:'danger'});</script>";
    }
  }else {
    echo "<script type=\"text/javascript\">$.jGrowl('No Playlist was Selected!',{themeState:'danger'});</script>";
  }
}

$remoteFalconState = "<h4 id=\"remoteFalconRunning\">Remote Updates are currently running</h4>";
if($remoteFppEnabled == 0) {
  $remoteFalconState = "<h4 id=\"remoteFalconStopped\">Remote Updates are currently stopped</h4>";
}

if (isset($_POST['updateBaseUrl'])) { 
  $baseUrl = trim($_POST['baseUrl']);
  WriteSettingToFile("baseUrl",$baseUrl,$pluginName);
  if($autoRestartPlugin == 1 && $remoteFppEnabled == 1) {
    WriteSettingToFile("remote_fpp_enabled",urlencode("false"),$pluginName);
    WriteSettingToFile("remote_fpp_restarting",urlencode("true"),$pluginName);
  }
  echo "<script type=\"text/javascript\">$.jGrowl('Remote Token Updated',{themeState:'success'});</script>";
}

if (isset($_POST['updateAPIKey'])) { 
  $apiKey = trim($_POST['apiKey']);
  WriteSettingToFile("apiKey",$apiKey,$pluginName);
  if($autoRestartPlugin == 1 && $remoteFppEnabled == 1) {
    WriteSettingToFile("remote_fpp_enabled",urlencode("false"),$pluginName);
    WriteSettingToFile("remote_fpp_restarting",urlencode("true"),$pluginName);
  }
  echo "<script type=\"text/javascript\">$.jGrowl('Remote Token Updated',{themeState:'success'});</script>";
}

if (isset($_POST['updateRequestFetchTime'])) { 
  $requestFetchTime = trim($_POST['requestFetchTime']);
  WriteSettingToFile("requestFetchTime",$requestFetchTime,$pluginName);
  if($autoRestartPlugin == 1 && $remoteFppEnabled == 1) {
    WriteSettingToFile("remote_fpp_enabled",urlencode("false"),$pluginName);
    WriteSettingToFile("remote_fpp_restarting",urlencode("true"),$pluginName);
  }
  echo "<script type=\"text/javascript\">$.jGrowl('Request Fetch Time Updated',{themeState:'success'});</script>";
}

$interruptSchedule = urldecode($pluginSettings['interrupt_schedule_enabled']);
$interruptSchedule = $interruptSchedule == "true" ? true : false;

if($interruptSchedule == 1) {
  $interruptYes = "btn-primary";
  $interruptNo = "btn-secondary";
}else {
  $interruptYes = "btn-secondary";
  $interruptNo = "btn-primary";
}

if (isset($_POST['restartRemoteFalcon'])) {
  $remoteFalconState = "<h4 id=\"remoteFalconRunning\">Remote Falcon is currently running</h4>";
  WriteSettingToFile("remote_fpp_enabled",urlencode("false"),$pluginName);
  WriteSettingToFile("remote_fpp_restarting",urlencode("true"),$pluginName);
}
if (isset($_POST['stopRemoteFalcon'])) {
  $remoteFalconState = "<h4 id=\"remoteFalconStopped\">Remote Falcon is currently stopped</h4>";
  WriteSettingToFile("remote_fpp_enabled",urlencode("false"),$pluginName);
}

$restartNotice = "";
if($autoRestartPlugin == 1) {
  $autoRestartPluginYes = "btn-primary";
  $autoRestartPluginNo = "btn-secondary";
  $restartNotice = "visibility: hidden;";
}else {
  $autoRestartPluginYes = "btn-secondary";
  $autoRestartPluginNo = "btn-primary";
  $restartNotice = "visibility: visible;";
}
if (isset($_POST['autoRestartPluginYes'])) {
  $autoRestartPluginYes = "btn-primary";
  $autoRestartPluginNo = "btn-secondary";
  WriteSettingToFile("autoRestartPlugin",urlencode("true"),$pluginName);
  echo "<script type=\"text/javascript\">$.jGrowl('Auto Restart On',{themeState:'success'});</script>";
}
if (isset($_POST['autoRestartPluginNo'])) {
  $autoRestartPluginYes = "btn-secondary";
  $autoRestartPluginNo = "btn-primary";
  WriteSettingToFile("autoRestartPlugin",urlencode("false"),$pluginName);
  echo "<script type=\"text/javascript\">$.jGrowl('Auto Restart Off',{themeState:'success'});</script>";
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
      /*background-image: url("https://remotefalcon.com/brick-wall-background-with-juke.jpg");*/
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
    .btn-primary {
      background-color: #D65A31;
      border-color: #D65A31;
    }
    .btn-primary:hover {
      background-color: #D65A31;
      border-color: #D65A31;
    }
    .btn-primary:focus {
      background-color: #D65A31;
      border-color: #D65A31;
    }
    .btn-danger {
      background-color: #A72525;
      border-color: #A72525;
    }
    .btn-danger:hover {
      background-color: #A72525;
      border-color: #A72525;
    }
    .btn-danger:focus {
      background-color: #A72525;
      border-color: #A72525;
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
		#remoteFalconRunning {
			color: #60F779;
		}
		#remoteFalconStopped {
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
		#restartNotice {
			font-weight: bold;
      color: #D65A31;
      <? echo $restartNotice; ?>
		}
  </style>
</head>
<body>
  <div class="container-fluid plugin-body">
    <div class="container-fluid" style="padding-top: 2em;">
      <div class="card">
        <div class="card-body"><div class="justify-content-md-center row" style="padding-bottom: 1em;">
          <div class="col-md-auto">
            <h1>Lights on Cloverleaf<? echo $pluginVersion; ?></h1>
          </div>
        </div>
        <div class="justify-content-md-center row" style="padding-bottom: 1em;">
          <div class="col-md-auto">
            <? echo $remoteFalconState; ?>
          </div>
        </div>
        <div style=<? echo "$showUpdateDiv"; ?>>
          <div id="update" class="justify-content-md-center row">
            <div class="col-md-auto">
              <h4 style="font-weight: bold;">An update is available!</h4>
            </div>
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
								Base URL <span id="restartNotice"> *</span>
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
								API Key <span id="restartNotice"> *</span>
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
                    <button id="updateAPIKey" name="updateAPIKey" class="btn mr-md-3 hvr-underline-from-center btn-primary" type="submit">Update</button>
                  </span>
                </div>
              </form>
            </div>
          </div>
          <!-- Requestable Playlist -->
          <div class="justify-content-md-center row setting-item">
            <div class="col-md-6">
              <div class="card-title h5">
                Requestable Playlist <span id="restartNotice"> *</span>
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
                    <? echo "$playlistOptions "; ?>
                  </select>
                  <span class="input-group-btn">
                    <button id="updateRemotePlaylist" name="updateRemotePlaylist" class="btn mr-md-3 hvr-underline-from-center btn-primary" type="submit">Update</button>
                  </span>
                </div>
              </form>
            </div>
          </div>
          <!-- Current Requestable Playlist -->
          <div class="justify-content-md-center row setting-item" style="padding-top: .5em;">
            <div class="col-md-6">
              <div class="card-title h5">
                Current Requestable Playlist
              </div>
              <div class="mb-2 text-muted card-subtitle h6">
                This is the current playlist synced with the web server.
              </div>
            </div>
            <div class="col-md-6">
              <h5><? echo "$remotePlaylist"; ?></h5>
            </div>
          </div>
          <!-- Request Fetch Time -->
          <div class="justify-content-md-center row setting-item">
            <div class="col-md-6">
							<div class="card-title h5">
								Request Fetch Time <span id="restartNotice"> *</span>
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
          <!-- Restart Remote Falcon -->
          <div class="justify-content-md-center row setting-item">
            <div class="col-md-6">
              <div class="card-title h5">
                Restart Plugin
              </div>
              <div class="mb-2 text-muted card-subtitle h6">
                This will restart the plugin
              </div>
            </div>
            <div class="col-md-6">
              <form method="post">
                <button class="btn mr-md-3 hvr-underline-from-center btn-primary" id="restartRemoteFalcon" name="restartRemoteFalcon" type="submit">
                  Restart Plugin
                </button>
              </form>
            </div>
          </div>
          <!-- Stop Remote Falcon -->
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
                <button class="btn mr-md-3 hvr-underline-from-center btn-danger" id="stopRemoteFalcon" name="stopRemoteFalcon" type="submit">
                  Stop Plugin
                </button>
              </form>
            </div>
          </div>
          <span id="restartNotice">* Requires Plugin Restart</span>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js" integrity="sha384-ygbV9kiqUc6oa4msXn9868pTtWMgiQaeYH7/t7LECLbyPA2x65Kgf80OJFdroafW" crossorigin="anonymous"></script>

</body>
</html>