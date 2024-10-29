<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Link\Bulky;

/**
 * ---
 * description: >
 *   The Bulky Links in this example point to ilias.de and includes tooltips
 *   Note the exact look of the Bulky Links is mostly defined by the
 *   surrounding container.
 *
 * expected output: >
 *   ILIAS shows a bulky link: A glyph ("Comment") with the text "Link to ilias.de with Glyph".
 *   - Hovering over the link will show tooltips
 *   - Clicking the link will redirect you to ilias.de
 * ---
 */
function with_tooltip()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $target = new \ILIAS\Data\URI("https://ilias.de");
    $glyph = $f->symbol()->glyph()->comment();

    $link = $f->link()->bulky($glyph, 'Link to ilias.de with Glyph', $target)
        ->withHelpTopics(
            ...$f->helpTopics("ilias", "learning management system")
        );

    return $renderer->render([
        $link
    ]);
}
