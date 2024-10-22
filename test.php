<?php
$lang = $_GET['langid'] ?? 'en';
include('locale/' . $lang . '.php');
echo welcome;
?>