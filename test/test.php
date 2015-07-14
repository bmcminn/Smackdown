<?php

  define('DS', DIRECTORY_SEPARATOR);

  require __DIR__.DS."../vendor/autoload.php";

  Timer::start('root');


  use \Gbox\Smackdown as Smackdown;


  $smackdown = new Smackdown();

  // RUN TEST
  $testfile = file_get_contents(__DIR__.DS.'test.md');


  Timer::start('render', ['test-render.json']);
  file_put_contents(__DIR__.DS.'test-render.json', json_encode($smackdown->render($testfile),JSON_PRETTY_PRINT));

  Timer::start('renderFile', ['test-renderFile.json']);
  file_put_contents(__DIR__.DS.'test-renderfile.json', json_encode($smackdown->render($testfile),JSON_PRETTY_PRINT));

  // print_r($smackdown);

  Timer::stop();
  echo Timer::result();
