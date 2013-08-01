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
    'modules' => $parser->getModules(),
    'offset' => $offset,
    'limit' => $limit,
  ));
  $processor->parseItems();

  // Fetch the report data.
  $data = $processor->getRawData();

  // Output the report.
  $output = $app['twig']->render('projects_overview.twig', array(
    'data' => $data,
    'offset' => $offset,
    'new_offset' => $offset + $limit,
    'limit' => $limit
  ));

  return $output;
});

$app->get('/module/{module_name}/{version}', function($module_name, $version) use ($app) {
  // Collect project data from d.o if we don't know the version we want to
  // process.
  // Otherwise just format the version and project title for processing.
  if (!$version) {
    // Fetch project matching given module name.
    $parser = new LocalizeParser(array(
      'modules' => array($module_name),
    ));
    $module = $parser->buildModule($module_name);
  }
  else {
    $module = array('version' => $version);
  }

  // Instanciate the po files processor.
  $processor = new LocalizeProcessor();
  $processor->parseItem($module_name, $module);

  // Fetch the report data.
  $data = $processor->getRawData();

  // Output the report.
  $output = $app['twig']->render('project_report.twig', array(
    'project_name' => $module_name,
    'project_title' => $data[$module_name]['project'],
    'project_data' => $data[$module_name]['results'],
    'project_count' => $data[$module_name]['count'],
    'project_version' => $data[$module_name]['version'],
  ));

  return $output;
})
->value('version', FALSE);

$app->get('/module/post_report/{module_name}/{version}', function($module_name, $version) use ($app) {
  // Instanciate the po files processor.
  $processor = new LocalizeProcessor();
  $module = array('version' => $version);

  // Do the processing.
  $processor->parseItem($module_name, $module);

  // Fetch the report data.
  $data = $processor->getRawData();

  if ($data[$module_name]['count'] > 0) {
    // Output the report.
    $report = $app['twig']->render('project_report_do.twig', array(
      'project_title' => $data[$module_name]['project'],
      'project_data' => $data[$module_name]['results'],
    ));

    // Post the report on d.o in a new issue of the given project.
    $issue_client = new DrupalIssueClient(array(
      'module_name' => $module_name,
      'version' => $version,
    ));
    $issue_client->postProjectIssue($report);

    return 'Project posted on d.o.';
  }
  else {
    return 'The project does not need to post a report on d.o (no translations errors).';
  }
});

$app['debug'] = TRUE;
$app->run();
