<?php

/**
 * @file
 * Contains \Drupal\migrate_ftorregrosa\Plugin\migrate\source\UrlAlias.
 */

namespace Drupal\migrate_ftorregrosa\Plugin\migrate\source;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Drupal\migrate\Row;

/**
 * ftorregrosa url alias source plugin.
 *
 * @MigrateSource(
 *   id = "ftorregrosa_url_alias"
 * )
 */
class UrlAlias extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('url_alias', 'ua')
      ->fields('ua', array(
          'pid',
          'source',
          'alias',
          'language',
        )
      );
    $query->orderBy('pid');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $source = $row->getSourceProperty('source');

    // Parse the source to handle the change of id such ad nid, uid, tid.
    $source_parts = explode('/', $source);
    if ($source_parts[0] == 'node') {
      $nid = $source_parts[1];
      $new_nid = $this->getNewNid($nid);
      $row->setSourceProperty('source', 'node/' . $new_nid);
    }
    elseif ($source_parts[0] == 'taxonomy') {
      $tid = $source_parts[2];
      $new_tid = $this->getNewTid($tid);
      $row->setSourceProperty('source', 'taxonomy/term/' . $new_tid);
    }
    elseif ($source_parts[0] == 'user') {
      $uid = $source_parts[1];
      $new_uid = $this->getNewUid($uid);
      $row->setSourceProperty('source', 'user/' . $new_uid);
    }
//    elseif ($source_parts[0] == 'file') {
//      $fid = $source_parts[1];
//      $new_fid = $this->getNewFid($fid);
//      $row->setSourceProperty('source', 'file/' . $new_fid);
//    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return array(
      'pid'      => $this->t('The numeric identifier of the path alias.'),
      'source'   => $this->t('The internal path.'),
      'alias'    => $this->t('The alias for this path; e.g. title-of-the-story.'),
      'language' => $this->t('The language code of the url alias.'),
    );
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
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['pid']['type'] = 'integer';
    return $ids;
  }

  /**
   * Helper function.
   *
   * Retrieve new tid from previous one.
   *
   * @param $tid
   *   The tid in the source database.
   */
  public function getNewTid($tid) {
    return $this->setUpDatabase(array('key' =>'default', 'target' => 'default'))
      ->select('migrate_map_ftorregrosa_taxonomy_term')
      ->fields('migrate_map_ftorregrosa_taxonomy_term', array('destid1'))
      ->condition('sourceid1', $tid)
      ->execute()
      ->fetchField();
  }

  /**
   * Helper function.
   *
   * Retrieve new uid from previous one.
   *
   * @param $uid
   *   The uid in the source database.
   */
  public function getNewUid($uid) {
    return $this->setUpDatabase(array('key' =>'default', 'target' => 'default'))
      ->select('migrate_map_ftorregrosa_user')
      ->fields('migrate_map_ftorregrosa_user', array('destid1'))
      ->condition('sourceid1', $uid)
      ->execute()
      ->fetchField();
  }

  /**
   * Helper function.
   *
   * Retrieve new nid from previous one.
   *
   * @param $nid
   *   The nid in the source database.
   */
  public function getNewNid($nid) {
    // Get the source nid content type.
    $result = $this->getDatabase()->query('
      SELECT
        n.type
      FROM
        {node} n
      WHERE
        n.nid = :nid
    ', array(':nid' => $nid));

    foreach ($result as $record) {
      $type = $record->type;
    }

    // Get the new nid.
    return $this->setUpDatabase(array('key' =>'default', 'target' => 'default'))
      ->select('migrate_map_ftorregrosa_' . $type)
      ->fields('migrate_map_ftorregrosa_' . $type, array('destid1'))
      ->condition('sourceid1', $nid)
      ->execute()
      ->fetchField();
  }

}
