<?php

$db = './yogsearch.db';
$dbc = './yogcontribute.db';
$dbrep = './yogreports.db';
$castmember = array("Lewis", "Simon", "Sips", "Duncan", "Rythian", "Ben", "Tom", "Zylus", "Zoey", "Nilesy", "Ravs", "Harry", "Lydia", "Bouphe", "Osie", "Briony", "Kirsty", "Boba", "Pedguin", "Trott", "Smith", "Ross", "Hulmes", "Daf", "Breeh", "Pyrion", "Daltos", "Rambler", "Spiff", "Martyn", "Kim", "Gee", "Wilsonator", "Ryan", "Vadact", "Bekki", "Mousie", "Fionn", "Mango", "Shadow", "Lolip");
$castWhiteList = array("@yogscast","@YogsLive","@yogscastshorts","@HoneydewLive","@Sips","@SipsLive","@doubledragon","@duncan","@YogsCiv","@Rythian","@DXPhoenix","@GamesNight","@LewisAndBen","@AngoryTom", "@Mystery_Quest","@Zylus","@zoey","@nilesy","@Ravs_","@SquidGame","@Bouphe","@Osiefish","@brionykay_","@KirstyYT","@boba69","@Pedguin","@hatfilms","@HatFilmsGaming","@HatFilmsLive","@SherlockHulmesDM","@SherlockHulmes","@HighRollersDnD","@pyrionflax","@Daltos","@AlextheRambler","@thespiffingbrit","@inthelittlewood","@martyn","@InTheLittleWoodLive","@inthelittleshorts","@yogscastkim","@Geestargames","@Wilsonator","@BryanCentralYT","@Vadact","@BekkiBooom","@conquest","@Mousie","@MousieAfterDark","@shadowatnoon","@Lolipopgi",);
define('MAX_VIDEOS_TO_SCAN_FOR_COUNT', 500);


$channelOwners = [
    '@HoneydewLive' => ['Simon'],
    '@Sips' => ['Sips'],
    '@SipsLive' => ['Sips'],
    '@duncan' => ['Duncan'],
    '@Rythian' => ['Rythian'],
    '@DXPhoenix' => ['Rythian'],
    '@AngoryTom' => ['Tom'],
    '@Mystery_Quest' => ['Tom'],
    '@Zylus' => ['Zylus'],
    '@zoey' => ['Zoey'],
    '@nilesy' => ['Nilesy'],
    '@Ravs_' => ['Ravs'], 
    '@SquidGame' => ['Lydia'],
    '@Bouphe' => ['Bouphe'],
    '@Osiefish' => ['Osie'],
    '@brionykay_' => ['Briony'],
    '@KirstyYT' => ['Kirsty'],
    '@boba69' => ['Boba'],
    '@Pedguin' => ['Pedguin'],
    '@hatfilms' => ['Trott', 'Smith', 'Ross'],
    '@HatFilmsGaming' => ['Trott', 'Smith', 'Ross'],
    '@HatFilmsLive' => ['Trott', 'Smith', 'Ross'],
    '@SherlockHulmesDM' => ['Hulmes'],
    '@SherlockHulmes' => ['Hulmes'],
    '@pyrionflax' => ['Pyrion'],
    '@Daltos' => ['Daltos'],
    '@AlextheRambler' => ['Rambler'],
    '@thespiffingbrit' => ['Spiff'],
    '@inthelittlewood' => ['Martyn'],
    '@martyn' => ['Martyn'],
    '@InTheLittleWoodLive' => ['Martyn'],
    '@inthelittleshorts' => ['Martyn'],
    '@yogscastkim' => ['Kim'],
    '@Geestargames' => ['Gee'],
    '@Wilsonator' => ['Wilsonator'],
    '@BryanCentralYT' => ['Ryan'],
    '@Vadact' => ['Vadact'],
    '@BekkiBooom' => ['Bekki'],
    '@conquest' => ['Bekki'], 
    '@Mousie' => ['Mousie'],
    '@MousieAfterDark' => ['Mousie'],
    '@shadowatnoon' => ['Shadow'],
    '@Lolipopgi' => ['Lolip'],
    // Channels with no specific owner:
    '@yogscast' => [],
    '@YogsLive' => [],
    '@yogscastshorts' => [],
    '@doubledragon' => [],
    '@YogsCiv' => [],
    '@GamesNight' => [],
    '@LewisAndBen' => [],
    '@HighRollersDnD' => [],
];

?>
