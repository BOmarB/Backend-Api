<?php
include_once __DIR__ . '/../models/User.php';

class ProfileController
{
  private $user;


  public function __construct($db)
  {
    $this->user = new User($db);
  }



  public function getProfile($userId)
  {
    try {
      if (!$this->user->validateUser($userId)) {
        return [
          'success' => false,
          'message' => 'User not found'
        ];
      }

      $profile = $this->user->getProfile($userId);

      if ($profile) {
        if (isset($profile['social_links'])) {
          $profile['social_links'] = json_decode($profile['social_links'], true);
        }

        return [
          'success' => true,
          'message' => 'Profile retrieved successfully',
          'profile' => $profile
        ];
      }

      return [
        'success' => false,
        'message' => 'Failed to retrieve profile'
      ];
    } catch (Exception $e) {
      return [
        'success' => false,
        'message' => 'An error occurred while retrieving the profile'
      ];
    }
  }

  public function updateProfile($userId, $data)
  {
    try {
      if (!$this->user->validateUser($userId)) {
        return [
          'success' => false,
          'message' => 'User not found'
        ];
      }

      if (!$data) {
        return [
          'success' => false,
          'message' => 'Invalid input data'
        ];
      }

      $sanitizedData = $this->sanitizeProfileData($data);

      if (isset($sanitizedData['social_links']) && is_array($sanitizedData['social_links'])) {
        $sanitizedData['social_links'] = json_encode($sanitizedData['social_links']);
      }

      if ($this->user->updateProfile($userId, $sanitizedData)) {
        return [
          'success' => true,
          'message' => 'Profile updated successfully'
        ];
      }

      return [
        'success' => false,
        'message' => 'Failed to update profile'
      ];
    } catch (Exception $e) {
      return [
        'success' => false,
        'message' => 'An error occurred while updating the profile'
      ];
    }
  }



  private function sanitizeProfileData($data)
  {
    $sanitized = [];

    if (isset($data['full_name'])) {
      $sanitized['full_name'] = strip_tags(trim($data['full_name']));
    }

    if (isset($data['phone'])) {
      $sanitized['phone'] = preg_replace('/[^0-9+\-()]/', '', $data['phone']);
    }

    if (isset($data['address'])) {
      $sanitized['address'] = strip_tags(trim($data['address']));
    }

    if (isset($data['bio'])) {
      $sanitized['bio'] = strip_tags(trim($data['bio']));
    }

    if (isset($data['date_of_birth'])) {
      $date = date_create($data['date_of_birth']);
      if ($date) {
        $sanitized['date_of_birth'] = date_format($date, 'Y-m-d');
      }
    }

    if (isset($data['gender'])) {
      $allowedGenders = ['male', 'female', 'other'];
      if (in_array($data['gender'], $allowedGenders)) {
        $sanitized['gender'] = $data['gender'];
      }
    }

    if (isset($data['social_links']) && is_array($data['social_links'])) {
      $sanitized['social_links'] = $data['social_links'];
    }

    return $sanitized;
  }
}
