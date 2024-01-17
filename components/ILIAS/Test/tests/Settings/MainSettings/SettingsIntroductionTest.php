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

use ILIAS\Test\Settings\MainSettings\SettingsIntroduction;

class SettingsIntroductionTest extends ilTestBaseTestCase
{
    /**
     * @dataProvider getAndWithIntroductionEnabledDataProvider
     */
    public function testGetAndWithIntroductionEnabled(bool $io): void
    {
        $settings_introduction = (new SettingsIntroduction(0))->withIntroductionEnabled($io);

        $this->assertInstanceOf(SettingsIntroduction::class, $settings_introduction);
        $this->assertEquals($io, $settings_introduction->getIntroductionEnabled());
    }

    public function getAndWithIntroductionEnabledDataProvider(): array
    {
        return [
            [true],
            [false]
        ];
    }

    /**
     * @dataProvider getAndWithIntroductionTextDataProvider
     */
    public function testGetAndWithIntroductionText(string $io): void
    {
        $settings_introduction = (new SettingsIntroduction(0))->withIntroductionText($io);

        $this->assertInstanceOf(SettingsIntroduction::class, $settings_introduction);
        $this->assertEquals($io, $settings_introduction->getIntroductionText());
    }

    public function getAndWithIntroductionTextDataProvider(): array
    {
        return [
            [''],
            ['string']
        ];
    }

    /**
     * @dataProvider getAndWithIntroductionPageIdDataProvider
     */
    public function testGetAndWithIntroductionPageId(?int $io): void
    {
        $settings_introduction = (new SettingsIntroduction(0))->withIntroductionPageId($io);

        $this->assertInstanceOf(SettingsIntroduction::class, $settings_introduction);
        $this->assertEquals($io, $settings_introduction->getIntroductionPageId());
    }

    public function getAndWithIntroductionPageIdDataProvider(): array
    {
        return [
            [null],
            [-1],
            [0],
            [1]
        ];
    }

    /**
     * @dataProvider getAndWithExamConditionsCheckboxEnabledDataProvider
     */
    public function testGetAndWithExamConditionsCheckboxEnabled(bool $io): void
    {
        $settings_introduction = (new SettingsIntroduction(0))->withExamConditionsCheckboxEnabled($io);

        $this->assertInstanceOf(SettingsIntroduction::class, $settings_introduction);
        $this->assertEquals($io, $settings_introduction->getExamConditionsCheckboxEnabled());
    }

    public function getAndWithExamConditionsCheckboxEnabledDataProvider(): array
    {
        return [
            [true],
            [false]
        ];
    }
}
