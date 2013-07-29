<?php

namespace LdoParser;

class LocalizeProcessor {

  /**
   * Copy of l10n_update module's _l10n_update_locale_import_read_po() function.
   *
   * @param string $filename
   * @return array|bool
   * @throws Exception
   */
  public function parse_po_file($filename) {
    $strings = array();

    $filepath = realpath('../downloads/' . $filename);

    $fd = fopen($filepath, "rb"); // File will get closed by PHP on return
    if (!$fd) {
      // @TODO: Should be LocalizeProcessorException.
      throw new Exception(sprintf('The .po file import failed, because the file "%s" could not be read.', $filename));
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
          throw new Exception(sprintf('"The translation file "%s" contains an error: "%s" was expected but not found on line %line.', $filename, $lineno));
          return FALSE;
        }
      }
      elseif (!strncmp("msgid_plural", $line, 12)) {
        if ($context != "MSGID") { // Must be plural form for current entry
          throw new Exception(sprintf('The translation file "%s" contains an error: "msgid_plural" was expected but not found on line %d.', $filename, $lineno));
          return FALSE;
        }
        $line = trim(substr($line, 12));
        $quoted = $this->parse_quoted($line);
        if ($quoted === FALSE) {
          throw new Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
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
          throw new Exception(sprintf('The translation file "%s" contains an error: "msgid" is unexpected on line %d.', $filename, $lineno));
          return FALSE;
        }
        $line = trim(substr($line, 5));
        $quoted = $this->parse_quoted($line);
        if ($quoted === FALSE) {
          throw new Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
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
          throw new Exception(sprintf('The translation file "%s" contains an error: "msgctxt" is unexpected on line %d.', $filename, $lineno));
          return FALSE;
        }
        $line = trim(substr($line, 7));
        $quoted = $this->parse_quoted($line);
        if ($quoted === FALSE) {
          throw new Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
          return FALSE;
        }
        $current["msgctxt"] = $quoted;
        $context = "MSGCTXT";
      }
      elseif (!strncmp("msgstr[", $line, 7)) {
        if (($context != "MSGID") && ($context != "MSGCTXT") && ($context != "MSGID_PLURAL") && ($context != "MSGSTR_ARR")) { // Must come after msgid, msgxtxt, msgid_plural, or msgstr[]
          throw new Exception(sprintf('The translation file "%s" contains an error: "msgstr[]" is unexpected on line %d.', $filename, $lineno));
          return FALSE;
        }
        if (strpos($line, "]") === FALSE) {
          throw new Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
          return FALSE;
        }
        $frombracket = strstr($line, "[");
        $plural = substr($frombracket, 1, strpos($frombracket, "]") - 1);
        $line = trim(strstr($line, " "));
        $quoted = $this->parse_quoted($line);
        if ($quoted === FALSE) {
          throw new Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
          return FALSE;
        }
        $current["msgstr"][$plural] = $quoted;
        $context = "MSGSTR_ARR";
      }
      elseif (!strncmp("msgstr", $line, 6)) {
        if (($context != "MSGID") && ($context != "MSGCTXT")) {   // Should come just after a msgid or msgctxt block
          throw new Exception(sprintf('The translation file "%s" contains an error: "msgstr" is unexpected on line %d.', $filename, $lineno));
          return FALSE;
        }
        $line = trim(substr($line, 6));
        $quoted = $this->parse_quoted($line);
        if ($quoted === FALSE) {
          throw new Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
          return FALSE;
        }
        $current["msgstr"] = $quoted;
        $context = "MSGSTR";
      }
      elseif ($line != "") {
        $quoted = $this->parse_quoted($line);
        if ($quoted === FALSE) {
          throw new Exception(sprintf('The translation file "%s" contains a syntax error on line %d.', $filename, $lineno));
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
          throw new Exception(sprintf('The translation file "%s" contains an error: there is an unexpected string on line %d.', $filename, $lineno));
          return FALSE;
        }
      }
    }

    // End of PO file, flush last entry.
    if (($context == "MSGSTR") || ($context == "MSGSTR_ARR")) {
      $strings[isset($current['msgctxt']) ? $current['msgctxt'] : ''][$current['msgid']] = $current['msgstr'];
    }
    elseif ($context != "COMMENT") {
      throw new Exception(sprintf('The translation file "%s" ended unexpectedly at line %d.', $filename, $lineno));
      return FALSE;
    }

    return $strings;
  }

  /**
   * Parses a string in quotes.
   *
   * Copy of l10n_update module's parse_quoted() function.
   *
   * @param $string
   *   A string specified with enclosing quotes.
   *
   * @return
   *   The string parsed from inside the quotes.
   */
  private function parse_quoted($string) {
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
  public function compare_strings($strings) {
    $result = array();

    if (isset($strings[''])) {
      unset($strings['']);
    }

    foreach (array_keys($strings) as $index => $string1) {
      $string1 = trim($string1);
      foreach (array_slice($strings, $index + 1) as $string2 => $translation) {
        $string2 = trim($string2);

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
        if (round($percent) >= 90) {
          $result['look_similar'][] = array($string1, $string2);
          continue;
        }
      }
    }
    return $result;
  }

}
