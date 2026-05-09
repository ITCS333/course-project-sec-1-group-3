<?php
/**
 * Discussion Board API
 *
 * RESTful API for CRUD operations on discussion topics and their replies.
 * Uses PDO to interact with the MySQL database defined in schema.sql.
 *
 * Database Tables (ground truth: schema.sql):
 *
 * Table: topics
 *   id         INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   subject    VARCHAR(255)  NOT NULL
 *   message    TEXT          NOT NULL
 *   author     VARCHAR(100)  NOT NULL
 *   created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
 *
 * Table: replies
 *   id         INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   topic_id   INT UNSIGNED  NOT NULL — FK → topics.id (ON DELETE CASCADE)
 *   text       TEXT          NOT NULL
 *   author     VARCHAR(100)  NOT NULL
 *   created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
 *
 * HTTP Methods Supported:
 *   GET    — Retrieve topic(s) or replies
 *   POST   — Create a new topic or reply
 *   PUT    — Update an existing topic
 *   DELETE — Delete a topic (cascade removes its replies) or a reply
 *
 * URL scheme (all requests go to index.php):
 *
 *   Topics:
 *     GET    ./api/index.php                  — list all topics
 *     GET    ./api/index.php?id={id}           — get one topic by integer id
 *     POST   ./api/index.php                  — create a new topic
 *     PUT    ./api/index.php                  — update a topic (id in JSON body)
 *     DELETE ./api/index.php?id={id}           — delete a topic
 *
 *   Replies (action parameter selects the replies sub-resource):
 *     GET    ./api/index.php?action=replies&topic_id={id}
 *                                             — list replies for a topic
 *     POST   ./api/index.php?action=reply     — create a reply
 *     DELETE ./api/index.php?action=delete_reply&id={id}
 *                                             — delete a single reply
 *
 * Query parameters for GET all topics:
 *   search — filter rows where subject LIKE or message LIKE or author LIKE
 *   sort   — column to sort by; allowed: subject, author, created_at
 *            (default: created_at)
 *   order  — sort direction; allowed: asc, desc (default: desc)
 *
 * Response format: JSON
 *   Success: { "success": true,  "data": ... }
 *   Error:   { "success": false, "message": "..." }
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// TODO: Set headers for JSON response and CORS.
// Set Content-Type to application/json.
// Allow cross-origin requests (CORS) if needed.
// Allow HTTP methods: GET, POST, PUT, DELETE, OPTIONS.
// Allow headers: Content-Type, Authorization.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


// TODO: Handle preflight OPTIONS request.
// If the request method is OPTIONS, return HTTP 200 and exit.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}


// TODO: Include the shared database connection file.
// require_once __DIR__ . '/../../common/db.php';
require_once __DIR__ . '/../../common/db.php';


// TODO: Get the PDO database connection.
// $db = getDBConnection();
$db = getDBConnection();


// TODO: Read the HTTP request method.
// $method = $_SERVER['REQUEST_METHOD'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';


// TODO: Read and decode the request body for POST and PUT requests.
// $rawData = file_get_contents('php://input');
// $data    = json_decode($rawData, true) ?? [];
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);
if (!is_array($data)) {
    $data = [];
}


// TODO: Read query parameters.
// $action  = $_GET['action']   ?? null;  // 'replies', 'reply', 'delete_reply'
// $id      = $_GET['id']       ?? null;  // integer topic or reply id
// $topicId = $_GET['topic_id'] ?? null;  // integer topic id for replies queries
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$topicId = $_GET['topic_id'] ?? null;


// ============================================================================
// TOPICS FUNCTIONS
// ============================================================================

/**
 * Get all topics (with optional search and sort).
 * Method: GET (no ?id or ?action parameter).
 *
 * Query parameters handled inside:
 *   search — filter by subject LIKE or message LIKE or author LIKE
 *   sort   — allowed: subject, author, created_at   (default: created_at)
 *   order  — allowed: asc, desc                     (default: desc)
 */
function getAllTopics(PDO $db): void
{
    $query = 'SELECT id, subject, message, author, created_at FROM topics';
    $search = trim((string) ($_GET['search'] ?? ''));
    $params = [];

    if ($search !== '') {
        $query .= ' WHERE subject LIKE :search OR message LIKE :search OR author LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $allowedSort = ['subject', 'author', 'created_at'];
    $sort = strtolower((string) ($_GET['sort'] ?? 'created_at'));
    if (!in_array($sort, $allowedSort, true)) {
        $sort = 'created_at';
    }

    $order = strtolower((string) ($_GET['order'] ?? 'desc'));
    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'desc';
    }

    $query .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $topics]);
}


/**
 * Get a single topic by its integer primary key.
 * Method: GET with ?id={id}.
 *
 * Response (found):
 *   { "success": true, "data": { id, subject, message, author, created_at } }
 * Response (not found): HTTP 404.
 */
