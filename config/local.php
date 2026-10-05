<?php
declare(strict_types=1);

// LOCAL DEVELOPMENT SETTINGS — this machine only. Do not copy this file to a production server.
// Delete this file (or set APP_ENV to 'production') to return to production behaviour.

const APP_ENV = 'development';

// Documents development test release: lets the listed staff accounts try the Approved → Released screens without an
// approved template, signing, payment or claimant verification. Test releases live only in that user's session and are
// never written to the database. Set to false to use only the official release workflow.
const DOCUMENTS_TEST_RELEASE = false;   // turned off 2026-10-04: only the official release workflow
// SMS: real texts are sent through Semaphore (registration codes and announcement texts). Turned on by the owner
// 2026-10-04. Set back to true to stop sending (codes are then shown on the screen instead).
defined('SMS_TEST_MODE') || define('SMS_TEST_MODE', false);

const DOCUMENTS_TEST_RELEASE_USER_IDS = [1, 2]; // local test accounts: System Administrator and Test Secretary
