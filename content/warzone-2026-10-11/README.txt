WARZONE CONTENT — 11 OCTOBER 2026

Game ID: Call of Duty Warzone. Short name: Warzone. Suggested theme: Green.
Compatibility status: review draft; see compatibility-review.csv.

This commit includes 270 weapon images, 3,279 attachment images, corresponding
128px/64px-wide thumbnails, and the game card, card thumbnail and header.
1,212 attachments use named artwork; 2,067 use visibly labeled generic slot icons.
The 23 original SVG masters are in generic-slot-masters/.
Legacy artwork already in public/uploads is preserved.

APPLYING THE CONTENT
1. Build the content release with docker build -f Dockerfile.warzone. It inherits
   the exact production runtime by digest and adds artwork and upload permissions.
   Authenticate to the project's Artifact Registry first. The original Dockerfile
   remains the source-build definition, but its archived Debian packages currently
   fail authentication because of expired signing keys. The release does not
   reinstall dependencies or alter application code. Deploy with no public traffic.
2. Back up the content tables before importing. Create Call of Duty Warzone with
   Live = 0 and import ONCE using a guarded transaction that refuses existing
   Warzone content. Preserve all 270 weapons and 3,279 attachment records, but omit
   links for the 63 options in launch-exclusions.json. Expected launch link count:
   11,884. Verify the other games are unchanged before committing.
3. warzone-import-review.csv retains the full review matrix (11,962 links). A raw
   admin import includes the 78 conditional links omitted from launch and can
   duplicate associations if repeated. Do not use a raw re-import to repair data.
4. Run link-images.sql against the application's MySQL database in ONE connection.
   Use a database backup and stop on SQL errors. The script only updates image URL
   fields and timestamps. It uses exact game/name/slot matches and refuses all
   updates unless the complete expected roster exists. Its completion message
   distinguishes a successful link from NO CHANGES. No records or links are created.
5. Verify the card/header, weapon/attachment images and loadout builder, including
   names containing parentheses. Publish by setting Live = 1 after content review.
   Route traffic to a fresh revision of the tested image so its instance-local
   game-list caches include Warzone. Retain the previous revision for rollback.

FILENAMES AND URLS
image-map.json maps each CSV record to its disk files, browser URLs, hashes,
image status and original source. Disk filenames follow PHP urlencode conventions
from the existing upload controllers, including bracket stripping for attachments.
Browser URLs additionally escape literal percent signs in filenames: %28 on disk
becomes %2528 in the stored URL. This preserves exact HTTP-to-file resolution.
No application controllers or templates are changed in this commit.

COMPATIBILITY LIMITS
All 270 researched weapons are included: BO7 64, BO6 69, MWIII 60, MWII 77.
An X means affirmative weapon-specific compatibility evidence. Blanks mean omitted,
not confirmed incompatible. The matrix is conservative and does not include every
shared-slot attachment. See 566 shared-slot review items and 63 conditional-kit
review items. The app cannot enforce cross-slot conversion-kit restrictions or
mode-specific bans. The spurious Tonfa underbarrel slot was removed.

STORAGE
Committed artwork persists through redeploys because it is copied into the image.
New admin uploads remain local to a Cloud Run instance and do not persist when it
stops unless persistent storage is configured. This commit does not add storage.
No deployment, live database update, CSV import or publication is performed by
merely building this Docker image or committing these files.

SOURCES
Weapon roster/compatibility/main imagery: https://codmunity.gg/create-loadout
https://codmunity.gg/database/weapons and https://codmunity.gg/database/attachments
Legacy artwork: https://allmeta.gg/warzone/weapons and https://www.gamesatlas.com/cod-warzone-2/weapons/
Game card/header: https://store.steampowered.com/app/1962663/Call_of_Duty_Warzone/
Season 6: https://www.callofduty.com/blog/2026/09/call-of-duty-black-ops-7-warzone-season-6-the-haunting-announcement
Carry-forward: https://www.callofduty.com/au/en/patchnotes/2025/12/call-of-duty-bo7-warzone-season-01-patch-notes
X52 restriction: https://www.callofduty.com/patchnotes/2025/10/call-of-duty-bo6-warzone-season-06-patch-notes
Artwork is Activision imagery publicly served by these sources; generic SVGs are
original slot illustrations. Source URLs are recorded in image-map.json.

SLOT KEYS
Multi-word slots use CarryHandle, ConversionKit, FireMods, RearGrip and StockPad
to match the existing app's HTML tab IDs and PHP form keys. Original source slot
labels are retained in image-map.json as source_slot. Asset filenames use the
normalized slot keys and follow the same upload-controller naming convention.
