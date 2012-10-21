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
*/
/* @var modX $modx
 * @var array $scriptProperties
 */

/**
 * Get the MenuExtended class to help us out. Cancel execution if can't be loaded.
 */
$corePath = $modx->getOption('menuextended.core_path', null, $modx->getOption('core_path') . 'components/menuextended/');
$me = $modx->getService('MenuExtended', 'MenuExtended', $corePath . 'model/menuextended/');
if (!($me instanceof MenuExtended)) {
    $modx->log(modX::LOG_LEVEL_ERROR,'Unable to load MenuExtended class','','',__FILE__,__LINE__);
    return '';
}
/**
 * Get the scriptProperties, and assign them to the class.
 * Default values and specific property parsing is done in there.
 */
$me->setOptions($scriptProperties);

/**
 * Fire it up!
 */
$me->process();


return;
/* Get current resources' parents for active class */
$active = $modx->getParentIDs($modx->resource->get('id'));
$active[$modx->resource->get('id')] = $modx->resource->get('id');

/* Loop over the map, get the right chunks and output some stuff. */
$output = array();
$idx = 0;
foreach ($map as $id => $children) {
    $idx++;
    $wrapper = '';

    /* If, according to the map, we have child resources: prepare the output for that */
    if (!empty($children)) {
        $cidx = 0; /* We use a separate index for child resources */
        $childOutput = array();
        foreach ($children as $childId => $childChild) {
            /* @todo Recursively loop over child child's - not yet supported */
            $cidx++;
            if (isset($results[$childId])) {
                $phs = $results[$childId];
                /* See if we need to add any classes */
                $phs['class'] = array();
                if ($cidx == 1) $phs['class'][] = $classFirst;
                if (count($children) == $cidx) $phs['class'][] = $classLast;
                if (in_array($childId,$active)) $phs['class'][] = $classActive;
                $phs['class'] = implode(' ',$phs['class']);
                $phs['idx'] = $cidx;
                $phs['parentidx'] = $idx;
                $childOutput[] = $modx->getChunk($childTpl,$phs);
            }
        }

        /* If we have any results, wrap it in the innerTpl and set in the $wrapper variable */
        if (!empty($childOutput)) {
            $wrapper = implode($childSeparator,$childOutput);
            $wrapper = $modx->getChunk($innerTpl,array('wrapper' => $wrapper));
        }
    }

    /* Prepare the resource output itself (so not the child) */
    if (isset($results[$id])) {
        $phs = $results[$id];
        /* Add a wrapper for any child resources */
        $phs['wrapper'] = $wrapper;
        /* Add some classes if needed */
        $phs['class'] = array();
        if ($idx == 1) $phs['class'][] = $classFirst;
        if (count($map) == $idx) $phs['class'][] = $classLast;
        if (in_array($id,$active)) $phs['class'][] = $classActive;
        $phs['class'] = implode(' ',$phs['class']);
        $phs['idx'] = $idx;
        $output[] = $modx->getChunk($rowTpl,$phs);
    }
}

/* Implode and if we have an outerTpl set, use that */
$output = implode($rowSeparator,$output);
if (!empty($outerTpl)) $output = $modx->getChunk($outerTpl,array('wrapper' => $output));

return $output;
