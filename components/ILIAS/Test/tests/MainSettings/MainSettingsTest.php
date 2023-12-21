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

use ILIAS\Test\MainSettings\MainSettings;
use ILIAS\Test\MainSettings\SettingsGeneral;
use ILIAS\Test\MainSettings\SettingsIntroduction;
use ILIAS\Test\MainSettings\SettingsAccess;
use ILIAS\Test\MainSettings\SettingsTestBehaviour;
use ILIAS\Test\MainSettings\SettingsQuestionBehaviour;
use ILIAS\Test\MainSettings\SettingsParticipantFunctionality;
use ILIAS\Test\MainSettings\SettingsFinishing;
use ILIAS\Test\MainSettings\SettingsAdditional;

class MainSettingsTest extends ilTestBaseTestCase
{
    /**
     * @dataProvider throwOnDifferentTestIdDataProvider
     */
    public function testThrowOnDifferentTestId(int $io): void
    {
        $test_settings = $this->createConfiguredMock(TestSettings::class, ['getTestId' => $io]);
        $main_settings = new MainSettings(
            $io,
            $this->createConfiguredMock(SettingsGeneral::class, ['getTestId' => $io]),
            $this->createConfiguredMock(SettingsIntroduction::class, ['getTestId' => $io]),
            $this->createConfiguredMock(SettingsAccess::class, ['getTestId' => $io]),
            $this->createConfiguredMock(SettingsTestBehaviour::class, ['getTestId' => $io]),
            $this->createConfiguredMock(SettingsQuestionBehaviour::class, ['getTestId' => $io]),
            $this->createConfiguredMock(SettingsParticipantFunctionality::class, ['getTestId' => $io]),
            $this->createConfiguredMock(SettingsFinishing::class, ['getTestId' => $io]),
            $this->createConfiguredMock(SettingsAdditional::class, ['getTestId' => $io])
        );

        $output = self::callMethod($main_settings, 'throwOnDifferentTestId', [$test_settings]);

        $this->assertNull($output);
    }

    public function throwOnDifferentTestIdDataProvider(): array
    {
        return [
            [-1],
            [0],
            [1]
        ];
    }

    /**
     * @dataProvider throwOnDifferentTestIdExceptionDataProvider
     */
    public function testThrowOnDifferentTestIdException(array $input): void
    {
        $test_settings = $this->createMock(TestSettings::class);
        $test_settings->method('getTestId')->willReturn($input['test_id_1']);
        $main_settings = new MainSettings(
            $input['test_id_2'],
            $this->createConfiguredMock(SettingsGeneral::class, ['getTestId' => $input['test_id_2']]),
            $this->createConfiguredMock(SettingsIntroduction::class, ['getTestId' => $input['test_id_2']]),
            $this->createConfiguredMock(SettingsAccess::class, ['getTestId' => $input['test_id_2']]),
            $this->createConfiguredMock(SettingsTestBehaviour::class, ['getTestId' => $input['test_id_2']]),
            $this->createConfiguredMock(SettingsQuestionBehaviour::class, ['getTestId' => $input['test_id_2']]),
            $this->createConfiguredMock(SettingsParticipantFunctionality::class, ['getTestId' => $input['test_id_2']]),
            $this->createConfiguredMock(SettingsFinishing::class, ['getTestId' => $input['test_id_2']]),
            $this->createConfiguredMock(SettingsAdditional::class, ['getTestId' => $input['test_id_2']])
        );
        $this->expectException(LogicException::class);
        self::callMethod($main_settings, 'throwOnDifferentTestId', [$test_settings]);
    }

    public function throwOnDifferentTestIdExceptionDataProvider(): array
    {
        return [
            [['test_id_1' => -1, 'test_id_2' => 0]],
            [['test_id_1' => 0, 'test_id_2' => 1]],
            [['test_id_1' => 1, 'test_id_2' => -1]]
        ];
    }

    /**
     * @dataProvider getAndWithTestIdDataProvider
     */
    public function testGetAndWithTestId(int $io): void
    {
        $main_settings = (new MainSettings(
            0,
            $this->createMock(SettingsGeneral::class),
            $this->createConfiguredMock(
                SettingsIntroduction::class,
                ['withTestId' => $this->createMock(SettingsIntroduction::class)]
            ),
            $this->createMock(SettingsAccess::class),
            $this->createMock(SettingsTestBehaviour::class),
            $this->createMock(SettingsQuestionBehaviour::class),
            $this->createMock(SettingsParticipantFunctionality::class),
            $this->createMock(SettingsFinishing::class),
            $this->createMock(SettingsAdditional::class)
        ))->withTestId($io);


        $this->assertInstanceOf(MainSettings::class, $main_settings);
        $this->assertEquals($io, $main_settings->getTestId());
    }

