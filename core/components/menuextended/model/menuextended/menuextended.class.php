<?php
/**
 * MenuExtended
 *
 * Copyright 2011-2012 by Mark Hamstra <hello@markhamstra.com>
 *
 * This file is part of MenuExtended, a menu building snippet for MODX Revolution.
 *
 * MenuExtended is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * MenuExtended is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * MenuExtended; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 *
 * @package menuextended
*/

class MenuExtended {
    /**
     * @var modX $modx Reference to the current modX instance.
     */
    public $modx;
    /**
     * @var array $config Contains paths and miscellaneous config options.
     */
    public $config = array();
    /**
     * @var array $defaultOptions Contains default options.
     */
    public $defaultOptions = array(
        'fields' => 'id,context_key,pagetitle,menutitle,longtitle',
        'resources' => '',
        'depth' => 10,
        'debug' => false,

        'hideUnpublished' => true,
        'hideHidden' => false,
        'hideDeleted' => true,

        'rowTpl' => 'menuExtendedRow',
        'innerTpl' => 'menuExtendedInner',
        'childTpl' => 'menuExtendedChild',
        'outerTpl' => 'menuExtendedOuter',
        'itemSeparator' => "\n",
        'childSeparator' => "\n",

        'classFirst' => 'first',
        'classActive' => 'active',
        'classLast' => 'last',
        'classParent' => 'parent',
    );
    /**
     * @var array $options Contains properties for the current snippet instance.
     */
    public $options = array();
    /**
     * @var array $chunks Array containing cache of chunk contents.
     */
    private $chunks = array();
    /**
     * @var bool $debug If enabled, we'll collect and dump debug info.
     */
    public $debug = false;

    /**
     * @param \modX $modx
     * @param array $config
     */
    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;

        $basePath = $this->modx->getOption('menuextended.core_path',$config,$this->modx->getOption('core_path').'components/menuextended/');
        $assetsUrl = $this->modx->getOption('menuextended.assets_url',$config,$this->modx->getOption('assets_url').'components/menuextended/');
        $assetsPath = $this->modx->getOption('menuextended.assets_path',$config,$this->modx->getOption('assets_path').'components/menuextended/');
        $this->config = array_merge(array(
            'baseBath' => $basePath,
            'corePath' => $basePath,
            'modelPath' => $basePath.'model/',
            'processorsPath' => $basePath.'processors/',
            'elementsPath' => $basePath.'elements/',
            'templatesPath' => $basePath.'templates/',
            'assetsPath' => $assetsPath,
            'jsUrl' => $assetsUrl.'js/',
            'cssUrl' => $assetsUrl.'css/',
            'assetsUrl' => $assetsUrl,
            'connectorUrl' => $assetsUrl.'connector.php',
        ),$config);
    }

    /**
    * Gets a Chunk and caches it; also falls back to file-based templates
    * for easier debugging.
    *
    * @access public
    * @param string $name The name of the Chunk
    * @param array $properties The properties for the Chunk
    * @return string The processed content of the Chunk
    * @author Shaun "splittingred" McCormick
    */
    public function getChunk($name,$properties = array()) {
        $chunk = null;
        if (!isset($this->chunks[$name])) {
            $chunk = $this->modx->getObject('modChunk',array('name' => $name),true);
            if (empty($chunk)) {
                $chunk = $this->_getTplChunk($name);
                if ($chunk == false) return false;
            }
            $this->chunks[$name] = $chunk->getContent();
        } else {
            $o = $this->chunks[$name];
            $chunk = $this->modx->newObject('modChunk');
            $chunk->setContent($o);
        }
        $chunk->setCacheable(false);
        return $chunk->process($properties);
    }

    /**
    * Returns a modChunk object from a template file.
    *
    * @access private
    * @param string $name The name of the Chunk. Will parse to name.chunk.tpl
    * @param string $postFix The postfix to append to the name
    * @return modChunk/boolean Returns the modChunk object if found, otherwise false.
    * @author Shaun "splittingred" McCormick
    */
    private function _getTplChunk($name,$postFix = '.chunk.tpl') {
        $chunk = false;
        $f = $this->config['elements_path'].'chunks/'.strtolower($name).$postFix;
        if (file_exists($f)) {
            $o = file_get_contents($f);
            /* @var modChunk $chunk */
            $chunk = $this->modx->newObject('modChunk');
            $chunk->set('name',$name);
            $chunk->setContent($o);
        }
        return $chunk;
    }

    /**
     * @return array
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @param bool $strict
     *
     * @return mixed
     */
    public function getOption($key, $default = null, $strict = false) {
        $value = $default;
        if (isset($this->options[$key]) && (!empty($this->options[$key]) || !$strict)){
            $value = $this->options[$key];
        }
        return $value;
    }

    /**
     * Parse options and set defaults.
     * @param array $options
     */
    public function setOptions($options) {
        $options = array_merge($this->defaultOptions, $options);
        /**
         * Set internal debug flag
         */
        $this->debug = (bool)$options['debug'];
        /**
         * Parse the &fields option.
         */
        $fields = array_map('trim',explode(',',$options['fields']));
        if (!in_array('id', $fields)) $fields[] = 'id';
        if (!in_array('context_key', $fields)) $fields[] = 'context_key';
        $options['fields'] = $fields;
        /**
         * Parse
         */


        $this->options = $options;
        var_dump($this->options);
    }

}

