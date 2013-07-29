<?php

use LdoParser\LocalizeParser;
use LdoParser\LocalizeProcessor;

$loader = require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
$loader->add('LdoParser', __DIR__ . '/../src/');

$app->get('/parser/list', function() {

  $parser = new LocalizeParser();
  $parser->buildProjects();
  $output = $parser->getOutput();

  return $output;
});

$app->get('/process/{filename}', function($filename) {

  $processor = new LocalizeProcessor();

  $strings = array_shift($processor->parse_po_file($filename));
  $similar = $processor->compare_strings($strings);
  var_dump($similar);
  return 'ok';
});

$app['debug'] = TRUE;
$app->run();
