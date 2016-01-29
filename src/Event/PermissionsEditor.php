<?php
/**
 * PHP version 5
 *
 * @category CakePhp3
 * @package  JcPires\AclManager\Event
 * @author   Jc Pires <djyss@live.fr>
 * @license  MIT http://opensource.org/licenses/MIT
 * @link     https://github.com/JcPires/CakePhp3-AclManager
 */

namespace JcPires\AclManager\Event;

use Acl\Controller\Component\AclComponent;
use Cake\Controller\ComponentRegistry;
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use JcPires\AclManager\Controller\Component\AclManagerComponent;

/**
 * Class PermissionsEditor
 *
 * @category CakePhp3
 * @package  JcPires\AclManager\Event
 * @author   Jc Pires <djyss@live.fr>
 * @license  MIT http://opensource.org/licenses/MIT
 * @link     https://github.com/JcPires/CakePhp3-AclManager
 */
class PermissionsEditor implements EventListenerInterface
{

    /**
     * Construct all dependencies
     */
    public function __construct()
    {
        $collection = new ComponentRegistry();
        $registry = new ComponentRegistry();
        $this->Acl = new AclComponent($collection);
        $this->AclManager = new AclManagerComponent($registry);
    }

    /**
     * List of all implemented events
     *
     * @return array
     */
    public function implementedEvents()
    {
        return [
            'Permissions.buildAcos' => 'buildAcos',
            'Permissions.addAro' => 'addAro',
            'Permissions.editPerms' => 'editPerms',
        ];
    }

    /**
     * This event will be fired when a new Group/User are saved
     *
     * @param Event $event Contain data from the caller
     *
     * @return bool
     */
    public function addAro(Event $event)
    {
        $Aro = $event->data['Aro'];
        $Parent = $event->data['Parent'];
        $Model = $event->data['Model'];
        $node = $this->Acl->Aro->node($Aro)->first();
        $node->parent_id = $Parent;
        if ($this->Acl->Aro->save($node)) {
            if ($Aro->parent_id) {
                $AroParent = $this->Acl->Aro->node(
                    [
                    'model' => $Model,
                    'foreign_key' => $Parent
                    ]
                )->first();
            } else {
                $AroParent = null;
            }
            if ($this->AclManager->addBasicsRules($Aro, $AroParent)) {
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * Automatic Acos Builder
     * Check all controllers and public actions and adding all of them
     * with tree behavior on the acos table
     *
     * @return bool
     */
    public function buildAcos()
    {
        return $this->AclManager->acosBuilder();
    }

    /**
     * Edit rules for a specific Aro
     *
     * @param Event $event Contain data from the caller
     *
     * @return bool
     */
    public function editPerms(Event $event)
    {
        $Aro = $event->data['Aro'];
        $datas = $event->data['datas'];
        foreach ($datas as $path => $data) {
            try {
                $this->AclManager->node($path);
            } catch (\Exception $e) {
                return false;
            }
        }
        foreach ($datas as $path => $data) {
            $this->AclManager->editRule($Aro, $path, $data);
        }
        return true;
    }
}
