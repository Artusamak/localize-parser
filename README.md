Localize Parser
===============

This project is targeting drupal.org, its purpose is to generate translations
similarity reports. It's supposed to help maintainers ease translators' life by
providing them the list of strings that are similar (or sound similar).

This idea came of a brainstorming between pomliane, saisai and DuaelFR.

How to use?
-----------

This projects is able to do 3 things:

### Analyze projects

Process a set of projects to determine weither or not a project has similar
strings that should be unified.

This feature is parsing a HTML file of the full projects list available in the
7.x branch.

Access `/process/0/5` to test it. 0 is the offset of the project to start with
in the project list. 5 is the number of items to process at a time.

### Generate reports

Generate a module translation similarity report. This is the core of the
project, it will look for similar strings and strings that sounds alike.

Access `/module/<project_name>/<version>` to test it. `<project_name>` is the
name of the project you want to generate the report from. `<version>` (optional)
is the release version of the project.

### Post issue on Drupal.org

Once the translation similarity report has been generated you can tell the
application to post it on drupal.org. (Note: You need to insert your credentials
first). It will automatically post the generated report in the issue queue of
the given project.

Access `/module/post_report/<project_name>/<version>` to test it.
`<project_name>` is the name of the project you want to generate the report
from. `<version>` is the release version of the project.

Contribute
----------

Improvements:
* Regularly scanning drupal projects list to update the projects.
* Test compatibility with dev branches.
* Implement a coder extension.
