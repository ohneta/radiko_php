<?php
class httpAccess {
  /**
   * リクエスト実行
   * @param string $url
   * @param string $method
   * @param array  $requestHeaders
   * @param array  $requestBody
   * @return array($status, $headers, $body)
   *   $status: httpステータス
   *   $headers: ヘッダ内容の配列
   *   $body: ボディ文字列
   */
  function requestHttp(
    string $url,
    string $method = 'GET',
    array $requestHeaders = [],
    array $requestBody = []
   ) {
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);                      // 対象URL
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);         // httpリクエストの method
    if (!empty($requestHeaders)) {
      curl_setopt($curl, CURLOPT_HTTPHEADER, $requestHeaders);  // HTTP ヘッダフィールド
    }
    if (!empty($requestBody)) {
      curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);     // POSTの場合のリクエストデータ
    }
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);          // 証明書の検証
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);           // 返り値は文字列
    curl_setopt($curl, CURLOPT_HEADER, true);                   // ヘッダ内容も出力
/*
curl_setopt($curl, CURLOPT_COOKIEJAR, "/tmp/test.cookie");  // cookie
curl_setopt($curl, CURLOPT_COOKIEFILE, "/tmp/test.cookie");
*/
    $response = curl_exec($curl);

    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headerString = substr($response, 0, $header_size);
    $status = '';
    $headers = [];
    {
      $tmps = explode("\n", $headerString);
      $tmps = array_map('trim', $tmps);
      $status = $tmps[0];
      for ($i = 1; $i < count($tmps); $i++) {
        if ($tmps[$i] == "")
          continue;
        $ones = explode(":", $tmps[$i]);
        $headers[trim($ones[0])] = trim($ones[1]);
      }
    }
    $body = substr($response, $header_size);
    curl_close($curl);

    return array($status, $headers, $body);
  }

  //----------------------------------------

}

?>
