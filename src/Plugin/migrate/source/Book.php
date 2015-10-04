<?php

/**
 * @file
 * Contains \Drupal\migrate_ftorregrosa\Plugin\migrate\source\Book.
 */

namespace Drupal\migrate_ftorregrosa\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * ftorregrosa book node source plugin.
 *
 * @MigrateSource(
 *   id = "ftorregrosa_book"
 * )
 */
class Book extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // This queries the built-in metadata, but not the body, tags, or images.
    $query = $this->select('book', 'b')
      ->fields('b', array('nid', 'bid'));
    $query->join('menu_links', 'ml', 'b.mlid = ml.mlid');
    $query->join('node', 'n', 'b.nid = n.nid');
    $ml_fields = array('mlid', 'plid', 'weight', 'has_children', 'depth');
    for ($i = 1; $i <= 9; $i++) {
      $field = "p$i";
      $ml_fields[] = $field;
      $query->orderBy($field);
    }
    $query->fields('ml', $ml_fields);
    $query->fields('n', array(
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
    $query->condition('n.type', 'book');
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
    $plid = $row->getSourceProperty('plid');

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

    // Parent nid.
    $pid = 0;
    // If node is the book root. plid = 0 and migrated_pid must be 0.
    if ($plid != 0) {
      $result = $this->getDatabase()->query('
        SELECT
          b.nid as pid
        FROM
          {menu_links} ml
        LEFT JOIN {book} b
          ON (b.mlid = ml.plid)
        WHERE
          ml.plid = :plid
          AND
          ml.plid != 0
      ', array(':plid' => $plid));
      foreach ($result as $record) {
        if (!is_null($record->pid)) {
          $pid = $record->pid;
        }
      }
    }
    $row->setSourceProperty('pid', $pid);

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

      'bid' => $this->t('Book ID'),
      'mlid' => $this->t('Menu link ID'),
      'plid' => $this->t('Parent link ID'),
      'weight' => $this->t('Weight'),
      'p1' => $this->t('The first mlid in the materialized path.'),
      'p2' => $this->t('The second mlid in the materialized path.'),
      'p3' => $this->t('The third mlid in the materialized path.'),
      'p4' => $this->t('The fourth mlid in the materialized path.'),
      'p5' => $this->t('The fifth mlid in the materialized path.'),
      'p6' => $this->t('The sixth mlid in the materialized path.'),
      'p7' => $this->t('The seventh mlid in the materialized path.'),
      'p8' => $this->t('The eight mlid in the materialized path.'),
      'p9' => $this->t('The nine mlid in the materialized path.'),
    );
    return $fields;
  }

}
