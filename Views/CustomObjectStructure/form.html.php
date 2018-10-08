<?php

/*
 * @copyright   2018 Mautic, Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$view->extend('MauticCoreBundle:Default:content.html.php');

$view['slots']->set('mauticContent', 'customObjectStructure');

if ($entity->getId()) {
    $header = $view['translator']->trans(
        'custom.object.structure.edit',
        ['%name%' => $view['translator']->trans($entity->getName())]
    );
} else {
    $header = $view['translator']->trans('custom.object.structure.new');
}

$view['slots']->set('headerTitle', $header);
?>

<?php echo $view['form']->start($form); ?>

<!-- start: box layout -->
<div class="box-layout">
    <!-- container -->
    <div class="col-md-9 bg-auto height-auto bdr-r">
        <div class="pa-md">
            <div class="row">
                <div class="col-md-4">
                    <?php echo $view['form']->row($form['name']); ?>
                    <?php echo $view['form']->row($form['alias']); ?>
                    <?php echo $view['form']->row($form['description']); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 bg-white height-auto">
        <div class="pr-lg pl-lg pt-md pb-md">
            <?php //echo $view['form']->row($form['category']); ?>
        </div>
    </div>
</div>

<?php echo $view['form']->end($form); ?>
