<?php
include('../twoauth/autoload.php');
use Abraham\TwitterOAuth\TwitterOAuth;

$file = "lehti.jpeg";
$file_json = "lehti.json";
$file_result = "uusi.jpeg";

$tw_consumer_key = "";
$tw_consumer_secret = "";
$tw_user_token = "";
$tw_user_secret = "";

$debug_fetch_paper = true;
$debug_fetch_image = true;

function getPaper($save_as = "lehti.json"){
  $paper = rand(613976, 1290000);
  $url = "https://digi.kansalliskirjasto.fi/rest/binding?id=".$paper;
  $server_output = getPage($url);
  file_put_contents($save_as, $server_output);
}

function makeBitLyLink($url){
  $result = null;
  $bit_ly_api_key = "";
  $url = "http://api.bit.ly/shorten?version=2.0.1&longUrl=".$url."&login=duukkis&apiKey=".$bit_ly_api_key;
  $new_link = file_get_contents($url);
  $json = json_decode($new_link);
  if($json->errorCode == 0){
    foreach($json->results AS $links){
      $result = $links->shortUrl;
    }
  }
  return $result;
}

function getPage($url, $binary = false){
  $headers = [
      'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'Cache-Control: no-cache',
      'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
  ];
  if($binary){
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => implode("\r\n", $headers)
        ]
    ];
    $context = stream_context_create($opts);
    return file_get_contents($url, false, $context);
  }
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  $server_output = curl_exec($ch);
  curl_close($ch);
  return $server_output;
}

if($debug_fetch_paper){
  getPaper($file_json);  
}
$c = file_get_contents($file_json);
$data = json_decode($c);
// print_r($data);
if($data === null || $data === null){
  die("page failed");
}

$sivu = rand(0,3);
switch($sivu){
  case 0:
  case 1:
    // etusivu
    $getpage = 0;
    break;
  case 2:
    // joku välisivu
    $getpage = rand(1,count($data->pages)-2);
    break;
  case 3:
    // viim sivu
    $getpage = count($data->pages)-1;
    break;
}
// print_r($data->pages);
$kuva = "http://digi.kansalliskirjasto.fi".$data->pages[$getpage]->imageUri;
print $kuva."\n";
if($debug_fetch_image){
  $image = getPage($kuva, true);
  file_put_contents($file, $image);
}
// print $kuva;

// -------------------- image handling

$info = getimagesize($file);
$image = imagecreatefromjpeg($file);
list($width_old, $height_old) = $info;
  
$tol = 25;
$jump = 5;

$linesx = array();
$linesy = array();
// vertical
for($x = 10;$x < $width_old-10;$x++){
  $pcount = 0;
  $xstart = null;
  $ystart = null;
  $empties = $jump;
  for($y = 10;$y < $height_old-10;$y++){
    $rgb = imagecolorat($image, $x, $y);
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;
    if($r < $tol && $g < $tol && $b < $tol){ // black
      if($xstart == null){
        $xstart = $x;
        $ystart = $y;
      }
      $pcount++;
      $empties = $jump;
    } else if($pcount > 300) { // line found
      array_push($linesx, array("xstart" => $xstart, "ystart" => $ystart, "xend" => $x, "yend" => $y, "len" => $pcount));
      $pcount = 0;
      $xstart = null;
      $ystart = null;
      $empties = $jump;
    } else if($empties <= 0){
      $pcount = 0;
      $xstart = null;
      $ystart = null;
      $empties = $jump;
    } else {
      $empties--;
    }
  }
}

// horizontal
for($y = 10;$y < $height_old-10;$y++){
  $pcount = 0;
  $xstart = null;
  $ystart = null;
  $empties = $jump;
  for($x = 10;$x < $width_old-10;$x++){
    $rgb = imagecolorat($image, $x, $y);
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;
    if($r < $tol && $g < $tol && $b < $tol){ // black
      if($xstart == null){
        $xstart = $x;
        $ystart = $y;
      }
      $pcount++;
      $empties = $jump;
    } else if($pcount > 300) { // line found
      array_push($linesy, array("xstart" => $xstart, "ystart" => $ystart, "xend" => $x, "yend" => $y, "len" => $pcount));
      $pcount = 0;
      $xstart = null;
      $ystart = null;
      $empties = $jump;
    } else if($empties <= 0) {
      $pcount = 0;
      $xstart = null;
      $ystart = null;
      $empties = $jump;
    } else {
      $empties--;
    }
  }
}

// linesx pystyviivat
// linesy vaakaviivat

$iterations = 0;

do{
  $randY = rand(500, $height_old-500);
  $randX = rand(300, $width_old-300);

  /*$randY = 1400;
  $randX = 2600;
  */

  $topy = $height_old;
  $boty = $height_old;
  foreach($linesy AS $k => $line){
    if($line["xstart"] < $randX && $line["xend"] > $randX && $randY > $line["ystart"] && ($randY-$line["ystart"]) < $topy){
      $topy = ($randY-$line["ystart"]);
      // print $line["ystart"]." topY \n";
    }
    if($line["xstart"] < $randX && $line["xend"] > $randX && $randY < $line["ystart"] && ($line["ystart"]-$randY) < $boty){
      $boty = ($line["ystart"]-$randY);
      // print $line["ystart"]." botY \n";
    }
  }

  $leftx = $width_old;
  $rightx = $width_old;
  foreach($linesx AS $k => $line){
    if($line["ystart"] < $randY && $line["yend"] > $randY && $line["xstart"] < $randX && ($randX-$line["xstart"]) < $leftx){
      $leftx = ($randX-$line["xstart"]);
      // print $line["xstart"]." leftX \n";
    }
    if($line["ystart"] < $randY && $line["yend"] > $randY && $line["xstart"] > $randX && ($line["xstart"]-$randX) < $rightx){
      $rightx = ($line["xstart"]-$randX);
      // print $line["xstart"]." rightX \n";
    }
  }

  $iterations++;
  $box = array(
    "randX" => $randX,
    "randY" => $randY,
    "iterations" => $iterations,
    "x1" => max(0, $randX-$leftx-6),
    "y1" => max(0, $randY-$topy-6),
    "x2" => min($width_old, $randX+$rightx+6),
    "y2" => min($height_old, $randY+$boty+6),
  );
  $newW = $box["x2"]-$box["x1"];
  $newH = $box["y2"]-$box["y1"];
} while( ($newW > 800 || $newH > 800) && $iterations < 20);

print_r( $box );



// $lines = array_merge($linesx, $linesy);


if($iterations < 20){

  $image_resized = imagecreatetruecolor($newW, $newH);
  imagecopyresized($image_resized, $image, 0, 0, $box["x1"], $box["y1"], $newW, $newH, $newW, $newH);
  imagedestroy($image);

  imagejpeg($image_resized, $file_result);
  imagedestroy($image_resized);

  $link = makeBitLyLink(urlencode("http://digi.kansalliskirjasto.fi/sanomalehti/binding/".$data->id."?page=".$getpage.""));

  $tweet = $data->title.", s.".$getpage." ".$link." Kansalliskirjaston Digitoidut aineistot";

  print $tweet."\n";

  $connection = new TwitterOAuth($tw_consumer_key, $tw_consumer_secret, $tw_user_token, $tw_user_secret);
  $connection->setTimeouts(10,15);

  $media1 = $connection->upload('media/upload', ['media' => $file_result]);
  $parameters = [
    'status' => $tweet,
    'media_ids' => implode(',', [$media1->media_id_string])
  ];
  $code = $connection->post('statuses/update', $parameters);
}
