<?php
/**
 * Office.php
 * Data-access for the "offices" table.
 */

require_once __DIR__ . '/../config/database.php';

class Office
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    public function findAll(): array
    {
        $result = $this->db->query('SELECT * FROM offices WHERE is_active = 1 ORDER BY office_name ASC');
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function findById(int $officeId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM offices WHERE office_id = ? LIMIT 1');
        $stmt->bind_param('i', $officeId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }
}
