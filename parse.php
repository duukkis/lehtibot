<?php
include('../twoauth/autoload.php');
use Abraham\TwitterOAuth\TwitterOAuth;

/**
* vars
* $bit_ly_api_key
* $bitly_user
* $tw_consumer_key
* $tw_consumer_secret
* $tw_user_token
* $tw_user_secret
*/
include("vars.php");

//----------- local files to write (for debugging)
$file = "lehti.jpeg";
$file_json = "lehti.json";
$file_result = "uusi.jpeg";

//------------ debug vars
$fetch_paper = true;
$fetch_image = true;
$make_bit_ly_link = true;
$tweet_for_real = true;

//------------ rajoita laatikon koko tähän
$box_size_min_w = 300;
$box_size_min_h = 300;
$box_size_max_w = 900;
$box_size_max_h = 600;

// hae satunnainen lehti ja kirjoita se levylle
function getPaper($save_as = "lehti.json"){
  $tries = 0;
  do{
    sleep(1);
    $paper = rand(613976, 1290000);
    $url = "https://digi.kansalliskirjasto.fi/rest/binding?id=".$paper;
    $server_output = getPage($url);
    $tries++;
  } while($server_output == null && $tries < 5);

  if(!empty($server_output)){
    file_put_contents($save_as, $server_output);
    return true;
  }
  return false;
}

// tee lyhyt linkki
function makeBitLyLink($url){
  global $bit_ly_api_key, $bitly_user;
  $result = null;
  $url = "http://api.bit.ly/shorten?version=2.0.1&longUrl=".$url."&login=".$bitly_user."&apiKey=".$bit_ly_api_key;
  $new_link = file_get_contents($url);
  $json = json_decode($new_link);
  if($json->errorCode == 0){
    foreach($json->results AS $links){
      $result = $links->shortUrl;
    }
  }
  return $result;
}

// hae sivu, (with headers)
function getPage($url){
  $headers = [
      'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'Cache-Control: no-cache',
      'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
      'Whoami: Twitter-bot @lehtibot - tekijä @duukkis',
  ];
  $opts = [
      "http" => [
          "method" => "GET",
          "header" => implode("\r\n", $headers)
      ]
  ];
  $context = stream_context_create($opts);
  $response = file_get_contents($url, false, $context);
  if($http_response_header[0] == "HTTP/1.1 401"){
    return null;
  }
  return $response;
}

// looppaa kunnes löytyy lehti
if($fetch_paper){
  $paperResult = getPaper($file_json);
  if(!$paperResult){
    die("no paper found!");
  }
}
$c = file_get_contents($file_json);
$data = json_decode($c);

// poimi satunnainen sivu, painotus etusivulla
$sivu = rand(0,9);
switch($sivu){
  case 0:
    // joku välisivu
    $poimisivu = rand(1,count($data->pages)-2);
    break;
  case 1:
    // viim sivu
    $poimisivu = count($data->pages)-1;
    break;
  default:
    // etusivu
    $poimisivu = 0;
    break;
}
$kuva = "http://digi.kansalliskirjasto.fi".$data->pages[$poimisivu]->imageUri;
print $kuva."\n";

