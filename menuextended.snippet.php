<?php
/**
 * MenuExtended
 *
 * Copyright 2011 by Mark Hamstra <hello@markhamstra.com>
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
 * bdListings; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 *
*/
/* @var modX $modx
 * @var array $scriptProperties
 */

/* Set up some properties to begin with */
$resources = $modx->getOption('resources',$scriptProperties,'2,3,4*');
$depth = $modx->getOption('depth',$scriptProperties,1);
$hideUnpub = $modx->getOption('hideUnpublished',$scriptProperties,true);
$hideHidden = $modx->getOption('hideHidden',$scriptProperties,false);
$hideDeleted = $modx->getOption('hideDeleted',$scriptProperties,true);
$fields = $modx->getOption('fields',$scriptProperties,'id,context_key,pagetitle,menutitle,longtitle');
$fields = explode(',',$fields);
if (!in_array('id',$fields)) $fields[] = 'id';
if (!in_array('context_key',$fields)) $fields[] = 'context_key';

/* Templating / Display stuff */
$rowTpl = $modx->getOption('rowTpl',$scriptProperties,'me.row');
$innerTpl = $modx->getOption('innerTpl',$scriptProperties,'me.inner');
$childTpl = $modx->getOption('childTpl',$scriptProperties,$rowTpl);
$outerTpl = $modx->getOption('outerTpl',$scriptProperties,null);
$childSeparator = $modx->getOption('childSeparator',$scriptProperties,"\n");
$rowSeparator = $modx->getOption('rowSeparator',$scriptProperties,"\n");

$classLast = $modx->getOption('classLast',$scriptProperties,'last');
$classActive = $modx->getOption('classActive',$scriptProperties,'active');
$classFirst = $modx->getOption('classFirst',$scriptProperties,'first');

/* When debug is enabled, we will dump some more info on screen to help figure out what goes wrong */
$debug = intval($modx->getOption('debug',$scriptProperties,false));

/* Generate arrays with:
 * - all IDs (used in the SQL statement)
 * - a multi-dimensional map of IDs to use in the actual outputting of data later on.
 * - a reverse map which links resource IDs to their parent
*/
$ids = array();
$map = array();
$revMap = array();
$forceIds = array();
$resRaw = explode(',',$resources);
foreach ($resRaw as $resItem) {
    switch (substr($resItem,-1)) {
        case '+': /* Sub resources only */
            $itemChildren = $modx->getChildIDs((int)substr($resItem,0,-1),$depth);
            foreach ($itemChildren as $iC) {
                $map[$iC] = array();
                $ids[] = (int)$iC;
            }
            break;

        case '*': /* Parent only */
            $map[(int)substr($resItem,0,-1)] = array();
            $ids[] = (int)substr($resItem,0,-1);
            $forceIds[] = (int)substr($resItem,0,-1);
            break;

        default: /* Parent + children */
            $itemChildren = $modx->getChildIDs($resItem,$depth);
            $secMap = array();
            foreach ($itemChildren as $iC) {
                $secMap[$iC] = array();
                $ids[] = (int)$iC;
                $revMap[$iC] = (int)$resItem;
            }
            $map[(int)$resItem] = $secMap;
            $ids[] = (int)$resItem;
            $forceIds[] = (int)$resItem;
            break;
    }
}
/* Output the map if debug is enabled */
if ($debug) { echo '<p><strong>Resource ID Map</strong></p><pre>'.print_r($map,true).'</pre>'; }

/* Prepare query and set some generic conditions */
$c = $modx->newQuery('modResource');
$c->select($fields);
if ($hideUnpub) $c->where(array('published' => true));
if ($hideHidden) $c->where(array('hidemenu' => false, 'OR:id:IN' => $forceIds));
if ($hideDeleted) $c->where(array('deleted' => false));
/* Only get the resources we want */
$c->where(array('id:IN' => $ids));
/* Make sure we get all the results and we don't get cut off half-way through */
$c->limit(0);
/* If debug is enabled, output the SQL query generated */
if ($debug) { $c->construct(); echo '<p><strong>Resource SQL Query</strong></p><code>'.$c->toSQL().'</code>'; }
/* Get all resources  */
$collection = $modx->getCollection('modResource',$c);

/* Put all resources' values into one array, for output with chunks later */
$results = array();
foreach ($collection as $res) {
    $ta = $res->toArray('',false,true); $id = $ta['id'];
    $results[$id] = $ta;
}

/* If debug is enabled, dump all results on screen */
if ($debug) { echo '<p><strong>Results</strong></p><pre>'.print_r($results,true).'</pre>'; }

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
                if ($cidx == 1) $phs['class'][] = $classFirst;
                if (count($children) == $cidx) $phs['class'][] = $classLast;
                if (in_array($childId,$active)) $phs['class'][] = $classActive;
                $phs['class'] = implode(' ',$phs['class']);
                $phs['idx'] = $cidx;
                $phs['parentidx'] = $idx;
                $childOutput[] = $modx->getChunk($childTpl,$results[$childId]);
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