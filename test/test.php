<?php

  define('DS', DIRECTORY_SEPARATOR);

  require __DIR__.DS."../vendor/autoload.php";

  $smackdown = new \Gbox\Smackdown();

  // RUN TEST
  $testfile = file_get_contents(__DIR__.DS.'test.md');

  // file_put_contents(__DIR__.DS.'test-render.json', json_encode($smackdown->render($testfile),JSON_PRETTY_PRINT));
  // file_put_contents(__DIR__.DS.'test-renderfile.json', json_encode($smackdown->render($testfile),JSON_PRETTY_PRINT));

  // print_r($smackdown);


  print_r(strtok($testfile));

  // http://onlinephpguide.com/php-strtok
