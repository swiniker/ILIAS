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

use ILIAS\Test\Settings\MainSettings\SettingsQuestionBehaviour;

class SettingsQuestionBehaviourTest extends ilTestBaseTestCase
{
    private function getTestInstance(): SettingsQuestionBehaviour
    {
        return new SettingsQuestionBehaviour(
            0,
            0,
            true,
            0,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true
        );
    }

    /**
     * @dataProvider getAndWithQuestionTitleOutputModeDataProvider
     */
    public function testGetAndWithQuestionTitleOutputMode(int $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withQuestionTitleOutputMode($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getQuestionTitleOutputMode());
    }

    public function getAndWithQuestionTitleOutputModeDataProvider(): array
    {
        return [
            [-1],
            [0],
            [1]
        ];
    }

    /**
     * @dataProvider getAndWithInstantFeedbackDataProvider
     */
    public function testGetAndWithAutosaveEnabled(bool $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withAutosaveEnabled($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getAutosaveEnabled());
    }

    public function getAndWithInstantFeedbackDataProvider(): array
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @dataProvider getAndWithAutosaveIntervalDataProvider
     */
    public function testGetAndWithAutosaveInterval(int $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withAutosaveInterval($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getAutosaveInterval());
    }

    public function getAndWithAutosaveIntervalDataProvider(): array
    {
        return [
            [-1],
            [0],
            [1]
        ];
    }

    /**
     * @dataProvider getAndWithShuffleQuestionsDataProvider
     */
    public function testGetAndWithShuffleQuestions(bool $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withShuffleQuestions($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getShuffleQuestions());
    }

    public function getAndWithShuffleQuestionsDataProvider(): array
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @dataProvider getAndWithQuestionHintsEnabledDataProvider
     */
    public function testGetAndWithQuestionHintsEnabled(bool $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withQuestionHintsEnabled($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getQuestionHintsEnabled());
    }

    public function getAndWithQuestionHintsEnabledDataProvider(): array
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @dataProvider getAndWithInstantFeedbackPointsEnabledDataProvider
     */
    public function testGetAndWithInstantFeedbackPointsEnabled(bool $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withInstantFeedbackPointsEnabled($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getInstantFeedbackPointsEnabled());
    }

    public function getAndWithInstantFeedbackPointsEnabledDataProvider(): array
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @dataProvider getAndWithInstantFeedbackGenericEnabledDataProvider
     */
    public function testGetAndWithInstantFeedbackGenericEnabled(bool $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withInstantFeedbackGenericEnabled($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getInstantFeedbackGenericEnabled());
    }

    public function getAndWithInstantFeedbackGenericEnabledDataProvider(): array
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @dataProvider getAndWithInstantFeedbackSpecificEnabledDataProvider
     */
    public function testGetAndWithInstantFeedbackSpecificEnabled(bool $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withInstantFeedbackSpecificEnabled($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getInstantFeedbackSpecificEnabled());
    }

    public function getAndWithInstantFeedbackSpecificEnabledDataProvider(): array
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @dataProvider getAndWithInstantFeedbackSolutionEnabledDataProvider
     */
    public function testGetAndWithInstantFeedbackSolutionEnabled(bool $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withInstantFeedbackSolutionEnabled($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getInstantFeedbackSolutionEnabled());
    }

    public function getAndWithInstantFeedbackSolutionEnabledDataProvider(): array
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @dataProvider getAndWithForceInstantFeedbackOnNextQuestionDataProvider
     */
    public function testGetAndWithForceInstantFeedbackOnNextQuestion(bool $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withForceInstantFeedbackOnNextQuestion($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getForceInstantFeedbackOnNextQuestion());
    }

    public function getAndWithForceInstantFeedbackOnNextQuestionDataProvider(): array
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @dataProvider getAndWithLockAnswerOnInstantFeedbackEnabledDataProvider
     */
    public function testGetAndWithLockAnswerOnInstantFeedbackEnabled(bool $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withLockAnswerOnInstantFeedbackEnabled($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getLockAnswerOnInstantFeedbackEnabled());
    }

    public function getAndWithLockAnswerOnInstantFeedbackEnabledDataProvider(): array
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @dataProvider getAndWithLockAnswerOnNextQuestionEnabledDataProvider
     */
    public function testGetAndWithLockAnswerOnNextQuestionEnabled(bool $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withLockAnswerOnNextQuestionEnabled($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getLockAnswerOnNextQuestionEnabled());
    }

    public function getAndWithLockAnswerOnNextQuestionEnabledDataProvider(): array
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @dataProvider getAndWithCompulsoryQuestionsEnabledDataProvider
     */
    public function testGetAndWithCompulsoryQuestionsEnabled(bool $io): void
    {
        $Settings_question_behaviour = $this->getTestInstance()->withCompulsoryQuestionsEnabled($io);

        $this->assertInstanceOf(SettingsQuestionBehaviour::class, $Settings_question_behaviour);
        $this->assertEquals($io, $Settings_question_behaviour->getCompulsoryQuestionsEnabled());
    }

    public function getAndWithCompulsoryQuestionsEnabledDataProvider(): array
    {
        return [
            [false],
            [true]
        ];
    }
}
