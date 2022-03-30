<?php
// c.f.)
//   $php radiko.php TBS

include_once ('./httpAccess.php');

  $gHttpAccess = new httpAccess();
  $calledStation = '';
  if ($argc > 1) {
    $calledStation = strtoupper($argv[1]);
  }

  //--------------------------------------------------------------------------------
  function getHttp($url, $headers = [])
  {
    global $gHttpAccess;
    return $gHttpAccess->requestHttp($url, 'GET', $headers);
  }

  //--------------------------------------------------------------------------------

  function auth1()
  {
    $url = 'https://radiko.jp/v2/api/auth1';
    $headers = [
      'X-Radiko-App: pc_html5',
      'X-Radiko-App-Version: 0.0.1',
      'X-Radiko-User: dummy_user',
      'X-Radiko-Device: pc',
    ];
    return getHttp($url, $headers);
  }

  //----------------------------------------
  function auth2($authToken, $keyOffset, $keyLength)
  {
    $authkey = "bcd151073c03b352e1ef2fd66c32209da9ca0afa";
    $keyStr = substr($authkey, $keyOffset, $keyLength);
    $encoredKeyStr = base64_encode($keyStr);

    $headers = [
      'X-Radiko-AuthToken: ' . $authToken,
      'X-Radiko-PartialKey: ' . $encoredKeyStr,
      'X-Radiko-User: dummy_user',
      'X-Radiko-Device: pc'
    ];
    return getHttp('https://radiko.jp/v2/api/auth2', $headers);
  }

  //----------------------------------------
  function getStationList($regionId)
  {
    $url = 'http://radiko.jp/v2/station/list/' . $regionId. '.xml';
//    $url = 'http://radiko.jp/v2/station/list/JP13.xml';
//    $url = 'http://radiko.jp/v2/station/list/JP1.xml';
    return getHttp($url);
  }

  //----------------------------------------
  function getPlaylistUrls($stationId)
  {
    $url = 'http://radiko.jp/v3/station/stream/pc_html5/' . $stationId. '.xml';
    return getHttp($url);
  }

 //--------------------------------------------------------------------------------
 //--------------------------------------------------------------------------------
  // 認証情報の取得
  function getAuthInfo()
  {
    list($status, $headers, $body) = auth1();
    if ($status != 'HTTP/1.1 200 OK') {
      exit("status error: $status\n");
    }

    $upperCaseHeaders = [];
    foreach ($headers as $key => $val) {
      $upperCaseHeaders[strtoupper($key)] = $val;
    }
    $authToken = $upperCaseHeaders['X-RADIKO-AUTHTOKEN'];
    $keyOffset = $upperCaseHeaders['X-RADIKO-KEYOFFSET'];
    $keyLength = $upperCaseHeaders['X-RADIKO-KEYLENGTH'];

    return array($authToken, $keyOffset, $keyLength);
 }

  //--------------------------------------------------------------------------------
  // 地域情報から再生可能局を取得
  function getStations($reginInfo)
  {
//print($reginInfo);
    $tmps = explode(",", $reginInfo);
    $regionId        = $tmps[0];
    $regionName      = $tmps[1];
    $regionAsciiName = $tmps[2];
    list($status, $headers, $body) = getStationList($regionId);

    $stationsInfo = [];
    $xmlDomDoc = new DOMDocument();
    {
      $ret = $xmlDomDoc->loadXML($body);
      $stationNodeList = $xmlDomDoc->getElementsByTagName('station');
      foreach ($stationNodeList as $stationNode) {
        $name = $stationNode->getElementsByTagName('name')['name']->nodeValue;
        $id   = $stationNode->getElementsByTagName('id')['id']->nodeValue;
        $stationsInfo[$id] = $name;
      }
    }

    return $stationsInfo;
  }

  //--------------------------------------------------------------------------------
  function getUrlFromPlaylist($xml)
  {
    global $calledStation;

    $xmlDomDoc = new DOMDocument();
    $ret = $xmlDomDoc->loadXML($xml);
    $urlNodeList = $xmlDomDoc->getElementsByTagName('url');
    foreach ($urlNodeList as $urlNode) {
      $areafree  = $urlNode->getAttribute('areafree');
      $max_delay = $urlNode->getAttribute('max_delay');
      $timefree  = $urlNode->getAttribute('timefree');
      $playlist_create_url = $urlNode->getElementsByTagName('playlist_create_url')['playlist_create_url']->nodeValue;

      // TODO:
      // このplaylist取得のための条件($areafree, $timefree, $calledStation/_definst_/等)は解析結果から得たもので誤りかもかもしれない。
      // 現時点(2020年08月14日)で経験則的にこれで上手く動いてるだけである。
      if (($areafree == 0) && ($timefree == 0)) {
        if (strpos($playlist_create_url, "$calledStation/_definst_/") !== false) {
          $playlist_url = $playlist_create_url;
          break;
        }
      }
    }

    return $playlist_url;
  }

  //--------------------------------------------------------------------------------
  function getChunklist($playlist_url, $authToken)
  {
    $headers = [
      'X-Radiko-AuthToken: ' . $authToken,
//      'X-Radiko-User: dummy_user',
//      'X-Radiko-Device: pc'
    ];
    list($status, $headers, $body) = getHttp($playlist_url, $headers);

    return $body;
  }

  //--------------------------------------------------------------------------------
  function getChunklistByStation($stationId, $authToken)
  {
    $chunklistUrl = '';

    $playlistUrl = '';
    {
      list($status, $headers, $body) = getPlaylistUrls($stationId);
      $playlistUrl = getUrlFromPlaylist($body);
    }
    if ($playlistUrl != '') {
      $m3u8 = getChunklist($playlistUrl, $authToken);
      $m3u8Array = explode("\n", $m3u8);
      foreach ($m3u8Array as $one) {
        if ($one == '')
          continue;
        if (strncmp('#', $one, 1) == 0)
          continue;
        $chunklistUrl = $one;
        break;
      }
    }

     return $chunklistUrl;
   }

  //--------------------------------------------------------------------------------
  //--------------------------------------------------------------------------------
  /**
   * chaunklistのm3u8から、#EXT-X-MEDIA-SEQUENCEの値を取得する
   */
  function getChunckToSequence($chunckM3u8)
  {
    $param = -1;
    $defineCommand = '#EXT-X-MEDIA-SEQUENCE:';

    $lines = explode("\n", $chunckM3u8);
    for ($i = 0; $i < count($lines); $i++) {
      $line = trim($lines[$i]);
      if (strncmp($defineCommand, $line, strlen($defineCommand)) == 0) {
        $tmps = explode(":", $line);
        return $tmps[1];
      }
    }

    return -1;
  }

  /**
   * chunklistのm3u8から、一番上のEXTINFに付随するURLを取得する
   */
  function getChunckToInfUrlOne($chunckM3u8)
  {
    $defineCommand = '#EXTINF:';
    $commandLen = strlen($defineCommand);

    $infDuration = 0;
    $lines = explode("\n", $chunckM3u8);
    for ($i = 0; $i < count($lines); $i++) {
      $line = $lines[$i];
      if (strncmp($defineCommand, $line, $commandLen) == 0) {
        $tmp = substr(substr($line, $commandLen), 0, -1);
        $infDuration = (float)$tmp;
        return array($lines[$i + 1], $infDuration);
      }
    }

    return '';
  }

  //--------------------------------------------------------------------------------
