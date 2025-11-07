<?php
// Called inside edit_mitarbeiter.php when avatar is uploaded
$id = (int)$id; // id already set
$upload = $_FILES['avatar'];
$target = __DIR__ . "/../pics/profile/$id.png";

// remove old
if (file_exists($target)) unlink($target);

// process
$src = imagecreatefromstring(file_get_contents($upload['tmp_name']));
if (!$src) return;
$size = 180;
$dst = imagecreatetruecolor($size,$size);
$w = imagesx($src); $h=imagesy($src);
$min = min($w,$h);
$src_cropped = imagecrop($src, ['x'=>($w-$min)/2,'y'=>($h-$min)/2,'width'=>$min,'height'=>$min]);
imagecopyresampled($dst,$src_cropped,0,0,0,0,$size,$size,$min,$min);
imagepng($dst,$target);
imagedestroy($src); imagedestroy($src_cropped); imagedestroy($dst);
?>