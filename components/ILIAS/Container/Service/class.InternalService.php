<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\Container;

use ILIAS\DI\Container;

class InternalService
{
<<<<<<< HEAD:components/ILIAS/Container/Service/class.InternalService.php
    protected Container $DIC;
    protected array $instance = [];

    public function __construct(Container $DIC)
    {
        $this->DIC = $DIC;
=======
    protected static array $instance = [];

    public function __construct(
        protected DI\Container $DIC
    ) {
>>>>>>> 9afe2259be7 (fixed 42276: no certificate creation in excisting courses (service initialisation)):Services/Container/Service/class.InternalService.php
    }

    public function data(): InternalDataService
    {
<<<<<<< HEAD:components/ILIAS/Container/Service/class.InternalService.php
        return $this->instance["data"] ??= new InternalDataService();
=======
        return self::$instance["data"] ??= new InternalDataService();
>>>>>>> 9afe2259be7 (fixed 42276: no certificate creation in excisting courses (service initialisation)):Services/Container/Service/class.InternalService.php
    }

    public function repo(): InternalRepoService
    {
<<<<<<< HEAD:components/ILIAS/Container/Service/class.InternalService.php
        return $this->instance["repo"] ??= new InternalRepoService(
=======
        return self::$instance["repo"] ??= new InternalRepoService(
>>>>>>> 9afe2259be7 (fixed 42276: no certificate creation in excisting courses (service initialisation)):Services/Container/Service/class.InternalService.php
            $this->data(),
            $this->DIC->database()
        );
    }

    public function domain(): InternalDomainService
    {
<<<<<<< HEAD:components/ILIAS/Container/Service/class.InternalService.php
        return $this->instance["domain"] ??= new InternalDomainService(
            $this->DIC,
            $this->repo(),
            $this->data()
=======
        return self::$instance["domain"] ??= new InternalDomainService(
            $this->DIC,
            $this->repo(),
            $this->data(),
>>>>>>> 9afe2259be7 (fixed 42276: no certificate creation in excisting courses (service initialisation)):Services/Container/Service/class.InternalService.php
        );
    }

    public function gui(): InternalGUIService
    {
<<<<<<< HEAD:components/ILIAS/Container/Service/class.InternalService.php
        return $this->instance["gui"] ??= new InternalGUIService(
=======
        return self::$instance["gui"] ??= new InternalGUIService(
>>>>>>> 9afe2259be7 (fixed 42276: no certificate creation in excisting courses (service initialisation)):Services/Container/Service/class.InternalService.php
            $this->DIC,
            $this->data(),
            $this->domain()
        );
    }
}
