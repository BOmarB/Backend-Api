<?php
include_once '../models/Notification.php';

class NotificationController
{
    private $notification;
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->notification = new Notification($db);
    }

    public function createNotification($data, $senderId, $senderRole)
    {
        // Validate sender's permission to create notifications
        if ($senderRole === 'student') {
            return ['success' => false, 'message' => 'Not authorized to send notifications'];
        }

        $this->notification->sender_id = $senderId;
        $this->notification->title = $data['title'];
        $this->notification->message = $data['message'];
        $this->notification->status = 'draft';

        // Handle image data if provided
        $this->notification->image_data = $data['image_data'] ?? null;

        $notificationId = $this->notification->createNotification();

        if ($notificationId) {
            // Add recipients if provided
            $userIds = $data['userIds'] ?? [];
            $groupIds = $data['groupIds'] ?? [];

            if ($this->notification->addRecipients($notificationId, $userIds, $groupIds)) {
                return [
                    'success' => true,
                    'message' => 'Notification draft created successfully',
                    'notificationId' => $notificationId
                ];
            }
        }

        return ['success' => false, 'message' => 'Failed to create notification'];
    }

    public function updateNotification($notificationId, $data, $senderId, $senderRole)
    {
        // Validate sender's permission
        $checkQuery = "SELECT sender_id, status FROM notifications WHERE id = :id";
        $stmt = $this->db->prepare($checkQuery);
        $stmt->bindParam(":id", $notificationId, PDO::PARAM_INT);
        $stmt->execute();
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notification || $notification['sender_id'] != $senderId) {
            return ['success' => false, 'message' => 'Not authorized to update this notification'];
        }

        // Only allow updates to draft notifications
        if ($notification['status'] === 'published') {
            return ['success' => false, 'message' => 'Cannot update published notification'];
        }

        $updateData = [
            'title' => $data['title'],
            'message' => $data['message'],
            'status' => $data['status'] ?? 'draft'
        ];

        // Include image_data if provided
        if (isset($data['image_data'])) {
            $updateData['image_data'] = $data['image_data'];
        }

        if ($this->notification->updateNotification($notificationId, $updateData)) {
            // Update recipients if provided
            if (isset($data['userIds']) || isset($data['groupIds'])) {
                // First delete existing recipients
                $deleteQuery = "DELETE FROM notification_recipients WHERE notification_id = :id";
                $deleteStmt = $this->db->prepare($deleteQuery);
                $deleteStmt->bindParam(":id", $notificationId, PDO::PARAM_INT);
                $deleteStmt->execute();

                // Add new recipients
                $this->notification->addRecipients(
                    $notificationId,
                    $data['userIds'] ?? [],
                    $data['groupIds'] ?? []
                );
            }

            return ['success' => true, 'message' => 'Notification updated successfully'];
        }

        return ['success' => false, 'message' => 'Failed to update notification'];
    }

    public function deleteNotification($notificationId, $senderId, $senderRole)
    {
        // Validate sender's permission
        $checkQuery = "SELECT sender_id, status FROM notifications WHERE id = :id";
        $stmt = $this->db->prepare($checkQuery);
        $stmt->bindParam(":id", $notificationId, PDO::PARAM_INT);
        $stmt->execute();
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notification || $notification['sender_id'] != $senderId) {
            return ['success' => false, 'message' => 'Not authorized to delete this notification'];
        }

        // Only allow deletion of draft notifications
        if ($notification['status'] === 'published') {
            return ['success' => false, 'message' => 'Cannot delete published notification'];
        }

        if ($this->notification->deleteNotification($notificationId)) {
            return ['success' => true, 'message' => 'Notification deleted successfully'];
        }

        return ['success' => false, 'message' => 'Failed to delete notification'];
    }

    public function unpublishNotification($notificationId, $userId)
    {
        try {
            $notification = $this->notification->getNotificationById($notificationId);

            // Check if the notification exists and user has permission
            if (!$notification || $notification['sender_id'] != $userId) {
                return ['success' => false, 'message' => 'Notification not found or permission denied'];
            }

            $this->notification->unpublish($notificationId, $userId);

            return ['success' => true, 'message' => 'Notification unpublished successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function publishNotification($notificationId, $userId)
    {
        try {
            $notification = $this->notification->getNotificationById($notificationId);

            // Check if the notification exists and user has permission
            if (!$notification || $notification['sender_id'] != $userId) {
                return ['success' => false, 'message' => 'Notification not found or permission denied'];
            }

            $this->notification->publish($notificationId, $userId);

            return ['success' => true, 'message' => 'Notification published successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getNotifications($userId, $userRole, $type = 'received')
    {
        return $this->notification->getNotifications($userId, $userRole, $type);
    }
    public function markAsRead($notificationId, $userId)
    {
        return $this->notification->markAsRead($notificationId, $userId);
    }
    public function markNotificationAsRead($notificationId, $userId)
    {
        return $this->notification->markAsRead($notificationId, $userId);
    }
}
