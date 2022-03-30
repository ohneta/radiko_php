# radiko PHP

radikoにアクセスするPHPライブラリ

## 概要

radiko APIは非公開のため、2020年8月14日現在のradikoを独自解析した内容です。

不具合等にはお応えできませんので、ご利用はご自身の判断でお願いします。

※ macOS 10.15およびGoogle Chromeがインストールされていることが前提になっていますが、依存関係の変更は容易と思います。

## ソースコード内容

```
radiko.php
```
radiko再生に必要なファイルを取得するまでの手順を実行するPHPコード。

実際の音声再生はchromeのm3u再生機能を利用します。

```
httpAccess.php
```

curlを使ったhttp/httpsアクセスのphpライブラリ。

radiko.phpで利用することを前提とした必要最低限の機能しかありません。


## コマンドラインでの使い方

### 放送局一覧を表示
パラメタなしで当方で受信を確認した放送局の一覧を表示します。（東京都内よりアクセス）

(radikoは、ご利用のネット環境のIPアドレスから受信可能地域を算出しているようです)

```bash
% php radiko.php
```

受信可能放送局ID
|ID| 放送局名|
|---|---|
|TBS|TBSラジオ|
|QRR|文化放送|
|LFR|ニッポン放送|
|RN1|ラジオNIKKEI第1|
|RN2|ラジオNIKKEI第2|
|INT|InterFM897|
|FMT|TOKYO FM|
|FMJ|J-WAVE|
|JORF|ラジオ日本|
|BAYFM78|bayfm78|
|NACK5|NACK5|
|YFM|ＦＭヨコハマ|
|HOUSOU-DAIGAKU|放送大学|
|JOAK|NHKラジオ第1（東京）|
|JOAK-FM|NHK-FM（東京）|


## 視聴する

「放送局の一覧」で表示された放送局IDを指定して、聞きたいラジオ局を選択します。

視聴例

NHK FM(東京)の場合
```bash
% php radiko.php JOAK-FM
```

ニッポン放送の場合
```bash
% php radiko.php LFR
```


再生はウェブブラウザで行っています。

このソースコードではmacOSでGoogle Chromeがインストールされていることを前提になっています。

(radio.php #313付近)

```php
	$cmd = "open -a '/Applications/Google Chrome.app' $chunklistUrl";
	system($cmd);
```

これをご利用環境に合わせて書き換えてください。

## 追記
### 30th March 2022
2022年３月20日現在の Chrome バージョン: 100.0.4896.60 ではradikoの音声ファイル(.m3u)を直接再生できなくなっているようでファイルとしてダウンロードされてしまいます。お気をつけて。
