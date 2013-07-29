<?php
/**
 * Created by PhpStorm.
 * User: Julien
 * Date: 29/07/13
 * Time: 12:09
 */

namespace LdoParser;

class LocalizeParser {
  var $base_url = 'http://ftp.drupal.org/files/translations/7.x/';
  var $language = 'fr';
  var $version = '7.x-1.0';
  var $projects = array();
  var $output = '';

  var $tmp_counter = 0;

  function getOutput() {
    return $this->output;
  }

  function buildProjects() {
    $this->prepareProjectsList();
    // Iterate on the projects.
    $this->downloadPojectsFiles();
  }

  function downloadPojectsFiles() {
    while(list( , $project) = each($this->projects)) {
      if ($this->tmp_counter < 3) {
        $this->buildProjectDetails($project);
      }
      $this->tmp_counter++;
    }
  }

  function buildProjectDetails($project) {
    $clean_project_name = substr($project, 0, -1);
    $translation_file = $this->base_url . $project . $clean_project_name . '-' . $this->version . '.' . $this->language . '.po';

    $headers = get_headers($translation_file);
    // e.g: HTTP/1.1 404 Not Found
    $code = array_slice(explode(' ', array_shift($headers)), 1, -1);

    // Check if the file exists, it means that we expect a 200 code as answer.
    if ($code[0] == '200') {
      $translation_content = file_get_contents($translation_file);
//      $filename = __DIR__ . '/../downloads/' . $clean_project_name . '/' . $clean_project_name . '-' . $this->version . '.' . $this->language . '.po';
      $filename = __DIR__ . '/../downloads/' . $clean_project_name . '-' . $this->version . 'po';
      $f = fopen($filename, 'w+');
      if ($f) {
        fwrite($f, $translation_content);
        fclose($f);

        $this->output .= $translation_file . '<br />';
        $this->output .= '<p>Module name: ' . $project . "<br />\n";
        $this->output .= '</p>';
      }
    }
  }

  function prepareProjectsList() {
    // Get file content.
    $filename = __DIR__ . '/../../snippet.modules.list.html';
    $f = fopen($filename, 'r');
    $contents = fread($f, filesize($filename));
    fclose($f);

    // Convert HTML to a parsable SimpleXML element in order to easily fetch
    // the project links.
    $doc = new \DOMDocument();
    $doc->strictErrorChecking = FALSE;
    $doc->loadHTML($contents);
    $xml = simplexml_import_dom($doc);
    $this->projects = $xml->xpath('body/div[2]/pre/a[position()>5]');
  }
}
