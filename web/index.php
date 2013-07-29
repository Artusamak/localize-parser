<?php

use LdoParser\LocalizeParser;

$loader = require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
$loader->add('LdoParser', __DIR__ . '/../src/');

$app->get('/parser/list', function() {

  $parser = new LocalizeParser();
  $parser->buildProjects();
  $output = $parser->getOutput();

  return $output;
});

$app['debug'] = TRUE;
$app->run();
