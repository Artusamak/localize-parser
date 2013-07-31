<?php

namespace LdoParser;

class LocalizeParser {
  private $base_url = 'http://ftp.drupal.org/files/translations/7.x/';
  private $update_url = 'http://updates.drupal.org/release-history/';
  private $language = 'fr';
  private $major_version = '7.x';
  private $version = '7.x-1.0';
  private $modules = array();
  private $offset;
  private $limit;
  private $modules_raw;

  /**
   * Lazy contructor to initialize arguments as attributes.
   */
  function __construct($params) {
    foreach ($params as $key => $value) {
      $this->$key = $value;
    }
  }

  /**
   * Getter to fetch the generated output.
   *
   * @return array()
   *   Returns an array of the modules metadata, keys are modules
   *   machine names and values are arrays of version and project title.
   *   E.g:
   *   views
   *     version => 7.x-3.1
   *     title => Views
   *   feeds
   *     version => 7.x-2.1
   *     title => Feeds
   *   ...
   */
  function getModules() {
    return $this->modules;
  }

  /**
   * Retrieves data of a project and download the po file associated.
   *
   * @param $module_name
   *   Machine name of the module.
   * @return array()
   *   Returns the module information.
   */
  function buildModule($module_name) {
    $this->buildModuleDetails($module_name);
    return $this->modules[$module_name];
  }

  /**
   * Builds the modules list and download the modules po files.
   */
  function buildModules() {
    // Parse the html dump of the ftp page listing the modules.
    // @see dump at $base_url.
    $this->prepareModulesList();

    // Download the modules translations.
    $this->prepareModulesFiles();
  }

  /**
   * Download the modules translations.
   *
   * Given a set of modules, fetch the appropriate .po files.
   */
  function prepareModulesFiles() {
    while(list( , $module) = each($this->modules_raw)) {
      $clean_module_name = substr($module, 0, -1);
      $this->buildModuleDetails($clean_module_name);
    }
  }

  /**
   * Builds the report of a module.
   *
   * Stores the report output of a given module.
   *
   * @param $module
   *   Module machine name.
   */
  function buildModuleDetails($module) {
    // Prepare the download url of a module.
    $metadata = $this->fetchProjectMetadata($module);

    $this->modules[$module]['version'] = $metadata['version'];
    $this->modules[$module]['title'] = $metadata['title'] . '('. $metadata['version'] .')';

    // Download the translation file.
    $this->downloadTranslationFile($module, $metadata['version']);
  }

  /**
   * Fetches the latest version number of a given project.
   *
   * @param $module_name
   *   Machine name of a project.
   *
   * @return array()
   *   Returns an array with two keys: version of the module and module title.
   */
  function fetchProjectMetadata($module_name) {
    $update_url = $this->update_url . $module_name . '/' . $this->major_version;
    $xml = simplexml_load_file($update_url);

    if ($xml) {
      $version = (string) $xml->releases->release[0]->version;
      $title = (string) $xml->title;

      return array(
        'version' => $version,
        'title' => $title,
      );
    }
    else {
      throw new \Exception(sprintf('Error while trying to retrieve module "%s" information from drupal.org.', $module_name));
    }
  }

  /**
   * Parses the html file listing modules.
   *
   * Stores an array of the modules.
   */
  function prepareModulesList() {
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

    $this->modules_raw = $xml->xpath($xpath_query);
  }

  /**
   * Downloads the po file of a given module in a specific version.
   *
   * @param $module
   *   Machine name of the module.
   * @param $version
   *   Version of the module (e.g: 7.x-2.1).
   */
  public function downloadTranslationFile($module, $version) {
    // Prepare the transaction URL.
    // Extracts the return code from headers to check if the file exists.
    // It can be 200 or 404 e.g: HTTP/1.1 404 Not Found
    $translation_url = $this->base_url . $module . '/' . $module . '-' . $version . '.' . $this->language . '.po';
    $headers = get_headers($translation_url);
    $code = array_slice(explode(' ', array_shift($headers)), 1, -1);

    // Only look for 200 code as answer.
    if ($code[0] == '200') {
      // Download the file in our downloads folder.
      // @TODO decide what to do of processed files.
      $translation_content = file_get_contents($translation_url);
      $translation_file = __DIR__ . '/../../downloads/' . $module . '-' . $version . '.po';
      $f = fopen($translation_file, 'w+');
      if ($f) {
        fwrite($f, $translation_content);
        fclose($f);
      }
      else {
        throw new \Exception(sprintf('Error while trying to access the file "%s".', $translation_file));
      }
    }
    else {
      throw new \Exception(sprintf('It looks like nobody is at the url: "%s" (returned code "%d").', $translation_url, $code[0]));
    }
  }
}
