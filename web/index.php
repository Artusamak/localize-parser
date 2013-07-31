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
  $parser->buildModules();

  // Process the projects fetched from the parser.
  $processor = new LocalizeProcessor(array(
    'projects' => $parser->getModules(),
    'offset' => $offset,
    'limit' => $limit,
  ));
  $processor->parseItems();

  // Generate the report of the processed projects.
  $output = $processor->getFormattedOutput();

  $new_offset = $offset + $limit;
  $new_limit = $limit;
  $output .= "<a href=\"/process/$new_offset/$new_limit\">Go for next round $new_offset / $new_limit</a>";
  return $output;
});

$app->get('/module/{module_name}/{version}', function($module_name, $version) use ($app) {
  // Fetch project matching given module name.
  $parser = new LocalizeParser(array(
    'modules' => array($module_name),
  ));

  // Instanciate the po files processor.
  $processor = new LocalizeProcessor();

  // Collect project data from d.o if we don't know the version we want to
  // process.
  // Otherwise just format the version and project title for processing.
  if (!$version) {
    $module = $parser->buildModule($module_name);
  }
  else {
    $module = array('version' => $version);
  }

  // Do the processing.
  $processor->parseItem($module_name, $module);

  // Fetch the report data.
  $data = $processor->getRawData();

  // Output the report.
  $output = $app['twig']->render('project_report.twig', array(
    'project_title' => $data[$module_name]['project'],
    'project_data' => $data[$module_name]['results'],
  ));

  // Post the report on d.o in a new issue of the given project.
//  $issue_client = new DrupalIssueClient(array(
//    'projects' => $processor->getRawData(),
//  ));
//  $output = $issue_client->postIssues();

  return $output;
})
->value('version', FALSE);

$app['debug'] = TRUE;
$app->run();
