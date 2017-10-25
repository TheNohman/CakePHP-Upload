<?php

namespace Upload\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\Event\Event;
use Cake\Datasource\EntityInterface;
use ArrayObject;
use Cake\Utility\Inflector;

class UploadBehavior extends Behavior {

    public $defaultOptions = [
        'fields' => []
    ];
    public $options;

    public function initialize(array $config) {
        $this->options = array_merge($this->defaultOptions, $config);
    }

    public function afterSave(Event $event, EntityInterface $entity, ArrayObject $options) {
        foreach ($this->options['fields'] as $field => $path):
            if (isset($entity->toArray()[$field . '_file']) && !empty($entity->toArray()[$field . '_file']['name'])):
                $file = $entity->toArray()[$field . '_file'];
                $extention = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $destination = $this->getUploadPath($entity, $path, $extention, $file['name']);
                $dirname = dirname($destination);
                if (!file_exists(WWW_ROOT . $dirname)):
                    mkdir(WWW_ROOT . $dirname, 0777, TRUE);
                endif;
                $this->deleteOldUpload($entity, $field);
                move_uploaded_file($file['tmp_name'], $destination);
                chmod(WWW_ROOT . $destination, 0777);
                $this->saveField($entity, $field, $destination);
            endif;
        endforeach;
    }

    public function beforeDelete(Event $event, EntityInterface $entity, ArrayObject $options) {
        foreach ($this->options['fields'] as $field => $path):
            $this->deleteOldUpload($entity, $field);
        endforeach;
        return TRUE;
    }

    public function fileExtentions($file, $extentions, $allowEmpty = TRUE) {
        if ($allowEmpty && empty($file['tmp_name'])):
            return TRUE;
        endif;
        $extention = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        return in_array($extention, $extentions);
    }

    public function maxSize($file, $size, $allowEmpty = TRUE) {
        if ($allowEmpty && empty($file['tmp_name'])):
            return TRUE;
        endif;
        return $file['size'] <= $size;
    }

    private function getUploadPath(Entity $entity, $path, $extention, $filename) {
        $replace = [
			':id1000'  => ceil($model->id / 1000),
            ':id100'   => ceil($model->id / 100),
            '%id' => $entity->id,
            '%y' => date('Y'),
            '%m' => date('m'),
            '%n' => $entity->name,
            '%s' => $entity->slug,
            '%filename' => Inflector::slug(basename($filename, $extention))
        ];
        return trim(strtr($path, $replace), '/') . '.' . $extention;
    }

    private function saveField(Entity $entity, $field, $destination) {
        $tables = TableRegistry::get($entity->source());
        $query = $tables->query();
        return $query->update()
                        ->set([$field => $destination])
                        ->where(['id' => $entity->id])
                        ->execute();
    }

    public function deleteOldUpload(Entity $entity, $field) {
        $file = $entity->$field;
        if (empty($file)):
            return TRUE;
        endif;
        if (file_exists(WWW_ROOT . $file)):
            return unlink(WWW_ROOT . $file);
        endif;
    }

}
