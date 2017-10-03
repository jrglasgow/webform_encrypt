<?php

namespace Drupal\webform_encrypt;

use Drupal\Core\Entity\EntityInterface;
use Drupal\webform\WebformSubmissionStorage;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Alter webform submission storage definitions.
 */
class WebformEncryptSubmissionStorage extends WebformSubmissionStorage {

  /**
   * Decrypts a string.
   *
   * @param string $string
   *   The string to be decrypted.
   * @param string $encryption_profile
   *   The encryption profile to be used to decrypt the string.
   * @param bool $check_permissions
   *   Flag that controls permissions check.
   *
   * @return string
   *   The decrypted value.
   */
  protected function decrypt($string, $encryption_profile, $check_permissions = TRUE) {
    if ($check_permissions && !\Drupal::currentUser()->hasPermission('view encrypted values')) {
      return '[Value Encrypted]';
    }

    $decrypted_value = \Drupal::service('encryption')->decrypt($string, $encryption_profile);
    if ($decrypted_value === FALSE) {
      return $string;
    }

    return $decrypted_value;
  }

  /**
   * Returns the Webform Submission data decrypted.
   *
   * @param \Drupal\webform\Entity\WebformSubmission $webform_submission
   *   The Webform Submission entity object.
   * @param bool $check_permissions
   *   Flag that controls permissions check.
   *
   * @return array
   *   An array containing the Webform Submission decrypted data.
   */
  protected function getDecryptedData(WebformSubmission $webform_submission, $check_permissions = TRUE) {
    $config = \Drupal::service('config.factory')->get('webform.encrypt')->get('element.settings');
    $webform = $webform_submission->getWebform();
    $elements = $webform->getElementsInitializedFlattenedAndHasValue();
    $data = $webform_submission->getData();

    foreach ($elements as $element_name => $element) {
      // Skip elements that have no data.
      if (!isset($data[$element_name])) {
        continue;
      }

      // Skip elements that are not encrypted.
      if (empty($config[$element_name]['encrypt'])) {
        continue;
      }

      $encryption_profile = EncryptionProfile::load($config[$element_name]['encrypt_profile']);

      // Checks whether is an element with multiple values.
      if (is_array($data[$element_name])) {
        foreach ($data[$element_name] as $element_value_key => $element_value) {
          $data[$element_name][$element_value_key] = $this->decrypt($element_value, $encryption_profile, $check_permissions);
        }

        continue;
      }

      // Single element value.
      $data[$element_name] = $this->decrypt($data[$element_name], $encryption_profile, $check_permissions);
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function doPostSave(EntityInterface $entity, $update) {
    $data = $this->getDecryptedData($entity, FALSE);
    $entity->setData($data);

    /** @var \Drupal\webform\WebformSubmissionInterface $entity */
    parent::doPostSave($entity, $update);
  }

  /**
   * {@inheritdoc}
   */
  protected function loadData(array &$webform_submissions) {
    parent::loadData($webform_submissions);

    foreach ($webform_submissions as &$webform_submission) {
      $data = $this->getDecryptedData($webform_submission);
      $webform_submission->setData($data);
      $webform_submission->setOriginalData($data);
    }
  }

}
