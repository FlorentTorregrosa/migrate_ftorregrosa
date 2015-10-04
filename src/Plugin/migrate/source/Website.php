<?php

/**
 * @file
 * Contains \Drupal\migrate_ftorregrosa\Plugin\migrate\source\Website.
 */

namespace Drupal\migrate_ftorregrosa\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * ftorregrosa website node source plugin.
 *
 * @MigrateSource(
 *   id = "ftorregrosa_website"
 * )
 */
class Website extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // This queries the built-in metadata, but not the body, tags, or images.
    $query = $this->select('node', 'n')
      ->condition('n.type', 'website')
      ->fields('n', array(
        'nid',
        'vid',
        'type',
        'language',
        'title',
        'uid',
        'status',
        'created',
        'changed',
        'promote',
        'sticky',
      ));
    $query->orderBy('nid');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();
    $fields['body_value'] = $this->t('Full text of body');
    $fields['body_summary'] = $this->t('Summary of body');
    $fields['body_format'] = $this->t('Format of body');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $nid = $row->getSourceProperty('nid');

    // Body (compound field with value, summary, and format).
    $result = $this->getDatabase()->query('
      SELECT
        fdb.body_value,
        fdb.body_summary,
        fdb.body_format
      FROM
        {field_data_body} fdb
      WHERE
        fdb.entity_id = :nid
    ', array(':nid' => $nid));
    foreach ($result as $record) {
      $row->setSourceProperty('body_value', $record->body_value);
      $row->setSourceProperty('body_summary', $record->body_summary);
      $row->setSourceProperty('body_format', $record->body_format);
    }

    // Taxonomy term IDs (here we use MySQL's GROUP_CONCAT() function to merge
    // all values into one row.)
    $result = $this->getDatabase()->query('
      SELECT
        GROUP_CONCAT(fdfwt.field_website_type_tid) as tids
      FROM
        {field_data_field_website_type} fdfwt
      WHERE
        fdfwt.entity_id = :nid
    ', array(':nid' => $nid));
    foreach ($result as $record) {
      if (!is_null($record->tids)) {
        $row->setSourceProperty('field_website_type', explode(',', $record->tids));
      }
    }

    // Taxonomy term IDs (here we use MySQL's GROUP_CONCAT() function to merge
    // all values into one row.)
    $result = $this->getDatabase()->query('
      SELECT
        GROUP_CONCAT(fdfwt.field_website_technology_tid) as tids
      FROM
        {field_data_field_website_technology} fdfwt
      WHERE
        fdfwt.entity_id = :nid
    ', array(':nid' => $nid));
    foreach ($result as $record) {
      if (!is_null($record->tids)) {
        $row->setSourceProperty('field_website_technology', explode(',', $record->tids));
      }
    }

    // field_website_link.
    $result = $this->getDatabase()->query('
      SELECT
        fdfwl.field_website_link_url,
        fdfwl.field_website_link_title
      FROM
        {field_data_field_website_link} fdfwl
      WHERE
        fdfwl.entity_id = :nid
    ', array(':nid' => $nid));
    // Create an associative array for each row in the result. The keys
    // here match the last part of the column name in the field table.
    $links = [];
    foreach ($result as $record) {
      $links[] = [
        'uri'   => $record->field_website_link_url,
        'title' => $record->field_website_link_title,
      ];
    }
    $row->setSourceProperty('field_website_link', $links);

    // field_website_dev_date.
    $result = $this->getDatabase()->query('
      SELECT
        fdfwdd.field_website_dev_date_value,
        fdfwdd.field_website_dev_date_value2
      FROM
        {field_data_field_website_dev_date} fdfwdd
      WHERE
        fdfwdd.entity_id = :nid
    ', array(':nid' => $nid));
    // Create an associative array for each row in the result. The keys
    // here match the last part of the column name in the field table.
    // Source: yyyy-MM-dd HH:mm:ss (where HH:mm:ss equals 00:00:00)
    // Target: yyyy-MM-dd
    foreach ($result as $record) {
      $row->setSourceProperty('field_website_dev_date_start', substr($record->field_website_dev_date_value, 0, 10));
      $row->setSourceProperty('field_website_dev_date_end', substr($record->field_website_dev_date_value2, 0, 10));
    }

    // Images.
    $result = $this->getDatabase()->query('
      SELECT
        fdfwi.field_website_image_fid,
        fdfwi.field_website_image_alt,
        fdfwi.field_website_image_title,
        fdfwi.field_website_image_width,
        fdfwi.field_website_image_height
      FROM
        {field_data_field_website_image} fdfwi
      WHERE
        fdfwi.entity_id = :nid
    ', array(':nid' => $nid));
    // Create an associative array for each row in the result. The keys
    // here match the last part of the column name in the field table.
    $images = [];
    foreach ($result as $record) {
      $images[] = [
        'target_id' => $record->field_files_fid,
        'alt'       => $record->field_image_alt,
        'title'     => $record->field_image_title,
        'width'     => $record->field_image_width,
        'height'    => $record->field_image_height,
      ];
    }
    $row->setSourceProperty('field_website_image', $images);

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['nid']['type'] = 'integer';
    $ids['nid']['alias'] = 'n';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function bundleMigrationRequired() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function entityTypeId() {
    return 'node';
  }

  /**
   * Returns the user base fields to be migrated.
   *
   * @return array
   *   Associative array having field name as key and description as value.
   */
  protected function baseFields() {
    $fields = array(
      'nid'      => $this->t('Node ID'),
      'vid'      => $this->t('Version ID'),
      'type'     => $this->t('Type'),
      'title'    => $this->t('Title'),
      'format'   => $this->t('Format'),
      'teaser'   => $this->t('Teaser'),
      'uid'      => $this->t('Authored by (uid)'),
      'created'  => $this->t('Created timestamp'),
      'changed'  => $this->t('Modified timestamp'),
      'status'   => $this->t('Published'),
      'promote'  => $this->t('Promoted to front page'),
      'sticky'   => $this->t('Sticky at top of lists'),
      'language' => $this->t('Language (fr, en, ...)'),
    );
    return $fields;
  }

}
