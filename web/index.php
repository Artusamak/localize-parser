<?php

use LdoParser\LocalizeParser;
use LdoParser\LocalizeProcessor;

$loader = require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();
$loader->add('LdoParser', __DIR__ . '/../src/');

$app->get('/process/{offset}/{limit}', function($offset = 0, $limit = 5) {

  // Fetch the projects matching the current limits.
  $parser = new LocalizeParser(array(
    'interval_bottom' => $offset,
    'interval_top' => $offset + $limit,
  ));
  $parser->buildProjects();

  $processor = new LocalizeProcessor(array(
    'projects' => $parser->getProjects(),
    'offset' => $offset,
    'limit' => $limit,
  ));
  $processor->parseItems();

  $output = $processor->getOutput();

  $new_offset = $offset + $limit;
  $new_limit = $limit;
  $output .= "<a href=\"/process/$new_offset/$new_limit\">Go for next round $new_offset / $new_limit</a>";
  return $output;
});

$app->get('/module/{module_name}', function($module_name) {

  // Fetch project matching given module name.
  $parser = new LocalizeParser(array(
    'modules' => array($module_name),
  ));
  $parser->buildProjects();

  $processor = new LocalizeProcessor(array(
    'projects' => $parser->getProjects(),
  ));
  $processor->parseItems();

  $output = $processor->getOutput();
  return $output;
});

$app['debug'] = TRUE;
$app->run();
