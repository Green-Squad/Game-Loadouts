<?php

User::observe(new UserObserver);
Loadout::observe(new LoadoutObserver);
Game::observe(new GameObserver);
Weapon::observe(new WeaponObserver);