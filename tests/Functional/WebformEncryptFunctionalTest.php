<?php

namespace Drupal\Tests\webform_encrypt\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * Functional tests for the webform_encrypt module.
 *
 * @group webform_encrypt
 */
class WebformEncryptFunctionalTest extends BrowserTestBase {

  /**
   * The admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'webform_encrypt',
    'webform_encrypt_test',
    'webform_ui',
  ];

  /**
   * Don't validate config schema https://www.drupal.org/node/2901950.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'access administration pages',
    'view any webform submission',
    'edit any webform',
  ];

  /**
   * Sets the test up.
   */
  protected function setUp() {
    parent::setUp();
    // Test admin user.
    $this->adminUser = $this->drupalCreateUser($this->permissions);
  }

  /**
   * Test webform field encryption.
   */
  public function testFieldEncryption() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalLogin($this->adminUser);
    $encrypted_value = '[Value Encrypted]';

    // Test admin functionality.
    $this->drupalGet('admin/structure/webform/manage/test_encryption');
    // Add a new element and set encryption on it.
    $page->clickLink('Add element');
    $page->clickLink('Date');
    $edit = [
      'key' => 'test_date',
      'title' => 'Test date',
      'encrypt' => 1,
      'encrypt_profile' => 'test_encryption_profile',
    ];
    $this->submitForm($edit, 'Save');
    $assert_session->responseContains('<em class="placeholder">Test date</em> has been created');

    // Make a submission.
    $edit = [
      'test_text_field' => 'Test text field value',
      'test_text_area' => 'Test text area value',
      'test_not_encrypted' => 'Test not encrypted value',
      'test_date' => '2017-08-14',
    ];
    $this->drupalPostForm('/webform/test_encryption', $edit, 'Submit');
    $assert_session->responseContains('New submission added to Test encryption.');

    // Ensure encrypted fields do not show values.
    $this->drupalGet('admin/structure/webform/manage/test_encryption/results/submissions');
    $assert_session->responseNotContains($edit['test_text_field']);
    $assert_session->responseNotContains($edit['test_text_field']);
    $assert_session->responseContains($edit['test_not_encrypted']);
    $assert_session->responseNotContains($edit['test_date']);
    $submission_path = 'admin/structure/webform/manage/test_encryption/submission/1';
    $this->drupalGet($submission_path);
    $text_selector = '.form-item-test-text-field';
    $area_selector = '.form-item-test-text-area';
    $not_encrypted_selector = '.form-item-test-not-encrypted';
    $date_selector = '.form-item-test-date';
    $assert_session->elementTextContains('css', $text_selector, $encrypted_value);
    $assert_session->elementTextNotContains('css', $text_selector, $edit['test_text_field']);
    $assert_session->elementTextContains('css', $area_selector, $encrypted_value);
    $assert_session->elementTextNotContains('css', $area_selector, $edit['test_text_area']);
    $assert_session->elementTextContains('css', $not_encrypted_selector, $edit['test_not_encrypted']);
    $assert_session->elementTextNotContains('css', $not_encrypted_selector, $encrypted_value);
    $assert_session->elementTextContains('css', $date_selector, $encrypted_value);
    $assert_session->elementTextNotContains('css', $date_selector, $edit['test_date']);

    // Grant user access to view encrypted values and check again.
    $this->grantPermissions(Role::load($this->adminUser->getRoles()[0]), ['view encrypted values']);
    $this->drupalGet($submission_path);
    $assert_session->responseNotContains($encrypted_value);
    $assert_session->elementTextContains('css', $text_selector, $edit['test_text_field']);
    $assert_session->elementTextContains('css', $area_selector, $edit['test_text_area']);
    $assert_session->elementTextContains('css', $date_selector, '08/14/2017');
  }

}
