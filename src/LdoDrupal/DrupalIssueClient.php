<?php

namespace LdoDrupal;

class DrupalIssueClient {

  private $module_name = '';
  private $version = '';

  function __construct($params) {
    foreach ($params as $key => $value) {
      $this->$key = $value;
    }
  }

  function postProjectIssue($output) {
    $ch = curl_init();
    $this->botConnect($ch);
    $this->postIssue($ch, $output);
    curl_close($ch);
  }

  function postIssue(&$ch, $output) {
    // Fetch empty new issue form to find form token.
    $new_issue_url = 'https://drupal.org/node/add/project-issue/';

    // @TODO: Remove this, uncomment one below. For the moment it uses
    // temporary sandbox project: https://drupal.org/project/issues/2053567
    curl_setopt($ch, CURLOPT_URL, $new_issue_url . $this->module_name);
    $result = curl_exec($ch);

    libxml_use_internal_errors(true);
    $doc = new \DOMDocument();
    $doc->strictErrorChecking = FALSE;
    $doc->loadHTML($result);
    $form = $doc->getElementById('node-form');
    $xml = simplexml_import_dom($form);

    $xpath_query = '//form[@id="node-form"]//input[@name="form_build_id"]/@value';
    $value = $xml->xpath($xpath_query);
    $form_build_id = (string) reset($value);

    $xpath_query = '//form[@id="node-form"]//input[@name="form_token"]/@value';
    $value = $xml->xpath($xpath_query);
    $form_token = (string) reset($value);

    // Prepare form "Component" value.
    $xpath_query = '//form[@id="node-form"]//select[@name="component"]/option[@value != "0"]/@value';
    $options = $xml->xpath($xpath_query);

    // Try to use one of the following components (in provided order).
    $best_matches = array('User interface', 'Code', 'Miscellaneous');
    foreach ($best_matches as $best_match) {
      if (in_array($best_match, $options)) {
        $component = $best_match;
        break;
      }
    }
    // Otherwise just use the first available component value.
    if (empty($component)) {
      $component = (string) array_shift($options);
    }

    // Prepare form "Category" value.
    $xpath_query = '//form[@id="node-form"]//select[@name="category"]/option[@value != "0"]/@value';
    $options = $xml->xpath($xpath_query);

    // Try to use one of the following categories (in provided order).
    $best_matches = array('task', 'bug');
    foreach ($best_matches as $best_match) {
      if (in_array($best_match, $options)) {
        $category = $best_match;
        break;
      }
    }
    // Otherwise just use the first available category value.
    if (empty($category)) {
      $category = (string) array_shift($options);
    }

    // Prepare form "Version" value.
    $xpath_query = '//form[@id="node-form"]//select[@name="rid"]/option[@value != "0"]/@value';
    $options = $xml->xpath($xpath_query);

    // Try to match the version of the parsed module.
    $best_matches = array($this->version);
    foreach ($best_matches as $best_match) {
      if (in_array($best_match, $options)) {
        $version = $best_match;
        break;
      }
    }
    // Otherwise just use the first available category value.
    if (empty($version)) {
      $version = (string) array_shift($options);
    }
    
    // POST a new issue.
    $new_issue_url = 'https://drupal.org/node/add/project-issue/';
    curl_setopt($ch, CURLOPT_URL, $new_issue_url . $this->module_name);
    $post_data = array(
      'component' => $component,
      'category' => $category,
      'rid' => $version,
      'title' => '[Bot] Translation similarity report',
      'body' => $output,
      'form_id' => 'project_issue_node_form',
      'form_token' => $form_token,
      'form_build_id' => $form_build_id,
      'op' => 'Save',
    );
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    $result = curl_exec($ch);
    $headers = curl_getinfo($ch);
    /* HTML assertion to make.
     <div class="messages messages-status clear-block">Issue <em>Test</em> has been created.</div>
     */
    if ($headers['url'] == $new_issue_url) {
      throw new \Exception(sprintf('Unable to post new issue to Drupal.org for project "%s".', $this->module_name));
    }
  }

  /**
   * @return array
   * @throws \Exception
   */
  public function botConnect(&$ch) {
    // Delete cookie file if it exists, otherwise login will fail.
    $cookie_file = '/tmp/cookie.txt';
    if (file_exists($cookie_file)) {
      unlink($cookie_file);
    }

    // Generic cURL config.
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'LdoParser');
    curl_setopt($ch, CURLOPT_POST, 1);

    // Log in to drupal.org.
    $login_url = 'https://drupal.org/user/login';
    curl_setopt($ch, CURLOPT_URL, $login_url);
    $post_data = array(
      // @TODO: Provide d.o. user details here.
      // Or even better: add external yaml config file. :P
      // https://github.com/igorw/ConfigServiceProvider ?
      'name' => '',
      'pass' => '',
      'form_id' => 'user_login',
      'op' => 'Log in',
    );
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    $result = curl_exec($ch);
    $headers = curl_getinfo($ch);
    if ($headers['url'] == $login_url) {
      throw new \Exception('Unable to log in to Drupal.org.');
    }
  }
}
