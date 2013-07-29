<?php

namespace LdoParser;

class LocalizeProcessor {

  public function parse_po_file($filename) {

    define('DRUPAL_ROOT', realpath('../../../drupal7'));
    require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

    require_once __DIR__ . '/../../web/l10n_update/l10n_update.locale.inc';
    require_once DRUPAL_ROOT . '/includes/locale.inc';

    $file = (object) array('uri' => $filename);
    _l10n_update_locale_import_read_po('mem-store', $file);

    $strings = &drupal_static('_l10n_update_locale_import_one_string:strings', array());
    return $strings;
  }

  public function compare_strings($strings) {
    $result = array();

    if (isset($strings[''])) {
      unset($strings['']);
    }
    var_dump($strings);

    foreach (array_keys($strings) as $index => $string1) {
      $string1 = trim($string1);
      print '$string1 : '; var_dump($string1);
      foreach (array_slice($strings, $index + 1) as $string2 => $translation) {
        $string2 = trim($string2);
        print '$string2 : '; var_dump($string2);

//      var_dump(levenshtein($string1, $string2));

        if (strtolower($string1) == strtolower($string2)) {
          // (Almost) identical.
          $result['identical'][] = array($string1, $string2);
//        continue;
        }

        if (metaphone($string1) == metaphone($string2)) {
          // Sound identical.
          $result['sound_same'][] = array($string1, $string2);
//        continue;
        }
        var_dump(metaphone($string1) . ' / ' . metaphone($string2));

        similar_text($string1, $string2, $percent);
        if (round($percent) >= 95) {
          $result['similar'][] = array($string1, $string2);
//        continue;
        }
        var_dump(round($percent) . '%');
      }
    }
    return $result;
  }

}
