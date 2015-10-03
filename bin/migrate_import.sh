#!/usr/bin/env bash

drush migrate-import ftorregrosa_user
# Files must be copied from Drupal 7 to the Drupal 8 files directory.
drush migrate-import ftorregrosa_file
drush migrate-import ftorregrosa_taxonomy_vocabulary
drush migrate-import ftorregrosa_taxonomy_term
drush migrate-import ftorregrosa_article
drush migrate-import ftorregrosa_page
drush migrate-import ftorregrosa_book
# The menu link in the book table can't be update (Integrity constraint
# violation). So we delete its and then we remake them with the import in update
# mode.
drush sqlq "TRUNCATE book;"
drush migrate-import --update ftorregrosa_book
drush migrate-import ftorregrosa_website
drush migrate-import ftorregrosa_comment
drush migrate-import ftorregrosa_url_alias
