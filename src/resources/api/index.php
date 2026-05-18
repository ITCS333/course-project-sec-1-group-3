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

$action     = $_GET['action']      ?? null;
$id         = $_GET['id']          ?? null;
$resourceId = $_GET['resource_id'] ?? null;
$commentId  = $_GET['comment_id']  ?? null;


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function validateUrl(string $url): bool
{
    return (bool) filter_var($url, FILTER_VALIDATE_URL);
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


// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================

function getAllResources(PDO $db): void
{
    $sql    = 'SELECT id, title, description, link, created_at FROM resources';
    $params = [];

    if (!empty($_GET['search'])) {
        $sql .= ' WHERE title LIKE :search OR description LIKE :search';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $allowedSort  = ['title', 'created_at'];
    $allowedOrder = ['asc', 'desc'];

    $sort  = in_array($_GET['sort']  ?? '', $allowedSort)  ? $_GET['sort']  : 'created_at';
    $order = in_array($_GET['order'] ?? '', $allowedOrder) ? $_GET['order'] : 'desc';

    $sql .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $resources = $stmt->fetchAll();

    sendResponse(['success' => true, 'data' => $resources]);
}

function getResourceById(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource id.'], 400);
    }

    $stmt = $db->prepare('SELECT id, title, description, link, created_at FROM resources WHERE id = ?');
    $stmt->execute([(int) $id]);
    $resource = $stmt->fetch();

    if (!$resource) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    sendResponse(['success' => true, 'data' => $resource]);
}

function createResource(PDO $db, array $data): void
{
    $validation = validateRequiredFields($data, ['title', 'link']);
    if (!$validation['valid']) {
        sendResponse(['success' => false, 'message' => 'title and link are required.'], 400);
    }

    $title       = trim($data['title']);
    $link        = trim($data['link']);
    $description = trim($data['description'] ?? '');

    if (!validateUrl($link)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL.'], 400);
    }

    $stmt = $db->prepare('INSERT INTO resources (title, description, link) VALUES (?, ?, ?)');
    $stmt->execute([$title, $description, $link]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Resource created.',
            'id'      => (int) $db->lastInsertId(),
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create resource.'], 500);
    }
}

function updateResource(PDO $db, array $data): void
{
    if (empty($data['id'])) {
        sendResponse(['success' => false, 'message' => 'id is required.'], 400);
    }

    $id = (int) $data['id'];

    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
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
    if (isset($data['link']) && $data['link'] !== '') {
        if (!validateUrl(trim($data['link']))) {
            sendResponse(['success' => false, 'message' => 'Invalid URL.'], 400);
        }
        $setClauses[] = 'link = ?';
        $params[]     = trim($data['link']);
    }

    if (empty($setClauses)) {
        sendResponse(['success' => false, 'message' => 'No fields to update.'], 400);
    }

    $params[] = $id;
    $sql      = 'UPDATE resources SET ' . implode(', ', $setClauses) . ' WHERE id = ?';
    $stmt     = $db->prepare($sql);
    $stmt->execute($params);

    sendResponse(['success' => true, 'message' => 'Resource updated successfully.']);
}

function deleteResource(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource id.'], 400);
    }

    $id = (int) $id;

    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM resources WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Resource deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete resource.'], 500);
    }
}


// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

function getCommentsByResourceId(PDO $db, $resourceId): void
{
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource_id.'], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, resource_id, author, text, created_at
         FROM comments_resource
         WHERE resource_id = ?
         ORDER BY created_at ASC'
    );
    $stmt->execute([(int) $resourceId]);
    $comments = $stmt->fetchAll();

    sendResponse(['success' => true, 'data' => $comments]);
}

function createComment(PDO $db, array $data): void
{
    $validation = validateRequiredFields($data, ['resource_id', 'author', 'text']);
    if (!$validation['valid']) {
        sendResponse(['success' => false, 'message' => 'resource_id, author, and text are required.'], 400);
    }

    if (!is_numeric($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'resource_id must be numeric.'], 400);
    }

    $resourceId = (int) $data['resource_id'];

    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$resourceId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    $author = trim($data['author']);
    $text   = trim($data['text']);

    $stmt = $db->prepare('INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)');
    $stmt->execute([$resourceId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId   = (int) $db->lastInsertId();
        $comment = [
            'id'          => $newId,
            'resource_id' => $resourceId,
            'author'      => $author,
            'text'        => $text,
            'created_at'  => date('Y-m-d H:i:s'),
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

    $check = $db->prepare('SELECT id FROM comments_resource WHERE id = ?');
    $check->execute([$commentId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM comments_resource WHERE id = ?');
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
            getCommentsByResourceId($db, $resourceId);
        } elseif ($id !== null) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateResource($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteResource($db, $id);
        }

    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

} catch (PDOException $e) {
    error_log('Resources API PDOException: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Database error.'], 500);

} catch (Exception $e) {
    error_log('Resources API Exception: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error.'], 500);
}
