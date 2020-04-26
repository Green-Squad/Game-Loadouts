<?php
use League\Csv\Reader;

class ImportController extends BaseController {

    public function import(Game $game) {
        return View::make('admin.import.import', compact('game'));
    }

    public function processImport(Game $game) {
        $csv = Input::file('spreadsheet');
        $game_id = $game -> id;

        if (!Input::hasFile('spreadsheet')) {
            return Redirect::back() -> with(array (
                'alert' => 'Error: Spreadsheet missing.',
                'alert-class' => 'alert-danger' 
            ));
        }

        $fileExtension = $csv -> getClientOriginalExtension();
        if ($fileExtension != "csv") {
            return Redirect::back() -> with(array (
                'alert' => 'Error: Only .csv is supported.',
                'alert-class' => 'alert-danger' 
            ));
        }

        $csvReader = Reader::createFromPath($csv->getRealPath(), 'r');

        $attachments = [];
        $weapons = [];
        $weaponAttachments = [];

        $weaponNames = $csvReader -> fetchOne(0);
        $weaponGroups = $csvReader -> fetchOne(1);
        $minAttachments = $csvReader -> fetchOne(2);
        $maxAttachments = $csvReader -> fetchOne(3);

        for ($i = 2; $i < count($weaponNames); $i++) {
            $weapon = new Weapon();
            $weapon -> name = $weaponNames[$i];
            $weapon -> game_id = $game_id;
            $weapon -> min_attachments = $minAttachments[$i];
            $weapon -> max_attachments = $maxAttachments[$i];
            $weapon -> image_url = "";
            $weapon -> thumb_url = "";
            $weapon -> type = $weaponGroups[$i];
            
            array_push($weapons, $weapon);
            $empty = [];
            array_push($weaponAttachments, $empty);
        }
        
        foreach ($csvReader as $index => $row) {
            if ($index > 4)
            {
                $attachment = new Attachment();
                $attachment -> name = $row[0];
                $attachment -> slot = $row[1];
                $attachment -> game_id = $game_id;
                $attachment -> image_url = "";
                $attachment -> thumb_url = "";
                array_push($attachments, $attachment);

                for ($i = 2; $i < count($row); $i++)
                {
                    if ($row[$i])
                    {
                        array_push($weaponAttachments[$i - 2], $attachment);
                    }
                }
            }
        }

        for ($i = 0; $i < count($attachments); $i++)
        {
            $attachment = Attachment::whereRaw('game_id = ? and name = ? and slot = ?', 
                array($game_id, $attachments[$i] -> name, $attachments[$i] -> slot))->first();
            if (!$attachment)
            {
                $attachments[$i] -> save();
            }
        }

        for ($i = 0; $i < count($weapons); $i++)
        {
            $weapon = Weapon::whereRaw('game_id = ? and name = ? and type = ?', 
            array($game_id, $weapons[$i] -> name, $weapons[$i] -> type))->first();

            if (!$weapon)
            {
                $weapons[$i] -> save();
                $weapon = $weapons[$i];
            }

            foreach ($weaponAttachments[$i] as $attachment) {
                $attachment = $attachment = Attachment::whereRaw('game_id = ? and name = ? and slot = ?', 
                array($game_id, $attachment -> name, $attachment -> slot))->first();
                $exisitng = DB::table('attachment_weapon') -> where('attachment_id', $attachment -> id, 'AND') -> where('weapon_id', $weapon -> id) -> first();
                if(!$exisitng);
                {
                    $weapon -> attachments() -> save($attachment);
                }
            }

        }
        
        return Redirect::route('admin.game.show', array (
            'game' => $game_id 
        )) -> with(array (
            'alert' => 'Import complete.',
            'alert-class' => 'alert-success' 
        ));

    }

}
