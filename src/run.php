<?php

require __DIR__ . "/../vendor/autoload.php";

use Parapente\Synacor\Arch\Memory;
use Parapente\Synacor\Arch\Processor;

$contents = file_get_contents(__DIR__ . "/../challenge.bin");
$contentArray = array_map(fn($item) => ord($item), str_split($contents));
// print_r($contentArray);
///////////// Example code //////////////
// $mem = new Memory();
// $mem->setAddr(0, 9);
// $mem->setAddr(1, 32768);
// $mem->setAddr(2, 32769);
// $mem->setAddr(3, 4);
// $mem->setAddr(4, 19);
// $mem->setAddr(5, 32768);
// $cpu = new Processor($mem);
// $cpu->writeTo(32769, ord('A'));
///////////// End of example code //////////////

$mem = new Memory($contentArray);
$cpu = new Processor($mem);

// $cpu->logger->start();
$cpu->start();


