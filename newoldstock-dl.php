#!/usr/local/bin/php
<?php

require_once('TumblrPhotoFetch.php');

$newoldstock = new TumblrPhotoFetch('http://nos.twnsnd.co/api/read',
                                    getenv('HOME') . '/Pictures/New Old Stock',
                                    getenv('HOME') . '/Library/Logs/com.mcdado.newoldstock.log');
$newoldstock->init();
$newoldstock->terminate();
