<?php

namespace LdoParser;

class LocalizeProcessor {

  const MIN_STRING_LENGTH = 4;

  var $projects = array();
  var $offset = 0;
  var $limit = 5;
  var $output = '';

  function __construct($params) {
    foreach ($params as $key => $value) {
      $this->$key = $value;
    }
  }

  public function getRawOutput() {
    return $this->output;
  }

  public function getFormattedOutput() {
    $output = '';
    foreach ($this->output as $project_name => $project_data) {
      if (!empty($project_data['results'])) {
        $output .= '<h2>Translation report</h2>';
        $output .= '<h3>Project <em>' . $this->projects[$project_name]['title'] . '</em>: </h3>';
        $output .= '<div class="project">';
        $output .= "<p>We generated for you a report based on an analyze of the strings in your module.This report is there in order to ease the work of the translation teams, they often bump into strings that are really alike (for instance the same sentence with a period at the end or not) and have to translate them twice!<br />";
        $output .= "We hope that you will use this report to unify those strings.</p>";
        $output .= "<p>Here is a list of strings that we identified to be really close or that sounds really alike.</p>";

        foreach ($project_data['results'] as $key => $similar_set) {
          if (count($similar_set) > 0) {
            foreach ($similar_set as $identical) {
              $output .= '<div class="entry">' . $identical[0] . ' <span class="amp">&</span> ' . $identical[1] . '</div>';
            }
          }
        }
      }
      else {
        $output .= '<p>The project <em>' . $this->projects[$project_name]['title'] . '</em> doesn\'t have similar strings. Perfect for the translators!</p>';
      }
    }
    return $output;
  }

  function parseItems() {
    foreach ($this->projects as $project_name => $project) {
      $strings = $this->parsePoFile($project_name . '-' . $project['version'] . '.po');
      // This might need to be checked, as parsing libraries-7.x-2.1.fr.po returns
      // an array of arrays, with the only main key being an empty string (hence the
      // call to reset() below) - could this be different for other (more
      // complicated) files?
      $strings = reset($strings);
      $similar = $this->compareStrings($strings);

      $this->output[$project_name] = array(
        'project' => $project['title'],
        'results' => $similar,
      );
    }
  }

