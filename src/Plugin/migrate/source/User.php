<?php
/**
 * @file
 * Contains \Drupal\migrate_ftorregrosa\Plugin\migrate\source\User.
 */

namespace Drupal\migrate_ftorregrosa\Plugin\migrate\source;

use Drupal\migrate\Plugin\SourceEntityInterface;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract users from ftorregrosa database.
 *
 * @MigrateSource(
 *   id = "ftorregrosa_user"
 * )
 */
class User extends DrupalSqlBase implements SourceEntityInterface {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('users', 'u')
      ->fields('u', array_keys($this->baseFields()))
      ->condition('uid', 0, '>');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return array(
      'uid' => array(
        'type'  => 'integer',
        'alias' => 'u',
      ),
    );
  }

  /**
   * Returns the user base fields to be migrated.
   *
   * @return array
   *   Associative array having field name as key and description as value.
   */
  protected function baseFields() {
    $fields = array(
      'uid'              => $this->t('User ID'),
      'name'             => $this->t('Username'),
      'pass'             => $this->t('Password'),
      'mail'             => $this->t('Email address'),
      'signature'        => $this->t('Signature'),
      'signature_format' => $this->t('Signature format'),
      'created'          => $this->t('Registered timestamp'),
      'access'           => $this->t('Last access timestamp'),
      'login'            => $this->t('Last login timestamp'),
      'status'           => $this->t('Status'),
      'timezone'         => $this->t('Timezone'),
      'language'         => $this->t('Language'),
      'picture'          => $this->t('Picture'),
      'init'             => $this->t('Init'),
    );
    return $fields;

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
    return 'user';
  }

}