/*
  function getChunckToAAC($chunckM3u8)
  {
    $defineCommands = [
      '#EXTM3U:',
      '#EXT-X-VERSION:',
      '#EXT-X-TARGETDURATION:',
      '#EXT-X-MEDIA-SEQUENCE:',
      '#EXTINF:',
    ];

    $lines = explode("\n", $chunckM3u8);
    for ($i = 0; $i < count($lines); $i++) {
      $line = $lines[$i];
      foreach ($defineCommands as $defineCommand) {
        if (strncmp($defineCommand, $line,strlen($defineCommand)) == 0) {
print('::comparea command ---> ');
print("$defineCommand = $line ". PHP_EOL);
          $tmps = explode(":", $line);
          $command = $tmps[0];
          $param = $tmps[1];
print("$command = $param ". PHP_EOL);
          if ($defineCommand == '#EXTINF:') { // 特別な処理
            $i++;
            $lineUrl = $lines[$i];
print("lineUrl = $lineUrl ". PHP_EOL);
          }
        }
      }
    }
  }
*/
  //--------------------------------------------------------------------------------
  //--------------------------------------------------------------------------------
  {
    // 認証情報の取得
    list ($authToken, $keyOffset, $keyLength) = getAuthInfo();

    // 認証の承認
    list($status, $headers, $body) = auth2($authToken, $keyOffset, $keyLength);
    if ($status != 'HTTP/1.1 200 OK')
      exit("status error: $status\n");

    $stationsInfo = getStations($body);
    if ($calledStation == '') {   // 局指定なし ...
      print("受信可能放送局ID ". PHP_EOL);
      foreach ($stationsInfo as $key => $value) {
        print("$key : $value". PHP_EOL);
      }
      exit();
    }

    if (!isset($stationsInfo[$calledStation])) {
      print("$calledStation は指定できません". PHP_EOL);
      exit();
    }

    //----------------------------------------------------------------------
/*
  // chaunklistのAACファイルを逐次読み込む処理
  {
    $infDuration = 1.0;
    $sequence = 0;
    $sequence_last = -1;
    while (true) {
      sleep((int)$infDuration);
      $chunklistUrl = getChunklistByStation($calledStation, $authToken);
      if ($chunklistUrl != '') {
        list($status, $headers, $body) = getHttp($chunklistUrl);
        $sequence = getChunckToSequence($body);
        if ($sequence != $sequence_last) {
          list($infUrlOne, $infDuration) = getChunckToInfUrlOne($body);
print("sequence = $sequence ". PHP_EOL);
print("infUrlOne = $infUrlOne ". PHP_EOL);
print("infDuration = $infDuration ". PHP_EOL);

          // AACファイルの読み込み
          $handle = fopen($infUrlOne, 'r');
          $aacData = fread($handle, 512 * 1024);
          fclose($handle);
          //fwrite(STDOUT, $aacData);
        }
      }
    }
  }
*/

    //----------------------------------------------------------------------

    // 再生はchromeにおまかせする処理
    $chunklistUrl = getChunklistByStation($calledStation, $authToken);
    if ($chunklistUrl != '') {
      $cmd = "open -a '/Applications/Google Chrome.app' $chunklistUrl";
      system($cmd);

/*
  $cmd = "wget $chunklistUrl";
  print($cmd . PHP_EOL);
  system($cmd);
*/
    }
  }

//----------------------------------------
?>
