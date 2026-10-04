<?php
/**
 * app/models/Group.php  —  Exam Group CRUD + examinee assignment
 */
require_once dirname(__DIR__, 2) . '/config/database.php';

class Group
{
    private PDO $db;

    public function __construct() { $this->db = Database::getConnection(); }

    public function all(): array
    {
        $sql = "SELECT g.*,
                       (SELECT COUNT(*) FROM user_groups ug WHERE ug.group_id = g.id) AS member_count,
                       (SELECT COUNT(*) FROM exams e WHERE e.group_id = g.id)         AS exam_count
                FROM groups g
                ORDER BY g.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM groups WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $name, ?string $description = null): int
    {
        $stmt = $this->db->prepare("INSERT INTO groups (name, description) VALUES (:name, :description)");
        $stmt->execute(['name' => $name, 'description' => $description]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description = null): bool
    {
        $stmt = $this->db->prepare("UPDATE groups SET name = :name, description = :description WHERE id = :id");
        return $stmt->execute(['name' => $name, 'description' => $description, 'id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM groups WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /** Examinees assigned to a group */
    public function members(int $groupId): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.reg_number, u.full_name, u.email
             FROM users u
             INNER JOIN user_groups ug ON ug.user_id = u.id
             WHERE ug.group_id = :gid AND u.role = 'examinee'
             ORDER BY u.reg_number"
        );
        $stmt->execute(['gid' => $groupId]);
        return $stmt->fetchAll();
    }

    public function assignUser(int $userId, int $groupId): void
    {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO user_groups (user_id, group_id) VALUES (:uid, :gid)"
        );
        $stmt->execute(['uid' => $userId, 'gid' => $groupId]);
    }

    public function unassignUser(int $userId, int $groupId): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM user_groups WHERE user_id = :uid AND group_id = :gid"
        );
        $stmt->execute(['uid' => $userId, 'gid' => $groupId]);
    }

    /** Replace a user's group memberships in one transaction */
    public function syncUserGroups(int $userId, array $groupIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM user_groups WHERE user_id = :uid")->execute(['uid' => $userId]);
            $ins = $this->db->prepare("INSERT INTO user_groups (user_id, group_id) VALUES (:uid, :gid)");
            foreach ($groupIds as $gid) {
                $ins->execute(['uid' => $userId, 'gid' => (int)$gid]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
