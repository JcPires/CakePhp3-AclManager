<?php
/**
 * PHP version 5
 *
 * @category CakePhp3
 * @package  JcPires\AclManager\Controller\Component
 * @author   Jc Pires <djyss@live.fr>
 * @license  MIT http://opensource.org/licenses/MIT
 * @link     https://github.com/JcPires/CakePhp3-AclManager
 */

namespace JcPires\AclManager\Controller\Component;

use Acl\Controller\Component\AclComponent;
use Acl\Model\Entity\Aro;
use App\Model\Entity\Group;
use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Filesystem\Folder;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class PermissionsEditor
 *
 * @category CakePhp3
 * @package  JcPires\AclManager\Controller\Component
 * @author   Jc Pires <djyss@live.fr>
 * @license  MIT http://opensource.org/licenses/MIT
 * @link     https://github.com/JcPires/CakePhp3-AclManager
 */
class AclManagerComponent extends Component
{

    /**
     * Base for acos
     *
     * @var string
     */
    protected $_base = 'App';

    /**
     * Basic Api actions
     *
     * @var array
     */
    protected $config = [];

    /**
     * Initialize all properties we need
     *
     * @param array $config initialize cake method need $config
     *
     * @return null
     */
    public function initialize(array $config)
    {
        $registry = new ComponentRegistry();
        $this->Acl = new AclComponent($registry, Configure::read('Acl'));
        $this->Aco = $this->Acl->Aco;
        $this->Aro = $this->Acl->Aro;
        $this->config = $config;
        return null;
    }