  /**
   * Copy of l10n_update module's _l10n_update_locale_import_read_po() function.
   *
   * @param string $filename
   * @return array|bool
   * @throws Exception
   */
  public function parsePoFile($filename) {
    $strings = array();

    $filepath = realpath('../downloads/' . $filename);
    $fd = fopen($filepath, "rb"); // File will get closed by PHP on return
    if (!$fd) {
      // @TODO: Should be LocalizeProcessorException.
      throw new \Exception(sprintf('The .po file import failed, because the file "%s" could not be read.', $filename));
    }

    $context = "COMMENT"; // Parser context: COMMENT, MSGID, MSGID_PLURAL, MSGSTR and MSGSTR_ARR
    $current = array();   // Current entry being read
    $plural = 0;          // Current plural form
    $lineno = 0;          // Current line

    while (!feof($fd)) {
      $line = fgets($fd, 10*1024); // A line should not be this long
      if ($lineno == 0) {
        // The first line might come with a UTF-8 BOM, which should be removed.
        $line = str_replace("\xEF\xBB\xBF", '', $line);
      }
      $lineno++;
      $line = trim(strtr($line, array("\\\n" => "")));

      if (!strncmp("#", $line, 1)) { // A comment
        if ($context == "COMMENT") { // Already in comment context: add
          $current["#"][] = substr($line, 1);
        }
        elseif (($context == "MSGSTR") || ($context == "MSGSTR_ARR")) { // End current entry, start a new one
          $strings[isset($current['msgctxt']) ? $current['msgctxt'] : ''][$current['msgid']] = $current['msgstr'];
          $current = array();
          $current["#"][] = substr($line, 1);
          $context = "COMMENT";
        }
        else { // Parse error
          throw new \Exception(sprintf('"The translation file "%s" contains an error: "%s" was expected but not found on line %line.', $filename, $lineno));
          return FALSE;
        }
      }
      elseif (!strncmp("msgid_plural", $line, 12)) {
        if ($context != "MSGID") { // Must be plural form for current entry
          throw new \Exception(sprintf('The translation file "%s" contains an error: "msgid_plural" was expected but not found on line %d.', $filename, $lineno));
          return FALSE;
        }
        $line = trim(substr($line, 12));
        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          throw new \Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
          return FALSE;
        }
        $current["msgid"] = $current["msgid"] . "\0" . $quoted;
        $context = "MSGID_PLURAL";
      }
      elseif (!strncmp("msgid", $line, 5)) {
        if (($context == "MSGSTR") || ($context == "MSGSTR_ARR")) {   // End current entry, start a new one
          $strings[isset($current['msgctxt']) ? $current['msgctxt'] : ''][$current['msgid']] = $current['msgstr'];
          $current = array();
        }
        elseif ($context == "MSGID") { // Already in this context? Parse error
          throw new \Exception(sprintf('The translation file "%s" contains an error: "msgid" is unexpected on line %d.', $filename, $lineno));
          return FALSE;
        }
        $line = trim(substr($line, 5));
        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          throw new \Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
          return FALSE;
        }
        $current["msgid"] = $quoted;
        $context = "MSGID";
      }
      elseif (!strncmp("msgctxt", $line, 7)) {
        if (($context == "MSGSTR") || ($context == "MSGSTR_ARR")) {   // End current entry, start a new one
          $strings[isset($current['msgctxt']) ? $current['msgctxt'] : ''][$current['msgid']] = $current['msgstr'];
          $current = array();
        }
        elseif (!empty($current["msgctxt"])) { // Already in this context? Parse error
          throw new \Exception(sprintf('The translation file "%s" contains an error: "msgctxt" is unexpected on line %d.', $filename, $lineno));
          return FALSE;
        }
        $line = trim(substr($line, 7));
        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          throw new \Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
          return FALSE;
        }
        $current["msgctxt"] = $quoted;
        $context = "MSGCTXT";
      }
      elseif (!strncmp("msgstr[", $line, 7)) {
        if (($context != "MSGID") && ($context != "MSGCTXT") && ($context != "MSGID_PLURAL") && ($context != "MSGSTR_ARR")) { // Must come after msgid, msgxtxt, msgid_plural, or msgstr[]
          throw new \Exception(sprintf('The translation file "%s" contains an error: "msgstr[]" is unexpected on line %d.', $filename, $lineno));
          return FALSE;
        }
        if (strpos($line, "]") === FALSE) {
          throw new \Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
          return FALSE;
        }
        $frombracket = strstr($line, "[");
        $plural = substr($frombracket, 1, strpos($frombracket, "]") - 1);
        $line = trim(strstr($line, " "));
        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          throw new \Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
          return FALSE;
        }
        $current["msgstr"][$plural] = $quoted;
        $context = "MSGSTR_ARR";
      }
      elseif (!strncmp("msgstr", $line, 6)) {
        if (($context != "MSGID") && ($context != "MSGCTXT")) {   // Should come just after a msgid or msgctxt block
          throw new \Exception(sprintf('The translation file "%s" contains an error: "msgstr" is unexpected on line %d.', $filename, $lineno));
          return FALSE;
        }
        $line = trim(substr($line, 6));
        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          throw new \Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
          return FALSE;
        }
        $current["msgstr"] = $quoted;
        $context = "MSGSTR";
      }
      elseif ($line != "") {
        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          throw new \Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
          return FALSE;
        }
        if (($context == "MSGID") || ($context == "MSGID_PLURAL")) {
          $current["msgid"] .= $quoted;
        }
        elseif ($context == "MSGCTXT") {
          $current["msgctxt"] .= $quoted;
        }
        elseif ($context == "MSGSTR") {
          $current["msgstr"] .= $quoted;
        }
        elseif ($context == "MSGSTR_ARR") {
          $current["msgstr"][$plural] .= $quoted;
        }
        else {
          throw new \Exception(sprintf('The translation file "%s" contains an error: there is an unexpected string on line %d.', $filename, $lineno));
          return FALSE;
        }
      }
    }

    // End of PO file, flush last entry.
    if (($context == "MSGSTR") || ($context == "MSGSTR_ARR")) {
      $strings[isset($current['msgctxt']) ? $current['msgctxt'] : ''][$current['msgid']] = $current['msgstr'];
    }
    elseif ($context != "COMMENT") {
      throw new \Exception(sprintf('The translation file "%s" ended unexpectedly at line %d.', $filename, $lineno));
      return FALSE;
    }

    return $strings;
  }

  /**
   * Parses a string in quotes.
   *
   * Copy of l10n_update module's parseQuoted() function.
   *
   * @param $string
   *   A string specified with enclosing quotes.
   *
   * @return
   *   The string parsed from inside the quotes.
   */
  private function parseQuoted($string) {
    if (substr($string, 0, 1) != substr($string, -1, 1)) {
      return FALSE;   // Start and end quotes must be the same
    }
    $quote = substr($string, 0, 1);
    $string = substr($string, 1, -1);
    if ($quote == '"') {        // Double quotes: strip slashes
      return stripcslashes($string);
    }
    elseif ($quote == "'") {    // Simple quote: return as-is
      return $string;
    }
    else {
      return FALSE;             // Unrecognized quote
    }
  }

  /**
   * Compare all strings against each other and return similar ones.
   *
   * @param array $strings
   * @return array
   */
  public function compareStrings($strings) {
    $result = array();

    if (isset($strings[''])) {
      unset($strings['']);
    }

    foreach (array_keys($strings) as $index => $string1) {
      $string1 = trim($string1);
      if (strlen($string1) >= self::MIN_STRING_LENGTH) {
        foreach (array_slice($strings, $index + 1) as $string2 => $translation) {
          $string2 = trim($string2);
          if (strlen($string2) >= self::MIN_STRING_LENGTH) {

            if (strtolower($string1) == strtolower($string2)) {
              // (Almost) identical.
              $result['identical'][] = array($string1, $string2);
              continue;
            }

            if (metaphone($string1) == metaphone($string2)) {
              // Sound identical.
              $result['sound_similar'][] = array($string1, $string2);
              continue;
            }

            similar_text($string1, $string2, $percent);
            if (round($percent) >= 95) {
              $result['look_similar'][] = array($string1, $string2);
              continue;
            }
          }
        }
      }
    }
    return $result;
  }

}
