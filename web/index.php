<?php

use LdoParser\LocalizeParser;
use LdoParser\LocalizeProcessor;

$loader = require_once __DIR__ . '/../vendor/autoload.php';

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
  $strings = $processor->parse_po_file($filename);
  // This might need to be checked, as parsing libraries-7.x-2.1.fr.po returns
  // an array of arrays, with the only main key being an empty string (hence the
  // call to reset() below) - could this be different for other (more
  // complicated) files?
  $strings = reset($strings);
  $similar = $processor->compare_strings($strings);

  // Do something smarter with the result here. :P
  var_dump($similar);
  return 'ok';
});

$app['debug'] = TRUE;
$app->run();
