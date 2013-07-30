<?php

namespace LdoDrupal;

class DrupalIssueClient {

  protected $projects;

  function __construct($params) {
    foreach ($params as $key => $value) {
      $this->$key = $value;
    }
  }

}