    public function getAndWithTestIdDataProvider(): array
    {
        return [
            [-1],
            [0],
            [1]
        ];
    }

    /**
     * @dataProvider getAndWithGeneralSettingsDataProvider
     */
    public function testGetAndWithGeneralSettings(SettingsGeneral $io): void
    {
        $main_settings = (new MainSettings(
            0,
            $this->createMock(SettingsGeneral::class),
            $this->createMock(SettingsIntroduction::class),
            $this->createMock(SettingsAccess::class),
            $this->createMock(SettingsTestBehaviour::class),
            $this->createMock(SettingsQuestionBehaviour::class),
            $this->createMock(SettingsParticipantFunctionality::class),
            $this->createMock(SettingsFinishing::class),
            $this->createMock(SettingsAdditional::class)
        ))->withGeneralSettings($io);

        $this->assertInstanceOf(MainSettings::class, $main_settings);
        $this->assertEquals($io, $main_settings->getGeneralSettings());
    }

    public function getAndWithGeneralSettingsDataProvider(): array
    {
        return [
            [$this->createMock(SettingsGeneral::class)]
        ];
    }

    /**
     * @dataProvider getAndWithIntroductionSettingsDataProvider
     */
    public function testGetAndWithIntroductionSettings(SettingsIntroduction $io): void
    {
        $main_settings = (new MainSettings(
            0,
            $this->createMock(SettingsGeneral::class),
            $this->createMock(SettingsIntroduction::class),
            $this->createMock(SettingsAccess::class),
            $this->createMock(SettingsTestBehaviour::class),
            $this->createMock(SettingsQuestionBehaviour::class),
            $this->createMock(SettingsParticipantFunctionality::class),
            $this->createMock(SettingsFinishing::class),
            $this->createMock(SettingsAdditional::class)
        ))->withIntroductionSettings($io);

        $this->assertInstanceOf(MainSettings::class, $main_settings);
        $this->assertEquals($io, $main_settings->getIntroductionSettings());
    }

    public function getAndWithIntroductionSettingsDataProvider(): array
    {
        return [
            [$this->createMock(SettingsIntroduction::class)]
        ];
    }

    /**
     * @dataProvider getAndWithAccessSettingsDataProvider
     */
    public function testGetAndWithAccessSettings(SettingsAccess $io): void
    {
        $main_settings = (new MainSettings(
            0,
            $this->createMock(SettingsGeneral::class),
            $this->createMock(SettingsIntroduction::class),
            $this->createMock(SettingsAccess::class),
            $this->createMock(SettingsTestBehaviour::class),
            $this->createMock(SettingsQuestionBehaviour::class),
            $this->createMock(SettingsParticipantFunctionality::class),
            $this->createMock(SettingsFinishing::class),
            $this->createMock(SettingsAdditional::class)
        ))->withAccessSettings($io);

        $this->assertInstanceOf(MainSettings::class, $main_settings);
        $this->assertEquals($io, $main_settings->getAccessSettings());
    }

    public function getAndWithAccessSettingsDataProvider(): array
    {
        return [
            [$this->createMock(SettingsAccess::class)]
        ];
    }

    /**
     * @dataProvider getAndWithTestBehaviourSettingsDataProvider
     */
    public function testGetAndWithTestBehaviourSettings(SettingsTestBehaviour $io): void
    {
        $main_settings = (new MainSettings(
            0,
            $this->createMock(SettingsGeneral::class),
            $this->createMock(SettingsIntroduction::class),
            $this->createMock(SettingsAccess::class),
            $this->createMock(SettingsTestBehaviour::class),
            $this->createMock(SettingsQuestionBehaviour::class),
            $this->createMock(SettingsParticipantFunctionality::class),
            $this->createMock(SettingsFinishing::class),
            $this->createMock(SettingsAdditional::class)
        ))->withTestBehaviourSettings($io);

        $this->assertInstanceOf(MainSettings::class, $main_settings);
        $this->assertEquals($io, $main_settings->getTestBehaviourSettings());
    }

