<?php
$lang = $_GET['langID'] ?? 'en_GB.utf8';
if (!putenv("LC_ALL=$lang")) exit ('putenv failed');
//putenv("LC_ALL=" . $lang);
setlocale(LC_ALL, $lang);
$results = setlocale(LC_ALL, $lang);
if (!$results) {
    exit ('setlocale failed: locale function is not available on this platform, or the given local does not exist in this environment');
}
bindtextdomain("messages", "./locale");
textdomain("messages");

echo _("welcome");
?>