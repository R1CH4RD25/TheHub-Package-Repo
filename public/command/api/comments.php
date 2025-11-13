<?php
/**
 * Command Center Comments API
 *
 * Handles adding comments to submissions
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Submission;

header('Content-Type: application/json');

// Require authentication
Auth::requireLogin();
Auth::requireRole(['admin', 'super_admin']);

$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];

try {
    $submission = new Submission();

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $submissionId = $input['submission_id'] ?? null;
        $commentText = $input['comment_text'] ?? null;
        $isInternal = $input['is_internal'] ?? 0;
        $parentCommentId = $input['parent_comment_id'] ?? null;

        if (!$submissionId || !$commentText) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }

        $commentId = $submission->addComment(
            $submissionId,
            $userId,
            $commentText,
            $isInternal,
            $parentCommentId
        );

        if ($commentId) {
            echo json_encode([
                'success' => true,
                'message' => 'Comment added successfully',
                'comment_id' => $commentId
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to add comment'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
}
