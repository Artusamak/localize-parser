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
  var $update_url = 'http://updates.drupal.org/release-history/';
  var $language = 'fr';
  var $major_version = '7.x';
  var $version = '7.x-1.0';
  var $projects = array();
  var $output = '';
  var $offset;
  var $limit;

  protected $modules;

  function __construct($params) {
    foreach ($params as $key => $value) {
      $this->$key = $value;
    }
  }

  /**
   * Getter to fetch the generated output.
   */
  function getOutput() {
    return $this->output;
  }

  /**
   * Getter to fetch the generated output.
   */
  function getProjects() {
    return $this->projects;
  }

  function buildProject($project) {
    $this->buildProjectDetails($project);
    return array($project => $this->projects[$project]);
  }

  /**
   * Builds the projects list and download the projects po files.
   */
  function buildProjects() {
    // Parse the html dump of the ftp page listing the projects.
    // @see dump at $base_url.
    $this->prepareProjectsList();

    // Download the projects translations.
    $this->downloadProjectsFiles();
  }

  /**
   * Download the projects translations.
   *
   * Given a set of projects, fetch the appropriate .po files.
   */
  function downloadProjectsFiles() {
    while(list( , $project) = each($this->projects_raw)) {
      $clean_project_name = substr($project, 0, -1);
      // If only specific module(s) were requested for processing,
      // skip everything else.
      if (!empty($this->modules) && !in_array($clean_project_name, $this->modules)) {
        continue;
      }
      $this->buildProjectDetails($clean_project_name);
    }
  }

  /**
   * Builds the report of a module.
   *
   * Stores the report output of a given module.
   *
   * @param $project
   *   Project machine name.
   */
  function buildProjectDetails($project) {
    // Prepare the download url of a project.
    $metadata = $this->fetchProjectMetadata($project);

    $this->projects[$project]['version'] = $metadata['version'];
    $this->projects[$project]['title'] = $metadata['title'];

    $translation_file = $this->downloadTranslationFile($project, $metadata['version']);


    return $translation_file;
  }

  /**
   * Fetchs the latest version number of a given project.
   *
   * @param $project_name
   *   Machine name of a project.
   *
   * @return mixed
   *   Returns the string of the project's version.
   */
  function fetchProjectMetadata($project_name) {
    $update_url = $this->update_url . $project_name . '/' . $this->major_version;
    $xml = simplexml_load_file($update_url);

    $version = (string) $xml->releases->release[0]->version;
    $title = (string) $xml->title;

    return array(
      'version' => $version,
      'title' => $title,
    );
  }

  /**
   * Parses the html file listing projects.
   *
   * Stores an array of the projects.
   */
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

    // Add the offset to avoid unwanted links.
    $xpath_interval_bottom = $this->interval_bottom + 5;
    $xpath_interval_top = $this->interval_top + 5;
    $xpath_query = 'body/div[2]/pre/a[position()>' . $xpath_interval_bottom . ' and position()<=' . $xpath_interval_top . ']';

    $this->projects_raw = $xml->xpath($xpath_query);
  }

  /**
   * @param $project
   * @param $version
   * @return string
   */
  public function downloadTranslationFile($project, $version) {
    // Prepare the transaction URL.
    // Extracts the return code from headers to check if the file exists.
    // It can be 200 or 404 e.g: HTTP/1.1 404 Not Found
    $translation_url = $this->base_url . $project . '/' . $project . '-' . $version . '.' . $this->language . '.po';
    $headers = get_headers($translation_url);
    $code = array_slice(explode(' ', array_shift($headers)), 1, -1);

    // Only look for 200 code as answer.
    if ($code[0] == '200') {
      // Download the file in our downloads folder.
      // @TODO decide what to do of processed files.
      $translation_content = file_get_contents($translation_url);
      $translation_file = __DIR__ . '/../../downloads/' . $project . '-' . $version . '.po';
      $f = fopen($translation_file, 'w+');
      if ($f) {
        fwrite($f, $translation_content);
        fclose($f);
        return $translation_file;
      }
      return $translation_file;
    }
    return FALSE;
  }
}
