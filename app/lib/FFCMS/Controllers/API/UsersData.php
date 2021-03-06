<?php

namespace FFCMS\Controllers\API;

use FFMVC\Helpers;
use FFCMS\{Traits, Models, Mappers};

/**
 * Api UsersData REST Controller Class.
 *
 * @author Vijay Mahrra <vijay@yoyo.org>
 * @copyright Vijay Mahrra
 * @license GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 */
class UsersData extends Mapper
{
    protected $adminOnly = false;


    /**
     * Perform a create/update of the an item, used by POST, PUT, PATCH
     *
     * @param \Base $f3
     * @param array $prohibitedFields
     * @return void
     */
    protected function save(\Base $f3, array $prohibitedFields = [])
    {
        // do not allow request to define these fields:
        $data = $f3->get('REQUEST');
        foreach ($prohibitedFields as $field) {
            if (array_key_exists($field, $data)) {
                unset($data[$field]);
            }
        }

        // load pre-existing value
        $m = $this->getMapper();
        if ($f3->get('VERB') == 'PUT') {
            $m->load(['uuid = ?', $data['uuid']]);
        } else {
            $m->load(['users_uuid = ? AND ' . $m->quotekey('key') . ' = ?', $data['users_uuid'], $data['key']]);
        }

        // copy data and validate
        $m->copyfrom($data);
        $m->validationRequired([
            'users_uuid', 'key', 'value'
        ]);
        $errors = $m->validate(false);
        if (true !== $errors) {
            foreach ($errors as $error) {
                $this->setOAuthError('invalid_request');
                $this->failure($error['field'], $error['rule']);
            }
        } else {
            // load in original data and then replace for save
            if (!$m->save()) {
                $this->setOAuthError('invalid_request');
                $this->failure('error', 'Unable to update object.');
                return;
            }

            // return raw data for object?
            $adminView = $f3->get('isAdmin') && 'admin' == $f3->get('REQUEST.view');
            $this->data = $adminView ? $m->castFields($f3->get('REQUEST.fields')) : $m->exportArray($f3->get('REQUEST.fields'));
        }
    }


    /**
     * Update data
     *
     * @param \Base $f3
     * @param array $params
     * @return null|array|boolean
     */
    public function patch(\Base $f3, array $params)
    {
        $isAdmin = $f3->get('isAdmin');
        $m = $this->getIdObjectIfUser($f3, $params, 'uuid', $params['id']);
        if (!is_object($m) || null == $m->uuid) {
            return;
        } elseif (!$isAdmin && $m->users_uuid !== $f3->get('uuid')) {
            $this->failure('authentication_error', "User does not have permission.", 401);
            return $this->setOAuthError('access_denied');
        }

        $f3->set('REQUEST.users_uuid', $m->users_uuid);
        $f3->set('REQUEST.key', $m->key);

        // these fields can't be modified
        return $this->save($f3, [
            'id', 'uuid'
        ]);
    }


    /**
     * Replace data
     *
     * @param \Base $f3
     * @param array $params
     * @return null|array|boolean
     */
    public function put(\Base $f3, array $params)
    {
        $isAdmin = $f3->get('isAdmin');
        $m = $this->getIdObjectIfUser($f3, $params, 'uuid', $params['id']);
        if (!is_object($m) || null == $m->uuid) {
            return;
        } elseif (!$isAdmin && $m->users_uuid !== $f3->get('uuid')) {
            $this->failure('authentication_error', "User does not have permission.", 401);
            return $this->setOAuthError('access_denied');
        }

        $f3->set('REQUEST.uuid', $m->uuid);
        $f3->set('REQUEST.users_uuid', $m->users_uuid);

        // these fields can't be modified
        return $this->save($f3, [
            'id'
        ]);
    }


    /**
     * Create new data
     *
     * @param \Base $f3
     * @param array $params
     * @return null|array|boolean
     */
    public function post(\Base $f3, array $params)
    {
        $isAdmin = $f3->get('isAdmin');
        if ($isAdmin && !empty($params) && array_key_exists('id', $params)) {
            $users_uuid = $params['id'];
        } elseif (!$isAdmin) {
            $users_uuid = $f3->get('uuid');
        } else {
            $users_uuid = $f3->get('REQUEST.users_uuid');
        }
        $f3->set('REQUEST.users_uuid', $users_uuid);

        // this fields can't be modified
        $prohibitedFields = [
            'id', 'uuid'
        ];

        return $this->save($f3, $prohibitedFields);
    }


}
