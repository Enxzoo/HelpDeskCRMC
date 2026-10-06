<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/config/env.php';
if (!in_array(env('DB_HOST', '127.0.0.1'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Chat tests require a local database.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$server = new mysqli(env('DB_HOST', '127.0.0.1'), env('DB_USER', 'root'), env('DB_PASS', ''));
$testDatabase = 'helpdeskcrmc_test_' . bin2hex(random_bytes(6));
$created = false;
$db = null;
$checks = 0;
function checkChatSession(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function importChatMigration(string $file): void
{
    $db = getDbConnection();
    $db->multi_query(file_get_contents(__DIR__ . '/../database/migrations/' . $file));
    do {
        $result = $db->store_result();
        if ($result) $result->free();
    } while ($db->more_results() && $db->next_result());
}
try {
    $server->query('CREATE DATABASE `' . $testDatabase . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $created = true;
    putenv('DB_NAME=' . $testDatabase);
    require_once __DIR__ . '/../app/models/User.php';
    require_once __DIR__ . '/../app/models/ChatSession.php';
    $db = getDbConnection();
    importChatMigration('database.sql');
    importChatMigration('20261003_add_student_profiles.sql');
    importChatMigration('20261005_student_self_registration.sql');
    importChatMigration('20261002_add_chat_sessions.sql');
    $users = new User();
    $ids = [];
    foreach (['first', 'second'] as $name) {
        $ids[] = $users->create(['role' => 'student', 'office_id' => null, 'student_number' => 'CHAT-' . $name,
            'first_name' => 'Chat', 'last_name' => $name, 'email' => 'chat-' . $name . '@example.test',
            'password_hash' => password_hash('chat-test-password', PASSWORD_DEFAULT)]);
    }
    require_once __DIR__ . '/student_fixtures.php';
    foreach ($ids as $id) studentProfileFixture($db, $id);
    $oldHistory = [['role' => 'user', 'message' => 'Original conversation']];
    $data = json_encode($oldHistory);
    $stmt = $db->prepare('INSERT INTO chat_sessions (student_id, category, session_data, last_message, updated_at)
        VALUES (?, "Registrar", ?, "Original conversation", "2025-01-02 03:04:05")');
    $stmt->bind_param('is', $ids[0], $data);
    $stmt->execute();
    $oldId = $stmt->insert_id;
    importChatMigration('20261002_allow_multiple_chat_sessions.sql');
    $model = new ChatSession();
    $old = $model->latestForCategory($ids[0], 'Registrar');
    $oldKey = $old['session_key'];
    checkChatSession((int)$old['session_id'] === $oldId, 'Migration replaced the original conversation ID.');
    checkChatSession(ChatSession::validKey($oldKey), 'Migration did not assign a valid key.');
    checkChatSession(json_decode($old['session_data'], true) === $oldHistory, 'Migration changed the old conversation.');
    checkChatSession($old['updated_at'] === '2025-01-02 03:04:05', 'Migration changed the conversation timestamp.');
    importChatMigration('20261002_allow_multiple_chat_sessions.sql');
    checkChatSession($model->latestForCategory($ids[0], 'Registrar')['session_key'] === $oldKey, 'A repeated migration changed the identity.');
    $newKey = bin2hex(random_bytes(16));
    $newHistory = [['role' => 'user', 'message' => 'New conversation']];
    $saved = $model->save($ids[0], 'Registrar', $newHistory, 'New conversation', $newKey);
    checkChatSession($saved['session_key'] === $newKey && $saved['session_id'] !== $oldId, 'A fresh chat reused the previous identity.');
    checkChatSession(count($model->recent($ids[0])) === 2, 'Two same-category conversations did not remain separate.');
    checkChatSession(json_decode($model->find($ids[0], $oldKey)['session_data'], true) === $oldHistory, 'The new chat overwrote the old one.');
    $newHistory[] = ['role' => 'model', 'message' => 'New answer'];
    $updated = $model->save($ids[0], 'Registrar', $newHistory, 'New answer', $newKey);
    checkChatSession($updated['session_id'] === $saved['session_id'], 'Saving a reply created a second history entry.');
    checkChatSession(count($model->recent($ids[0])) === 2, 'Saving a reply duplicated the conversation.');
    checkChatSession(json_decode($model->find($ids[0], $newKey)['session_data'], true) === $newHistory, 'Saved messages could not be restored.');
    checkChatSession($model->find($ids[1], $oldKey) === null && $model->recent($ids[1]) === [], 'Another student can read the chat.');
    $model->save($ids[1], 'Registrar', [['role' => 'user', 'message' => 'Other student']], 'Other student', $newKey);
    checkChatSession(json_decode($model->find($ids[0], $newKey)['session_data'], true) === $newHistory, 'Another student overwrote the chat.');
    checkChatSession(count($model->recent($ids[1])) === 1, 'Student history scope is incorrect.');
    try { $model->save($ids[0], 'Finance', [], null, $newKey); throw new RuntimeException('Category mismatch was allowed.'); }
    catch (InvalidArgumentException $exception) { $checks++; }
    try { $model->save($ids[0], 'Registrar', [], null, 'bad-key'); throw new RuntimeException('Invalid key was allowed.'); }
    catch (InvalidArgumentException $exception) { $checks++; }
    checkChatSession(!ChatSession::validKey([]) && !ChatSession::validKey(str_repeat('a', 33)), 'Invalid key types/lengths passed validation.');
    $legacy = $model->save($ids[0], 'Registrar', $newHistory, 'Legacy client');
    checkChatSession($legacy['session_id'] === $saved['session_id'], 'The legacy API contract stopped updating its latest conversation.');
    checkChatSession(count($model->recent($ids[0])) === 2, 'The legacy client created an extra conversation.');
    checkChatSession(!array_key_exists('session_data', $model->recent($ids[0])[0]), 'The history list unnecessarily exposes complete conversations.');
    echo 'Chat session PHP regression checks passed: ' . $checks . PHP_EOL;
} finally {
    if ($db instanceof mysqli) $db->close();
    if ($created && preg_match('/^helpdeskcrmc_test_[a-f0-9]{12}$/', $testDatabase)) $server->query('DROP DATABASE `' . $testDatabase . '`');
    $server->close();
}
