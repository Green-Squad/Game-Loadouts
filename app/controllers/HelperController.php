<?php

class HelperController extends BaseController {

    public static function adsEnabled() {
        return $_ENV['ads'];
    }

    public function toggleAds() {
        if ($_ENV['ads']) {
            $_ENV['ads'] = 0;
        } else {
            $_ENV['ads'] = 1;
        }
        return Redirect::back();
    }

    // $list is the collection
    // $attribute is the attribute of the list item
    public static function listToString($list, $attribute) {
        $array = array();
        $counter = 0;
        $string = '';
        foreach ($list as $key => $value) {
            $array[$counter++] = $value -> $attribute;
        }
        if (count($array) > 1) {
            for ($i = 0; $i < count($array); $i++) {
                if ($i < count($array) - 2) {
                    $string .= $array[$i] . ', ';
                } elseif ($i == count($array) - 2) {
                    $string .= $array[$i] . ' ';
                } elseif ($i == count($array) - 1) {
                    $string .= 'and ' . $array[$i];
                }
            }
        } elseif (count($array) == 1) {
            $string = $array[0];
        }
        return $string;
    }

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

    public function sitemap() {
        // use this package for the easy sitemap creation in Laravel 4.*: https://github.com/RoumenDamianoff/laravel4-sitemap
        // then, do something like this for all your dynamic and static content:

        // Place the following code in a route or controller that should return a sitemap
        $sitemap = App::make("sitemap");
        
        $time = '2014-05-20T12:30:00+02:00';
        // Add static pages like this:
        $sitemap -> add(URL::to('/'), $time, '1.0', 'daily');
        $sitemap -> add(URL::to('login'), $time, '0.8', 'weekly');
        $sitemap -> add(URL::to('join'), $time, '0.8', 'weekly');
        $sitemap -> add(URL::to('games'), $time, '0.8', 'weekly');

        $games = Game::all();

        foreach ($games as $game) {
            $sitemap -> add(URL::route('showGame', array(urlencode($game -> id))), $time, '0.8', 'daily');
            $weapons = Weapon::where('game_id', $game -> id) -> get();
            foreach ($weapons as $weapon) {
                $sitemap -> add(URL::route('showLoadouts', array(
                    urlencode($game -> id),
                    urlencode($weapon -> name)
                )), $time, '0.8', 'weekly');
                $loadouts = Loadout::where('weapon_id', $weapon -> id) -> get();
                foreach ($loadouts as $loadout) {
                    $sitemap -> add(URL::route('showLoadout', array(
                        urlencode($game -> id),
                        urlencode($weapon -> name),
                        urlencode($loadout -> id)
                    )), $time, '0.5', 'weekly');
                }
            }
        }

        // Now, output the sitemap:
        return $sitemap -> render('xml');
    }

}
