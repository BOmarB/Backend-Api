<?php

class Notification
{
  private $conn;
  private $table = "notifications";
  private $recipients_table = "notification_recipients";

  public $id;
  public $sender_id;
  public $title;
  public $message;
  public $status;
  public $image_data;

  public function __construct($db)
  {
    $this->conn = $db;
  }

  public function createNotification()
  {
    $query = "INSERT INTO " . $this->table . " 
                  (sender_id, title, message, status, image_data) 
                  VALUES (:sender_id, :title, :message, :status, :image_data)";

    $stmt = $this->conn->prepare($query);

    $stmt->bindParam(":sender_id", $this->sender_id);
    $stmt->bindParam(":title", $this->title);
    $stmt->bindParam(":message", $this->message);
    $stmt->bindParam(":status", $this->status);
    $stmt->bindParam(":image_data", $this->image_data);

    if ($stmt->execute()) {
      return $this->conn->lastInsertId();
    }
    return false;
  }

  public function addRecipients($notificationId, $userIds = [], $groupIds = [])
  {
    $this->conn->beginTransaction();
    try {
      // Add user recipients
      if (!empty($userIds)) {
        $userQuery = "INSERT INTO " . $this->recipients_table . " 
                              (notification_id, user_id) VALUES ";
        $userValues = [];
        foreach ($userIds as $userId) {
          $userValues[] = "($notificationId, $userId)";
        }
        $userQuery .= implode(", ", $userValues);
        $this->conn->exec($userQuery);
      }

      // Add group recipients
      if (!empty($groupIds)) {
        $groupQuery = "INSERT INTO " . $this->recipients_table . " 
                               (notification_id, group_id) VALUES ";
        $groupValues = [];
        foreach ($groupIds as $groupId) {
          $groupValues[] = "($notificationId, $groupId)";
        }
        $groupQuery .= implode(", ", $groupValues);
        $this->conn->exec($groupQuery);
      }

      $this->conn->commit();
      return true;
    } catch (Exception $e) {
      $this->conn->rollBack();
      return false;
    }
  }

  public function unpublish($notificationId, $userId)
  {
    $query = "UPDATE " . $this->table . " 
                SET status = 'draft' 
                WHERE id = :id AND sender_id = :sender_id";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":id", $notificationId, PDO::PARAM_INT);
    $stmt->bindParam(":sender_id", $userId, PDO::PARAM_INT);

    return $stmt->execute();
  }

  public function publish($notificationId, $userId)
  {
    $query = "UPDATE " . $this->table . " 
                  SET status = 'published' 
                  WHERE id = :id AND sender_id = :sender_id";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":id", $notificationId, PDO::PARAM_INT);
    $stmt->bindParam(":sender_id", $userId, PDO::PARAM_INT);

    return $stmt->execute();
  }


  public function updateNotification($notificationId, $data)
  {
    $query = "UPDATE " . $this->table . " 
                  SET title = :title, 
                      message = :message, 
                      status = :status";

    // Add image_data to the query if it exists
    if (isset($data['image_data'])) {
      $query .= ", image_data = :image_data";
    }

    $query .= " WHERE id = :id";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":title", $data['title']);
    $stmt->bindParam(":message", $data['message']);
    $stmt->bindParam(":status", $data['status']);

    // Bind image_data parameter if it exists
    if (isset($data['image_data'])) {
      $stmt->bindParam(":image_data", $data['image_data']);
    }

    $stmt->bindParam(":id", $notificationId, PDO::PARAM_INT);

    return $stmt->execute();
  }

  public function deleteNotification($notificationId)
  {
    $query = "DELETE FROM " . $this->table . " WHERE id = :id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":id", $notificationId, PDO::PARAM_INT);
    return $stmt->execute();
  }

  public function getNotifications($userId, $userRole, $type = 'received')
  {
    try {
      // Base query will depend on the type
      if ($type === 'sent') {
        // Only notifications sent by the user
        $query = "SELECT 
                        n.id AS notification_id,
                        n.title,
                        n.message,
                        n.image_data,
                        n.status,
                        n.created_at,
                        n.sender_id,
                        'sender' AS user_role,
                        (SELECT COUNT(*) FROM {$this->recipients_table} nr 
                         WHERE nr.notification_id = n.id) as recipient_count
                      FROM {$this->table} n
                      WHERE n.sender_id = :userId
                      ORDER BY n.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':userId', $userId);
        $stmt->execute();
      } else {
        // For received notifications (combining direct and group)
        $query = "
                -- Direct recipients (published only)
                SELECT 
                    n.id AS notification_id,
                    n.title,
                    nr.is_read,
                    n.message,
                    n.image_data,
                    n.status,
                    n.created_at,
                    n.sender_id,
                    sender.full_name AS sender_name,
                    'direct_recipient' AS user_role
                FROM 
                    {$this->table} n
                JOIN 
                    users sender ON n.sender_id = sender.id
                JOIN 
                    {$this->recipients_table} nr ON n.id = nr.notification_id
                WHERE 
                    nr.user_id = :userId
                    AND n.status = 'published'
                
                UNION
                
                -- Group recipients (published only)
                SELECT 
                    n.id AS notification_id,
                    n.title,
                    nr.is_read,
                    n.message,
                    n.image_data,
                    n.status,
                    n.created_at,
                    n.sender_id,
                    sender.full_name AS sender_name,
                    'group_recipient' AS user_role
                FROM 
                    {$this->table} n
                JOIN 
                    users sender ON n.sender_id = sender.id
                JOIN 
                    {$this->recipients_table} nr ON n.id = nr.notification_id
                JOIN
                    users u ON u.group_id = nr.group_id
                WHERE 
                    u.id = :userId
                    AND n.status = 'published'
                    AND nr.group_id IS NOT NULL
                
                ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':userId', $userId);
        $stmt->execute();
      }

      $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
      return $notifications;
    } catch (PDOException $e) {
      // Handle error
      return ["error" => $e->getMessage()];
    }
  }

  public function markAsRead($notificationId, $userId)
  {
    $query = "UPDATE " . $this->recipients_table . " 
                  SET is_read = 1 
                  WHERE notification_id = :notification_id 
                  AND (user_id = :user_id OR 
                       group_id IN (SELECT group_id FROM users WHERE id = :user_id))";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":notification_id", $notificationId, PDO::PARAM_INT);
    $stmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
    if ($stmt->execute()) {
      return ["success" => true, "message" => "Notification marked as read"];
    }
    return ["error" => "Failed to mark notification as read"];
  }
  public function getNotificationById($notificationId)
  {
    try {
      $query = "SELECT * FROM {$this->table} WHERE id = :notificationId";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':notificationId', $notificationId);
      $stmt->execute();

      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      return ["error" => $e->getMessage()];
    }
  }
}