    /**
     * Acos Builder, find all public actions from controllers and stored them
     * with Acl tree behavior to the acos table.
     * Alias first letter of Controller will
     * be capitalized and actions will be lowercase
     *
     * @return bool return true if acos saved
     */
    public function acosBuilder()
    {
        $resources = $this->getResources();
        $root = $this->__checkNodeOrSave($this->_base, $this->_base, null);
        unset($resources[0]);
        foreach ($resources as $controllers) {
            foreach ($controllers as $controller => $actions) {
                $tmp = explode('/', $controller);
                if (!empty($tmp) && isset($tmp[1])) {
                    $path = [0 => $this->_base];
                    $slash = '/';
                    $parent = [1 => $root->id];
                    $countTmp = count($tmp);
                    for ($i = 1; $i <= $countTmp; $i++) {
                        $path[$i] = $path[$i - 1];
                        if ($i >= 1 && isset($tmp[$i - 1])) {
                            $path[$i] = $path[$i] . $slash;
                            $path[$i] = $path[$i] . $tmp[$i - 1];
                            $this->__checkNodeOrSave(
                                $path[$i],
                                $tmp[$i - 1],
                                $parent[$i]
                            );
                            $new = $this->Aco
                                ->find()
                                ->where(
                                    [
                                        'alias' => $tmp[$i - 1],
                                        'parent_id' => $parent[$i]
                                    ]
                                )
                                ->first();
                            $parent[$i + 1] = $new['id'];
                        }
                    }
                    foreach ($actions as $action) {
                        if (!empty($action)) {
                            $this->__checkNodeOrSave(
                                $controller . $action,
                                $action,
                                end($parent)
                            );
                        }
                    }
                } else {
                    $controllerName = array_pop($tmp);
                    $path = $this->_base . '/' . $controller;
                    $controllerNode = $this->__checkNodeOrSave(
                        $path,
                        $controllerName,
                        $root->id
                    );
                    foreach ($actions as $action) {
                        if (!empty($action)) {
                            $this->__checkNodeOrSave(
                                $controller . '/' . $action,
                                $action,
                                $controllerNode['id']
                            );
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * Get all controllers with actions
     *
     * @return array like Controller => actions
     */
    public function getResources()
    {
        $controllers = $this->__getControllers();
        $resources = [];
        foreach ($controllers as $controller) {
            $actions = $this->__getActions($controller);
            array_push($resources, $actions);
        }
        return $resources;
    }

    /**
     * Get all controllers only from "Controller path only"
     * TO DO: Implements Plugin path
     *
     * @return array return a list of all controllers
     */
    private function __getControllers()
    {
        $path = App::path('Controller');
        $dir = new Folder($path[0]);
        $files = $dir->findRecursive('.*Controller\.php');
        $results = [];
        foreach ($files as $file) {
            $controller = str_replace(App::path('Controller'), '', $file);
            $controller = explode('.', $controller)[0];
            $controller = str_replace('Controller', '', $controller);
            array_push($results, $controller);

        }
        return $results;
    }

    /**
     * Return all actions from the controller
     *
     * @param string $controllerName the controller to be check
     *
     * @return array
     */
    private function __getActions($controllerName)
    {
        $className = 'App\\Controller\\' . $controllerName . 'Controller';
        $class = new ReflectionClass($className);
        $actions = $class->getMethods(ReflectionMethod::IS_PUBLIC);
        $controllerName = str_replace("\\", "/", $controllerName);
        $results = [$controllerName => []];
        $ignoreList = ['beforeFilter', 'afterFilter', 'initialize'];
        foreach ($actions as $action) {
            if ($action->class == $className
                && !in_array($action->name, $ignoreList)
            ) {
                array_push($results[$controllerName], $action->name);
            }
        }
        return $results;
    }

    /**
     * Check if the aco exist and store it if empty
     *
     * @param string $path     the path like App/Admin/Admin/home
     * @param string $alias    the name of the alias like home
     * @param null   $parentId the parent id
     *
     * @return object
     */
    private function __checkNodeOrSave($path, $alias, $parentId = null)
    {
        $node = $this->Aco->node($path);
        if (!$node) {
            $data = [
                'parent_id' => $parentId,
                'model' => null,
                'alias' => $alias,
            ];
            $entity = $this->Aco->newEntity($data);
            $node = $this->Aco->save($entity);
            return $node;
        }
        return $node;
    }

    /**
     * Add all basics acos/aros rules when add a new aro
     *
     * @param Group $aro    the Group to check
     * @param Aro   $parent the parent Aro
     *
     * @return bool return  true/false
     */
    public function addBasicsRules(Group $aro, Aro $parent = null)
    {
        $controllers = $this->getResources();
        $controllers = $this->__setAlias($controllers, $this->_base);
        if (!$parent) {
            $this->Acl->allow($aro, $this->_base);
            return true;
        } else {
            foreach ($controllers as $controllerName => $actions) {
                $this->Acl->inherit($aro, $controllerName);
            }
        }
        return true;
    }

    /**
     * Set the alias for actions like
     * App/Controller/action or App/Folder/Controller/action
     *
     * @param array  $actions list of actions
     * @param string $_base   the base like App
     *
     * @return array return actions with Aco alias
     */
    private function __setAlias($actions, $_base)
    {
        $results = [];
        foreach ($actions as $controller) {
            if (!empty($controller)) {
                foreach ($controller as $controllerName => $actionList) {
                    if (!empty($actionList)) {
                        foreach ($actionList as $key => $action) {
                            $results[$_base . '/' . $controllerName][$key]
                                = $_base . '/' . $controllerName . '/' . $action;
                        }
                    } else {
                        $results[$_base] = $_base;
                    }
                }
            }
        }
        return $results;
    }

    /**
     * Edit all permissions for aro specified
     *
     * @param Group  $group the group to check
     * @param string $alias the alias like App/Admin/Admin/home
     * @param int    $data  the request->data 1 or 0 (Allowed/Deny)
     *
     * @return bool return true/false
     */
    public function editRule(Group $group, $alias, $data)
    {
        if (empty($alias) || empty($group)) {
            return false;
        }
        if ($data == '0') {
            if ($this->Acl->check($group, $alias)) {
                $this->Acl->deny($group, $alias);
            }
            return true;
        } elseif ($data == '1') {
            if (!$this->Acl->check($group, $alias)) {
                $this->Acl->allow($group, $alias);
            }
            return true;
        }
        return false;
    }

    /**
     * Get the list of actions for building the permissions edit form
     *
     * @return array
     */
    public function getFormActions()
    {
        $controllers = $this->__getControllers();
        $resources = [];
        foreach ($controllers as $controller) {
            $actions = $this->__getActions($controller);
            $excluded = $this->__getExcludeProperty($controller);
            if (is_array($excluded)) {
                $controllerName = str_replace("\\", "/", $controller);
                foreach ($excluded as $excl) {
                    if (in_array($excl, $actions[$controllerName])) {
                        foreach ($actions[$controllerName] as $k => $action) {
                            if ($action == $excl) {
                                unset($actions[$controllerName][$k]);
                            }
                        }
                    }
                }
            }
            array_push($resources, $actions);
        }
        return $resources;
    }

    /**
     * Get all exclude actions from public static AclActionsExclude var in controller
     *
     * @param string $controllerName controller to check
     *
     * @return bool|mixed
     */
    private function __getExcludeProperty($controllerName)
    {
        $className = 'App\\Controller\\' . $controllerName . 'Controller';
        try {
            $prop = new \ReflectionProperty($className, 'AclActionsExclude');
            $ExcludeProperty = $prop->getValue('AclActionsExclude');
        } catch (\Exception $e) {
            $ExcludeProperty = false;
        }
        return $ExcludeProperty;
    }

    /**
     * Get the Aco node
     *
     * @param string $path path like App/Admin/Admin/home
     *
     * @return bool return an ACL Aco Object
     */
    public function node($path)
    {
        try {
            $this->Aco->node($path);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }
}
