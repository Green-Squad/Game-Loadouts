What is Game Loadouts?
========

Game Loadouts is a portal for finding the best ways to outfit your weapons in your favorite video games.   

This website comes in handy when you need to complete challenges with various weapons and need to know the best attachment combination for that weapon.   

It is also great for fine-tuning your favorite gun to help improve your gameplay. This will save you rounds of trial and error to find the best loadout.


# Environment Variables

RECAPTCHA_PRIVATE_KEY
DISQUS_SECRET_KEY
SEARCH_URL
KEY
DB_HOST
DB_DATABASE
DB_USERNAME
DB_PASSWORD
MAIL_USERNAME
MAIL_PASSWORD

# Issues

If we re-import the db, we might need to run `ALTER TABLE `users` MODIFY `remember_token` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '';`