// hae kuva
if($fetch_image){
  $image = getPage($kuva);
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

// etsi viivoja unohda reunat
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

// etsi viivoja unohda reunat
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

/**
* Etsi laatikko pisteen x_coord,y_coord ympäriltä
* @param int $x_coord
* @param int $y_coord
* @param array $linesx - pystyviivat
* @param array $linesy - vaakaviivat
* @param int $width - kuvan leveys
* @param int $height - kuvan korkeus
* @return array("x1" => int, "y1" => int, "x2" => int, "y2" => int)
*/
function findBox($x_coord, $y_coord, $linesx, $linesy, $width, $height){
  $topy = $height;
  $boty = $height;
  foreach($linesy AS $k => $line){
    if($line["xstart"] < $x_coord && $line["xend"] > $x_coord && $y_coord > $line["ystart"] && ($y_coord-$line["ystart"]) < $topy){
      $topy = ($y_coord-$line["ystart"]);
      // print $line["ystart"]." topY \n";
    }
    if($line["xstart"] < $x_coord && $line["xend"] > $x_coord && $y_coord < $line["ystart"] && ($line["ystart"]-$y_coord) < $boty){
      $boty = ($line["ystart"]-$y_coord);
      // print $line["ystart"]." botY \n";
    }
  }

  $leftx = $width;
  $rightx = $width;
  foreach($linesx AS $k => $line){
    if($line["ystart"] < $y_coord && $line["yend"] > $y_coord && $line["xstart"] < $x_coord && ($x_coord-$line["xstart"]) < $leftx){
      $leftx = ($x_coord-$line["xstart"]);
      // print $line["xstart"]." leftX \n";
    }
    if($line["ystart"] < $y_coord && $line["yend"] > $y_coord && $line["xstart"] > $x_coord && ($line["xstart"]-$x_coord) < $rightx){
      $rightx = ($line["xstart"]-$x_coord);
      // print $line["xstart"]." rightX \n";
    }
  }
  $box = array(
    "x1" => max(0, $x_coord-$leftx),
    "y1" => max(0, $y_coord-$topy),
    "x2" => min($width, $x_coord+$rightx),
    "y2" => min($height, $y_coord+$boty),
  );
  return $box;
}

/**
* onko piste jonkin laatikon sisällä
* @param array $boxes
* @param int $x
* @param int $y
* @return boolean
*/
function isInBox($boxes, $x, $y){
  if(!empty($boxes)){    
    foreach($boxes AS $k => $box){
      if($box["x1"] < $x && $box["x2"] > $x && $box["y1"] < $y && $box["y2"] > $y){
        return true;
      }
    }
  }
  return false;
}

//------------- etsi kaikki laatikot ja poimi niistä sopivat
$boxes = array();
$step = 100;
for($i = $step;$i < $width_old;$i = $i+$step){
  for($j = $step;$j < $height_old;$j = $j+$step){
    if(!isInBox($boxes, $i, $j)){
      $box = findBox($i, $j, $linesx, $linesy, $width_old, $height_old);
      $boxw = $box["x2"]-$box["x1"];
      $boxh = $box["y2"]-$box["y1"];
      if($boxw < $box_size_max_w && $boxh < $box_size_max_h && $boxw > $box_size_min_w && $boxh > $box_size_min_h){
        array_push($boxes, $box);
      }
    }
  }
}

// onko bokseja?
if(!empty($boxes)){
  $random_box = rand(0,count($boxes)-1);

  $box = $boxes[$random_box];
  $newW = $box["x2"]-$box["x1"];
  $newH = $box["y2"]-$box["y1"];
  // crop the big image
  $image_resized = imagecreatetruecolor($newW, $newH);
  imagecopyresized($image_resized, $image, 0, 0, $box["x1"], $box["y1"], $newW, $newH, $newW, $newH);
  imagedestroy($image);

  imagejpeg($image_resized, $file_result);
  imagedestroy($image_resized);

  $link = "http://digi.kansalliskirjasto.fi/sanomalehti/binding/".$data->id."?page=".($poimisivu+1)."";
  if ($make_bit_ly_link) {
    $link = makeBitLyLink(urlencode($link));
  }
  $tweet = $data->title.", s.".($poimisivu+1)."\n".$link."\nKansalliskirjasto";
  print $tweet."\n";

  if ($tweet_for_real) {
    $connection = new TwitterOAuth($tw_consumer_key, $tw_consumer_secret, $tw_user_token, $tw_user_secret);
    $connection->setTimeouts(10,15);

    $media1 = $connection->upload('media/upload', ['media' => $file_result]);
    $parameters = [
      'status' => $tweet,
      'media_ids' => implode(',', [$media1->media_id_string])
    ];
    $code = $connection->post('statuses/update', $parameters);
  }
}