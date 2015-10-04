<?php

/**
 * @file
 * Contains \Drupal\migrate_ftorregrosa\Plugin\migrate\source\Comment.
 */

namespace Drupal\migrate_ftorregrosa\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * ftorregrosa comment source plugin.
 *
 * @MigrateSource(
 *   id = "ftorregrosa_comment",
 *   source_provider = "comment"
 * )
 */
class Comment extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('comment', 'c')
      ->fields('c', array(
          'cid',
          'pid',
          'nid',
          'uid',
          'subject',
          'hostname',
          'created',
          'changed',
          'status',
          'thread',
          'name',
          'mail',
          'homepage',
        )
      );
    $query->innerJoin('node', 'n', 'c.nid = n.nid');
    $query->fields('n', array('type'));
    $query->orderBy('c.created');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $cid = $row->getSourceProperty('cid');
    $previous_nid = $row->getSourceProperty('nid');
    $type = $row->getSourceProperty('type');

    // Get the new nid.
    $new_nid = $this->setUpDatabase(array('key' =>'default', 'target' => 'default'))
      ->select('migrate_map_ftorregrosa_' . $type)
      ->fields('migrate_map_ftorregrosa_' . $type, array('destid1'))
      ->condition('sourceid1', $previous_nid)
      ->execute()
      ->fetchField();

    if (!is_null($new_nid)) {
      $row->setSourceProperty('nid', $new_nid);
    }
    else {
      // The node has not been migrated.
      return FALSE;
    }

    // Body (compound field with value, summary, and format).
    $result = $this->getDatabase()->query('
      SELECT
        fdcb.comment_body_value,
        fdcb.comment_body_format
      FROM
        {field_data_comment_body} fdcb
      WHERE
        fdcb.entity_id = :cid
    ', array(':cid' => $cid));
    foreach ($result as $record) {
      $row->setSourceProperty('body_value', $record->comment_body_value);
      $row->setSourceProperty('body_format', $record->comment_body_format);
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return array(
      'cid' => $this->t('Comment ID.'),
      'pid' => $this->t('Parent comment ID. If set to 0, this comment is not a reply to an existing comment.'),
      'nid' => $this->t('The {node}.nid to which this comment is a reply.'),
      'uid' => $this->t('The {users}.uid who authored the comment. If set to 0, this comment was created by an anonymous user.'),
      'subject' => $this->t('The comment title.'),
      'hostname' => $this->t("The author's host name."),
      'created' => $this->t('The time that the comment was created as a Unix timestamp.'),
      'changed' => $this->t('The time that the comment was last updated as a Unix timestamp.'),
      'status' => $this->t('The published status of a comment.'),
      'body_value' => $this->t('The comment body.'),
      'body_format' => $this->t('The {filter_formats}.format of the comment body.'),
      'thread' => $this->t("The vancode representation of the comment's place in a thread."),
      'name' => $this->t("The comment author's name. Uses {users}.name if the user is logged in, otherwise uses the value typed into the comment form."),
      'mail' => $this->t("The comment author's email address from the comment form, if user is anonymous, and the 'Anonymous users may/must leave their contact information' setting is turned on."),
      'homepage' => $this->t("The comment author's home page address from the comment form, if user is anonymous, and the 'Anonymous users may/must leave their contact information' setting is turned on."),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['cid']['type'] = 'integer';
    return $ids;
  }

}
