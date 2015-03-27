<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2014 (original work) Open Assessment Technologies SA;
 *
 *
 */
namespace oat\taoMediaManager\model;


use oat\tao\model\media\MediaBrowser;
use oat\taoMediaManager\model\fileManagement\FileManager;

class MediaManagerBrowser implements MediaBrowser
{

    private $lang;
    private $rootClassUri;

    /**
     * get the lang of the class in case we want to filter the media on language
     * @param $data
     */
    public function __construct($data)
    {
        \common_ext_ExtensionsManager::singleton()->getExtensionById('taoMediaManager');
        $this->lang = (isset($data['lang'])) ? $data['lang'] : '';
        $this->rootClassUri = (isset($data['rootClass'])) ? $data['rootClass'] : MEDIA_URI;
    }

    /**
     * (non-PHPdoc)
     * @see \oat\tao\model\media\MediaBrowser::getDirectory
     */
    public function getDirectory($parentLink = '/', $acceptableMime = array(), $depth = 1)
    {
        if ($parentLink == '/') {
            $class = new \core_kernel_classes_Class($this->rootClassUri);
            $parentLink = '';
        } else {
            if (strpos($parentLink, '/') === 0) {
                $parentLink = substr($parentLink, 1);
            }
            $class = new \core_kernel_classes_Class($parentLink);
        }

        if ($class->getUri() !== $this->rootClassUri) {
            $path = array($class->getLabel());
            foreach ($class->getParentClasses(true) as $parent) {
                if ($parent->getUri() === $this->rootClassUri) {
                    $path[] = 'mediamanager';
                    break;
                }
                $path[] = $parent->getLabel();
            }
            $path = array_reverse($path);
        }
        $data = array(
            'path' => 'mediamanager/' . $parentLink,
            'relPath' => (isset($path)) ? implode('/', $path) : 'mediamanager/',
            'label' => $class->getLabel()
        );

        if ($depth > 0) {
            $children = array();
            foreach ($class->getSubClasses() as $subclass) {
                $children[] = $this->getDirectory($subclass->getUri(), $acceptableMime, $depth - 1);

            }

            //add a filter for example on language (not for now)
            $filter = array();

            foreach ($class->searchInstances($filter) as $instance) {
                $thing = $instance->getUniquePropertyValue(new \core_kernel_classes_Property(MEDIA_LINK));
                $link = $thing instanceof \core_kernel_classes_Resource ? $thing->getUri() : (string)$thing;
                $file = $this->getFileInfo($link, $acceptableMime);
                if (!is_null($file) && (count($acceptableMime) == 0 || in_array($file['mime'], $acceptableMime)) ) {
                    //add the alt text to file array
                    $altArray = $instance->getPropertyValues(new \core_kernel_classes_Property(MEDIA_ALT_TEXT));
                    if (count($altArray) > 0) {
                        $file['alt'] = $altArray[0];
                    }
                    $children[] = $file;
                }

            }
            $data['children'] = $children;
        } else {
            $data['url'] = \tao_helpers_Uri::url(
                'files',
                'ItemContent',
                'taoItems',
                array('lang' => $this->lang, 'path' => $parentLink)
            );
        }
        return $data;


    }

    /**
     * (non-PHPdoc)
     * @see \oat\tao\model\media\MediaBrowser::getFileInfo
     */
    public function getFileInfo($link)
    {
        $file = null;
        $fileManagement = FileManager::getFileManagementModel();
        $filePath = $fileManagement->retrieveFile($link);
        $mime = \tao_helpers_File::getMimeType($filePath);

        if (file_exists($filePath)) {
            $file = array(
                'name' => basename($filePath),
                'identifier' => 'mediamanager/',
                'relPath' => $link,
                'mime' => $mime,
                'size' => filesize($filePath),
            );
        }
        return $file;

    }

    /**
     * (non-PHPdoc)
     * @see \oat\tao\model\media\MediaBrowser::download
     */
    public function download($link)
    {
        $fileManagement = FileManager::getFileManagementModel();
        $filePath = $fileManagement->retrieveFile($link);
        \tao_helpers_Http::returnFile($filePath);
    }
}