function getTopicById(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid topic id.'], 400);
    }

    $stmt = $db->prepare('SELECT id, subject, message, author, created_at FROM topics WHERE id = ?');
    $stmt->execute([(int) $id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($topic) {
        sendResponse(['success' => true, 'data' => $topic]);
    }

    sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
}


/**
 * Create a new topic.
 * Method: POST (no ?action parameter).
 *
 * Required JSON body fields:
 *   subject — string (required)
 *   message — string (required)
 *   author  — string (required)
 *
 * Response (success): HTTP 201 — { success, message, id }
 * Response (missing fields): HTTP 400.
 *
 * Note: id and created_at are handled automatically by MySQL.
 */
function createTopic(PDO $db, array $data): void
{
    $subject = sanitizeInput((string) ($data['subject'] ?? ''));
    $message = sanitizeInput((string) ($data['message'] ?? ''));
    $author = sanitizeInput((string) ($data['author'] ?? ''));

    if ($subject === '' || $message === '' || $author === '') {
        sendResponse(['success' => false, 'message' => 'subject, message, and author are required.'], 400);
    }

    $stmt = $db->prepare('INSERT INTO topics (subject, message, author) VALUES (?, ?, ?)');
    $stmt->execute([$subject, $message, $author]);

    if ($stmt->rowCount() > 0) {
        sendResponse(
            [
                'success' => true,
                'message' => 'Topic created successfully.',
                'id' => (int) $db->lastInsertId(),
            ],
            201
        );
    }

    sendResponse(['success' => false, 'message' => 'Failed to create topic.'], 500);
}


/**
 * Update an existing topic.
 * Method: PUT.
 *
 * Required JSON body:
 *   id — integer primary key of the topic to update (required).
 * Optional JSON body fields (at least one must be present):
 *   subject, message.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function updateTopic(PDO $db, array $data): void
{
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Topic id is required.'], 400);
    }

    $id = (int) $data['id'];
    $check = $db->prepare('SELECT id FROM topics WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    $setClauses = [];
    $values = [];

    if (array_key_exists('subject', $data)) {
        $setClauses[] = 'subject = ?';
        $values[] = sanitizeInput((string) $data['subject']);
    }
    if (array_key_exists('message', $data)) {
        $setClauses[] = 'message = ?';
        $values[] = sanitizeInput((string) $data['message']);
    }

    if (count($setClauses) === 0) {
        sendResponse(['success' => false, 'message' => 'No fields to update.'], 400);
    }

    $values[] = $id;
    $sql = 'UPDATE topics SET ' . implode(', ', $setClauses) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $ok = $stmt->execute($values);

    if ($ok) {
        sendResponse(['success' => true, 'message' => 'Topic updated successfully.'], 200);
    }

    sendResponse(['success' => false, 'message' => 'Failed to update topic.'], 500);
}


/**
 * Delete a topic by integer id.
 * Method: DELETE with ?id={id}.
 *
 * The ON DELETE CASCADE constraint on replies.topic_id automatically
 * removes all replies for this topic — no manual deletion of replies
 * is needed.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function deleteTopic(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid topic id.'], 400);
    }

    $topicId = (int) $id;
    $check = $db->prepare('SELECT id FROM topics WHERE id = ?');
    $check->execute([$topicId]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM topics WHERE id = ?');
    $stmt->execute([$topicId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Topic deleted successfully.'], 200);
    }

    sendResponse(['success' => false, 'message' => 'Failed to delete topic.'], 500);
}


// ============================================================================
// REPLIES FUNCTIONS
// ============================================================================

/**
 * Get all replies for a specific topic.
 * Method: GET with ?action=replies&topic_id={id}.
 *
 * Reads from the replies table.
 * Returns an empty data array if no replies exist — not an error.
 *
 * Each reply object: { id, topic_id, text, author, created_at }
 */
function getRepliesByTopicId(PDO $db, $topicId): void
{
    if ($topicId === null || !is_numeric($topicId)) {
        sendResponse(['success' => false, 'message' => 'Invalid topic_id.'], 400);
    }

    $stmt = $db->prepare('SELECT id, topic_id, text, author, created_at FROM replies WHERE topic_id = ? ORDER BY created_at ASC');
    $stmt->execute([(int) $topicId]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(['success' => true, 'data' => $replies]);
}


/**
 * Create a new reply.
 * Method: POST with ?action=reply.
 *
 * Required JSON body:
 *   topic_id — integer FK into topics.id (required)
 *   text     — string (required, must be non-empty after trim)
 *   author   — string (required)
 *
 * Response (success): HTTP 201 — { success, message, id, data: reply }
 * Response (topic not found): HTTP 404.
 * Response (missing fields): HTTP 400.
 *
 * Note: id and created_at are handled automatically by MySQL.
 */
function createReply(PDO $db, array $data): void
{
    $topicId = $data['topic_id'] ?? null;
    $text = sanitizeInput((string) ($data['text'] ?? ''));
    $author = sanitizeInput((string) ($data['author'] ?? ''));

    if ($topicId === null || $text === '' || $author === '') {
        sendResponse(['success' => false, 'message' => 'topic_id, text, and author are required.'], 400);
    }
    if (!is_numeric($topicId)) {
        sendResponse(['success' => false, 'message' => 'topic_id must be numeric.'], 400);
    }

    $topicId = (int) $topicId;
    $check = $db->prepare('SELECT id FROM topics WHERE id = ?');
    $check->execute([$topicId]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    $stmt = $db->prepare('INSERT INTO replies (topic_id, text, author) VALUES (?, ?, ?)');
    $stmt->execute([$topicId, $text, $author]);

    if ($stmt->rowCount() > 0) {
        $newId = (int) $db->lastInsertId();
        $rowStmt = $db->prepare('SELECT id, topic_id, text, author, created_at FROM replies WHERE id = ?');
        $rowStmt->execute([$newId]);
        $reply = $rowStmt->fetch(PDO::FETCH_ASSOC);

        sendResponse(
            [
                'success' => true,
                'message' => 'Reply created successfully.',
                'id' => $newId,
                'data' => $reply,
            ],
            201
        );
    }

    sendResponse(['success' => false, 'message' => 'Failed to create reply.'], 500);
}


/**
 * Delete a single reply.
 * Method: DELETE with ?action=delete_reply&id={id}.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function deleteReply(PDO $db, $replyId): void
{
    if ($replyId === null || !is_numeric($replyId)) {
        sendResponse(['success' => false, 'message' => 'Invalid reply id.'], 400);
    }

    $replyId = (int) $replyId;
    $check = $db->prepare('SELECT id FROM replies WHERE id = ?');
    $check->execute([$replyId]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(['success' => false, 'message' => 'Reply not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM replies WHERE id = ?');
    $stmt->execute([$replyId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Reply deleted successfully.'], 200);
    }

    sendResponse(['success' => false, 'message' => 'Failed to delete reply.'], 500);
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        // ?action=replies&topic_id={id} → list replies for a topic
        // TODO: if $action === 'replies', call getRepliesByTopicId($db, $topicId)
        if ($action === 'replies') {
            getRepliesByTopicId($db, $topicId);
        } elseif ($id !== null) {
            getTopicById($db, $id);
        } else {
            getAllTopics($db);
        }

        // ?id={id} → single topic
        // TODO: elseif $id is set, call getTopicById($db, $id)

        // no parameters → all topics (supports ?search, ?sort, ?order)
        // TODO: else call getAllTopics($db)

    } elseif ($method === 'POST') {

        // ?action=reply → create a reply in the replies table
        // TODO: if $action === 'reply', call createReply($db, $data)
        if ($action === 'reply') {
            createReply($db, $data);
        } else {
            createTopic($db, $data);
        }

        // no action → create a new topic
        // TODO: else call createTopic($db, $data)

    } elseif ($method === 'PUT') {

        // Update a topic; id comes from the JSON body
        // TODO: call updateTopic($db, $data)
        updateTopic($db, $data);

    } elseif ($method === 'DELETE') {

        // ?action=delete_reply&id={id} → delete one reply
        // TODO: if $action === 'delete_reply', call deleteReply($db, $id)
        if ($action === 'delete_reply') {
            deleteReply($db, $id);
        } else {
            deleteTopic($db, $id);
        }

        // ?id={id} → delete a topic (and its replies via CASCADE)
        // TODO: else call deleteTopic($db, $id)

    } else {
        // TODO: sendResponse HTTP 405 Method Not Allowed.
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

} catch (PDOException $e) {
    // TODO: Log the error with error_log().
    // Return a generic HTTP 500 — do NOT expose $e->getMessage() to clients.
    error_log('Discussion API PDOException: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal server error.'], 500);

} catch (Exception $e) {
    // TODO: Log the error with error_log().
    // Return HTTP 500 using sendResponse().
    error_log('Discussion API Exception: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal server error.'], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Send a JSON response and stop execution.
 *
 * @param array $data        Must include a 'success' key.
 * @param int   $statusCode  HTTP status code (default 200).
 */
function sendResponse(array $data, int $statusCode = 200): void
{
    // TODO: http_response_code($statusCode);
    // TODO: echo json_encode($data, JSON_PRETTY_PRINT);
    // TODO: exit;
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}


/**
 * Sanitize a string input.
 *
 * @param  string $data
 * @return string  Trimmed, tag-stripped, HTML-encoded string.
 */
function sanitizeInput(string $data): string
{
    // TODO: return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
