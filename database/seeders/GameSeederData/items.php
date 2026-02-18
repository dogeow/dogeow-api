<?php

$dir = __DIR__.'/items';

return array_merge(
    require $dir.'/weapons.php',
    require $dir.'/helmets.php',
    require $dir.'/armor.php',
    require $dir.'/gloves.php',
    require $dir.'/boots.php',
    require $dir.'/belts.php',
    require $dir.'/rings.php',
    require $dir.'/amulets.php',
    require $dir.'/potions.php'
);
