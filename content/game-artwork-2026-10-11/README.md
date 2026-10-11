# Game artwork refresh

Official EA and Activision artwork replaces the text-only BF6, BO7 and COD Mobile
cards and the incorrectly sized Warzone card. No captions, instructions or
generated artwork are added to the images.

The files follow the existing layout: full artwork in `public/uploads`, cards in
`public/uploads/thumb`, and backgrounds in `public/uploads/header`. Cards are
525 × 179, full artwork is 1920 × 657, and headers are 1500 × 215. Original images
are cropped around the subjects without stretching. Versioned filenames prevent
visitors from reusing the earlier card images from their browser cache.

`artwork.json` records the official source pages, image URLs, crop centers,
dimensions, output URLs and SHA-256 hashes. Sources:

- [Battlefield 6](https://www.ea.com/games/battlefield/battlefield-6)
- [Black Ops 7](https://www.callofduty.com/blackops7)
- [COD Mobile](https://www.callofduty.com/mobile)
- [Warzone](https://www.callofduty.com/warzone)

Deploy the image files before changing the four game records' image, thumbnail
and header URLs to the `game_images` values in the manifest. Preserve weapon and
attachment data, game publication settings, and timestamps. Start a fresh revision
after linking the new artwork so Laravel's file cache reads the updated URLs.
