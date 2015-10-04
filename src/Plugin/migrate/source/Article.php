<?php

/**
 * @file
 * Contains \Drupal\migrate_ftorregrosa\Plugin\migrate\source\Article.
 */

namespace Drupal\migrate_ftorregrosa\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * ftorregrosa article node source plugin.
 *
 * @MigrateSource(
 *   id = "ftorregrosa_article"
 * )
 */
class Article extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // This queries the built-in metadata, but not the body, tags, or images.
    $query = $this->select('node', 'n')
      ->condition('n.type', 'article')
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
        GROUP_CONCAT(fdft.field_tags_tid) as tids
      FROM
        {field_data_field_tags} fdft
      WHERE
        fdft.entity_id = :nid
    ', array(':nid' => $nid));
    foreach ($result as $record) {
      if (!is_null($record->tids)) {
        $row->setSourceProperty('tags', explode(',', $record->tids));
      }
    }

    // Images.
    // Site using file_entity:
    // alt text => field_data_field_file_image_alt_text
    // title text => field_data_field_file_image_title_text
    $result = $this->getDatabase()->query('
      SELECT
        fdfi.field_image_fid,
        fdffiat.field_file_image_alt_text_value,
        fdffitt.field_file_image_title_text_value,
        fdfi.field_image_width,
        fdfi.field_image_height
      FROM
        {field_data_field_image} fdfi
      LEFT JOIN {field_data_field_file_image_alt_text} fdffiat
        ON (fdffiat.entity_id = fdfi.field_image_fid)
      LEFT JOIN {field_data_field_file_image_title_text} fdffitt
        ON (fdffitt.entity_id = fdfi.field_image_fid)
      WHERE
        fdfi.entity_id = :nid
    ', array(':nid' => $nid));
    // Create an associative array for each row in the result. The keys
    // here match the last part of the column name in the field table.
    $images = [];
    foreach ($result as $record) {
      // Retrieve the migrated fid from ftorregrosa_file migration.
      $migrated_fid = $this->setUpDatabase(array('key' =>'default', 'target' => 'default'))
        ->select('migrate_map_ftorregrosa_file')
        ->fields('migrate_map_ftorregrosa_file', array('destid1'))
        ->condition('sourceid1', $record->field_image_fid)
        ->execute()
        ->fetchField();

      // Skip file if not migrated yet.
      if (!is_null($migrated_fid)) {
        $images[] = [
          'target_id' => $migrated_fid,
          'alt'       => $record->field_file_image_alt_text_value,
          'title'     => $record->field_file_image_title_text_value,
          'width'     => $record->field_image_width,
          'height'    => $record->field_image_height,
        ];
      }
    }
    $row->setSourceProperty('images', $images);

    // Attachments.
    $result = $this->getDatabase()->query('
      SELECT
        fdfa.field_attachment_fid,
        fdfa.field_attachment_display,
        fdfa.field_attachment_description
      FROM
        {field_data_field_attachment} fdfa
      WHERE
        fdfa.entity_id = :nid
    ', array(':nid' => $nid));
    // Create an associative array for each row in the result. The keys
    // here match the last part of the column name in the field table.
    $attachments = [];
    foreach ($result as $record) {
      // Retrieve the migrated fid from ftorregrosa_file migration.
      $migrated_fid = $this->setUpDatabase(array('key' =>'default', 'target' => 'default'))
        ->select('migrate_map_ftorregrosa_file')
        ->fields('migrate_map_ftorregrosa_file', array('destid1'))
        ->condition('sourceid1', $record->field_attachment_fid)
        ->execute()
        ->fetchField();

      // Skip file if not migrated yet.
      if (!is_null($migrated_fid)) {
        $attachments[] = [
          'target_id'   => $migrated_fid,
          'display'     => $record->field_attachment_display,
          'description' => $record->field_attachment_description,
        ];
      }
    }
    $row->setSourceProperty('attachments', $attachments);

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
