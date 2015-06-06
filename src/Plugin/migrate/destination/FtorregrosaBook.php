<?php

/**
 * @file
 * Contains \Drupal\migrate_ftorregrosa\Plugin\migrate\destination\FtorregrosaBook.
 */

namespace Drupal\migrate_ftorregrosa\Plugin\migrate\destination;

//use Drupal\migrate\Plugin\migrate\destination\Book;
use Drupal\Core\Entity\EntityInterface;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Row;

/**
 * @MigrateDestination(
 *   id = "ftorregrosa_book",
 *   provider = "book"
 * )
 */
//class FtorregrosaBook extends Book {
class FtorregrosaBook extends EntityContentBase {
  /**
   * {@inheritdoc}
   */
  protected static function getEntityTypeId($plugin_id) {
    return 'node';
  }

  /**
   * {@inheritdoc}
   */
  protected function updateEntity(EntityInterface $entity, Row $row) {
    $entity->book = $row->getDestinationProperty('book');
  }

  /**
   * {@inheritdoc}
   */
  public function postImport() {
    print 'toto';
//    die();
  }

}
