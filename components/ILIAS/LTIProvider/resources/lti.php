<?php

declare(strict_types=1);

/**
 * LTI launch target script
 *
 * @author  Stefan Meyer <smeyer.ilias@gmx.de>
 */

require_once("../vendor/composer/vendor/autoload.php");

ilContext::init(ilContext::CONTEXT_LTI_PROVIDER);

// This is done to replace the deprecated method $DIC->ctrl()->setCmd
$_GET['cmd'] = 'post';
$_POST['cmd'] = 'doLTIAuthentication';

ilInitialisation::initILIAS();

global $DIC;

$DIC->ctrl()->setTargetScript('ilias.php');
$DIC->ctrl()->callBaseClass('ilStartUpGUI');
