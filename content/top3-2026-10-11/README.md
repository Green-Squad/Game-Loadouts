# BO7, COD Mobile and Battlefield 6 content

Prepared 11 October 2026. This package adds data and PNG assets only. It does not
change application code, the schema, the Dockerfile or the loadout validator.

| Game | Weapons | Attachment options | Compatibility links |
| --- | ---: | ---: | ---: |
| Call of Duty Black Ops 7 | 64 | 1,198 | 2,172 |
| Call of Duty Mobile | 147 | 382 | 892 |
| Battlefield 6 | 63 | 382 | 5,077 |

COD uses the standard five-attachment maximum. Gunfighter is excluded. Melee and
weapons with no functional customization use a maximum of zero. BF6 permits one
attachment per supported slot; users must check their attachment point budget and
other in-game restrictions themselves. Default BF6 parts are selectable options;
leaving a slot empty represents keeping its in-game default.

## Coverage and sources

BO7 uses the BO7-origin weapon roster and weapon-specific attachment metadata
from [CODMunity](https://codmunity.gg/database/weapons), with the same conditional
kit exclusions used for the Warzone package. The standard five-attachment rule is
confirmed in [Activision's multiplayer deep dive](https://www.callofduty.com/ca/en/blog/2025/09/call-of-duty-black-ops-7-multiplayer-deep-dive).
Existing BO7 artwork from the Warzone package is reused under each game's filename.

COD Mobile includes the current base-weapon roster, not skins or operator skills.
Its attachment options are a conservative selection explicitly present in
weapon-specific published Gunsmith builds, **not an exhaustive list of every
Gunsmith option**. Sources are [Zilliongamer's weapon guides](https://zilliongamer.com/call-of-duty-mobile/),
[CODM War Room's researched builds](https://github.com/Bate-Epey93/codm-warroom),
and the linked Striker/HS2126 guides recorded on individual attachment entries.
The upcoming Season 9 Grav is excluded: [Activision's announcement](https://www.callofduty.com/blog/2026/10/call-of-duty-mobile-season-9-vampires-werewolves)
places its release after this snapshot. Character perks, cosmetics and BR mods are
excluded; the Perk slot contains only weapon Gunsmith perks.

BF6 uses [Companion Battlefield's](https://www.companionbattlefield.com/app)
63-weapon, 353-definition Season 4 dataset extracted from local game files
(changelist 29122033). Weapon-specific display labels produce 382 CSV attachment
rows. Weapon-specific point costs are preserved in `bf6-catalogue.json` for
reference, but the app does not enforce them. Compatibility is recorded per weapon.

Every catalogue entry includes its source pages. Image maps record artwork sources,
PNG hashes and whether an entry uses sourced artwork or a labeled fallback.
Missing attachment artwork uses generic slot icons. Nineteen weapons use clearly
labeled artwork placeholders (mostly COD Mobile melee, plus BF6 Interdictor).
Runtime assets are PNG; SVG graphic masters are kept here, outside the web root.

Game cards and headers now use cropped official EA/Activision artwork. Source
URLs, hashes and standard card/header dimensions are recorded in
`../game-artwork-2026-10-11/artwork.json`. The earlier SVG card/header masters are
historical and are no longer used by the published game records.

## Import and publish

1. Deploy the PNG assets from this exact commit. Create the three game records
   hidden (`live = 0`) with the names and artwork URLs from their catalogues.
2. Import each `*-import.csv` **once**, into its matching new game. Preserve the
   five header rows and the weapon columns. The existing importer can duplicate
   compatibility links on a repeat import.
3. Match imported weapons by exact game/name/type and attachments by exact
   game/name/slot, then set their image and thumbnail URLs from the image maps.
   A guarded transactional database import of the same CSV rows is equivalent.
4. Verify roster and link counts, image URLs, PNG decoding, slot names and
   attachment limits. Back up content before any live database writes.
5. Set the games live only after verification, then start a fresh Cloud Run revision
   using the same image so the Laravel file cache reads the new catalogue.

Public assets follow the existing `public/uploads`, `public/uploads/thumb` and
`public/uploads/header` pattern. Filenames follow PHP `urlencode`; stored URLs
encode literal percent characters again. A compact ID suffix is used if an artwork
URL would exceed the existing 128-character database field.
