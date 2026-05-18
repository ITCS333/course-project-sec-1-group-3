<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

$db     = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

$action       = $_GET['action']        ?? null;
$id           = $_GET['id']            ?? null;
$assignmentId = $_GET['assignment_id'] ?? null;
$commentId    = $_GET['comment_id']    ?? null;


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateRequiredFields(array $data, array $requiredFields): array
{
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            $missing[] = $field;
        }
    }
    return ['valid' => count($missing) === 0, 'missing' => $missing];
}

function decodeFiles($raw): array
{
    if (is_array($raw)) return $raw;
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}


// ============================================================================
// ASSIGNMENT FUNCTIONS
// ============================================================================

function getAllAssignments(PDO $db): void
{
    $sql    = 'SELECT id, title, description, due_date, files, created_at FROM assignments';
    $params = [];

    if (!empty($_GET['search'])) {
        $sql .= ' WHERE title LIKE :search OR description LIKE :search';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $allowedSort  = ['title', 'due_date', 'created_at'];
    $allowedOrder = ['asc', 'desc'];

    $sort  = in_array($_GET['sort']  ?? '', $allowedSort)  ? $_GET['sort']  : 'due_date';
    $order = in_array($_GET['order'] ?? '', $allowedOrder) ? $_GET['order'] : 'asc';

    $sql .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['files'] = decodeFiles($row['files']);
    }

    sendResponse(['success' => true, 'data' => $rows]);
}

function getAssignmentById(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid assignment id.'], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, title, description, due_date, files, created_at FROM assignments WHERE id = ?'
    );
    $stmt->execute([(int) $id]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        sendResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }

    $assignment['files'] = decodeFiles($assignment['files']);
    sendResponse(['success' => true, 'data' => $assignment]);
}

function createAssignment(PDO $db, array $data): void
{
    $validation = validateRequiredFields($data, ['title', 'description', 'due_date']);
    if (!$validation['valid']) {
        sendResponse(['success' => false, 'message' => 'title, description, and due_date are required.'], 400);
    }

    $title       = trim($data['title']);
    $description = trim($data['description']);
    $due_date    = trim($data['due_date']);

    if (!validateDate($due_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid due_date format. Use YYYY-MM-DD.'], 400);
    }

    $files = (isset($data['files']) && is_array($data['files']))
        ? json_encode($data['files'])
        : json_encode([]);

    $stmt = $db->prepare(
        'INSERT INTO assignments (title, description, due_date, files) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$title, $description, $due_date, $files]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Assignment created.',
            'id'      => (int) $db->lastInsertId(),
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create assignment.'], 500);
    }
}

function updateAssignment(PDO $db, array $data): void
{
    if (empty($data['id'])) {
        sendResponse(['success' => false, 'message' => 'id is required.'], 400);
    }

    $id = (int) $data['id'];

    $check = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }

    $setClauses = [];
    $params     = [];

    if (isset($data['title']) && $data['title'] !== '') {
        $setClauses[] = 'title = ?';
        $params[]     = trim($data['title']);
    }
    if (isset($data['description'])) {
        $setClauses[] = 'description = ?';
        $params[]     = trim($data['description']);
    }
    if (isset($data['due_date']) && $data['due_date'] !== '') {
        if (!validateDate(trim($data['due_date']))) {
            sendResponse(['success' => false, 'message' => 'Invalid due_date format. Use YYYY-MM-DD.'], 400);
        }
        $setClauses[] = 'due_date = ?';
        $params[]     = trim($data['due_date']);
    }
    if (isset($data['files'])) {
        $setClauses[] = 'files = ?';
        $params[]     = is_array($data['files']) ? json_encode($data['files']) : json_encode([]);
    }

    if (empty($setClauses)) {
        sendResponse(['success' => false, 'message' => 'No fields to update.'], 400);
    }

    $params[] = $id;
    $sql      = 'UPDATE assignments SET ' . implode(', ', $setClauses) . ' WHERE id = ?';
    $stmt     = $db->prepare($sql);
    $stmt->execute($params);

    sendResponse(['success' => true, 'message' => 'Assignment updated successfully.']);
}

function deleteAssignment(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid assignment id.'], 400);
    }

    $id = (int) $id;

    $check = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM assignments WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Assignment deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete assignment.'], 500);
    }
}


// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

function getCommentsByAssignment(PDO $db, $assignmentId): void
{
    if (!$assignmentId || !is_numeric($assignmentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid assignment_id.'], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, assignment_id, author, text, created_at
         FROM comments_assignment
         WHERE assignment_id = ?
         ORDER BY created_at ASC'
    );
    $stmt->execute([(int) $assignmentId]);
    $comments = $stmt->fetchAll();

    sendResponse(['success' => true, 'data' => $comments]);
}

function createComment(PDO $db, array $data): void
{
    $validation = validateRequiredFields($data, ['assignment_id', 'author', 'text']);
    if (!$validation['valid']) {
        sendResponse(['success' => false, 'message' => 'assignment_id, author, and text are required.'], 400);
    }

    if (!is_numeric($data['assignment_id'])) {
        sendResponse(['success' => false, 'message' => 'assignment_id must be numeric.'], 400);
    }

    $assignmentId = (int) $data['assignment_id'];

    $check = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $check->execute([$assignmentId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }

    $author = trim($data['author']);
    $text   = trim($data['text']);

    $stmt = $db->prepare(
        'INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?)'
    );
    $stmt->execute([$assignmentId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId   = (int) $db->lastInsertId();
        $comment = [
            'id'            => $newId,
            'assignment_id' => $assignmentId,
            'author'        => $author,
            'text'          => $text,
            'created_at'    => date('Y-m-d H:i:s'),
        ];
        sendResponse([
            'success' => true,
            'message' => 'Comment added.',
            'id'      => $newId,
            'data'    => $comment,
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to add comment.'], 500);
    }
}

function deleteComment(PDO $db, $commentId): void
{
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment_id.'], 400);
    }

    $commentId = (int) $commentId;

    $check = $db->prepare('SELECT id FROM comments_assignment WHERE id = ?');
    $check->execute([$commentId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM comments_assignment WHERE id = ?');
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment.'], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        if ($action === 'comments') {
            getCommentsByAssignment($db, $assignmentId);
        } elseif ($id !== null) {
            getAssignmentById($db, $id);
        } else {
            getAllAssignments($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createAssignment($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateAssignment($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteAssignment($db, $id);
        }

    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

} catch (PDOException $e) {
    error_log('Assignments API PDOException: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Database error.'], 500);

} catch (Exception $e) {
    error_log('Assignments API Exception: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error.'], 500);
}