    public function getAndWithTestBehaviourSettingsDataProvider(): array
    {
        return [
            [$this->createMock(SettingsTestBehaviour::class)]
        ];
    }

    /**
     * @dataProvider getAndWithQuestionBehaviourSettingsDataProvider
     */
    public function testGetAndWithQuestionBehaviourSettings(SettingsQuestionBehaviour $io): void
    {
        $main_settings = (new MainSettings(
            0,
            $this->createMock(SettingsGeneral::class),
            $this->createMock(SettingsIntroduction::class),
            $this->createMock(SettingsAccess::class),
            $this->createMock(SettingsTestBehaviour::class),
            $this->createMock(SettingsQuestionBehaviour::class),
            $this->createMock(SettingsParticipantFunctionality::class),
            $this->createMock(SettingsFinishing::class),
            $this->createMock(SettingsAdditional::class)
        ))->withQuestionBehaviourSettings($io);

        $this->assertInstanceOf(MainSettings::class, $main_settings);
        $this->assertEquals($io, $main_settings->getQuestionBehaviourSettings());
    }

    public function getAndWithQuestionBehaviourSettingsDataProvider(): array
    {
        return [
            [$this->createMock(SettingsQuestionBehaviour::class)]
        ];
    }

    /**
     * @dataProvider getAndWithParticipantFunctionalitySettingsDataProvider
     */
    public function testGetAndWithParticipantFunctionalitySettings(SettingsParticipantFunctionality $io): void
    {
        $main_settings = (new MainSettings(
            0,
            $this->createMock(SettingsGeneral::class),
            $this->createMock(SettingsIntroduction::class),
            $this->createMock(SettingsAccess::class),
            $this->createMock(SettingsTestBehaviour::class),
            $this->createMock(SettingsQuestionBehaviour::class),
            $this->createMock(SettingsParticipantFunctionality::class),
            $this->createMock(SettingsFinishing::class),
            $this->createMock(SettingsAdditional::class)
        ))->withParticipantFunctionalitySettings($io);

        $this->assertInstanceOf(MainSettings::class, $main_settings);
        $this->assertEquals($io, $main_settings->getParticipantFunctionalitySettings());
    }

    public function getAndWithParticipantFunctionalitySettingsDataProvider(): array
    {
        return [
            [$this->createMock(SettingsParticipantFunctionality::class)]
        ];
    }

    /**
     * @dataProvider getAndWithFinishingSettingsDataProvider
     */
    public function testGetAndWithFinishingSettings(SettingsFinishing $io): void
    {
        $main_settings = (new MainSettings(
            0,
            $this->createMock(SettingsGeneral::class),
            $this->createMock(SettingsIntroduction::class),
            $this->createMock(SettingsAccess::class),
            $this->createMock(SettingsTestBehaviour::class),
            $this->createMock(SettingsQuestionBehaviour::class),
            $this->createMock(SettingsParticipantFunctionality::class),
            $this->createMock(SettingsFinishing::class),
            $this->createMock(SettingsAdditional::class)
        ))->withFinishingSettings($io);

        $this->assertInstanceOf(MainSettings::class, $main_settings);
        $this->assertEquals($io, $main_settings->getFinishingSettings());
    }

    public function getAndWithFinishingSettingsDataProvider(): array
    {
        return [
            [$this->createMock(SettingsFinishing::class)]
        ];
    }

    /**
     * @dataProvider getAndWithAdditionalSettingsDataProvider
     */
    public function testGetAndWithAdditionalSettings(SettingsAdditional $io): void
    {
        $main_settings = (new MainSettings(
            0,
            $this->createMock(SettingsGeneral::class),
            $this->createMock(SettingsIntroduction::class),
            $this->createMock(SettingsAccess::class),
            $this->createMock(SettingsTestBehaviour::class),
            $this->createMock(SettingsQuestionBehaviour::class),
            $this->createMock(SettingsParticipantFunctionality::class),
            $this->createMock(SettingsFinishing::class),
            $this->createMock(SettingsAdditional::class)
        ))->withAdditionalSettings($io);

        $this->assertInstanceOf(MainSettings::class, $main_settings);
        $this->assertEquals($io, $main_settings->getAdditionalSettings());
    }

    public function getAndWithAdditionalSettingsDataProvider(): array
    {
        return [
            [$this->createMock(SettingsAdditional::class)]
        ];
    }
}
