<?php

  require "smackdown.php";

  use Gbox\Smackdown;


  $smackdown = new Smackdown();

  // RUN TEST
  $testfile = file_get_contents('test.md');

  file_put_contents('test-render.html', json_encode($smackdown->render($testfile),JSON_PRETTY_PRINT));

  file_put_contents('test-renderfile.html', json_encode($smackdown->render($testfile),JSON_PRETTY_PRINT));

  print_r($smackdown);
