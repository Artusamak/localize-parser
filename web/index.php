<?php

// @TODO:
// * Refactor the Parser to be able to generate the po files on one side
//   and prepare a po file for the processor in another side.

use LdoParser\LocalizeParser;
use LdoParser\LocalizeProcessor;
use LdoDrupal\DrupalIssueClient;

$loader = require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();
$loader->add('LdoParser', __DIR__ . '/../src/');
$loader->add('LdoDrupal', __DIR__ . '/../src/');

$app->register(new Silex\Provider\TwigServiceProvider(), array(
  'twig.path' => __DIR__.'/views',
));

$app->get('/process/{offset}/{limit}', function($offset = 0, $limit = 5) use ($app) {
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


  $output = $processor->getFormattedOutput();

  $new_offset = $offset + $limit;
  $new_limit = $limit;
  $output .= "<a href=\"/process/$new_offset/$new_limit\">Go for next round $new_offset / $new_limit</a>";
  return $output;
});

$app->get('/module/{module_name}', function($module_name, $version = FALSE) use ($app) {
  // Fetch project matching given module name.
  $parser = new LocalizeParser(array(
    'modules' => array($module_name),
  ));

  $processor = new LocalizeProcessor(array(
    'projects' => $parser->buildProject($module_name),
  ));
  $processor->parseItems();

  $output = $processor->getRawOutput();


  return $app['twig']->render('hello.twig', array(
    'project_title' => $output[$module_name]['project'],
    'project_data' => $output[$module_name]['results'],
  ));


  $issue_client = new DrupalIssueClient(array(
    'projects' => $processor->getRawOutput(),
  ));
//  $output = $issue_client->postIssues();

  return $output;
});

$app['debug'] = TRUE;
$app->run();
