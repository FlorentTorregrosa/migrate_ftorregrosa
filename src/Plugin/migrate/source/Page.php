<?php

/**
 * @file
 * Contains \Drupal\migrate_ftorregrosa\Plugin\migrate\source\Page.
 */

namespace Drupal\migrate_ftorregrosa\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * ftorregrosa page node source plugin.
 *
 * @MigrateSource(
 *   id = "ftorregrosa_page"
 * )
 */
class Page extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // This queries the built-in metadata, but not the body, tags, or images.
    $query = $this->select('node', 'n')
      ->condition('n.type', 'page')
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
