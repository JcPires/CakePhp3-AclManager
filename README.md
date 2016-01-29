# CakePhp3-AclManager

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![CakePHP 3](https://img.shields.io/badge/Version-CakePhp%203-brightgreen.svg?style=flat-square)](http://cakephp.org)
[![Build Status](https://travis-ci.org/JcPires/CakePhp3-AclManager.svg?branch=master)](https://travis-ci.org/JcPires/CakePhp3-AclManager)


## Install

```
    Composer require jcpires/cakephp3-aclmanager
```
or on composer.json

```
    "jcpires/cakephp3-aclmanager": "dev-master"
```

### Requirements

[CakePhp ACL](https://github.com/cakephp/acl)


## How to

### Build your Acos

First you need to build your acos, to do, you need to add this lines where you want. There are two way:

*   By an event:

    ```
        use JcPires\AclManager\Event\PermissionsEditor;
    ```
    
    ```
        $this->eventManager()->on(new PermissionsEditor());
        $acosBuilder = new Event('Permissions.buildAcos', $this);
        $this->eventManager()->dispatch($acosBuilder);
    ```

*   By the component

    ```
        $this->loadComponent('JcPires/AclManager.AclManager');
        $this->AclManager->acosBuilder();
    ```

NB: !!! Don't forget to delete those lines after building !!!


### Add permissions when creating a new group

!!! Be caution, to works, you need first a first level ARO with base node full granted like a Super Admin like this on the aros_acos table: create:1 read: 1 update: 1 delete: 1!!!

On your Admin/GroupsController.php

```
    use JcPires\AclManager\Event\PermissionsEditor;
```

Add basics permissions, on your action add

```
    if ($this->Groups->save($group)) {

        if (isset($this->request->data['parent_id'])) {
            $parent = $this->request->data['parent_id'];
        } else {
            $parent = null;
        }

        $this->eventManager()->on(new PermissionsEditor());
        $perms = new Event('Permissions.addAro', $this, [
            'Aro' => $group,
            'Parent' => $parent,
            'Model' => 'Groups'
        ]);
        $this->eventManager()->dispatch($perms);
    }
```

### Edit permissions

1.    On your action edit()

    we need to get all acos "not really necessary is just an automatic array builder":

    ```
        $this->loadComponent('JcPires/AclManager.AclManager');
        $EditablePerms = $this->AclManager->getFormActions();
    ```

    If you to exclude some actions for the form like ajax actions, you have to add a static property
    
    On the specified controller like PostController or BlogController, ...:
    
    ```
        public static $AclActionsExclude = [
            'action1',
            'action2',
            '...'
        ];
    ```
    
    You will have an array with all acos's alias indexed by the controller aco path like:
    
    ```
        'Blog' => [
            'add',
            'edit',
            'delete
        ],
        'Post' => [
            'add',
            'edit',
            'delete'
        ],
        'Admin/Post' => [
            'add',
            'edit',
            'delete'
        ]
    ```

2.    Build your form

    First if you want to use the AclManager Helper 
    
    ```
        public $helpers = [            
                'AclManager' => [
                            'className' => 'JcPires/AclManager.AclManager'
                        ]
            ];
    ```
    
    an exemple with an Acl helper for checking if permissions are allowed or denied:
    
    ```
        <?php foreach ($EditablePerms as $Acos) :?>
            <?php foreach ($Acos as $controllerPath => $actions) :?>
                <?php if (!empty($actions)) :?>
                    <h4><?= $controllerPath ;?></h4>
                    <?php foreach ($actions as $action) :?>
                        <?php ($this->AclManager->checkGroup($group, 'App/'.$controllerPath.'/'.$action)) ? $val = 1 : $val = 0 ?>
                        <?= $this->Form->label('App/'.$controllerPath.'/'.$action, $action);?>
                        <?= $this->Form->select('App/'.$controllerPath.'/'.$action, [0 => 'No', 1 => 'Yes'], ['value' => $val]) ;?>
                    <?php endforeach ;?>
                <?php endif;?>
            <?php endforeach ;?>
        <?php endforeach ;?>
    ```
    
    render:
    
    ```
        <select name="App/Blog/add">
            <option value="0">No</option>
            <option value="1" selected>Yes</option>
        </select>
    ```

If you don't use the Array Builder you need to specified your input name like aco path: App/Blog/add or App/Admin/Blog/add ... :base/:folder/:subfolder/:controller/:action "Folder and subfolder can be empty"

3.   Update new permissions

    ```
    
        if ($this->request->is('post')) {
    
            $this->eventManager()->on(new PermissionsEditor());
            $perms = new Event('Permissions.editPerms', $this, [
                'Aro' => $group,
                'datas' => $this->request->data
            ]);
            $this->eventManager()->dispatch($perms);
        }
        
    ```
    
    data need to be like this 'aco path' => value "0 deny / 1 allow"
    
    ```
        'App/Blog/add' => 0
        'App/Blog/edit' => 1
        ...
    ```
