<?php
include_once __DIR__ . '/../models/Group.php';

class GroupController
{
  private $group;

  public function __construct($db)
  {
    $this->group = new Group($db);
  }

  public function createGroup($data)
  {
    $this->group->name = $data['name'];
    if ($this->group->create()) {
      return ['message' => 'Group created successfully'];
    }
    return ['message' => 'Failed to create group'];
  }

  public function getAllGroups()
  {
    $groups = $this->group->getAll();
    return [
      'success' => true,
      'message' => 'Exam created successfully',
      'groups' => $groups
    ];
  }
}
