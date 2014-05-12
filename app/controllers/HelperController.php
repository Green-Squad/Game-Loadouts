<?php

class HelperController extends BaseController {

    public static function createThumbnail($thumb_url, $extension, $maxsize) {

        /* read the source image */
        if ($extension == 'png') {
            $source_image = imagecreatefrompng($thumb_url);
        } elseif ($extension == 'jpg' || $extension == 'jpeg') {
            $source_image = imagecreatefromjpeg($thumb_url);
        } elseif ($extension == 'gif') {
            $source_image = imagecreatefromgif($thumb_url);
        }

        $desired_width = $maxsize;

        $width = imagesx($source_image);
        $height = imagesy($source_image);

        /* find the "desired height" of this thumbnail, relative to the desired width  */
        $desired_height = floor($height * ($desired_width / $width));

        /* create a new, "virtual" image */
        $virtual_image = imagecreatetruecolor($desired_width, $desired_height);
        
        imagealphablending($virtual_image, false);
        imagesavealpha($virtual_image, true);

        /* copy source image at a resized size */
        imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);

        /* create the physical thumbnail image to its destination */
        if ($extension == 'png') {
            imagepng($virtual_image, $thumb_url);
        } elseif ($extension == 'jpg' || $extension == 'jpeg') {
            imagejpeg($virtual_image, $thumb_url);
        } elseif ($extension == 'gif') {
            imagegif($virtual_image, $thumb_url);
        }

    }

}
