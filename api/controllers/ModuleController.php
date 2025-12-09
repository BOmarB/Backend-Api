<?php
include_once '../models/Module.php';

class ModuleController
{
  private $module;

  public function __construct($db)
  {
    $this->module = new Module($db);
  }

  public function createModule($data)
  {
    $this->module->name = $data['name'];
    if ($this->module->create()) {
      return ['message' => 'Module created successfully'];
    }
    return ['message' => 'Failed to create module'];
  }

  public function getAllModules()
  {
    $modules = $this->module->getAll(); 
    return [
      'success' => true,
      'message' => 'Exam created successfully',
      'modules' => $modules
    ];
  }
}

