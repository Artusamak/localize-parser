<?php

namespace LdoDrupal;

class DrupalIssueClient {

  function __construct($params) {
    foreach ($params as $key => $value) {
      $this->$key = $value;
    }
  }

}
