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
        'resources' => '0',
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
     * @var bool $debugData Contains debug information.
     */
    public $debugData = array();
    /**
     * @var bool $resourceMap Contains a multi dimensional array representing the menu.
     */
    public $resourceMap = array();
    /**
     * @var bool $resourceIDs Contains a flat array with every ID to collect for the menu.
     */
    public $resourceIDs = array();

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

    public function process() {
        $this->_getResourceIDs();

        if ($this->debug) {
            var_dump($this->resourceMap);
            echo $this->modx->toJSON($this->resourceIDs);
            var_dump($this->debugData);
        }
    }

    public function debug($key, $description, array $data = array(), $start = 0) {
        if (!$this->debug) return;

        $logtime = microtime(true);
        $this->debugData[$key] = array(
            'logtime' => $logtime,
            'description' => $description,
            'data' => $data,
        );
        if ($start > 0) {
            $this->debugData[$key]['starttime'] = $start;
            $this->debugData[$key]['runtime'] = $logtime - $start;
        }
    }

    /**
     * Individual attributes in the &resources property can define:
     * - get the children of this resource ID           (no token; default)
     * - get this resource ID                           (+ sign in front of the ID)
     * - get this resource ID and its children          (+ sign after the ID)
     * - get the root of this context and its children  (name of context)
     *
     * &resources=`web,+6,7,8,8+`
     *                        ^ get 8 and its children
     *                      ^ get children of 8
     *                    ^ get children of 7
     *                 ^ get resource 6 itself
     *             ^ get all resources in "web"
     *
     * &resources=`6`
     *             ^ get all children of 6
     *
     * &resources=`other_context`
     *             ^ get all resources in other_context
     */
    private function _getResourceIDs() {
        /* Time when we started for debugging */
        $timing = microtime(true);

        $resourceMap = array();
        $cacheKey = 'menuextended/'.md5(serialize($this->options));
        if ($this->getOption('cacheMap', true)) {
            $cache = $this->modx->cacheManager->get($cacheKey);
            if (!empty($cache) && !empty($cache['map']) && !empty($cache['ids'])) {
                $this->resourceMap = $cache['map'];
                $this->resourceIDs = $cache['ids'];
                $this->debug('resourceids','Retrieved Resource IDs from cache', $cache, $timing);
                return;
            }
        }

        $depth = $this->getOption('depth', 10, true);
        $resources = explode(',',$this->getOption('resources'));
        if (empty($resources)) $resources = array('0');

        foreach ($resources as $declaration) {
            /**
             * If the declaration is a + sign, followed by a number
             * bigger than 0, we include the resource itself as root.
             */
            if ( (substr($declaration, 0, 1) == '+') &&
                is_numeric(substr($declaration,1)) &&
                ((int)substr($declaration,1) > 0) ) {
                $resourceMap[(int)substr($declaration, 1)] = array();
            }
            /**
             * If the declaration is a number bigger than 0,
             * followed by + sign, we include the resource itself as root and
             * its children below that.
             */
            elseif ( (substr($declaration, -1) == '+') &&
                is_numeric(substr($declaration, 0, -1)) &&
                ((int)substr($declaration, 0, -1) > 0) ) {
                $rootParent = (int)substr($declaration, 0, -1);
                $resourceMap[$rootParent] = $this->modx->getTree($rootParent, $depth--);
            }
            /**
             * If the declaration is a number (no token) and equal to 0,
             * we do 2 things: get the root parents to add as roots to the map
             * and get their children.
             */
            elseif (is_numeric($declaration) && $declaration == 0) {
                $rootParents = $this->modx->getChildIds(0, 1, array(
                    'context' => $this->modx->context->get('key'),
                ));
                foreach ($rootParents as $rootParent) {
                    $resourceMap[$rootParent] = $this->modx->getTree($rootParent, $depth--);
                }
            }

            /**
             * If the declaration is a number larger than 0, we get their children
             * and add them as roots to the map.
             */
            elseif (is_numeric($declaration) && $declaration > 0) {
                $children = $this->modx->getTree($declaration, $depth);
                foreach ($children as $child => $childChildren) {
                    $resourceMap[$child] = $childChildren;
                }
            }

            /**
             * If no other options apply, we might have a context key.
             * Test if a context exists with this key, and if so, get all its children.
             * If it's not a context.. well idk what it is then. Probably a case of PEBCAK.
             */
            else {
                $ctx = $this->modx->getObject('modContext',array('key' => $declaration));
                if ($ctx instanceof modContext) {
                    $rootParents = $this->modx->getChildIds(0, 1, array(
                        'context' => $declaration,
                    ));
                    foreach ($rootParents as $rootParent) {
                        $resourceMap[$rootParent] = $this->modx->getTree($rootParent, $depth--);
                    }
                }
            }
        }
        $this->resourceMap = $resourceMap;
        $this->resourceIDs = $this->_getIdsFromMap($resourceMap);

        if ($this->getOption('cacheMap', true)) {
            $cache = array(
                'map' => $this->resourceMap,
                'ids' => $this->resourceIDs,
            );
            $this->modx->cacheManager->set($cacheKey, $cache);
        }

        $this->debug('resourceids','Retrieved resource IDs and map.', $resourceMap, $timing);
    }

    /**
     * Recursively gets all the unique IDs from the resource map generated.
     * @param array $resourceMap
     * @return array
     */
    private function _getIdsFromMap(array $resourceMap = array()) {
        $keys = array_keys($resourceMap);
        foreach ($resourceMap as $children) {
            if (is_array($children)) {
                $keys = array_merge($keys, $this->_getIdsFromMap($children));
            }
        }
        return array_unique($keys);
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
        /* Get the defaults and overwrite with what was given */
        $options = array_merge($this->defaultOptions, $options);

        /* Set the boolean flag */
        $this->debug = (bool)$options['debug'];

        /* Parse the &fields property */
        $fields = array_map('trim',explode(',',$options['fields']));
        if (!in_array('id', $fields)) $fields[] = 'id';
        if (!in_array('context_key', $fields)) $fields[] = 'context_key';
        $options['fields'] = $fields;

        /* Set options to the internal array. */
        $this->options = $options;

        $this->debug('options', 'Parsed snippet properties.', $options);
    }
}

