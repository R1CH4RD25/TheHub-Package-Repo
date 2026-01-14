<?php

/**
 * Package Forms API
 * Manage dynamic forms for packages
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\PackageForm;
use Hub\AuditLogger;

header('Content-Type: application/json');

Auth::requireLogin();
Auth::requireRole(['super_admin', 'admin']);

$packageForm = new PackageForm();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

try {
    switch ($method) {
        case 'GET':
            handleGet($packageForm, $action);
            break;

        case 'POST':
            handlePost($packageForm, $action);
            break;

        case 'PUT':
            handlePut($packageForm, $action);
            break;

        case 'DELETE':
            handleDelete($packageForm, $action);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (\Exception $e) {
    error_log("Package Forms API Error: " . $e->getMessage());
    jsonResponse(['error' => $e->getMessage()], 500);
}

/**
 * GET handlers
 */
function handleGet($packageForm, $action)
{
    switch ($action) {
        case 'list':
            listForms($packageForm);
            break;

        case 'get':
            getForm($packageForm);
            break;

        case 'fields':
            getFormFields($packageForm);
            break;

        case 'submissions':
            getSubmissions($packageForm);
            break;

        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

/**
 * List all forms for a package
 */
function listForms($packageForm)
{
    if (empty($_GET['package_id'])) {
        jsonResponse(['error' => 'package_id required'], 400);
    }

    $context = $_GET['context'] ?? null;
    $forms = $packageForm->getByPackage($_GET['package_id'], $context);

    jsonResponse([
        'success' => true,
        'forms' => $forms
    ]);
}

/**
 * Get a single form with its fields
 */
function getForm($packageForm)
{
    if (empty($_GET['form_id'])) {
        jsonResponse(['error' => 'form_id required'], 400);
    }

    $form = $packageForm->getById($_GET['form_id']);
    if (!$form) {
        jsonResponse(['error' => 'Form not found'], 404);
    }

    $form['fields'] = $packageForm->getFields($form['id']);

    jsonResponse([
        'success' => true,
        'form' => $form
    ]);
}

/**
 * Get fields for a form
 */
function getFormFields($packageForm)
{
    if (empty($_GET['form_id'])) {
        jsonResponse(['error' => 'form_id required'], 400);
    }

    $fields = $packageForm->getFields($_GET['form_id']);

    jsonResponse([
        'success' => true,
        'fields' => $fields
    ]);
}

/**
 * Get submissions for a form
 */
function getSubmissions($packageForm)
{
    if (empty($_GET['form_id'])) {
        jsonResponse(['error' => 'form_id required'], 400);
    }

    $filters = [
        'status' => $_GET['status'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'limit' => $_GET['limit'] ?? 100
    ];

    $submissions = $packageForm->getSubmissions($_GET['form_id'], $filters);

    jsonResponse([
        'success' => true,
        'submissions' => $submissions
    ]);
}

/**
 * POST handlers
 */
function handlePost($packageForm, $action)
{
    $data = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'create':
            createForm($packageForm, $data);
            break;

        case 'add-field':
            addField($packageForm, $data);
            break;

        case 'submit':
            submitForm($packageForm, $data);
            break;

        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

/**
 * Create a new form
 */
function createForm($packageForm, $data)
{
    if (empty($data['package_id']) || empty($data['name'])) {
        jsonResponse(['error' => 'package_id and name required'], 400);
    }

    $currentUser = Auth::getCurrentUser();
    $data['created_by'] = $currentUser['id'];

    $formId = $packageForm->create($data);

    AuditLogger::log('package_form_created', 'package_forms', $formId, null, $data);

    jsonResponse([
        'success' => true,
        'form_id' => $formId,
        'message' => 'Form created successfully'
    ]);
}

/**
 * Add a field to a form
 */
function addField($packageForm, $data)
{
    if (empty($data['form_id']) || empty($data['field_key']) || empty($data['label'])) {
        jsonResponse(['error' => 'form_id, field_key, and label required'], 400);
    }

    $fieldId = $packageForm->addField($data['form_id'], $data);

    AuditLogger::log('package_form_field_added', 'package_form_fields', $fieldId, null, $data);

    jsonResponse([
        'success' => true,
        'field_id' => $fieldId,
        'message' => 'Field added successfully'
    ]);
}

/**
 * Submit a form
 */
function submitForm($packageForm, $data)
{
    if (empty($data['form_id']) || empty($data['data'])) {
        jsonResponse(['error' => 'form_id and data required'], 400);
    }

    $currentUser = Auth::getCurrentUser();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $submissionId = $packageForm->submit(
        $data['form_id'],
        $currentUser['id'],
        $data['data'],
        $ipAddress,
        $userAgent
    );

    AuditLogger::log('package_form_submitted', 'package_form_submissions', $submissionId, null, [
        'form_id' => $data['form_id']
    ]);

    jsonResponse([
        'success' => true,
        'submission_id' => $submissionId,
        'message' => 'Form submitted successfully'
    ]);
}

/**
 * PUT handlers
 */
function handlePut($packageForm, $action)
{
    parse_str(file_get_contents('php://input'), $data);

    switch ($action) {
        case 'update':
            updateForm($packageForm, $data);
            break;

        case 'update-field':
            updateField($packageForm, $data);
            break;

        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

/**
 * Update a form
 */
function updateForm($packageForm, $data)
{
    if (empty($data['id'])) {
        jsonResponse(['error' => 'id required'], 400);
    }

    $oldForm = $packageForm->getById($data['id']);
    if (!$oldForm) {
        jsonResponse(['error' => 'Form not found'], 404);
    }

    $packageForm->update($data['id'], $data);

    AuditLogger::log('package_form_updated', 'package_forms', $data['id'], $oldForm, $data);

    jsonResponse([
        'success' => true,
        'message' => 'Form updated successfully'
    ]);
}

/**
 * Update a field
 */
function updateField($packageForm, $data)
{
    if (empty($data['id'])) {
        jsonResponse(['error' => 'id required'], 400);
    }

    $packageForm->updateField($data['id'], $data);

    AuditLogger::log('package_form_field_updated', 'package_form_fields', $data['id'], null, $data);

    jsonResponse([
        'success' => true,
        'message' => 'Field updated successfully'
    ]);
}

/**
 * DELETE handlers
 */
function handleDelete($packageForm, $action)
{
    parse_str(file_get_contents('php://input'), $data);

    switch ($action) {
        case 'delete':
            deleteForm($packageForm, $data);
            break;

        case 'delete-field':
            deleteField($packageForm, $data);
            break;

        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

/**
 * Delete a form
 */
function deleteForm($packageForm, $data)
{
    if (empty($data['id'])) {
        jsonResponse(['error' => 'id required'], 400);
    }

    $form = $packageForm->getById($data['id']);
    if (!$form) {
        jsonResponse(['error' => 'Form not found'], 404);
    }

    $packageForm->delete($data['id']);

    AuditLogger::log('package_form_deleted', 'package_forms', $data['id'], $form, null);

    jsonResponse([
        'success' => true,
        'message' => 'Form deleted successfully'
    ]);
}

/**
 * Delete a field
 */
function deleteField($packageForm, $data)
{
    if (empty($data['id'])) {
        jsonResponse(['error' => 'id required'], 400);
    }

    $packageForm->deleteField($data['id']);

    AuditLogger::log('package_form_field_deleted', 'package_form_fields', $data['id'], null, null);

    jsonResponse([
        'success' => true,
        'message' => 'Field deleted successfully'
    ]);
}
