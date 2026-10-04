<?php
/**
 * app/models/Exam.php  —  Exam configuration CRUD
 */
require_once dirname(__DIR__, 2) . '/config/database.php';

class Exam
{
    private PDO $db;

    public function __construct() { $this->db = Database::getConnection(); }

    public function all(): array
    {
        $sql = "SELECT e.*, g.name AS group_name,
                       (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) AS question_count
                FROM exams e
                INNER JOIN groups g ON g.id = e.group_id
                ORDER BY e.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, g.name AS group_name
             FROM exams e INNER JOIN groups g ON g.id = e.group_id
             WHERE e.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Exams visible to an examinee through their group memberships */
    public function forExaminee(int $userId): array
    {
        $sql = "SELECT e.*, g.name AS group_name,
                       (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) AS question_count,
                       (SELECT a.id FROM exam_attempts a WHERE a.exam_id = e.id AND a.user_id = :uid1 ORDER BY a.id DESC LIMIT 1) AS last_attempt_id,
                       (SELECT a.status FROM exam_attempts a WHERE a.exam_id = e.id AND a.user_id = :uid2 ORDER BY a.id DESC LIMIT 1) AS last_attempt_status,
                       (SELECT a.final_score FROM exam_attempts a WHERE a.exam_id = e.id AND a.user_id = :uid3 ORDER BY a.id DESC LIMIT 1) AS last_score,
                       (SELECT a.percentage FROM exam_attempts a WHERE a.exam_id = e.id AND a.user_id = :uid4 ORDER BY a.id DESC LIMIT 1) AS last_percentage,
                       (SELECT a.passed FROM exam_attempts a WHERE a.exam_id = e.id AND a.user_id = :uid5 ORDER BY a.id DESC LIMIT 1) AS last_passed
                FROM exams e
                INNER JOIN groups g ON g.id = e.group_id
                WHERE e.is_active = 1
                  AND e.group_id IN (SELECT group_id FROM user_groups WHERE user_id = :uid6)
                ORDER BY e.id DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'uid1' => $userId,
            'uid2' => $userId,
            'uid3' => $userId,
            'uid4' => $userId,
            'uid5' => $userId,
            'uid6' => $userId,
        ]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO exams (title, description, group_id, duration_minutes, passing_percentage, questions_to_answer, positive_marks, negative_marks, shuffle_questions, shuffle_options, show_immediate_score, is_active) 
             VALUES (:title, :desc, :gid, :dur, :pass, :qta, :pos, :neg, :sq, :so, :sis, :active)"
        );
        $stmt->execute([
            'title'                => trim($data['title']),
            'desc'                 => trim($data['description'] ?? ''),
            'gid'                  => (int)$data['group_id'],
            'dur'                  => (int)($data['duration_minutes'] ?? 15),
            'pass'                 => (float)($data['passing_percentage'] ?? 50),
            'qta'                  => (int)($data['questions_to_answer'] ?? 0), // ✅ NEW
            'pos'                  => (float)($data['positive_marks'] ?? 1),
            'neg'                  => (float)($data['negative_marks'] ?? 0),
            'sq'                   => !empty($data['shuffle_questions']) ? 1 : 0,
            'so'                   => !empty($data['shuffle_options']) ? 1 : 0,
            'sis'                  => !empty($data['show_immediate_score']) ? 1 : 0,
            'active'               => !empty($data['is_active']) ? 1 : 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $d): bool
    {
        $sql = "UPDATE exams SET
                    title = :title, description = :description, group_id = :group_id,
                    duration_minutes = :duration_minutes, passing_percentage = :passing_percentage,
                    positive_marks = :positive_marks, negative_marks = :negative_marks,
                    shuffle_questions = :shuffle_questions, shuffle_options = :shuffle_options,
                    show_immediate_score = :show_immediate_score, is_active = :is_active
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($this->bind($d) + ['id' => $id]);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare("DELETE FROM exams WHERE id = :id")->execute(['id' => $id]);
    }

    public function countActive(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM exams WHERE is_active = 1")->fetchColumn();
    }

    private function bind(array $d): array
    {
        return [
            'title'                => trim((string)$d['title']),
            'description'          => $d['description'] ?? null,
            'group_id'             => (int)$d['group_id'],
            'duration_minutes'     => max(1, (int)($d['duration_minutes'] ?? 30)),
            'passing_percentage'   => (float)($d['passing_percentage'] ?? 50),
            'positive_marks'       => (float)($d['positive_marks'] ?? 1),
            'negative_marks'       => (float)($d['negative_marks'] ?? 0.25),
            'shuffle_questions'    => !empty($d['shuffle_questions']) ? 1 : 0,
            'shuffle_options'      => !empty($d['shuffle_options']) ? 1 : 0,
            'show_immediate_score' => !empty($d['show_immediate_score']) ? 1 : 0,
            'is_active'            => !empty($d['is_active']) ? 1 : 0,
        ];
    }
}
